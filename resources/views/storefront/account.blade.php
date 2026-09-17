@extends('layouts.storefront')

@section('title', 'Customer Account Portal | Laijau.com')
@section('description', 'Access your Laijau customer account, track orders, view delivery status, and manage profile.')

@section('content')
<script>
    window.__LAIJAU_INITIAL_ORDERS__ = @json($ordersData ?? []);

    function accountPortal() {
        return {
            activeTab: 'overview',
            authMode: 'login',
            loading: false,
            authError: null,
            successMsg: null,

            // Auth Form States
            loginEmail: '',
            loginPassword: '',
            regName: '',
            regEmail: '',
            regPassword: '',
            regPasswordConfirm: '',
            forgotEmail: '',
            resetToken: '',
            resetEmail: '',

            // Orders & Search in Account
            orderSearch: '',
            expandedOrders: {},
            userOrders: window.__LAIJAU_INITIAL_ORDERS__ || [],

            // Profile details
            profileName: '',
            profilePhone: '',
            profileAddress: '',
            profileCity: '',
            profilePostal: '',
            profileCountry: 'NP',

            // Password change
            currentPassword: '',
            newPassword: '',
            newPasswordConfirm: '',

            // Measurements
            measureBust: '',
            measureWaist: '',
            measureHip: '',
            measureShoulder: '',
            measureHeight: '',
            measureNotes: '',

            get filteredOrders() {
                if (!this.orderSearch.trim()) return this.userOrders;
                const q = this.orderSearch.toLowerCase().trim();
                return this.userOrders.filter(o => {
                    const num = (o.order_number || '').toLowerCase();
                    const stat = (o.status || '').toLowerCase();
                    const pay = (o.payment_status || '').toLowerCase();
                    const tracking = (o.tracking_number || '').toLowerCase();
                    const items = (o.items || []).some(i => (i.product_name || '').toLowerCase().includes(q));
                    return num.includes(q) || stat.includes(q) || pay.includes(q) || tracking.includes(q) || items;
                });
            },

            toggleOrderExpand(orderId) {
                this.expandedOrders[orderId] = !this.expandedOrders[orderId];
            },

            normalizeCountry(val) {
                if (!val) return 'NP';
                const s = String(val).trim().toUpperCase();
                if (s.length === 2) return s;
                const map = {
                    'NEPAL': 'NP',
                    'INDIA': 'IN',
                    'UNITED STATES': 'US', 'USA': 'US',
                    'UNITED KINGDOM': 'GB', 'UK': 'GB',
                    'AUSTRALIA': 'AU',
                    'CANADA': 'CA'
                };
                return map[s] || (s.length === 2 ? s : 'NP');
            },

            async init() {
                const urlParams = new URLSearchParams(window.location.search);
                const token = urlParams.get('token');
                const error = urlParams.get('error');
                const resetTok = urlParams.get('reset_token');
                const emailParam = urlParams.get('email');

                if (resetTok && emailParam) {
                    this.authMode = 'reset';
                    this.resetToken = resetTok;
                    this.resetEmail = emailParam;
                }

                if (error) {
                    if (error === 'google_auth_cancelled') {
                        this.authError = 'Google sign-in was cancelled.';
                    } else if (error === 'account_conflict') {
                        this.authError = 'This Google account is linked to another customer profile.';
                    } else {
                        this.authError = 'Google authentication could not be completed. Please sign in with email.';
                    }
                    window.history.replaceState(null, '', window.location.pathname);
                }

                if (token) {
                    try { localStorage.setItem('auth_token', token); } catch (e) {}
                    window.history.replaceState(null, '', window.location.pathname);
                }

                // Server-side customer session is strictly AUTHORITATIVE
                const serverUser = @json(auth('web')->user());
                if (serverUser) {
                    if (this.$store && this.$store.store) {
                        this.$store.store.user = serverUser;
                    }
                    try { localStorage.setItem('laijau_cached_user', JSON.stringify(serverUser)); } catch (e) {}
                } else {
                    if (this.$store && this.$store.store) {
                        this.$store.store.user = null;
                    }
                    try {
                        localStorage.removeItem('laijau_cached_user');
                        localStorage.removeItem('auth_token');
                    } catch (e) {}
                }

                const currentUser = this.$store && this.$store.store ? this.$store.store.user : serverUser;
                if (currentUser) {
                    this.profileName = currentUser.name || '';
                    this.profilePhone = currentUser.phone || '';
                    this.profileAddress = currentUser.address || '';
                    this.profileCity = currentUser.city || '';
                    this.profilePostal = currentUser.postal_code || '';
                    this.profileCountry = this.normalizeCountry(currentUser.country || 'NP');

                    const m = currentUser.saved_measurements || {};
                    this.measureBust = m.bust || '';
                    this.measureWaist = m.waist || '';
                    this.measureHip = m.hip || '';
                    this.measureShoulder = m.shoulder_width || '';
                    this.measureHeight = m.height || '';
                    this.measureNotes = m.notes || '';

                    this.fetchOrders();
                }
            },

            async fetchOrders() {
                const token = localStorage.getItem('auth_token');
                try {
                    const res = await fetch('/api/user/orders', {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (Array.isArray(data) && data.length > 0) {
                            this.userOrders = data;
                        }
                    }
                } catch (e) {}
            },

            async loginWithGoogle() {
                this.authError = null;
                this.loading = true;
                try {
                    const res = await fetch('/auth/google/redirect', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (res.ok && data.url) {
                        window.location.href = data.url;
                    } else {
                        this.authError = data.message || 'Google Sign-In is temporarily unavailable.';
                        this.loading = false;
                    }
                } catch (e) {
                    this.authError = 'Unable to connect to Google authentication service.';
                    this.loading = false;
                }
            },

            async saveAddress() {
                this.loading = true;
                this.successMsg = null;
                this.authError = null;
                const token = localStorage.getItem('auth_token');
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/api/user/profile', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        },
                        body: JSON.stringify({
                            name: this.profileName,
                            phone: this.profilePhone,
                            address: this.profileAddress,
                            city: this.profileCity,
                            postal_code: this.profilePostal,
                            country: this.normalizeCountry(this.profileCountry)
                        })
                    });
                    if (res.ok) {
                        this.profileCountry = this.normalizeCountry(this.profileCountry);
                        this.successMsg = 'Delivery address saved successfully.';
                        if (this.$store && this.$store.store && this.$store.store.user) {
                            this.$store.store.user.address = this.profileAddress;
                            this.$store.store.user.city = this.profileCity;
                            this.$store.store.user.postal_code = this.profilePostal;
                            this.$store.store.user.country = this.profileCountry;
                            this.$store.store.user.phone = this.profilePhone;
                            try { localStorage.setItem('laijau_cached_user', JSON.stringify(this.$store.store.user)); } catch (e) {}
                        }
                    } else {
                        const errData = await res.json();
                        this.authError = errData.message || 'Could not update address.';
                    }
                } catch (e) {
                    this.authError = 'Could not update address.';
                } finally {
                    this.loading = false;
                }
            },

            async saveMeasurements() {
                this.loading = true;
                this.successMsg = null;
                this.authError = null;
                const token = localStorage.getItem('auth_token');
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/api/user/profile', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        },
                        body: JSON.stringify({
                            saved_measurements: {
                                bust: this.measureBust,
                                waist: this.measureWaist,
                                hip: this.measureHip,
                                shoulder_width: this.measureShoulder,
                                height: this.measureHeight,
                                notes: this.measureNotes
                            }
                        })
                    });
                    if (res.ok) {
                        this.successMsg = 'Bespoke measurements updated successfully.';
                        if (this.$store && this.$store.store && this.$store.store.user) {
                            this.$store.store.user.saved_measurements = {
                                bust: this.measureBust,
                                waist: this.measureWaist,
                                hip: this.measureHip,
                                shoulder_width: this.measureShoulder,
                                height: this.measureHeight,
                                notes: this.measureNotes
                            };
                            try { localStorage.setItem('laijau_cached_user', JSON.stringify(this.$store.store.user)); } catch (e) {}
                        }
                    } else {
                        const errData = await res.json();
                        this.authError = errData.message || 'Could not save measurements.';
                    }
                } catch (e) {
                    this.authError = 'Could not save measurements.';
                } finally {
                    this.loading = false;
                }
            },

            async changePassword() {
                this.authError = null;
                this.successMsg = null;
                if (this.newPassword !== this.newPasswordConfirm) {
                    this.authError = 'New passwords do not match.';
                    return;
                }
                this.loading = true;
                const token = localStorage.getItem('auth_token');
                const csrf = document.querySelector('meta[name=csrf-token]')?.content;
                try {
                    const res = await fetch('/api/user/change-password', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf || '',
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        },
                        body: JSON.stringify({
                            current_password: this.currentPassword,
                            password: this.newPassword,
                            password_confirmation: this.newPasswordConfirm
                        })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.successMsg = 'Your password has been changed successfully.';
                        this.currentPassword = '';
                        this.newPassword = '';
                        this.newPasswordConfirm = '';
                    } else {
                        this.authError = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || 'Password change failed.';
                    }
                } catch (e) {
                    this.authError = 'Connection error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async sendPasswordReset() {
                this.authError = null;
                this.successMsg = null;
                this.loading = true;
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/api/auth/forgot-password', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ email: this.forgotEmail })
                    });
                    const data = await res.json();
                    if (res.ok) {
                        this.successMsg = data.message || 'If an account exists with that email, a password reset link has been dispatched.';
                        this.forgotEmail = '';
                    } else {
                        this.authError = data.message || 'Unable to request password reset.';
                    }
                } catch (e) {
                    this.authError = 'Connection error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async submitPasswordReset() {
                this.authError = null;
                this.successMsg = null;
                if (this.newPassword !== this.newPasswordConfirm) {
                    this.authError = 'New passwords do not match.';
                    return;
                }
                this.loading = true;
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/api/auth/reset-password', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            token: this.resetToken,
                            email: this.resetEmail,
                            password: this.newPassword,
                            password_confirmation: this.newPasswordConfirm
                        })
                    });
                    const data = await res.json();
                    if (res.ok && data.access_token) {
                        localStorage.setItem('auth_token', data.access_token);
                        localStorage.setItem('laijau_cached_user', JSON.stringify(data.user));
                        if (this.$store && this.$store.store) {
                            this.$store.store.user = data.user;
                        }
                        window.location.href = '/account';
                    } else {
                        this.authError = data.message || 'Password reset could not be completed.';
                    }
                } catch (e) {
                    this.authError = 'Connection error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async login() {
                this.authError = null;
                this.loading = true;
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/login', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({ email: this.loginEmail, password: this.loginPassword })
                    });
                    const data = await res.json();
                    if (res.ok && (data.success || data.access_token)) {
                        if (data.access_token) {
                            try { localStorage.setItem('auth_token', data.access_token); } catch(e) {}
                        }
                        if (data.user) {
                            try { localStorage.setItem('laijau_cached_user', JSON.stringify(data.user)); } catch(e) {}
                            if (this.$store && this.$store.store) {
                                this.$store.store.user = data.user;
                            }
                        }
                        window.location.href = '/account';
                    } else {
                        this.authError = data.message || 'Invalid email address or password.';
                    }
                } catch (e) {
                    this.authError = 'Connection error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async register() {
                this.authError = null;
                if (this.regPassword !== this.regPasswordConfirm) {
                    this.authError = 'Passwords do not match.';
                    return;
                }
                this.loading = true;
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    const res = await fetch('/register', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            name: this.regName,
                            email: this.regEmail,
                            password: this.regPassword,
                            password_confirmation: this.regPasswordConfirm
                        })
                    });
                    const data = await res.json();
                    if (res.ok && (data.success || data.access_token)) {
                        if (data.access_token) {
                            try { localStorage.setItem('auth_token', data.access_token); } catch(e) {}
                        }
                        if (data.user) {
                            try { localStorage.setItem('laijau_cached_user', JSON.stringify(data.user)); } catch(e) {}
                            if (this.$store && this.$store.store) {
                                this.$store.store.user = data.user;
                            }
                        }
                        window.location.href = '/account';
                    } else {
                        this.authError = data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Registration could not be completed.');
                    }
                } catch (e) {
                    this.authError = 'Connection error. Please try again.';
                } finally {
                    this.loading = false;
                }
            },

            async logout() {
                const token = localStorage.getItem('auth_token');
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                try {
                    await fetch('/logout', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                        }
                    });
                } catch (e) {}
                try {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('laijau_cached_user');
                } catch (e) {}
                if (this.$store && this.$store.store) {
                    this.$store.store.user = null;
                }
                window.location.href = '/account';
            }
        };
    }
