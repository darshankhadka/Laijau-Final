@extends('layouts.storefront')

@section('title', 'Exchange Policy | Laijau')
@section('description', 'Learn about Laijau’s 7-day exchange policy. 7-day exchange only. No returns.')

@section('content')
<div class="bg-slate-50 py-12 md:py-16 text-left">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-[10px] font-bold tracking-wider uppercase text-blue-600 block mb-2">
                Customer Care & Policy
            </span>
            <h1 class="text-2xl md:text-4xl font-black text-slate-900 tracking-tight">
                Exchange Policy
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-2 max-w-xl mx-auto">
                7-day exchange only. No Returns.
            </p>
        </div>

        <div class="space-y-6 text-xs sm:text-sm text-slate-700 leading-relaxed bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs">
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">7-Day Exchange Policy (No Returns)</h3>
                <p>
                    Laijau operates a strict <strong>7-day exchange only</strong> policy. We do not offer returns or refunds. If your item requires a size exchange, you may request an exchange within 7 days of receiving your order.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Product Condition Requirements</h3>
                <p>
                    Items submitted for exchange must be completely unused, unwashed, unaltered, and in their original packaging with all tags intact. Footwear must show zero sole markings.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">Exchange Logistics in Nepal</h3>
                <p>
                    <strong>Kathmandu Valley:</strong> You can exchange sizes at our showrooms (Laijau Showroom or Laijau Showroom 2) or request rider dispatch.<br />
                    <strong>Outside Valley:</strong> Dispatch the item via courier, and our fulfillment team will courier the replacement size following verification.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">How to Request an Exchange</h3>
                <p>
                    Contact us with your Order Number:
                    <br />
                    WhatsApp: <a href="https://wa.me/9779843512095" target="_blank" class="text-emerald-600 font-bold hover:underline">9843512095</a>
                    <br />
                    Email: <a href="mailto:info@laijau.com" class="text-blue-600 font-bold hover:underline">info@laijau.com</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
