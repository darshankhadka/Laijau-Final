<?php

declare(strict_types=1);

namespace App\Services\Operational;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryReconciliationService
{
    /**
     * Standard authentic Laijau catalog category taxonomy.
     */
    public const TAXONOMY = [
        'men' => [
            'name' => 'Men',
            'children' => [
                'mens-footwear' => [
                    'name' => "Men's Footwear",
                    'children' => [
                        'mens-boots' => ['name' => 'Boots'],
                        'mens-sneakers' => ['name' => 'Sneakers'],
                        'mens-casual-shoes' => ['name' => 'Casual Shoes'],
                        'mens-formal-loafers' => ['name' => 'Loafers & Formal Shoes'],
                        'mens-slippers-sandals' => ['name' => 'Slippers & Sandals'],
                    ],
                ],
                'mens-apparel' => [
                    'name' => "Men's Apparel",
                    'children' => [
                        'mens-jeans-denim' => ['name' => 'Jeans & Denim'],
                        'mens-trousers-pants' => ['name' => 'Trousers & Pants'],
                        'mens-t-shirts-polos' => ['name' => 'T-Shirts & Polos'],
                        'mens-shirts' => ['name' => 'Shirts'],
                        'mens-jackets-outerwear' => ['name' => 'Jackets & Outerwear'],
                        'mens-shorts' => ['name' => 'Shorts'],
                        'mens-tank-tops' => ['name' => 'Tank Tops & Innerwear'],
                    ],
                ],
            ],
        ],
        'women' => [
            'name' => 'Women',
            'children' => [
                'womens-footwear' => [
                    'name' => "Women's Footwear",
                    'children' => [
                        'womens-boots' => ['name' => 'Boots'],
                        'womens-shoes-sneakers' => ['name' => 'Shoes & Sneakers'],
                        'womens-sandals-flats' => ['name' => 'Sandals & Flats'],
                    ],
                ],
                'womens-apparel' => [
                    'name' => "Women's Apparel",
                    'children' => [
                        'womens-dresses-tunics' => ['name' => 'Dresses & Tunics'],
                        'womens-jackets-coats' => ['name' => 'Jackets & Coats'],
                        'womens-tops-tshirts' => ['name' => 'Tops & T-Shirts'],
                        'womens-trousers-pants' => ['name' => 'Trousers & Pants'],
                    ],
                ],
            ],
        ],
        'accessories' => [
            'name' => 'Accessories',
            'children' => [
                'belts-wallets' => ['name' => 'Belts & Wallets'],
                'personal-care-appliances' => ['name' => 'Personal Care & Appliances'],
                'scarves-shawls' => ['name' => 'Scarves & Shawls'],
                'bags-backpacks' => ['name' => 'Bags & Backpacks'],
            ],
        ],
    ];

    /**
     * Ensure the canonical category hierarchy exists in the database.
     * Prevents duplicates case-insensitively and creates clean parent-child structures.
     *
     * @return array<string, Category> Keyed by slug
     */
    public function ensureHierarchy(): array
    {
        $categoryMap = [];

        foreach (self::TAXONOMY as $rootSlug => $rootData) {
            $root = $this->findOrCreateCategory($rootData['name'], $rootSlug, null);
            $categoryMap[$rootSlug] = $root;

            if (!empty($rootData['children'])) {
                foreach ($rootData['children'] as $deptSlug => $deptData) {
                    $dept = $this->findOrCreateCategory($deptData['name'], $deptSlug, $root->id);
                    $categoryMap[$deptSlug] = $dept;

                    if (!empty($deptData['children'])) {
                        foreach ($deptData['children'] as $subSlug => $subData) {
                            $sub = $this->findOrCreateCategory($subData['name'], $subSlug, $dept->id);
                            $categoryMap[$subSlug] = $sub;
                        }
                    }
                }
            }
        }

        Cache::forget('public_categories_list');

        return $categoryMap;
    }

