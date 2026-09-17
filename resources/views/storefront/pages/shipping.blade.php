@extends('layouts.storefront')

@section('title', 'Delivery Policy & Shipping Rates | Laijau.com')
@section('description', 'Nepal nationwide delivery rates, Kathmandu Valley Cash on Delivery (COD), transit times, and live order tracking via NCM and Pathao Courier on Laijau.')

@section('content')
<div class="bg-slate-50 py-12 md:py-16 text-left">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-[10px] font-bold tracking-wider uppercase text-blue-600 block mb-2">
                Logistics & Delivery Coverage
            </span>
            <h1 class="text-2xl md:text-4xl font-black text-slate-900 tracking-tight">
                Nepal Shipping & Delivery
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-2 max-w-xl mx-auto">
                All products are dispatched directly from our Kathmandu fulfillment center with door-to-door courier service across all 77 districts of Nepal.
            </p>
        </div>

        <!-- Shipping Rates Table -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 mb-10 overflow-x-auto shadow-xs">
            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider mb-4">
                Delivery Rates & Coverage
            </h2>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500 uppercase tracking-wider font-semibold">
                        <th class="py-3 px-3">Zone</th>
                        <th class="py-3 px-3">Courier</th>
                        <th class="py-3 px-3">Time</th>
                        <th class="py-3 px-3">Standard</th>
                        <th class="py-3 px-3">Free Above</th>
                        <th class="py-3 px-3">Cash on Delivery</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr>
                        <td class="py-3.5 px-3 font-semibold text-slate-900">Kathmandu Valley (Kathmandu, Lalitpur, Bhaktapur)</td>
                        <td class="py-3.5 px-3">Laijau Express</td>
                        <td class="py-3.5 px-3">24–48 hours</td>
                        <td class="py-3.5 px-3 font-bold">Rs. 100</td>
                        <td class="py-3.5 px-3 text-emerald-600 font-bold">Rs. 2,000</td>
                        <td class="py-3.5 px-3 text-emerald-600 font-bold">✓ Yes (COD Available)</td>
                    </tr>
                    <tr>
                        <td class="py-3.5 px-3 font-semibold text-slate-900">Major Cities (Pokhara, Biratnagar, Chitwan, Butwal, etc.)</td>
                        <td class="py-3.5 px-3">NCM / Pathao</td>
                        <td class="py-3.5 px-3">2–3 business days</td>
                        <td class="py-3.5 px-3 font-bold">Rs. 150</td>
                        <td class="py-3.5 px-3 text-emerald-600 font-bold">Rs. 3,500</td>
                        <td class="py-3.5 px-3 text-amber-600 font-medium">Prepaid (eSewa / Khalti / Bank)</td>
                    </tr>
                    <tr>
                        <td class="py-3.5 px-3 font-semibold text-slate-900">All Other 77 Districts & Hill Areas</td>
                        <td class="py-3.5 px-3">Nepal Can Move (NCM)</td>
                        <td class="py-3.5 px-3">3–5 business days</td>
                        <td class="py-3.5 px-3 font-bold">Rs. 150</td>
                        <td class="py-3.5 px-3 text-emerald-600 font-bold">Rs. 3,500</td>
                        <td class="py-3.5 px-3 text-amber-600 font-medium">Prepaid (eSewa / Khalti / Bank)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-6 text-xs sm:text-sm text-slate-700 leading-relaxed bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-1.5">Cash on Delivery Policy</h3>
                <p>
                    COD is supported inside Kathmandu, Lalitpur, and Bhaktapur. The delivery rider will call your phone prior to arrival.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-1.5">Nationwide Dispatch Process</h3>
                <p>
                    For orders outside Kathmandu Valley, once you submit your payment reference (eSewa, Khalti, or Bank Transfer), our operations team manually verifies the transaction and assigns an NCM or Pathao courier consignment number.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-1.5">Live Tracking</h3>
                <p>
                    Track your order progress anytime at <a href="{{ route('storefront.track_order') }}" class="text-blue-600 font-bold underline">laijau.com/track-order</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
