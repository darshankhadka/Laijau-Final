@extends('layouts.storefront')

@section('title', 'About Laijau | Quality Footwear & Fashion in Nepal')
@section('description', 'Laijau brings quality footwear, loafers, party shoes, sneakers, boots, and contemporary fashion to customers across Kathmandu Valley and nationwide Nepal.')

@section('content')
<div class="bg-slate-50 min-h-screen py-12 md:py-16 text-left">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center max-w-2xl mx-auto mb-16">
            <span class="text-[10px] font-bold tracking-wider uppercase text-blue-600 block mb-2">
                Our Story & Commitment
            </span>
            <h1 class="text-3xl sm:text-5xl font-black text-slate-900 tracking-tight">
                Authentic Craft, <br />
                Everyday Comfort.
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-4 leading-relaxed">
                Laijau is Nepal’s premier footwear and lifestyle retail brand, delivering quality loafers, party shoes, boots, sneakers, slippers, and apparel with transparent pricing and fast nationwide courier delivery.
            </p>
        </div>

        <!-- Narrative Section 1 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center mb-16 bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs">
            <div class="aspect-4/3 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="Laijau Brand"
                    class="h-full w-full object-contain p-8"
                />
            </div>
            <div class="space-y-4 text-xs sm:text-sm text-slate-600 leading-relaxed">
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Premium Quality</span>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Dedicated to Footwear Excellence
                </h2>
                <p>
                    From handcrafted leather loafers and formal party shoes to rugged outdoor boots, casual sneakers, and comfortable daily slippers, every model in our collection is curated for durability, comfort, and style.
                </p>
                <p>
                    We partner with verified footwear manufacturers and quality tanneries to ensure genuine materials, strong stitching, ergonomic insoles, and long-lasting outsoles crafted for everyday wear in Nepal.
                </p>
            </div>
        </div>

        <!-- Narrative Section 2 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center mb-16 bg-white p-6 sm:p-10 rounded-3xl border border-slate-200 shadow-xs">
            <div class="space-y-4 text-xs sm:text-sm text-slate-600 leading-relaxed order-2 md:order-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Nationwide Reach</span>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Kathmandu Hub & All 77 Districts
                </h2>
                <p>
                    Headquartered at Bohara Tol, Kageshwori Manahara 09 in Kathmandu, Laijau operates active physical showrooms and a high-capacity central fulfillment facility.
                </p>
                <p>
                    Every order is individually inspected for sizing precision and spotless finish before dispatch. We provide Cash on Delivery inside Kathmandu Valley and rapid courier delivery across all 77 districts via trusted partners like Nepal Can Move (NCM) and Pathao.
                </p>
            </div>
            <div class="bg-blue-50/70 border border-blue-100 p-6 sm:p-8 rounded-2xl order-1 md:order-2 space-y-3">
                <h3 class="text-base font-bold text-slate-900">Why Customers Trust Laijau</h3>
                <ul class="space-y-2.5 text-xs text-slate-700">
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span><strong>100% Genuine Inventory:</strong> Every product shown online is stocked in our real Kathmandu warehouse.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span><strong>Cash on Delivery:</strong> Pay at your doorstep anywhere within Kathmandu, Lalitpur, and Bhaktapur.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span><strong>7-Day Size Exchange:</strong> Quick, hassle-free size replacement support.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="text-emerald-600 font-bold">✓</span>
                        <span><strong>Live WhatsApp Support:</strong> Direct assistance via WhatsApp at 9843512095.</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="text-center bg-white p-8 rounded-3xl border border-slate-200 shadow-xs">
            <h3 class="text-lg font-bold text-slate-900 mb-2">Explore Our Latest Catalogue</h3>
            <p class="text-xs text-slate-500 mb-5">Discover over 500+ genuine footwear styles and contemporary apparel.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('storefront.catalogue') }}" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors shadow-xs">
                    Browse All Products
                </a>
                <a href="{{ route('storefront.contact') }}" class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors">
                    Visit Our Showrooms
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
