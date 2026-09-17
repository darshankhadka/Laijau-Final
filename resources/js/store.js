export function normalizeCountryCode(val) {
    if (!val) return 'NP';
    const s = String(val).trim().toUpperCase();
    if (s.length === 2) return s;
    const map = {
        'NEPAL': 'NP',
        'INDIA': 'IN',
        'UNITED STATES': 'US', 'USA': 'US',
        'UNITED KINGDOM': 'GB', 'UK': 'GB',
        'AUSTRALIA': 'AU',
        'CANADA': 'CA',
        'JAPAN': 'JP',
    };
    return map[s] || (s.length === 2 ? s : 'NP');
}

export function initStore(Alpine) {
    Alpine.store('store', {
        cart: [],
        wishlist: [],
        currency: 'NPR',
        isCartDrawerOpen: false,
        isSearchOpen: false,
        isMobileMenuOpen: false,
        searchQuery: '',
        searchSuggestions: {
            products: [],
            categories: [],
            popular_searches: [],
            total_products_count: 0
        },
        isSearching: false,
        searchAbortController: null,
        toastMessage: '',
        toastVisible: false,
        toastTimer: null,
        user: null,
        isHydrated: false,

        init() {
            try {
                const savedCart = localStorage.getItem('laijau_cart') || localStorage.getItem('cart');
                if (savedCart) {
                    const parsed = JSON.parse(savedCart);
                    if (Array.isArray(parsed)) {
                        this.cart = parsed.filter(item =>
                            item &&
                            typeof item === 'object' &&
                            Number(item.product_id) > 0 &&
                            Number(item.quantity) > 0
                        ).map(item => ({
                            product_id: Number(item.product_id),
                            variant_id: (item.variant_id !== null && item.variant_id !== undefined && item.variant_id !== '') ? Number(item.variant_id) : null,
                            name: String(item.name || 'Product'),
                            slug: String(item.slug || item.product_id),
                            sku: String(item.sku || ''),
                            price: Number(item.price) || 0,
                            compare_at_price: item.compare_at_price ? Number(item.compare_at_price) : null,
                            image: String(item.image || ''),
                            quantity: Math.max(1, parseInt(item.quantity, 10) || 1),
                            selected_size: item.selected_size ? String(item.selected_size) : null,
                            selected_color: item.selected_color ? String(item.selected_color) : null,
                            max_stock: item.max_stock ? Number(item.max_stock) : null,
                        }));
                    } else {
                        this.cart = [];
                    }
                }
            } catch (e) {
                this.cart = [];
            }

            try {
                const savedWishlist = localStorage.getItem('laijau_wishlist');
                if (savedWishlist) {
                    const parsed = JSON.parse(savedWishlist);
                    if (Array.isArray(parsed)) this.wishlist = parsed.map(Number);
                }
            } catch (e) {
                this.wishlist = [];
            }

            this.currency = 'NPR';

            // Authoritative Server-side authentication
            try {
                const serverUser = (typeof window !== 'undefined') ? window.__LAIJAU_AUTH_USER__ : null;
                if (serverUser) {
                    this.user = serverUser;
                    localStorage.setItem('laijau_cached_user', JSON.stringify(this.user));
                } else {
                    const savedUser = localStorage.getItem('laijau_cached_user');
                    if (savedUser) {
                        this.user = JSON.parse(savedUser);
                    }
                }
            } catch (e) {
                this.user = null;
            }

            this.isHydrated = true;

            // Global escape key listener
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeCartDrawer();
                    this.isSearchOpen = false;
                    this.isMobileMenuOpen = false;
                }
            });

            // Global custom events
            window.addEventListener('open-cart-drawer', () => {
                this.openCartDrawer();
            });
            window.addEventListener('close-cart-drawer', () => {
                this.closeCartDrawer();
            });

            // Delegated click handler for any [data-open-cart]
            if (typeof document !== 'undefined') {
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-open-cart]');
                    if (btn) {
                        e.preventDefault();
                        this.openCartDrawer();
                    }
                });

                // Lock body scroll when cart drawer is open
                Alpine.effect(() => {
                    if (this.isCartDrawerOpen) {
                        document.body.classList.add('overflow-hidden');
                    } else if (!this.isSearchOpen && !this.isMobileMenuOpen) {
                        document.body.classList.remove('overflow-hidden');
                    }
                });
            }

            // Initial fetch of popular searches / top categories for search bar
            this.fetchSearchSuggestions('');
        },

        showToast(message) {
            this.toastMessage = message;
            this.toastVisible = true;
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toastVisible = false;
            }, 3000);
        },

        openCartDrawer() {
            this.isCartDrawerOpen = true;
            this.isMobileMenuOpen = false;
            this.isSearchOpen = false;
        },

        closeCartDrawer() {
            this.isCartDrawerOpen = false;
        },

        toggleCartDrawer() {
            if (this.isCartDrawerOpen) {
                this.closeCartDrawer();
            } else {
                this.openCartDrawer();
            }
        },

        saveCart() {
            try {
                localStorage.setItem('laijau_cart', JSON.stringify(this.cart));
            } catch (e) { }
        },

        saveWishlist() {
            try {
                localStorage.setItem('laijau_wishlist', JSON.stringify(this.wishlist));
            } catch (e) { }
        },

        getLineKey(item) {
            if (!item) return '';
            const pid = Number(item.product_id) || 0;
            const vid = (item.variant_id !== null && item.variant_id !== undefined && item.variant_id !== '') ? Number(item.variant_id) : 'none';
            const size = (item.selected_size || '').trim().toLowerCase();
            const color = (item.selected_color || '').trim().toLowerCase();
            return `${pid}_${vid}_${size}_${color}`;
        },

        addToCart(item, openDrawer = true) {
            if (!item || !item.product_id) return;
            const maxStock = (item.max_stock !== null && item.max_stock !== undefined) ? Math.max(0, Number(item.max_stock)) : 99;
            if (maxStock <= 0) {
                this.showToast('Sorry, this product is currently out of stock.');
                return;
            }

            const targetKey = this.getLineKey(item);
            const existingIdx = this.cart.findIndex(i => this.getLineKey(i) === targetKey);
            const qtyToAdd = Math.max(1, parseInt(item.quantity, 10) || 1);

            if (existingIdx > -1) {
                const currentQty = this.cart[existingIdx].quantity;
                this.cart[existingIdx].quantity = Math.min(currentQty + qtyToAdd, maxStock);
            } else {
                this.cart.push({
                    product_id: Number(item.product_id),
                    variant_id: (item.variant_id !== null && item.variant_id !== undefined && item.variant_id !== '') ? Number(item.variant_id) : null,
                    name: String(item.name || 'Product'),
                    slug: String(item.slug || item.product_id),
                    sku: String(item.sku || ''),
                    price: Number(item.price) || 0,
                    compare_at_price: item.compare_at_price ? Number(item.compare_at_price) : null,
                    image: String(item.image || ''),
                    quantity: Math.min(qtyToAdd, maxStock),
                    selected_size: item.selected_size ? String(item.selected_size) : null,
                    selected_color: item.selected_color ? String(item.selected_color) : null,
                    max_stock: item.max_stock ? Number(item.max_stock) : null,
                });
            }

            this.saveCart();
            this.showToast(`Added "${item.name}" to cart!`);

            if (openDrawer) {
                this.openCartDrawer();
            }
        },

        removeFromCart(index) {
            if (index >= 0 && index < this.cart.length) {
                const removed = this.cart[index];
                this.cart.splice(index, 1);
                this.saveCart();
                if (removed) {
                    this.showToast(`Removed "${removed.name}" from cart.`);
                }
            }
        },

        removeItemByKey(key) {
            const idx = this.cart.findIndex(i => this.getLineKey(i) === key);
            if (idx > -1) {
                this.removeFromCart(idx);
            }
        },

        updateQuantity(index, quantity) {
            if (index < 0 || index >= this.cart.length) return;
            const q = parseInt(quantity, 10);
            if (isNaN(q) || q <= 0) {
                this.removeFromCart(index);
                return;
            }
            const item = this.cart[index];
            const maxStock = (item.max_stock !== null && item.max_stock !== undefined) ? Math.max(0, Number(item.max_stock)) : 99;
            this.cart[index].quantity = Math.min(q, maxStock);
            this.saveCart();
        },

        clearCart() {
            this.cart = [];
            this.saveCart();
        },

        get cartCount() {
            return this.cart.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);
        },

        get cartSubtotal() {
            return this.cart.reduce((sum, item) => {
                return sum + (Number(item.price) || 0) * (Number(item.quantity) || 0);
            }, 0);
        },

        get freeShippingThreshold() {
            return 2000; // NPR 2,000 inside Kathmandu Valley
        },

        get remainingForFreeShipping() {
            return Math.max(0, this.freeShippingThreshold - this.cartSubtotal);
        },

        get freeShippingProgressPercent() {
            return Math.min(100, (this.cartSubtotal / this.freeShippingThreshold) * 100);
        },

        formatAmount(amount) {
            const num = Number(amount) || 0;
            const isNeg = num < 0;
            const abs = Math.abs(num);
            const str = abs.toFixed(0);
            if (str.length <= 3) {
                return (isNeg ? '-' : '') + 'Rs. ' + str;
            }
            const last3 = str.substring(str.length - 3);
            let rem = str.substring(0, str.length - 3);
            const groups = [];
            while (rem.length > 2) {
                groups.unshift(rem.substring(rem.length - 2));
                rem = rem.substring(0, rem.length - 2);
            }
            if (rem.length > 0) groups.unshift(rem);
            return (isNeg ? '-' : '') + 'Rs. ' + groups.join(',') + ',' + last3;
        },

        formatPrice(price) {
            return this.formatAmount(price);
        },

        getImageUrl(path, size = 'card') {
            if (!path) return '';
            const str = String(path).trim();
            if (str === '' || str === 'null' || str === 'undefined' || str === '[object Object]' || str.toLowerCase().includes('placeholder')) {
                return '';
            }
            if (str.startsWith('http://') || str.startsWith('https://')) {
                return str;
            }
            if (str.startsWith('images/') || str.startsWith('assets/') || str.startsWith('build/') || str.startsWith('icons/')) {
                return '/' + str;
            }
            let clean = str.replace(/^(\/?storage\/)+/, '');
            if (clean.startsWith('product/') && !clean.startsWith('product/card/') && !clean.startsWith('product/thumbnail/') && !clean.startsWith('product/large/') && !clean.startsWith('product/original/')) {
                const fname = clean.replace(/^product\//, '').replace(/\.[^/.]+$/, '.webp');
                return `/storage/product/${size}/${fname}`;
            }
            return '/storage/' + clean;
        },

        toggleWishlist(productId) {
            const id = Number(productId);
            if (!id) return;
            const idx = this.wishlist.indexOf(id);
            if (idx > -1) {
                this.wishlist.splice(idx, 1);
                this.showToast('Removed from wishlist');
            } else {
                this.wishlist.push(id);
                this.showToast('Saved to wishlist');
            }
            this.saveWishlist();
        },

        isInWishlist(productId) {
            return this.wishlist.includes(Number(productId));
        },

        get wishlistCount() {
            return this.wishlist.length;
        },

        async fetchSearchSuggestions(query = null) {
            const q = (query !== null ? query : this.searchQuery).trim();

            if (this.searchAbortController) {
                this.searchAbortController.abort();
            }
            this.searchAbortController = new AbortController();

            this.isSearching = true;
            try {
                const res = await fetch(`/api/search/suggestions?q=${encodeURIComponent(q)}`, {
                    signal: this.searchAbortController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.searchSuggestions = {
                        products: data.products || [],
                        categories: data.categories || [],
                        popular_searches: data.popular_searches || [],
                        total_products_count: data.total_products_count || 0
                    };
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    // Retain existing on soft failure
                }
            } finally {
                this.isSearching = false;
            }
        },

        performSearch() {
            this.fetchSearchSuggestions();
        }
    });
}