</script>

<div
    class="bg-slate-50 min-h-screen py-10 sm:py-16 text-left"
    x-data="accountPortal()"
>
    <div class="container mx-auto px-6 max-w-5xl">

        <!-- ================= GUEST AUTHENTICATION PORTAL ================= -->
        <template x-if="!$store.store.user">
            <div class="max-w-md mx-auto">
                <div class="text-center mb-8">
                    <span class="text-[11px] font-bold tracking-wider uppercase text-brand-600 block mb-1">
                        Laijau Account
                    </span>
                    <h1 class="font-display font-bold text-3xl text-slate-900 tracking-tight mb-2">
                        Sign In or Register
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                        Access your order tracking, delivery addresses, and saved items across Nepal.
                    </p>
                </div>

                <!-- Notification Alerts -->
                <template x-if="authError">
                    <div class="p-3 bg-red-50 border border-red-200 text-xs text-red-700 rounded-lg mb-6 animate-in fade-in">
                        <span x-text="authError"></span>
                    </div>
                </template>
                <template x-if="successMsg">
                    <div class="p-3 bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 rounded-lg mb-6 animate-in fade-in">
                        <span x-text="successMsg"></span>
                    </div>
                </template>

                <!-- Auth Mode Tabs (Sign In / Register) -->
                <div x-show="authMode === 'login' || authMode === 'register'" class="flex border-b border-slate-200 mb-8">
                    <button
                        type="button"
                        @click="authMode = 'login'; authError = null; successMsg = null;"
                        :class="authMode === 'login' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="flex-1 py-3 text-xs uppercase tracking-wider text-center cursor-pointer"
                    >
                        Sign In
                    </button>
                    <button
                        type="button"
                        @click="authMode = 'register'; authError = null; successMsg = null;"
                        :class="authMode === 'register' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="flex-1 py-3 text-xs uppercase tracking-wider text-center cursor-pointer"
                    >
                        Create Account
                    </button>
                </div>

                <!-- 1. Sign In Form -->
                <div x-show="authMode === 'login'" class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs">
                    <form @submit.prevent="login()" class="space-y-4">
                        <div>
                            <label for="login-email" class="block text-xs font-medium text-slate-700 mb-1">Email Address</label>
                            <input id="login-email" type="email" required x-model="loginEmail" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <div class="flex justify-between items-baseline mb-1">
                                <label for="login-password" class="block text-xs font-medium text-slate-700">Password</label>
                                <button type="button" @click="authMode = 'forgot'; authError = null; successMsg = null;" class="text-[11px] text-brand-600 hover:underline cursor-pointer">
                                    Forgot Password?
                                </button>
                            </div>
                            <input id="login-password" type="password" required x-model="loginPassword" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full py-3 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer mt-4"
                        >
                            <span x-show="!loading">Sign In &rarr;</span>
                            <span x-show="loading" style="display: none;">Verifying Credentials...</span>
                        </button>
                    </form>

                    <!-- Google Sign-In Divider & Button -->
                    <div class="relative flex py-4 items-center mt-2">
                        <div class="flex-grow border-t border-slate-200"></div>
                        <span class="flex-shrink mx-4 text-[10px] uppercase tracking-widest text-slate-400">or continue with</span>
                        <div class="flex-grow border-t border-slate-200"></div>
                    </div>

                    <button
                        type="button"
                        @click="loginWithGoogle()"
                        :disabled="loading"
                        class="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium transition-all disabled:opacity-50 cursor-pointer"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Continue with Google</span>
                    </button>
                </div>

                <!-- 2. Create Account Form -->
                <div x-show="authMode === 'register'" class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs" style="display: none;">
                    <form @submit.prevent="register()" class="space-y-4">
                        <div>
                            <label for="reg-name" class="block text-xs font-medium text-slate-700 mb-1">Full Name</label>
                            <input id="reg-name" type="text" required x-model="regName" placeholder="Aayushma Sharma" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="reg-email" class="block text-xs font-medium text-slate-700 mb-1">Email Address</label>
                            <input id="reg-email" type="email" required x-model="regEmail" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="reg-pass" class="block text-xs font-medium text-slate-700 mb-1">Password</label>
                            <input id="reg-pass" type="password" required minlength="8" x-model="regPassword" placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="reg-pass-confirm" class="block text-xs font-medium text-slate-700 mb-1">Confirm Password</label>
                            <input id="reg-pass-confirm" type="password" required minlength="8" x-model="regPasswordConfirm" placeholder="Repeat password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full py-3 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer mt-4"
                        >
                            <span x-show="!loading">Create Account</span>
                            <span x-show="loading" style="display: none;">Creating Profile...</span>
                        </button>
                    </form>

                    <!-- Google Sign-In Divider & Button -->
                    <div class="relative flex py-4 items-center mt-2">
                        <div class="flex-grow border-t border-slate-200"></div>
                        <span class="flex-shrink mx-4 text-[10px] uppercase tracking-widest text-slate-400">or continue with</span>
                        <div class="flex-grow border-t border-slate-200"></div>
                    </div>

                    <button
                        type="button"
                        @click="loginWithGoogle()"
                        :disabled="loading"
                        class="w-full flex items-center justify-center gap-3 px-4 py-2.5 border border-slate-300 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-medium transition-all disabled:opacity-50 cursor-pointer"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span>Continue with Google</span>
                    </button>
                </div>

                <!-- 3. Forgot Password Form -->
                <div x-show="authMode === 'forgot'" class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs" style="display: none;">
                    <div class="mb-4">
                        <h3 class="font-display font-bold text-lg text-slate-900">Reset Account Password</h3>
                        <p class="text-xs text-slate-500 mt-1">Enter your registered email address and we will dispatch a secure reset link.</p>
                    </div>
                    <form @submit.prevent="sendPasswordReset()" class="space-y-4">
                        <div>
                            <label for="forgot-email" class="block text-xs font-medium text-slate-700 mb-1">Email Address</label>
                            <input id="forgot-email" type="email" required x-model="forgotEmail" placeholder="name@example.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full py-3 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer"
                        >
                            <span x-show="!loading">Send Reset Link &rarr;</span>
                            <span x-show="loading" style="display: none;">Dispatching Link...</span>
                        </button>
                    </form>
                    <div class="mt-6 text-center">
                        <button type="button" @click="authMode = 'login'; authError = null;" class="text-xs text-brand-600 hover:underline cursor-pointer">
                            &larr; Back to Sign In
                        </button>
                    </div>
                </div>

                <!-- 4. Reset Password Form (Token Activated) -->
                <div x-show="authMode === 'reset'" class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs" style="display: none;">
                    <div class="mb-4">
                        <h3 class="font-display font-bold text-lg text-slate-900">Set New Password</h3>
                        <p class="text-xs text-slate-500 mt-1" x-text="`Resetting password for ${resetEmail}`"></p>
                    </div>
                    <form @submit.prevent="submitPasswordReset()" class="space-y-4">
                        <div>
                            <label for="reset-pass" class="block text-xs font-medium text-slate-700 mb-1">New Password</label>
                            <input id="reset-pass" type="password" required minlength="8" x-model="newPassword" placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="reset-pass-confirm" class="block text-xs font-medium text-slate-700 mb-1">Confirm New Password</label>
                            <input id="reset-pass-confirm" type="password" required minlength="8" x-model="newPasswordConfirm" placeholder="Repeat new password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full py-3 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer"
                        >
                            <span x-show="!loading">Update & Sign In &rarr;</span>
                            <span x-show="loading" style="display: none;">Updating Password...</span>
                        </button>
                    </form>
                </div>
            </div>
        </template>

        <!-- ================= AUTHENTICATED CUSTOMER ACCOUNT ================= -->
        <template x-if="$store.store.user">
            <div>
                <!-- Customer Welcome Banner -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between border-b border-slate-200 pb-6 mb-8 gap-4">
                    <div>
                        <span class="text-[11px] font-bold tracking-wider uppercase text-brand-600 block mb-1">
                            Customer Portal
                        </span>
                        <h1 class="font-display font-bold text-2xl sm:text-3xl text-slate-900 tracking-tight">
                            Welcome back, <span x-text="$store.store.user.name"></span>
                        </h1>
                        <p class="text-xs text-slate-500 mt-1" x-text="$store.store.user.email"></p>
                    </div>
                    <button
                        type="button"
                        @click="logout()"
                        class="px-4 py-2 border border-red-200 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-colors cursor-pointer self-start sm:self-auto"
                    >
                        Sign Out
                    </button>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex border-b border-slate-200 overflow-x-auto no-scrollbar gap-2 mb-8">
                    <button
                        type="button"
                        @click="activeTab = 'overview'"
                        :class="activeTab === 'overview' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-3 text-xs uppercase tracking-wider shrink-0 cursor-pointer"
                    >
                        Overview
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'orders'"
                        :class="activeTab === 'orders' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-3 text-xs uppercase tracking-wider shrink-0 cursor-pointer flex items-center gap-1.5"
                    >
                        <span>Orders &amp; Receipts</span>
                        <span class="px-1.5 py-0.5 bg-slate-100 text-[10px] font-mono rounded" x-text="userOrders.length"></span>
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'addresses'"
                        :class="activeTab === 'addresses' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-3 text-xs uppercase tracking-wider shrink-0 cursor-pointer"
                    >
                        Delivery Addresses
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'security'; authError = null; successMsg = null;"
                        :class="activeTab === 'security' ? 'border-b-2 border-brand-600 text-brand-700 font-semibold' : 'text-slate-500 hover:text-slate-900'"
                        class="px-5 py-3 text-xs uppercase tracking-wider shrink-0 cursor-pointer"
                    >
                        Password & Security
                    </button>
                </div>

                <!-- TAB 1: OVERVIEW -->
                <div x-show="activeTab === 'overview'" class="space-y-8">
                    <!-- Quick KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-xs">
                            <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold block mb-1">Total Orders</span>
                            <h3 class="font-display font-bold text-2xl text-slate-900" x-text="`${userOrders.length} Orders`"></h3>
                            <p class="text-xs text-slate-500 mt-1">Kathmandu Central Fulfillment Hub</p>
                        </div>
                        <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-xs">
                            <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold block mb-1">Saved Items</span>
                            <h3 class="font-display font-bold text-2xl text-slate-900" x-text="`${$store.store.wishlistCount} in Wishlist`"></h3>
                            <a href="{{ url('/wishlist') }}" class="text-xs text-brand-600 hover:underline mt-1 block">View Wishlist &rarr;</a>
                        </div>
                        <div class="p-6 bg-white border border-slate-200 rounded-xl shadow-xs">
                            <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold block mb-1">Account Status</span>
                            <h3 class="font-display font-bold text-2xl text-emerald-600">Active Customer</h3>
                            <p class="text-xs text-slate-500 mt-1">Nepal Nationwide Delivery Support</p>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: ORDERS WITH LIVE SEARCH & EXPANDABLE DETAILS -->
                <div x-show="activeTab === 'orders'" style="display: none;">
                    <div class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs">
                        <!-- Search Bar in Account -->
                        <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
                            <div>
                                <h3 class="font-display font-bold text-lg text-slate-900">Your Orders & Receipts</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Filter past orders by number, product name, or status.</p>
                            </div>
                            <div class="relative w-full sm:w-72">
                                <input
                                    type="search"
                                    x-model="orderSearch"
                                    placeholder="Search orders, products..."
                                    class="w-full px-3.5 py-2 pl-9 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Orders List -->
                        <template x-if="filteredOrders.length > 0">
                            <div class="divide-y divide-slate-100">
                                <template x-for="order in filteredOrders" :key="order.order_number || order.id">
                                    <div class="py-5">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                            <div>
                                                <div class="flex items-center gap-3">
                                                    <span class="font-display font-bold text-sm text-slate-900" x-text="`#${order.order_number}`"></span>
                                                    <span
                                                        class="px-2 py-0.5 text-[9px] uppercase tracking-wider font-semibold rounded-full"
                                                        :class="order.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                                        x-text="order.payment_status"
                                                    ></span>
                                                    <template x-if="order.status">
                                                        <span class="text-[10px] text-slate-500" x-text="`(${order.status_label || order.status})`"></span>
                                                    </template>
                                                </div>
                                                <p class="text-[11px] text-slate-500 mt-1">
                                                    <span x-text="order.date"></span> &bull;
                                                    <span x-text="`${order.items_count || (order.items ? order.items.length : 1)} item(s)`"></span> &bull;
                                                    <strong class="font-semibold text-slate-900" x-text="order.total"></strong>
                                                </p>
                                                <template x-if="order.tracking_number">
                                                    <p class="text-[11px] text-slate-700 mt-1 flex items-center gap-1.5">
                                                        <span class="text-brand-600 font-medium">Courier Tracking:</span>
                                                        <a :href="order.tracking_url || '#'" target="_blank" class="underline hover:text-brand-600 font-mono" x-text="order.tracking_number"></a>
                                                    </p>
                                                </template>
                                            </div>
                                            <div class="flex items-center gap-2 self-start sm:self-auto">
                                                <button
                                                    type="button"
                                                    @click="toggleOrderExpand(order.order_number)"
                                                    class="px-3 py-1.5 border border-slate-200 rounded-lg text-[11px] font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
                                                >
                                                    <span x-text="expandedOrders[order.order_number] ? 'Hide Items ▲' : 'View Items ▼'"></span>
                                                </button>
                                                <a
                                                    :href="`{{ url('/orders') }}/${order.raw_id || order.id}/receipt`"
                                                    target="_blank"
                                                    class="px-3.5 py-1.5 border border-brand-200 bg-brand-50 text-xs font-semibold rounded-lg text-brand-700 hover:bg-brand-600 hover:text-white transition-colors"
                                                >
                                                    Receipt &darr;
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Expandable Items Drawer -->
                                        <div
                                            x-show="expandedOrders[order.order_number]"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            class="mt-4 pt-4 border-t border-slate-100 bg-slate-50 rounded-lg p-4"
                                            style="display: none;"
                                        >
                                            <h4 class="text-[10px] font-bold tracking-wider uppercase text-slate-500 mb-3">Order Items</h4>
                                            <div class="space-y-3">
                                                <template x-for="(item, itemIdx) in (order.items || [])" :key="itemIdx">
                                                    <div class="flex items-center justify-between gap-4 text-xs">
                                                        <div class="flex items-center gap-3">
                                                            <template x-if="item.image">
                                                                <img :src="item.image" :alt="item.product_name" class="h-12 w-12 rounded object-cover border border-slate-200 shrink-0">
                                                            </template>
                                                            <div>
                                                                <p class="font-medium text-slate-900" x-text="item.product_name"></p>
                                                                <p class="text-[10px] text-slate-500">
                                                                    <span x-text="item.size ? `Size: ${item.size}` : ''"></span>
                                                                    <span x-show="item.size && item.color"> &bull; </span>
                                                                    <span x-text="item.color ? `Colour: ${item.color}` : ''"></span>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="text-right shrink-0">
                                                            <p class="font-mono text-slate-600" x-text="`Qty: ${item.quantity}`"></p>
                                                            <p class="font-semibold text-slate-900" x-text="item.unit_price ? `Rs. ${item.unit_price}` : ''"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- No Orders Search / Empty State -->
                        <template x-if="filteredOrders.length === 0">
                            <div class="py-12 text-center text-slate-500">
                                <template x-if="orderSearch.trim().length > 0">
                                    <div>
                                        <p class="font-display font-semibold text-base text-slate-900">No orders found matching "<span x-text="orderSearch"></span>"</p>
                                        <button type="button" @click="orderSearch = ''" class="mt-3 text-xs text-brand-600 underline cursor-pointer">
                                            Clear search filter
                                        </button>
                                    </div>
                                </template>
                                <template x-if="orderSearch.trim().length === 0">
                                    <div>
                                        <p class="text-xs text-slate-500">No prior orders found on this account.</p>
                                        <a href="{{ url('/products') }}" class="mt-4 inline-block px-5 py-2.5 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors">
                                            Start Shopping
                                        </a>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- TAB 3: ADDRESSES -->
                <div x-show="activeTab === 'addresses'" style="display: none;">
                    <div class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs max-w-xl">
                        <h3 class="font-display font-bold text-lg text-slate-900 mb-4">Default Delivery Address</h3>
                        <template x-if="successMsg">
                            <div class="p-3 bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 rounded-lg mb-4">
                                <span x-text="successMsg"></span>
                            </div>
                        </template>
                        <template x-if="authError">
                            <div class="p-3 bg-red-50 border border-red-200 text-xs text-red-700 rounded-lg mb-4">
                                <span x-text="authError"></span>
                            </div>
                        </template>
                        <form @submit.prevent="saveAddress()" class="space-y-4 text-xs">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Full Name / Recipient</label>
                                <input type="text" x-model="profileName" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Street Address / Tole / Landmark</label>
                                <input type="text" x-model="profileAddress" placeholder="Putalisadak, Ward 28" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">City / District</label>
                                    <input type="text" x-model="profileCity" placeholder="Kathmandu" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Postal Code</label>
                                    <input type="text" x-model="profilePostal" placeholder="44600" required class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Country</label>
                                    <select x-model="profileCountry" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                        <option value="NP">Nepal</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Telephone / WhatsApp</label>
                                    <input type="tel" x-model="profilePhone" placeholder="+977 98XXXXXXXX" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                            </div>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="px-6 py-2.5 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer mt-2"
                            >
                                <span x-show="!loading">Save Address</span>
                                <span x-show="loading" style="display: none;">Saving...</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- TAB 4: SECURITY & PASSWORD -->
                <div x-show="activeTab === 'security'" style="display: none;">
                    <div class="bg-white p-6 sm:p-8 border border-slate-200 rounded-xl shadow-xs max-w-md">
                        <h3 class="font-display font-bold text-lg text-slate-900 mb-2">Change Password</h3>
                        <p class="text-xs text-slate-500 mb-6">Ensure your account uses a secure password of at least 8 characters.</p>

                        <template x-if="successMsg">
                            <div class="p-3 bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 rounded-lg mb-4">
                                <span x-text="successMsg"></span>
                            </div>
                        </template>
                        <template x-if="authError">
                            <div class="p-3 bg-red-50 border border-red-200 text-xs text-red-700 rounded-lg mb-4">
                                <span x-text="authError"></span>
                            </div>
                        </template>

                        <form @submit.prevent="changePassword()" class="space-y-4 text-xs">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Current Password</label>
                                <input type="password" required x-model="currentPassword" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">New Password</label>
                                <input type="password" required minlength="8" x-model="newPassword" placeholder="Minimum 8 characters" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Confirm New Password</label>
                                <input type="password" required minlength="8" x-model="newPasswordConfirm" placeholder="Repeat new password" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="px-6 py-2.5 bg-brand-600 text-white text-xs font-semibold rounded-lg hover:bg-brand-700 transition-colors cursor-pointer mt-2"
                            >
                                <span x-show="!loading">Update Password</span>
                                <span x-show="loading" style="display: none;">Updating...</span>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </template>

    </div>
</div>
@endsection
