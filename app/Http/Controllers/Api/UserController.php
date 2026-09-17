<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TaxCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $normalizedCountry = TaxCalculatorService::normalizeCountryCode($user->country ?? 'NP');

        // Synthesize saved_addresses if user has primary address but empty array
        $savedAddresses = $user->saved_addresses;
        if (empty($savedAddresses) && !empty($user->address)) {
            $savedAddresses = [
                [
                    'id' => 'addr_default',
                    'full_name' => $user->name,
                    'address_line' => $user->address,
                    'apartment' => '',
                    'postal_code' => $user->postal_code ?? '',
                    'city' => $user->city ?? '',
                    'country' => $normalizedCountry,
                    'phone' => $user->phone ?? '',
                    'is_default' => true,
                ]
            ];
        } elseif (!empty($savedAddresses)) {
            $savedAddresses = array_map(function ($addr) {
                if (isset($addr['country'])) {
                    $addr['country'] = TaxCalculatorService::normalizeCountryCode($addr['country']);
                }
                return $addr;
            }, $savedAddresses);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'city' => $user->city,
            'postal_code' => $user->postal_code,
            'country' => $normalizedCountry,
            'saved_addresses' => $savedAddresses ?? [],
            'saved_measurements' => $user->saved_measurements,
            'roles' => $user->roles->pluck('name'),
            'orders_count' => $user->orders()->count(),
            'created_at' => $user->created_at ? $user->created_at->format('M d, Y') : '',
        ]);
    }

    /**
     * Update customer profile, addresses, and saved couture measurements.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'saved_addresses' => 'nullable|array',
            'saved_measurements' => 'nullable|array',
        ]);

        if ($request->filled('country')) {
            $validated['country'] = TaxCalculatorService::normalizeCountryCode($request->input('country'));
        }

        if ($request->filled('first_name') || $request->filled('last_name')) {
            $fName = trim((string)$request->input('first_name'));
            $lName = trim((string)$request->input('last_name'));
            $fullName = trim("{$fName} {$lName}");
            if (!empty($fullName)) {
                $validated['name'] = $fullName;
            }
        }

        // If saved_addresses are passed and contain a default address, sync primary address fields
        if ($request->has('saved_addresses')) {
            $addresses = $request->input('saved_addresses') ?? [];
            $addresses = array_map(function ($addr) {
                if (isset($addr['country'])) {
                    $addr['country'] = TaxCalculatorService::normalizeCountryCode($addr['country']);
                }
                return $addr;
            }, $addresses);
            $validated['saved_addresses'] = $addresses;

            $defaultAddr = collect($addresses)->firstWhere('is_default', true) ?? (count($addresses) > 0 ? $addresses[0] : null);
            if ($defaultAddr) {
                $validated['address'] = $defaultAddr['address_line'] ?? ($defaultAddr['address'] ?? $user->address);
                $validated['city'] = $defaultAddr['city'] ?? $user->city;
                $validated['postal_code'] = $defaultAddr['postal_code'] ?? $user->postal_code;
                $validated['country'] = TaxCalculatorService::normalizeCountryCode($defaultAddr['country'] ?? ($user->country ?? 'NP'));
                if (!empty($defaultAddr['phone']) && empty($validated['phone'])) {
                    $validated['phone'] = $defaultAddr['phone'];
                }
            }
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'city' => $user->city,
                'postal_code' => $user->postal_code,
                'country' => TaxCalculatorService::normalizeCountryCode($user->country ?? 'NP'),
                'saved_addresses' => $user->saved_addresses ?? [],
                'saved_measurements' => $user->saved_measurements,
            ],
        ]);
    }

    /**
     * Change customer password securely with current password check.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided current password does not match our records.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your password has been changed successfully.',
        ]);
    }

    /**
     * Get authenticated user's order history.
     */
    public function orders(Request $request)
    {
        $user = $request->user();

        // Auto-link any guest orders with matching email
        Order::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        $orders = Order::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
            })
            ->with(['items.product'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->order_number ?? (string)$order->id,
                    'order_number' => $order->order_number,
                    'raw_id' => $order->id,
                    'date' => $order->created_at ? $order->created_at->format('M d, Y') : '',
                    'status' => $order->status,
                    'status_label' => Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)),
                    'payment_status' => $order->payment_status,
                    'subtotal' => number_format($order->subtotal, 2),
                    'vat_amount' => number_format($order->vat_amount, 2),
                    'total' => 'Rs. ' . number_format($order->total_amount, 2),
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency ?: 'NPR',
                    'carrier' => $order->carrier,
                    'tracking_number' => $order->tracking_number,
                    'tracking_url' => $order->tracking_url ?? Order::resolveTrackingUrl($order->carrier, $order->tracking_number),
                    'items_count' => $order->items->sum('quantity'),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name ?? $item->product?->name ?? 'Luxury Piece',
                            'product_slug' => $item->product?->slug,
                            'featured_image' => $item->product?->featured_image,
                            'quantity' => $item->quantity,
                            'unit_price' => number_format($item->unit_price, 2),
                            'size' => $item->selected_size,
                            'color' => $item->selected_color,
                            'is_preorder' => (bool)$item->is_preorder,
                            'preorder_dispatch_note' => $item->preorder_dispatch_note,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }

    /**
     * Get details of a single owned order.
     */
    public function showOrder(Request $request, $orderNumber)
    {
        $user = $request->user();

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
            })
            ->with(['items.product'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or access denied.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->order_number,
                'order_number' => $order->order_number,
                'date' => $order->created_at ? $order->created_at->format('M d, Y') : '',
                'status' => $order->status,
                'status_label' => Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)),
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => number_format($order->subtotal, 2),
                'vat_amount' => number_format($order->vat_amount, 2),
                'shipping_fee' => number_format($order->shipping_fee, 2),
                'total_amount' => $order->total_amount,
                'total' => 'Rs. ' . number_format($order->total_amount, 2),
                'currency' => $order->currency ?: 'NPR',
                'carrier' => $order->carrier,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url ?? Order::resolveTrackingUrl($order->carrier, $order->tracking_number),
                'shipping_address' => [
                    'first_name' => $order->first_name,
                    'last_name' => $order->last_name,
                    'address' => $order->shipping_address,
                    'city' => $order->shipping_city,
                    'postal_code' => $order->shipping_postal_code,
                    'country' => $order->shipping_country,
                ],
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name ?? $item->product?->name ?? 'Luxury Piece',
                        'product_slug' => $item->product?->slug,
                        'featured_image' => $item->product?->featured_image,
                        'quantity' => $item->quantity,
                        'unit_price' => number_format($item->unit_price, 2),
                        'total_price' => number_format($item->total_price, 2),
                        'size' => $item->selected_size,
                        'color' => $item->selected_color,
                        'is_preorder' => (bool)$item->is_preorder,
                        'preorder_dispatch_note' => $item->preorder_dispatch_note,
                    ];
                }),
            ],
        ]);
    }
}
