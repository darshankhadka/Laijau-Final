<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrmService
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Resolve or unify a customer record for a lead to guarantee zero duplicate customers.
     */
    public function resolveOrCreateCustomer(CrmLead $lead): User
    {
        if ($lead->customer_id && ($customer = User::find($lead->customer_id))) {
            return $customer;
        }

        if ($lead->user_id && ($customer = User::find($lead->user_id)) && $customer->role === 'customer') {
            $lead->customer_id = $customer->id;
            $lead->saveQuietly();
            return $customer;
        }

        $customer = $this->customerService->resolveOrCreateCustomer(
            name: $lead->contact_name,
            phone: $lead->phone,
            email: $lead->email
        );

        $lead->customer_id = $customer->id;
        $lead->user_id = $customer->id;
        $lead->saveQuietly();

        return $customer;
    }

    /**
     * Convert a public or customer inquiry into a CRM sales lead with full linkage.
     */
    public function createLeadFromInquiry(ContactMessage $message, ?int $assignedStaffId = null): CrmLead
    {
        return DB::transaction(function () use ($message, $assignedStaffId) {
            // Unify customer if details provided
            $customer = null;
            if (!empty($message->email) || !empty($message->phone)) {
                $customer = $this->customerService->resolveOrCreateCustomer(
                    name: $message->name,
                    phone: $message->phone,
                    email: $message->email
                );
            }

            $title = !empty($message->subject)
                ? $message->subject
                : ('Inquiry from ' . ($message->name ?: 'Customer'));

            $channel = match ($message->inquiry_type) {
                ContactMessage::INQUIRY_BESPOKE => CrmLead::CHANNEL_CONCIERGE,
                default => CrmLead::CHANNEL_WEBSITE,
            };

            $lead = CrmLead::create([
                'title' => $title,
                'contact_name' => $message->name,
                'phone' => $message->phone,
                'email' => $message->email,
                'channel' => $channel,
                'stage' => CrmLead::STAGE_NEW,
                'estimated_value' => 0.00,
                'currency' => Setting::get('default_currency', 'NPR'),
                'priority' => $message->priority ?: CrmLead::PRIORITY_MEDIUM,
                'assigned_staff_id' => $assignedStaffId ?? $message->assigned_staff_id,
                'customer_id' => $customer?->id ?? $message->customer_id,
                'user_id' => $customer?->id ?? $message->customer_id,
                'product_id' => $message->product_id,
                'order_id' => $message->order_id,
                'bespoke_notes' => $message->message,
                'internal_notes' => "Converted from Customer Inquiry #{$message->id} on " . now()->format('d M Y H:i'),
            ]);

            // Record initial activity
            $this->logActivity(
                lead: $lead,
                type: CrmActivity::TYPE_CONVERSION,
                description: "Created from Customer Inquiry #{$message->id} ({$message->inquiry_type})",
                metadata: [
                    'inquiry_id' => $message->id,
                    'inquiry_type' => $message->inquiry_type,
                    'initial_subject' => $message->subject,
                ]
            );

            // Update original message
            $message->update([
                'status' => ContactMessage::STATUS_IN_PROGRESS,
                'customer_id' => $customer?->id ?? $message->customer_id,
                'reply_notes' => trim(($message->reply_notes ? $message->reply_notes . "\n" : '') . "Converted to CRM Lead #{$lead->id} on " . now()->format('d M Y H:i')),
            ]);

            return $lead;
        });
    }

    /**
     * Convert an active CRM Lead into a confirmed sales order.
     */
    public function convertLeadToOrder(CrmLead $lead, array $orderOverrides = [], ?User $staff = null): Order
    {
        return DB::transaction(function () use ($lead, $orderOverrides, $staff) {
            // Atomic lock to prevent duplicate orders from repeated rapid clicks
            $lockedLead = CrmLead::where('id', $lead->id)->lockForUpdate()->firstOrFail();

            if ($lockedLead->order_id && ($existingOrder = Order::find($lockedLead->order_id))) {
                return $existingOrder;
            }

            // 1. Ensure unified customer exists
            $customer = $this->resolveOrCreateCustomer($lockedLead);

            // 2. Resolve pricing & product
            $product = $lockedLead->product_id ? Product::find($lockedLead->product_id) : null;
            $unitPrice = $product ? (float)$product->price : (float)$lockedLead->estimated_value;
            $totalAmount = (float)($orderOverrides['total_amount'] ?? ($unitPrice > 0 ? $unitPrice : (float)$lockedLead->estimated_value));

            // Name splitting
            $parts = explode(' ', trim($lockedLead->contact_name ?: 'Valued Client'), 2);
            $firstName = $parts[0] ?? 'Valued';
            $lastName = $parts[1] ?? 'Client';

            // 3. Create Order
            $order = Order::create([
                'order_number' => Order::generateNextOrderNumber(),
                'guest_access_token' => Order::generateGuestToken(),
                'channel' => $orderOverrides['channel'] ?? 'concierge',
                'user_id' => $customer->id,
                'crm_lead_id' => $lockedLead->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $lockedLead->email ?: $customer->email,
                'phone' => $lockedLead->phone ?: $customer->phone,
                'shipping_address' => $orderOverrides['shipping_address'] ?? 'Kathmandu, Nepal',
                'shipping_city' => $orderOverrides['shipping_city'] ?? 'Kathmandu',
                'district' => $orderOverrides['district'] ?? ($customer->district ?? 'Kathmandu'),
                'subtotal' => $totalAmount,
                'total_amount' => $totalAmount,
                'currency' => $lockedLead->currency ?: Setting::get('default_currency', 'NPR'),
                'status' => $orderOverrides['status'] ?? Order::STATUS_PROCESSING,
                'payment_method' => $orderOverrides['payment_method'] ?? 'cod',
                'payment_status' => $orderOverrides['payment_status'] ?? 'pending',
                'internal_notes' => $orderOverrides['internal_notes'] ?? ($orderOverrides['notes'] ?? "Concierge order generated from CRM Lead #{$lockedLead->id} ({$lockedLead->title})."),
            ]);

            // 4. Attach line item if product exists
            if ($product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku ?: 'SKU-' . $product->id,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                ]);
            }

            // 5. Update lead state
            $lockedLead->order_id = $order->id;
            $lockedLead->stage = CrmLead::STAGE_WON;
            $lockedLead->closed_at = now();
            $lockedLead->save();

            // 6. Log audit activity
            $this->logActivity(
                lead: $lockedLead,
                type: CrmActivity::TYPE_ORDER_LINKED,
                description: "Lead successfully converted to Order #{$order->order_number} for Rs. " . number_format($totalAmount, 2),
                metadata: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $totalAmount,
                    'channel' => $order->channel,
                ],
                staff: $staff
            );

            return $order;
        });
    }

    /**
     * Transition a lead through the visual pipeline stages and log audit entry.
     */
    public function transitionStage(CrmLead $lead, string $newStage, ?string $notes = null, ?User $staff = null): void
    {
        $oldStage = $lead->stage;
        $stages = CrmLead::getStages();

        $lead->stage = $newStage;
        if ($newStage === CrmLead::STAGE_WON || $newStage === CrmLead::STAGE_LOST) {
            $lead->closed_at = now();
        }

        if (!empty($notes)) {
            $lead->internal_notes = trim(($lead->internal_notes ? $lead->internal_notes . "\n" : '') . "[" . now()->format('d M H:i') . "] {$notes}");
        }

        $lead->save();

        $oldLabel = $stages[$oldStage] ?? ucfirst($oldStage);
        $newLabel = $stages[$newStage] ?? ucfirst($newStage);

        $description = "Stage changed from '{$oldLabel}' to '{$newLabel}'";
        if (!empty($notes)) {
            $description .= " — Note: {$notes}";
        }

        $this->logActivity(
            lead: $lead,
            type: CrmActivity::TYPE_STAGE_CHANGE,
            description: $description,
            metadata: [
                'from_stage' => $oldStage,
                'to_stage' => $newStage,
                'note' => $notes,
            ],
            staff: $staff
        );
    }

    /**
     * Schedule or reschedule follow-up date and notes.
     */
    public function scheduleFollowUp(CrmLead $lead, CarbonInterface $dateTime, ?string $notes = null, ?User $staff = null): void
    {
        $lead->follow_up_date = $dateTime;
        if (!empty($notes)) {
            $lead->follow_up_notes = $notes;
        }
        $lead->save();

        $this->logActivity(
            lead: $lead,
            type: CrmActivity::TYPE_FOLLOW_UP,
            description: "Follow-up scheduled for " . $dateTime->format('d M Y, h:i A') . ($notes ? " ({$notes})" : ""),
            metadata: [
                'scheduled_date' => $dateTime->toIso8601String(),
                'notes' => $notes,
            ],
            staff: $staff
        );
    }

    /**
     * Add internal staff note to timeline.
     */
    public function addNote(CrmLead $lead, string $note, ?User $staff = null): CrmActivity
    {
        $lead->internal_notes = trim(($lead->internal_notes ? $lead->internal_notes . "\n" : '') . "[" . now()->format('d M H:i') . "] {$note}");
        $lead->saveQuietly();

        return $this->logActivity(
            lead: $lead,
            type: CrmActivity::TYPE_NOTE,
            description: $note,
            metadata: [],
            staff: $staff
        );
    }

    /**
     * Record WhatsApp clienteling dispatch and auto-schedule next follow-up if none set.
     */
    public function recordWhatsAppOutreach(CrmLead $lead, User|int|null $staff = null, bool $autoScheduleFollowUp = true): CrmActivity
    {
        if ($autoScheduleFollowUp && empty($lead->follow_up_date)) {
            $lead->follow_up_date = now()->addDays(2);
            $lead->follow_up_notes = 'Auto-scheduled 48h follow-up after WhatsApp clienteling dispatch';
        }

        if ($lead->stage === CrmLead::STAGE_NEW) {
            $lead->stage = CrmLead::STAGE_CONTACTED;
        }

        $lead->saveQuietly();

        return $this->logActivity(
            lead: $lead,
            type: CrmActivity::TYPE_WHATSAPP,
            description: "WhatsApp clienteling inquiry link opened for phone {$lead->phone}",
            metadata: [
                'phone' => $lead->phone,
                'auto_followup' => $autoScheduleFollowUp ? $lead->follow_up_date?->toIso8601String() : null,
            ],
            staff: $staff
        );
    }

    /**
     * Log order exception into customer CRM timeline.
     */
    public function logOrderException(Order $order, string $exceptionType, string $notes, User|int|null $staff = null): ?CrmActivity
    {
        $order->internal_notes = trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . "[Exception: {$exceptionType}] {$notes}");
        $order->saveQuietly();

        $lead = $order->lead ?? ($order->crm_lead_id ? CrmLead::find($order->crm_lead_id) : null);
        if (!$lead) {
            return null;
        }

        return $this->logActivity(
            lead: $lead,
            type: CrmActivity::TYPE_NOTE,
            description: "⚠️ Order Exception [{$exceptionType}]: {$notes}",
            metadata: [
                'exception_type' => $exceptionType,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            staff: $staff
        );
    }

    /**
     * Log a structured activity timeline event.
     */
    public function logActivity(
        CrmLead $lead,
        string $type,
        string $description,
        array $metadata = [],
        User|int|null $staff = null
    ): CrmActivity {
        $staffId = $staff instanceof User ? $staff->id : ($staff ?: (auth('admin')->id() ?? auth('web')->id()));

        return CrmActivity::create([
            'crm_lead_id' => $lead->id,
            'user_id' => $staffId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Retrieve leads with overdue follow-ups.
     */
    public function getOverdueLeads(?int $staffId = null): Collection
    {
        $query = CrmLead::overdueFollowUps()->with(['customer', 'product', 'assignedStaff']);

        if ($staffId) {
            $query->where('assigned_staff_id', $staffId);
        }

        return $query->orderBy('follow_up_date')->get();
    }
}
