@extends('layouts.storefront')

@section('title', 'Privacy Policy | Laijau.com')
@section('description', 'Learn how Laijau protects customer data, order details, and privacy for online shopping in Nepal.')

@section('content')
<div class="bg-slate-50 py-12 md:py-16 text-left">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-[10px] font-bold tracking-wider uppercase text-blue-600 block mb-2">
                Privacy & Data Security
            </span>
            <h1 class="text-2xl md:text-4xl font-black text-slate-900 tracking-tight">
                Privacy Policy
            </h1>
            <p class="text-xs md:text-sm text-slate-500 mt-2 max-w-xl mx-auto">
                Laijau is committed to protecting your personal information and ensuring a secure shopping experience across Nepal.
            </p>
        </div>

        <div class="space-y-6 text-xs sm:text-sm text-slate-700 leading-relaxed bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs">
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">1. Operating Entity</h3>
                <p>
                    Delta Nine business group (trading as Laijau), headquartered at Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal (VAT No: 604335148), operates Laijau.com and is the controller responsible for protecting your personal information.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">2. Information We Collect</h3>
                <p>
                    We collect essential customer details (full name, phone number, delivery address with province, district, and tole) strictly required to dispatch your orders, provide tracking updates, and coordinate with courier services (NCM, Pathao).
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">3. Payment Information Security</h3>
                <p>
                    For Cash on Delivery, payment is made directly to the delivery courier. For digital payments via eSewa, Khalti, or Bank Transfer, transactions occur directly inside official third-party apps and we only verify reference numbers manually.
                </p>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 mb-2">4. Your Rights & Data Contact</h3>
                <p>
                    You may update or delete your account information anytime through your customer portal, or contact our team at <a href="mailto:privacy@laijau.com" class="text-blue-600 font-bold underline">privacy@laijau.com</a>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