    /**
     * Find existing category case-insensitively by slug or name, or create it.
     */
    public function findOrCreateCategory(string $name, string $slug, ?int $parentId): Category
    {
        $normalizedName = trim(preg_replace('/\s+/', ' ', $name));
        $normalizedSlug = Str::slug($slug);

        // 1. Check exact slug
        $cat = Category::where('slug', $normalizedSlug)->first();

        // 2. Check case-insensitive name under same parent
        if (!$cat) {
            $cat = Category::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])
                ->when($parentId, fn($q) => $q->where('parent_id', $parentId))
                ->first();
        }

        // 3. Fallback: check case-insensitive name globally if top level
        if (!$cat && $parentId === null) {
            $cat = Category::whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])->first();
        }

        if ($cat) {
            $needsUpdate = false;
            if ($cat->name !== $normalizedName && strtolower($cat->name) === strtolower($normalizedName)) {
                $cat->name = $normalizedName;
                $needsUpdate = true;
            }
            if ($parentId !== null && (int)$cat->parent_id !== (int)$parentId) {
                $cat->parent_id = $parentId;
                $needsUpdate = true;
            }
            if (!$cat->is_active) {
                $cat->is_active = true;
                $needsUpdate = true;
            }
            if ($needsUpdate) {
                $cat->save();
            }
            return $cat;
        }

        return Category::create([
            'name' => $normalizedName,
            'slug' => $normalizedSlug,
            'parent_id' => $parentId,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * Determine category taxonomy slugs for a given product.
     * Returns an array of category slugs: [root, department, leaf]
     *
     * @return array{root: string, department: string, leaf: string, status: string}
     */
    public function classifyProduct(Product $product): array
    {
        $name = strtolower((string)$product->name);
        $sku = strtolower((string)$product->sku);
        $desc = strtolower((string)$product->description);

        // 1. Staging or test products
        if (
            preg_match('/^(cod|cips|esewa|khalti|test)/i', $sku) ||
            preg_match('/(payment test|sample item|dummy|flow-01|flow-02)/i', $name)
        ) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-t-shirts-polos',
                'status' => 'STAGING_TEST',
            ];
        }

        // 2. Accessories
        if (preg_match('/(hair dryer|dryer|shaver|trimmer)/i', $name)) {
            return [
                'root' => 'accessories',
                'department' => 'personal-care-appliances',
                'leaf' => 'personal-care-appliances',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(belt|wallet|keychain)/i', $name)) {
            return [
                'root' => 'accessories',
                'department' => 'belts-wallets',
                'leaf' => 'belts-wallets',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(shawl|scarf|pashmina|muffler)/i', $name)) {
            return [
                'root' => 'accessories',
                'department' => 'scarves-shawls',
                'leaf' => 'scarves-shawls',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(bag|backpack|crossbody|tote)/i', $name)) {
            return [
                'root' => 'accessories',
                'department' => 'bags-backpacks',
                'leaf' => 'bags-backpacks',
                'status' => 'CONFIRMED',
            ];
        }

        // 3. Women Footwear
        if (preg_match('/(ladies|womens?)\s+(dr\.?\s*martin|boot|shoe|sandal|heel|sneaker)/i', $name)) {
            if (preg_match('/boot/i', $name)) {
                return [
                    'root' => 'women',
                    'department' => 'womens-footwear',
                    'leaf' => 'womens-boots',
                    'status' => 'CONFIRMED',
                ];
            }
            if (preg_match('/(sandal|heel|flat)/i', $name)) {
                return [
                    'root' => 'women',
                    'department' => 'womens-footwear',
                    'leaf' => 'womens-sandals-flats',
                    'status' => 'CONFIRMED',
                ];
            }
            return [
                'root' => 'women',
                'department' => 'womens-footwear',
                'leaf' => 'womens-shoes-sneakers',
                'status' => 'CONFIRMED',
            ];
        }

        // 4. Women Apparel
        if (preg_match('/(jacket|coat|blazer)/i', $name)) {
            return [
                'root' => 'women',
                'department' => 'womens-apparel',
                'leaf' => 'womens-jackets-coats',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(dress|tunic|gown)/i', $name)) {
            return [
                'root' => 'women',
                'department' => 'womens-apparel',
                'leaf' => 'womens-dresses-tunics',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(women|ladies)/i', $name)) {
            if (preg_match('/(pant|trouser|jeans)/i', $name)) {
                return [
                    'root' => 'women',
                    'department' => 'womens-apparel',
                    'leaf' => 'womens-trousers-pants',
                    'status' => 'CONFIRMED',
                ];
            }
            return [
                'root' => 'women',
                'department' => 'womens-apparel',
                'leaf' => 'womens-tops-tshirts',
                'status' => 'CONFIRMED',
            ];
        }

        // 5. Footwear (Predominant Laijau Master Catalog)
        $isFootwear = $product->type === 'footwear' ||
            preg_match('/(shoe|boot|sneaker|slipper|sandel|croc|clog|slide|loafer|dockside|derby|party shoe|formal shoe|oxford|dunk|airforce|jordan|samba|chelsea|timberland|caterpillar|harley|whites|dingo|onitsuka|puma|canvas|kitto|bigbrave)/i', $name) ||
            preg_match('/^(1314|0021|1327|1635|1330|1331|1338|1631|1328|1450|bc[0-9]|cat|pf|max|hs|sk|cut)/i', $sku);

        if ($isFootwear) {
            // Slippers & Sandals
            if (preg_match('/(slipper|sandel|croc|clog|slide|kitto|bigbrave)/i', $name)) {
                return [
                    'root' => 'men',
                    'department' => 'mens-footwear',
                    'leaf' => 'mens-slippers-sandals',
                    'status' => 'CONFIRMED',
                ];
            }
            // Boots
            if (preg_match('/(boot|chelsea|timberland|caterpillar|harley|whites|dingo|combat|western|underground)/i', $name) || preg_match('/^(f007|cat|balen|underground)/i', $sku)) {
                return [
                    'root' => 'men',
                    'department' => 'mens-footwear',
                    'leaf' => 'mens-boots',
                    'status' => 'CONFIRMED',
                ];
            }
            // Loafers & Formal / Derby Shoes
            if (preg_match('/(loafer|dockside|derby|party shoe|formal shoe|oxford|derbies|dm-hf|shining leather)/i', $name) || preg_match('/(dockside|derby|loafer)/i', $sku)) {
                return [
                    'root' => 'men',
                    'department' => 'mens-footwear',
                    'leaf' => 'mens-formal-loafers',
                    'status' => 'CONFIRMED',
                ];
            }
            // Sneakers
            if (preg_match('/(sneaker|airforce|jordan|onitsuka|running|run\s|canvas|dunk|samba|streetwear|puma)/i', $name) || preg_match('/^(run|tbl)/i', $sku)) {
                return [
                    'root' => 'men',
                    'department' => 'mens-footwear',
                    'leaf' => 'mens-sneakers',
                    'status' => 'CONFIRMED',
                ];
            }

            // Casual Everyday Shoes (Citizen, Max, Prasiddha, SK, etc.)
            return [
                'root' => 'men',
                'department' => 'mens-footwear',
                'leaf' => 'mens-casual-shoes',
                'status' => 'CONFIRMED',
            ];
        }

        // 6. Men Apparel
        if (preg_match('/(jeans|denim|dean|raw denim|star denim|baggy)/i', $name) || preg_match('/(denim|jeans)/i', $desc)) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-jeans-denim',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(trouser|pant|camo|cargo|formal pant|chinos|jogger|box pant|compass pant|linen trouser|straight)/i', $name) || preg_match('/(pant|trouser)/i', $desc)) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-trousers-pants',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(t-shirt|tshirt|tee|polo|crew neck|tank-top|tanktop|tank)/i', $name)) {
            if (preg_match('/(tank|sleeveless|vest)/i', $name)) {
                return [
                    'root' => 'men',
                    'department' => 'mens-apparel',
                    'leaf' => 'mens-tank-tops',
                    'status' => 'CONFIRMED',
                ];
            }
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-t-shirts-polos',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(shirt|camp collar|button down)/i', $name)) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-shirts',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(jacket|hoodie|windcheater|coat|sweater|fleece|cadigan|cardigan)/i', $name)) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-jackets-outerwear',
                'status' => 'CONFIRMED',
            ];
        }
        if (preg_match('/(short|half pant)/i', $name)) {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-shorts',
                'status' => 'CONFIRMED',
            ];
        }

        // Fallback for general clothing items from Clothes Stock
        if (str_starts_with($sku, 'clo-') || $product->type === 'apparel') {
            return [
                'root' => 'men',
                'department' => 'mens-apparel',
                'leaf' => 'mens-trousers-pants',
                'status' => 'CONFIRMED',
            ];
        }

        return [
            'root' => 'men',
            'department' => 'mens-footwear',
            'leaf' => 'mens-casual-shoes',
            'status' => 'REVIEW_REQUIRED',
        ];
    }

    /**
     * Reconcile all categories and product attachments across the entire catalog.
     * Idempotent and safe.
     */
    public function reconcile(bool $dryRun = false): array
    {
        $hierarchy = $this->ensureHierarchy();

        $metrics = [
            'categories_ensured' => count($hierarchy),
            'categories_created' => 0,
            'products_evaluated' => 0,
            'products_attached' => 0,
            'products_type_corrected' => 0,
            'categories_deprecated' => 0,
            'category_tree_counts' => [],
        ];

        // Trademark / legacy categories to deprecate from public storefront
        $legacyTrademarkCategories = [
            'timberland-inspired-boot',
            'dr-martin-inspired-shoes',
            'black-cobra',
            'onitsuka-tiger',
            'whites-inspired-boots',
            'tbl-shoes',
            'harley-davidson-inspired-boots',
            'caterpillar-inspired-boots',
            'dingo',
            'loafer-party-shoes',
        ];

        DB::beginTransaction();
        try {
            $products = Product::all();
            $metrics['products_evaluated'] = $products->count();

            foreach ($products as $product) {
                $classification = $this->classifyProduct($product);

                // Correct product type if mislabeled
                $expectedType = match ($classification['root']) {
                    'accessories' => 'accessory',
                    default => str_contains($classification['department'], 'footwear') ? 'footwear' : 'apparel',
                };

                if ($product->type !== $expectedType) {
                    if (!$dryRun) {
                        $product->updateQuietly(['type' => $expectedType]);
                    }
                    $metrics['products_type_corrected']++;
                }

                // Collect category IDs to attach: root, department, leaf
                $categoryIds = [];
                if (isset($hierarchy[$classification['root']])) {
                    $categoryIds[] = $hierarchy[$classification['root']]->id;
                }
                if (isset($hierarchy[$classification['department']])) {
                    $categoryIds[] = $hierarchy[$classification['department']]->id;
                }
                if (isset($hierarchy[$classification['leaf']])) {
                    $categoryIds[] = $hierarchy[$classification['leaf']]->id;
                }

                $categoryIds = array_unique(array_filter($categoryIds));

                if (!$dryRun) {
                    // Sync the standardized categories
                    $product->categories()->sync($categoryIds);
                }
                $metrics['products_attached']++;

                // Track distribution
                $leafName = $hierarchy[$classification['leaf']]->name ?? $classification['leaf'];
                $deptName = $hierarchy[$classification['department']]->name ?? $classification['department'];
                $rootName = $hierarchy[$classification['root']]->name ?? $classification['root'];
                $path = "{$rootName} → {$deptName} → {$leafName}";

                $metrics['category_tree_counts'][$path] = ($metrics['category_tree_counts'][$path] ?? 0) + 1;
            }

            // Deactivate legacy trademark categories so they don't pollute customer navigation
            if (!$dryRun) {
                $deprecated = Category::whereIn('slug', $legacyTrademarkCategories)
                    ->update(['is_active' => false, 'is_featured' => false]);
                $metrics['categories_deprecated'] = $deprecated;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                Cache::forget('public_categories_list');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Category reconciliation failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }

        ksort($metrics['category_tree_counts']);

        return $metrics;
    }

    /**
     * Generate a category tree report with product counts.
     */
    public function generateCategoryTreeReport(): array
    {
        $roots = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($q) {
                $q->where('is_active', true)->with(['children' => function ($cq) {
                    $cq->where('is_active', true)->withCount('products');
                }])->withCount('products');
            }])
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        $tree = [];
        foreach ($roots as $root) {
            $rootNode = [
                'id' => $root->id,
                'name' => $root->name,
                'slug' => $root->slug,
                'products_count' => $root->products_count,
                'children' => [],
            ];

            foreach ($root->children as $dept) {
                $deptNode = [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'slug' => $dept->slug,
                    'products_count' => $dept->products_count,
                    'children' => [],
                ];

                foreach ($dept->children as $leaf) {
                    $deptNode['children'][] = [
                        'id' => $leaf->id,
                        'name' => $leaf->name,
                        'slug' => $leaf->slug,
                        'products_count' => $leaf->products_count,
                    ];
                }

                $rootNode['children'][] = $deptNode;
            }

            $tree[] = $rootNode;
        }

        return $tree;
    }
}
