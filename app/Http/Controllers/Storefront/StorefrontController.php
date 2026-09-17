<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\ShippingMethod;

class StorefrontController extends Controller
{
    /**
     * Homepage - Product Discovery Engine
     */
    public function home()
    {
        $categories = Category::where('is_active', true)
            ->whereHas('products', fn($q) => $q->storefrontReady())
            ->withCount(['products' => fn($q) => $q->storefrontReady()])
            ->with(['products' => fn($q) => $q->storefrontReady()->select('products.id', 'products.featured_image')->take(1)])
            ->orderByDesc('products_count')
            ->take(12)
            ->get();

        // Trending products (photo-backed) - prioritize in-stock
        $trendingProducts = Product::storefrontReady()
            ->where('is_featured', true)
            ->with(['categories'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->take(10)
            ->get();
        if ($trendingProducts->where('quantity', '>', 0)->count() < 5) {
            $trendingProducts = Product::storefrontReady()
                ->with(['categories'])
                ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
                ->latest()
                ->take(10)
                ->get();
        }

        // Real Deals & Discounts (compare_at_price > price) - prioritize in-stock
        $dealProducts = Product::storefrontReady()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->with(['categories'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->take(10)
            ->get();

        // New Arrivals - prioritize in-stock
        $newArrivals = Product::storefrontReady()
            ->where('is_new_arrival', true)
            ->with(['categories'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->take(10)
            ->get();
        if ($newArrivals->where('quantity', '>', 0)->count() < 5) {
            $newArrivals = Product::storefrontReady()
                ->with(['categories'])
                ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
                ->latest()
                ->take(10)
                ->get();
        }

        // Top Category Showcase (prioritize in-stock)
        $showcaseCategory = $categories->first();
        $showcaseProducts = $showcaseCategory
            ? $showcaseCategory->products()
                ->storefrontReady()
                ->with(['categories'])
                ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
                ->latest()
                ->take(10)
                ->get()
            : collect();

        // Explore All Catalog - prioritize available in-stock items first
        $exploreProducts = Product::storefrontReady()
            ->with(['categories'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->inRandomOrder()
            ->take(15)
            ->get();

        return view('storefront.home', compact(
            'categories',
            'trendingProducts',
            'dealProducts',
            'newArrivals',
            'showcaseCategory',
            'showcaseProducts',
            'exploreProducts'
        ));
    }

    /**
     * Product Catalogue / Shop Page with Faceted Filtering & NPR Sorting
     */
    public function catalogue(Request $request)
    {
        $query = Product::storefrontReady()
            ->with(['categories']);

        // Filter: Category
        $activeCategory = $request->get('category', 'all');
        if (!empty($activeCategory) && $activeCategory !== 'all') {
            $query->whereHas('categories', function ($q) use ($activeCategory) {
                $q->where('slug', $activeCategory);
            });
        }

        // Filter: Collection
        $activeCollection = $request->get('collection', 'all');
        if (!empty($activeCollection) && $activeCollection !== 'all') {
            $query->whereHas('collections', function ($q) use ($activeCollection) {
                $q->where('slug', $activeCollection);
            });
        }

        // Filter: Search Keyword
        $activeSearch = trim($request->get('search', $request->get('q', '')));
        if (!empty($activeSearch)) {
            $query->searchRetail($activeSearch);
        }

        // Filter: Price Range (NPR)
        $minPrice = $request->filled('min_price') ? (float)$request->get('min_price') : null;
        $maxPrice = $request->filled('max_price') ? (float)$request->get('max_price') : null;
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }

        // Filter: In Stock Only
        $inStockOnly = $request->boolean('in_stock');
        if ($inStockOnly) {
            $query->where('quantity', '>', 0);
        }

        // Filter: Deals / Discounts Only
        $dealsOnly = $request->boolean('deals');
        if ($dealsOnly) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
        }

        // Filter: New Arrivals
        $isNewArrivals = $request->boolean('new_arrivals') || $request->get('new_arrivals') === 'true' || $request->get('new_arrivals') === '1';
        if ($isNewArrivals) {
            $query->where('is_new_arrival', true);
        }

        // Filter: Featured
        $isFeatured = $request->boolean('featured') || $request->get('featured') === 'true' || $request->get('featured') === '1';
        if ($isFeatured) {
            $query->where('is_featured', true);
        }

        // Sort Options (Authoritative NPR)
        $activeSort = $request->get('sort', 'newest');
        switch ($activeSort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'discount':
                $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price')
                      ->orderByRaw('(compare_at_price - price) DESC');
                break;
            case 'popular':
                $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate(24)->withQueryString();

        // Resilient fallback for new arrivals filter if empty
        if ($isNewArrivals && $products->isEmpty()) {
            $products = Product::storefrontReady()
                ->with(['categories'])
                ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
                ->latest()
                ->paginate(24)
                ->withQueryString();
        }

        $categories = Category::where('is_active', true)
            ->whereHas('products', fn($q) => $q->storefrontReady())
            ->withCount(['products' => fn($q) => $q->storefrontReady()])
            ->orderByDesc('products_count')
            ->get();

        $collections = Collection::where('is_published', true)->get();

        return view('storefront.catalogue', compact(
            'products',
            'categories',
            'collections',
            'activeCategory',
            'activeCollection',
            'activeSort',
            'activeSearch',
            'isNewArrivals',
            'isFeatured',
            'minPrice',
            'maxPrice',
            'inStockOnly',
            'dealsOnly'
        ));
    }

    /**
     * Product Detail Page
     */
    public function product(string $slugOrId)
    {
        $product = Product::storefrontReady()
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)
                  ->orWhere('id', $slugOrId);
            })
            ->with(['categories', 'collections', 'variants'])
            ->firstOrFail();

        $firstCat = $product->categories->first();
        $relatedQuery = Product::storefrontReady()
            ->where('products.id', '!=', $product->id);
        if ($firstCat) {
            $relatedQuery->whereHas('categories', fn($cq) => $cq->where('categories.id', $firstCat->id));
        }
        $relatedProducts = $relatedQuery->with(['categories'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->take(5)
            ->get();
        if ($relatedProducts->count() < 4) {
            $relatedProducts = Product::storefrontReady()
                ->where('id', '!=', $product->id)
                ->with(['categories'])
                ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
                ->latest()
                ->take(5)
                ->get();
        }

        return view('storefront.product', compact('product', 'relatedProducts'));
    }

    /**
     * Category Detail Page
     */
    public function category(Category $category)
    {
        $category->load(['parent', 'children' => function ($q) {
            $q->where('is_active', true)->orderBy('sort_order');
        }]);

        $products = $category->getAllProductsQuery()
            ->storefrontReady()
            ->with(['categories', 'variants'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->paginate(24);

        return view('storefront.category', compact('category', 'products'));
    }

    /**
     * Collections Index
     */
    public function collections()
    {
        $collections = Collection::where('is_published', true)->get();
        return view('storefront.collections', compact('collections'));
    }

    /**
     * Collection Detail Page
     */
    public function collection(Collection $collection)
    {
        $products = $collection->products()
            ->storefrontReady()
            ->with(['categories', 'variants'])
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')
            ->latest()
            ->paginate(24);

        return view('storefront.collection', compact('collection', 'products'));
    }

    /**
     * Search Results Page
     */
    public function search(Request $request)
    {
        $queryText = trim($request->get('q', $request->get('search', '')));
        $isRelaxed = false;
        $matchingCategories = collect();

        if (!empty($queryText)) {
            $productsQuery = Product::storefrontReady()->searchRetail($queryText);
            
            // Check count; if 0 and multiple words, fallback to relaxed OR search
            $tokens = array_values(array_filter(preg_split('/[\s\-_,\/]+/', $queryText), fn($t) => mb_strlen(trim($t)) > 0));
            if (count($tokens) > 1 && (clone $productsQuery)->count() === 0) {
                $products = Product::storefrontReady()
                    ->searchRetailOr($queryText)
                    ->with(['categories', 'variants'])
                    ->paginate(24)
                    ->withQueryString();
                $isRelaxed = true;
            } else {
                $products = $productsQuery
                    ->with(['categories', 'variants'])
                    ->paginate(24)
                    ->withQueryString();
            }

            $matchingCategories = \App\Models\Category::where('is_active', true)
                ->where(function ($cq) use ($queryText, $tokens) {
                    $cq->where('name', 'like', "%{$queryText}%");
                    foreach ($tokens as $t) {
                        $cq->orWhere('name', 'like', "%{$t}%");
                    }
                })
                ->limit(6)
                ->get();
        } else {
            $products = Product::storefrontReady()
                ->with(['categories', 'variants'])
                ->latest()
                ->paginate(24);
        }

        return view('storefront.search', compact('products', 'queryText', 'isRelaxed', 'matchingCategories'));
    }

    /**
     * Full Shopping Bag Page
     */
    public function cart()
    {
        return view('storefront.cart');
    }

    /**
     * Checkout Page
     */
    public function checkout()
    {
        $shippingMethods = ShippingMethod::where('is_active', true)->get();
        $provincesWithDistricts = \App\Services\NepalLocationService::PROVINCES_WITH_DISTRICTS;
        $valleyDistricts = \App\Services\NepalLocationService::VALLEY_DISTRICTS;
        $paymentMethods = app(\App\Services\PaymentSettingsService::class)->getAvailablePaymentMethods();
        return view('storefront.checkout', compact('shippingMethods', 'provincesWithDistricts', 'valleyDistricts', 'paymentMethods'));
    }

    /**
     * Order Confirmation / Success Page
     */
    public function checkoutSuccess(Request $request)
    {
        $orderNumber = $request->get('order_number') ?: $request->get('order');
        $token = $request->get('token');
        $order = null;

        if (!empty($orderNumber)) {
            $candidateOrder = Order::where('order_number', $orderNumber)->with('items')->first();
            if ($candidateOrder) {
                $user = auth('web')->user();
                $placedOrders = (array) ($request->hasSession() ? $request->session()->get('placed_orders', []) : []);
                $isSessionPlaced = in_array($candidateOrder->order_number, $placedOrders) || in_array((string)$candidateOrder->id, $placedOrders);
                $isTokenMatch = !empty($token) && $candidateOrder->guest_access_token === $token;

                if (auth('admin')->check() || ($user && (int)$user->id === (int)$candidateOrder->user_id) || $isSessionPlaced || $isTokenMatch) {
                    $order = $candidateOrder;
                }
            }
        }

        $instructions = ($order && $order->payment_method)
            ? app(\App\Services\PaymentSettingsService::class)->getPaymentInstructions($order->payment_method)
            : [];

        return view('storefront.checkout-success', compact('order', 'orderNumber', 'instructions'));
    }

    /**
     * Guest & Registered Customer Order Tracking
     */
    public function trackOrder(Request $request)
    {
        $orderNumber = trim((string)$request->get('order_number', ''));
        $phone = trim((string)$request->get('phone', ''));
        $token = trim((string)$request->get('token', ''));
        $order = null;
        $error = null;

        if (!empty($orderNumber) && (!empty($phone) || !empty($token) || auth('web')->check() || auth('admin')->check())) {
            $candidate = Order::where('order_number', $orderNumber)->with('items')->first();
            if (!$candidate) {
                $error = 'Order not found. Please double check your order number.';
            } else {
                $cleanOrderPhone = preg_replace('/[^0-9]/', '', (string)$candidate->phone);
                $cleanInputPhone = preg_replace('/[^0-9]/', '', $phone);

                $match = (!empty($token) && $candidate->guest_access_token === $token)
                    || (!empty($cleanInputPhone) && str_ends_with($cleanOrderPhone, $cleanInputPhone))
                    || auth('admin')->check()
                    || (auth('web')->check() && (int)auth('web')->id() === (int)$candidate->user_id)
                    || (in_array($candidate->order_number, (array)($request->hasSession() ? $request->session()->get('placed_orders', []) : [])));

                if ($match) {
                    $order = $candidate;
                } else {
                    $error = 'The phone number or token provided does not match this order. Please verify your details.';
                }
            }
        }

        return view('storefront.track-order', compact('order', 'error', 'orderNumber', 'phone'));
    }

    /**
     * Customer Account Portal
     */
    public function account(Request $request)
    {
        $user = auth('web')->user();
        if ($user) {
            \App\Models\Order::where('email', $user->email)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);

            $orders = \App\Models\Order::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('email', $user->email);
                })
                ->latest()
                ->with('items.product')
                ->get();
        } else {
            $orders = collect();
        }

        $ordersData = $orders->map(function ($o) {
            return [
                'id' => $o->order_number ?? (string)$o->id,
                'raw_id' => $o->id,
                'order_number' => $o->order_number,
                'date' => $o->created_at ? $o->created_at->format('d M Y') : '',
                'status' => $o->status,
                'status_label' => ucfirst(str_replace('_', ' ', $o->status)),
                'payment_status' => $o->payment_status,
                'subtotal' => number_format($o->subtotal, 2),
                'vat_amount' => number_format($o->vat_amount, 2),
                'shipping_fee' => number_format($o->shipping_fee, 2),
                'total' => \App\Helpers\StorefrontHelper::formatPrice($o->total_amount, $o->currency ?: 'NPR'),
                'currency' => $o->currency ?: 'NPR',
                'carrier' => $o->carrier,
                'tracking_number' => $o->tracking_number,
                'tracking_url' => $o->tracking_url ?? \App\Models\Order::resolveTrackingUrl($o->carrier, $o->tracking_number),
                'items_count' => $o->items->sum('quantity'),
                'items' => $o->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name ?? $item->product?->name ?? 'Product',
                        'product_slug' => $item->product?->slug,
                        'image' => $item->product?->featured_image ? asset('storage/' . ltrim($item->product->featured_image, '/')) : '',
                        'quantity' => $item->quantity,
                        'unit_price' => \App\Helpers\NepaliNumberHelper::format($item->unit_price, 2),
                        'size' => $item->selected_size,
                        'color' => $item->selected_color,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return view('storefront.account', compact('user', 'orders', 'ordersData'));
    }

    /**
     * Wishlist Page
     */
    public function wishlist()
    {
        return view('storefront.wishlist');
    }

    /**
     * Content Pages
     */
    public function about() { return view('storefront.pages.about'); }
    public function contact() { return view('storefront.pages.contact'); }
    public function shipping() { return view('storefront.pages.shipping'); }
    public function returns() { return view('storefront.pages.returns'); }
    public function privacy() { return view('storefront.pages.privacy'); }
    public function terms() { return view('storefront.pages.terms'); }
}
