@extends('layouts.storefront')

@section('title', 'Customer Support & Showrooms | Laijau.com')
@section('description', 'Connect with Laijau customer care in Kathmandu for orders, sizing, delivery queries, or WhatsApp support at 9843512095.')

@section('content')
<div class="bg-slate-50 min-h-screen py-12 md:py-16 text-left" x-data="{
    name: '',
    email: '',
    phone: '',
    inquiry_type: 'general',
    subject: '',
    message: '',
    status: 'idle',
    errorMsg: null,
    async submitForm() {
        this.status = 'submitting';
        this.errorMsg = null;
        try {
            const token = document.querySelector('meta[name=csrf-token]')?.content;
            const res = await fetch('{{ url('/api/contact') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || ''
                },
                body: JSON.stringify({
                    name: this.name,
                    email: this.email,
                    phone: this.phone,
                    inquiry_type: this.inquiry_type,
                    subject: this.subject,
                    message: this.message
                })
            });
            if (res.ok) {
                this.status = 'success';
                this.name = ''; this.email = ''; this.phone = ''; this.inquiry_type = 'general'; this.subject = ''; this.message = '';
            } else {
                const data = await res.json();
                this.errorMsg = data.message || 'Unable to submit your message. Please try again.';
                this.status = 'error';
            }
        } catch(e) {
            this.errorMsg = 'Network error. Please try again.';
            this.status = 'error';
        }
    }
}">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-[10px] font-bold tracking-wider uppercase text-blue-600 block mb-2">
                Customer Care & Showrooms
            </span>
            <h1 class="text-3xl md:text-5xl font-black text-slate-900 tracking-tight mb-3">
                Contact Laijau
            </h1>
            <p class="text-xs md:text-sm text-slate-500 max-w-lg mx-auto leading-relaxed">
                Our support team in Kathmandu is ready to assist you with order status, sizing inquiries, showroom visits, and delivery tracking.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12 items-start">
            <!-- Left Column: Direct Info -->
            <div class="md:col-span-5 space-y-6 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
                <div>
                    <h2 class="text-base font-bold text-slate-900 uppercase tracking-wider mb-3">
                        Showrooms & Office
                    </h2>
                    <p class="text-xs text-slate-600 leading-relaxed mb-3">
                        <strong>Showroom 1:</strong> Laijau Showroom<br />
                        <strong>Showroom 2:</strong> Laijau Showroom 2<br />
                        Bohara Tol, Kageshwori Manahara 09<br />
                        Kathmandu, Nepal
                    </p>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 space-y-1">
                        <p><strong>Legal Entity:</strong> Delta Nine business group</p>
                        <p><strong>VAT Number:</strong> 604335148</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                        Direct Inquiries
                    </h3>
                    <div class="text-xs text-slate-600 space-y-2">
                        <p class="flex items-center gap-2">
                            <span class="font-semibold text-slate-900">Email:</span>
                            <a href="mailto:info@laijau.com" class="text-blue-600 hover:underline">info@laijau.com</a>
                        </p>
                        <p class="flex items-center gap-2">
                            <span class="font-semibold text-slate-900">Phone / WhatsApp:</span>
                            <a href="tel:9843512095" class="text-emerald-600 font-bold hover:underline">9843512095</a>
                        </p>
                        <p class="text-[11px] text-slate-400">
                            Support Hours: 8:00 AM – 7:00 PM, 7 days a week
                        </p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 space-y-2.5">
                    <a
                        href="https://wa.me/9779843512095?text={{ urlencode('Namaste! Welcome to Laijau. How can we help you today?') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-[#25D366] text-white text-xs font-bold rounded-xl hover:bg-[#20bd5a] transition-colors shadow-xs w-full justify-center"
                    >
                        <span>WhatsApp Concierge (9843512095)</span>
                    </a>
                    <a
                        href="{{ route('storefront.track_order') }}"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors shadow-xs w-full justify-center"
                    >
                        <span>Track Your Order Online</span>
                    </a>
                </div>
            </div>

            <!-- Right Column: Interactive Form -->
            <div class="md:col-span-7 bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-xs">
                <template x-if="status === 'success'">
                    <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 text-xs space-y-2">
                        <h4 class="font-bold text-sm">Message Received!</h4>
                        <p>Thank you for reaching out to Laijau. Our customer support team will reply within 24 hours.</p>
                    </div>
                </template>

                <form x-show="status !== 'success'" @submit.prevent="submitForm" class="space-y-4">
                    <template x-if="errorMsg">
                        <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs" x-text="errorMsg"></div>
                    </template>

                    <div>
                        <label class="block text-xs font-bold text-slate-900 mb-1">
                            Your Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            required
                            x-model="name"
                            placeholder="Full name"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-900 mb-1">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            required
                            x-model="email"
                            placeholder="name@example.com"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-900 mb-1">
                                WhatsApp / Mobile Phone
                            </label>
                            <input
                                type="tel"
                                x-model="phone"
                                placeholder="98XXXXXXXX"
                                class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-900 mb-1">
                                Inquiry Topic
                            </label>
                            <select
                                x-model="inquiry_type"
                                class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                            >
                                <option value="general">General Inquiry</option>
                                <option value="product_inquiry">Footwear & Sizing Questions</option>
                                <option value="order_status">Order Status & Tracking</option>
                                <option value="delivery">Delivery & Courier Queries</option>
                                <option value="exchange">7-Day Size Exchange</option>
                                <option value="complaint">Feedback / Support Issue</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-900 mb-1">
                            Subject
                        </label>
                        <input
                            type="text"
                            x-model="subject"
                            placeholder="Order inquiry, sizing guidance, exchange requests..."
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-900 mb-1">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            rows="5"
                            required
                            x-model="message"
                            placeholder="How can we assist you today?"
                            class="w-full bg-slate-50 border border-slate-300 rounded-xl focus:border-blue-600 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        :disabled="status === 'submitting'"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-colors disabled:opacity-50 shadow-xs cursor-pointer"
                    >
                        <span x-text="status === 'submitting' ? 'Sending Message...' : 'Send Message'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
