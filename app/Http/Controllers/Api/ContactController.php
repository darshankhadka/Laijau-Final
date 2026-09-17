<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:200',
            'inquiry_type' => 'nullable|string|in:general,product_inquiry,order_status,delivery,complaint,bespoke,sizing',
            'order_id' => 'nullable|integer|exists:orders,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'message' => 'required|string|min:10|max:5000',
        ]);

        // Auto-associate customer if exists or create unified record
        $customer = $this->customerService->resolveOrCreateCustomer(
            name: $validated['name'],
            phone: $validated['phone'] ?? null,
            email: $validated['email']
        );

        $validated['customer_id'] = $customer->id;
        $validated['inquiry_type'] = $validated['inquiry_type'] ?? ContactMessage::INQUIRY_GENERAL;

        $contact = ContactMessage::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been received. Our concierge team in Kathmandu will respond shortly.',
            'data' => [
                'id' => $contact->id,
                'inquiry_type' => $contact->inquiry_type,
            ],
        ], 201);
    }
}
