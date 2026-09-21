<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Inventory\Warehouse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\ProductService;
use App\Services\ProductSkuService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Products';
    protected static ?string $navigationLabel = 'Product Catalog';
    protected static ?int $navigationSort = 10;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Master Product';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Master Product Catalog';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode', 'brand', 'model', 'variants.sku', 'variants.barcode'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        $brand = $record->brand ? " [{$record->brand}]" : '';
        return $record->name . $brand . ($record->sku ? ' (' . $record->sku . ')' : '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'SKU' => $record->sku ?? 'N/A',
            'Barcode' => $record->barcode ?? 'N/A',
            'Price' => 'Rs. ' . number_format((float) ($record->price ?? 0), 2),
            'Stock' => $record->formatted_stock,
        ];
    }

    public static function form(Schema $form): Schema
    {
        $canViewCost = auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole('Store Manager', 'admin') || auth()->user()?->hasRole('Store Manager', 'web');

        return $form
            ->schema([
                // =========================================================
                // 1. MAIN COLUMN (2/3 width on desktop, 100% on mobile)
                // =========================================================
                Group::make()->schema([

                    // SECTION 1: BASIC INFORMATION
                    Section::make('Basic Product Information')
                        ->icon('heroicon-o-information-circle')
                        ->description('Core title, brand, category taxonomy, and catalog status.')
                        ->schema([
                            TextInput::make('name')
                                ->label('Product Name / Title *')
                                ->placeholder('e.g. Nike Air Max 270, Classic Leather Loafer, Oxford Cotton Shirt')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $set, ?Product $record) {
                                    if (!$record && !empty($state)) {
                                        $set('slug', Str::slug($state));
                                    }
                                }),

                            Select::make('type')
                                ->label('Product Type *')
                                ->options([
                                    'simple' => 'Simple Product (Single SKU / No Variations)',
                                    'variable' => 'Variable Product (Color & Size Variations)',
                                ])
                                ->default('variable')
                                ->required()
                                ->live(),

                            TextInput::make('brand')
                                ->label('Brand')
                                ->placeholder('e.g. Nike, Adidas, Laijau Heritage, Casio')
                                ->maxLength(150),

                            TextInput::make('model')
                                ->label('Model / Style Code')
                                ->placeholder('e.g. AH8050-002, Slim Fit, Traditional Zari')
                                ->maxLength(150),

                            Select::make('categories')
                                ->label('Primary Category *')
                                ->relationship('categories', 'name')
                                ->getOptionLabelFromRecordUsing(fn(Category $record) => $record->hierarchy_path)
                                ->preload()
                                ->searchable()
                                ->required(),

                            Select::make('collections')
                                ->label('Curated Collections')
                                ->relationship('collections', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),

                            Select::make('product_status')
                                ->label('Quick Catalog & Storefront Status Preset')
                                ->options([
                                    'active' => '● Active & Published (Live in Storefront & POS)',
                                    'draft' => '○ Internal Only (Showroom POS & Warehouse Only / Unpublished)',
                                    'archived' => '⏸ Archived (Retired from Sales & Catalog)',
                                ])
                                ->default(fn(?Product $record) => $record ? ($record->is_published && $record->is_active ? 'active' : ($record->is_active ? 'draft' : 'archived')) : 'active')
                                ->afterStateHydrated(function ($component, ?Product $record) {
                                    if ($record) {
                                        $val = $record->is_published && $record->is_active ? 'active' : ($record->is_active ? 'draft' : 'archived');
                                        $component->state($val);
                                    }
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, $set) {
                                    if ($state === 'active') {
                                        $set('is_published', true);
                                        $set('is_active', true);
                                    } elseif ($state === 'draft') {
                                        $set('is_published', false);
                                        $set('is_active', true);
                                    } else {
                                        $set('is_published', false);
                                        $set('is_active', false);
                                    }
                                })
                                ->helperText('Quick preset. Fine-grained publishing toggles are also available in the right sidebar.')
                                ->columnSpanFull(),

                            Toggle::make('is_new_arrival')
                                ->label('New Arrival Badge')
                                ->default(true),

                            Toggle::make('is_featured')
                                ->label('Featured Item')
                                ->default(false),

                            Textarea::make('short_description')
                                ->label('Short Summary / Catalog Excerpt')
                                ->placeholder('1-2 sentence product overview for search previews and POS details.')
                                ->rows(2)
                                ->maxLength(500)
                                ->columnSpanFull(),

                            RichEditor::make('description')
                                ->label('Detailed Description & Specifications')
                                ->placeholder('Complete product details, material breakdown, styling tips...')
                                ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote'])
                                ->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2]),

                    // SECTION 2: PRODUCT IDENTITY & BARCODE
                    Section::make('Product Identity & Barcodes')
                        ->icon('heroicon-o-qr-code')
                        ->description('SKU allocation, unique EAN-13 barcodes, and supplier references.')
                        ->schema([
                            TextInput::make('sku')
                                ->label('Product SKU')
                                ->placeholder('Auto-generated (e.g. LJ-SHO-000245)')
                                ->helperText('Authoritative master SKU. Leave blank to automatically generate based on category sequence.')
                                ->maxLength(100),

                            TextInput::make('barcode')
                                ->label('Barcode (EAN-13)')
                                ->placeholder('Auto-generated (e.g. 2001234567890)')
                                ->helperText('GS1-compliant 13-digit barcode for POS barcode scanner.')
                                ->maxLength(13),

                            TextInput::make('supplier_name')
                                ->label('Supplier / Artisan / Distributor')
                                ->placeholder('e.g. Nepal Sports Distribution, Heritage Handloom Co.')
                                ->maxLength(150),

                            TextInput::make('supplier_sku')
                                ->label('Supplier Sourcing Reference')
                                ->placeholder('e.g. SUP-2026-X09')
                                ->maxLength(100),

                            TextInput::make('internal_reference')
                                ->label('Internal Reference / Bin Location')
                                ->placeholder('e.g. BIN-A4-RACK2')
                                ->maxLength(100),
                        ])->columns(['default' => 1, 'sm' => 2]),

                    // SECTION 3: VARIANT MATRIX BUILDER
                    Section::make('Variant Matrix & Combinations')
                        ->icon('heroicon-o-squares-2x2')
                        ->description('Color and size variations. Each variant has its own unique SKU, barcode, price, cost, and stock.')
                        ->headerActions([
                            \Filament\Actions\Action::make('generate_matrix')
                                ->label('⚡ Quick Matrix Generator')
                                ->icon('heroicon-o-bolt')
                                ->color('primary')
                                ->form([
                                    Select::make('category_type')
                                        ->label('Retail Department')
                                        ->options([
                                            'shoes' => 'Footwear (Np Sizes 36-45)',
                                            'apparel' => 'Apparel / Tops (XS, S, M, L, XL, XXL, 3XL)',
                                            'pants' => 'Pants & Waist (28, 30, 32, 34, 36, 38, 40)',
                                            'universal' => 'Universal (Standard, Free Size)',
                                        ])
                                        ->default('shoes')
                                        ->live(),

                                    Select::make('preset_sizes')
                                        ->label('Select Sizes')
                                        ->options(fn() => self::getSizeOptions())
                                        ->multiple()
                                        ->default(['39', '40', '41', '42', '43', '44'])
                                        ->required(),

                                    Select::make('preset_colors')
                                        ->label('Select Colors')
                                        ->options(fn() => self::getColorOptions())
                                        ->multiple()
                                        ->default(['Black', 'White'])
                                        ->required(),

                                    TextInput::make('default_stock')
                                        ->label('Default Stock Per Variant')
                                        ->numeric()
                                        ->default(5)
                                        ->required(),

                                    TextInput::make('default_price')
                                        ->label('Default Variant Price (Rs.)')
                                        ->numeric()
                                        ->prefix('Rs.')
                                        ->placeholder('Leave empty to inherit base product price'),
                                ])
                                ->action(function (array $data, $set, $get) {
                                    $defaultStock = (int) ($data['default_stock'] ?? 5);
                                    $defaultPrice = !empty($data['default_price']) ? (float) $data['default_price'] : null;

                                    $colorHexMap = [
                                        'Black' => '#000000',
                                        'White' => '#FFFFFF',
                                        'Brown' => '#8B4513',
                                        'Blue' => '#1E3A8A',
                                        'Red' => '#B91C1C',
                                        'Grey' => '#6B7280',
                                        'Green' => '#047857',
                                        'Navy' => '#0F172A',
                                        'Beige' => '#D2B48C',
                                        'Standard' => '#4B5563',
                                    ];

                                    $generated = [];
                                    foreach ($data['preset_colors'] as $color) {
                                        $hex = $colorHexMap[$color] ?? '#000000';
                                        foreach ($data['preset_sizes'] as $size) {
                                            $generated[] = [
                                                'color' => $color,
                                                'color_hex' => $hex,
                                                'size' => $size,
                                                'price' => $defaultPrice,
                                                'stock_quantity' => $defaultStock,
                                                'is_active' => true,
                                            ];
                                        }
                                    }

                                    $set('variants', $generated);
                                    Notification::make()
                                        ->title('Variant Combinations Populated')
                                        ->body(count($generated) . ' variant combinations ready. Unique SKUs & barcodes will generate upon save.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->schema([
                            Repeater::make('variants')
                                ->relationship('variants')
                                ->schema([
                                    TextInput::make('color')
                                        ->label('Color *')
                                        ->placeholder('e.g. Black, White, Navy')
                                        ->default('Standard')
                                        ->required(),

                                    ColorPicker::make('color_hex')
                                        ->label('Swatch Color'),

                                    Select::make('size')
                                        ->label('Size *')
                                        ->options(fn() => self::getSizeOptions())
                                        ->searchable()
                                        ->default('40')
                                        ->required(),

                                    TextInput::make('price')
                                        ->label('Variant Price (Rs.)')
                                        ->numeric()
                                        ->prefix('Rs.')
                                        ->placeholder('Inherits base price if blank'),

                                    TextInput::make('cost_price')
                                        ->label('Cost Base (Rs.)')
                                        ->numeric()
                                        ->prefix('Rs.')
                                        ->visible($canViewCost),

                                    TextInput::make('wholesale_price')
                                        ->label('Wholesale (Rs.)')
                                        ->numeric()
                                        ->prefix('Rs.'),

                                    TextInput::make('stock_quantity')
                                        ->label('Stock Qty *')
                                        ->numeric()
                                        ->default(5)
                                        ->required(),

                                    TextInput::make('sku')
                                        ->label('Variant SKU')
                                        ->placeholder('Auto-generated (e.g. LJ-SHO-000245-BLK-42)'),

                                    TextInput::make('barcode')
                                        ->label('Barcode (EAN-13)')
                                        ->placeholder('Auto-generated (e.g. 200...)'),

                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true),
                                ])
                                ->columns(['default' => 1, 'sm' => 2, 'md' => 4, 'xl' => 5])
                                ->defaultItems(0)
                                ->collapsible()
                                ->itemLabel(function (array $state): ?string {
                                    $color = $state['color'] ?? 'Standard';
                                    $size = $state['size'] ?? 'Standard';
                                    $stock = $state['stock_quantity'] ?? 0;
                                    $price = !empty($state['price']) ? " — Rs. " . number_format((float) $state['price'], 0) : '';
                                    $sku = !empty($state['sku']) ? " [{$state['sku']}]" : '';
                                    $active = ($state['is_active'] ?? true) ? '' : ' (Inactive)';
                                    return "● {$color} / {$size} — {$stock} in stock{$price}{$sku}{$active}";
                                })
                                ->columnSpanFull(),
                        ]),

                    // SECTION 4: PRODUCT IMAGERY & MEDIA
                    Section::make('Visual Gallery & Product Photography')
                        ->icon('heroicon-o-photo')
                        ->description('Primary cover photograph and reorderable high-resolution gallery.')
                        ->schema([
                            FileUpload::make('featured_image')
                                ->label('Primary Cover Photo (Storefront & POS Thumbnail)')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios(['1:1', '4:5', '16:9'])
                                ->disk('public')
                                ->directory('product/thumbnail')
                                ->visibility('public')
                                ->openable()
                                ->downloadable()
                                ->maxSize(10240)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/jpg'])
                                ->helperText('Recommended: 800×800 square WebP or PNG format.')
                                ->columnSpanFull(),

                            FileUpload::make('images')
                                ->label('Additional Gallery Photography (Drag to Reorder • Up to 12 Photos)')
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(12)
                                ->maxSize(10240)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/jpg'])
                                ->panelLayout('grid')
                                ->imageEditor()
                                ->openable()
                                ->downloadable()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/jpg'])
                                ->disk('public')
                                ->directory('product')
                                ->visibility('public')
                                ->helperText('Alternative product angles, soles, tags, packaging, and scale references.')
                                ->columnSpanFull(),
                        ]),

                    // SECTION 5: GARMENT & PRODUCT SPECIFICATIONS (COLLAPSIBLE)
                    Section::make('Product Specifications & Provenance')
                        ->icon('heroicon-o-archive-box')
                        ->description('Material composition, dimensions, weight, and care guidelines.')
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            TextInput::make('material')
                                ->label('Material / Composition')
                                ->placeholder('e.g. 100% Genuine Leather, Breathable Mesh, Mulberry Silk'),

                            TextInput::make('fabric')
                                ->label('Fabric / Texture')
                                ->placeholder('e.g. Full-Grain Nappa, Jacquard Weave, Ripstop Nylon'),

                            TextInput::make('country_of_origin')
                                ->label('Country of Origin')
                                ->default('Nepal'),

                            TextInput::make('dimensions')
                                ->label('Physical Dimensions / Packaging Size')
                                ->placeholder('e.g. 32cm × 22cm × 12cm box'),

                            RichEditor::make('care_instructions')
                                ->label('Care & Maintenance Guide')
                                ->toolbarButtons(['bold', 'italic', 'bulletList'])
                                ->default('Store in a cool, dry place away from direct sunlight. Wipe with clean cloth.')
                                ->columnSpanFull(),
                        ])->columns(['default' => 1, 'sm' => 2]),

                ])->columnSpan(['default' => 'full', 'lg' => 2]),

                // =========================================================
                // 2. SIDEBAR COLUMN (1/3 width on desktop, 100% on mobile)
                // =========================================================
                Group::make()->schema([

                    // SIDEBAR SECTION 0: STOREFRONT PUBLISHING CONTROL
                    Section::make('Storefront Publishing')
                        ->icon('heroicon-o-globe-alt')
                        ->description('Control public ecommerce publication vs internal POS/warehouse visibility.')
                        ->schema([
                            Toggle::make('is_published')
                                ->label('Storefront: Published')
                                ->helperText('When enabled, this master product is visible on the public webshop (requires verified photo & valid price). When disabled, it remains available internally for Showroom POS, inventory, and accounting.')
                                ->default(true)
                                ->live(),

                            Toggle::make('is_active')
                                ->label('Master Catalog Active')
                                ->helperText('Master operational status. Disabling archives this piece across POS and ERP.')
                                ->default(true)
                                ->live(),

                            Placeholder::make('publishing_state_indicator')
                                ->label('Publication State')
                                ->content(function ($get): HtmlString {
                                    $isPublished = (bool) $get('is_published');
                                    $isActive = (bool) $get('is_active');

                                    if ($isPublished && $isActive) {
                                        return new HtmlString("<div class='p-2.5 bg-emerald-50 text-emerald-800 font-semibold rounded-lg text-xs border border-emerald-200 flex items-center gap-2'>
                                            <span class='w-2 h-2 rounded-full bg-emerald-500 animate-pulse'></span>
                                            <span><strong>Storefront: Published & Live</strong> (Also active in POS & Inventory)</span>
                                        </div>");
                                    } elseif ($isActive) {
                                        return new HtmlString("<div class='p-2.5 bg-amber-50 text-amber-800 font-semibold rounded-lg text-xs border border-amber-200 flex items-center gap-2'>
                                            <span class='w-2 h-2 rounded-full bg-amber-500'></span>
                                            <span><strong>Storefront: Unpublished</strong> (Active internally for Showroom POS & Stock)</span>
                                        </div>");
                                    }

                                    return new HtmlString("<div class='p-2.5 bg-gray-100 text-gray-700 font-semibold rounded-lg text-xs border border-gray-300 flex items-center gap-2'>
                                        <span class='w-2 h-2 rounded-full bg-gray-400'></span>
                                        <span><strong>Archived</strong> (Hidden from Storefront, POS & Active Inventory)</span>
                                    </div>");
                                }),
                        ])->columns(1),

                    // SIDEBAR SECTION 1: PRICING, COST & PROFIT ENGINE (NPR)
                    Section::make('Pricing, Cost & Profit Engine (NPR)')
                        ->icon('heroicon-o-banknotes')
                        ->description('Authoritative Nepal Rupees (Rs.) pricing, cost, and commercial margin.')
                        ->schema([
                            TextInput::make('price')
                                ->label('Selling Price (NPR / Rs.) *')
                                ->numeric()
                                ->prefix('Rs.')
                                ->required()
                                ->live(onBlur: true)
                                ->helperText('Retail selling price in Nepali Rupees (NPR).'),

                            TextInput::make('compare_at_price')
                                ->label('Compare-At / Was Price (Rs.)')
                                ->numeric()
                                ->prefix('Rs.')
                                ->nullable()
                                ->live(onBlur: true)
                                ->helperText('Original retail price for discount display.'),

                            TextInput::make('cost_price')
                                ->label('Landed Cost Price (Rs.)')
                                ->numeric()
                                ->prefix('Rs.')
                                ->nullable()
                                ->live(onBlur: true)
                                ->visible($canViewCost)
                                ->helperText('Unit landed sourcing cost from supplier.'),

                            TextInput::make('wholesale_price')
                                ->label('Wholesale / Bulk Price (Rs.)')
                                ->numeric()
                                ->prefix('Rs.')
                                ->nullable()
                                ->helperText('Price for verified wholesale or B2B accounts.'),

                            Select::make('tax_class')
                                ->label('Tax Status (Nepal VAT)')
                                ->options([
                                    'standard' => 'Standard Rate (13% VAT Included)',
                                    'exempt' => 'VAT Exempt (Zero-Rated)',
                                ])
                                ->default('standard'),

                            // Automated Live Profit & Margin Breakdown (NPR)
                            Placeholder::make('profit_margin_analysis')
                                ->label('Live Profit & Margin Breakdown (NPR)')
                                ->visible($canViewCost)
                                ->content(function ($get): HtmlString {
                                    $price = (float) ($get('price') ?: 0);
                                    $cost = (float) ($get('cost_price') ?: 0);

                                    $profit = $price > 0 && $cost > 0 ? ($price - $cost) : 0;
                                    $marginPct = $price > 0 && $cost > 0 ? round(($profit / $price) * 100, 1) : 0;
                                    $markupMultiplier = $cost > 0 ? round($price / $cost, 2) : 0;

                                    $marginBadgeColor = match (true) {
                                        $marginPct >= 50 => 'text-emerald-700 bg-emerald-50 border-emerald-300',
                                        $marginPct >= 30 => 'text-sky-700 bg-sky-50 border-sky-300',
                                        $marginPct >= 15 => 'text-amber-700 bg-amber-50 border-amber-300',
                                        $marginPct > 0 => 'text-rose-700 bg-rose-50 border-rose-300',
                                        default => 'text-gray-500 bg-gray-50 border-gray-200',
                                    };

                                    return new HtmlString("
                                        <div class='p-3 bg-gray-50 rounded-xl text-xs space-y-2 border border-gray-200 font-mono'>
                                            <div class='flex justify-between items-center pb-1.5 border-b border-gray-200'>
                                                <span class='text-gray-600 font-sans font-medium'>Gross Margin:</span>
                                                <span class='px-2 py-0.5 rounded font-bold border {$marginBadgeColor}'>
                                                    " . ($marginPct > 0 ? "{$marginPct}% Margin" : "Cost not entered") . "
                                                </span>
                                            </div>
                                            <div class='flex justify-between text-gray-700'>
                                                <span>Unit Profit (NPR):</span>
                                                <span class='font-bold text-emerald-600'>" . ($profit > 0 ? '+Rs. ' . number_format($profit, 2) : '—') . "</span>
                                            </div>
                                            " . ($markupMultiplier > 0 ? "
                                            <div class='flex justify-between text-gray-500'>
                                                <span>Markup Multiplier:</span>
                                                <span>{$markupMultiplier}x cost</span>
                                            </div>" : "") . "
                                            <div class='flex justify-between text-gray-500 pt-1 border-t border-gray-200 font-sans text-[11px]'>
                                                <span>Currency:</span>
                                                <span class='font-bold text-blue-700'>NPR (Nepalese Rupee)</span>
                                            </div>
                                        </div>
                                    ");
                                })
                                ->columnSpanFull(),
                        ])->columns(1),

                    // SIDEBAR SECTION 2: STOCK & INVENTORY
                    Section::make('Stock & Warehouse Inventory')
                        ->icon('heroicon-o-cube')
                        ->schema([
                            TextInput::make('quantity')
                                ->label('Base / Aggregated Stock *')
                                ->numeric()
                                ->default(10)
                                ->required()
                                ->helperText('Aggregated units on hand. If variants exist, total stock auto-sums from variant records.'),

                            TextInput::make('low_stock_threshold')
                                ->label('Low Stock Alert Threshold')
                                ->numeric()
                                ->default(3)
                                ->required(),

                            Toggle::make('track_quantity')
                                ->label('Track Physical Inventory')
                                ->default(true),

                            Radio::make('availability_status')
                                ->label('Availability Mode')
                                ->options([
                                    'available' => '● Available (In Stock & Ready for Dispatch)',
                                    'out_of_stock' => '○ Out of Stock',
                                    'restock_requests' => '✉ Waitlist (Accept Customer Restock Requests)',
                                    'pre_order' => '★ Pre-order (Accept Orders for Future Dispatch)',
                                    'permanently_unavailable' => '✕ Discontinued / Archived',
                                ])
                                ->default('available'),
                        ])->columns(1),

                    // SIDEBAR SECTION 3: SHIPPING & FULFILLMENT
                    Section::make('Fulfillment & Shipping')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            TextInput::make('weight')
                                ->label('Product Weight (kg)')
                                ->numeric()
                                ->default(0.5)
                                ->suffix('kg'),

                            Placeholder::make('fulfillment_origin')
                                ->label('Primary Fulfillment Hub')
                                ->content('Kathmandu Central Hub, Nepal'),
                        ])->columns(1),

                    // SIDEBAR SECTION 4: PUBLISHING READINESS
                    Section::make('Publishing Readiness')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            Placeholder::make('readiness_check')
                                ->label('Pre-Publishing Verification')
                                ->content(function ($get): HtmlString {
                                    $hasName = filled($get('name'));
                                    $hasCategory = filled($get('categories'));
                                    $hasImage = filled($get('featured_image'));
                                    $hasPrice = (float) $get('price') > 0;
                                    $hasStock = (int) $get('quantity') > 0 || count($get('variants') ?? []) > 0;

                                    $ready = $hasName && $hasCategory && $hasImage && $hasPrice && $hasStock;

                                    $checkItem = fn($ok, $label) => $ok
                                        ? "<div class='text-emerald-700 font-semibold flex items-center gap-1.5'>✓ <span>{$label}</span></div>"
                                        : "<div class='text-gray-400 flex items-center gap-1.5'>○ <span>{$label} (Required)</span></div>";

                                    $html = "<div class='space-y-1.5 text-xs'>";
                                    $html .= $checkItem($hasName, 'Product Title');
                                    $html .= $checkItem($hasCategory, 'Category Assigned');
                                    $html .= $checkItem($hasImage, 'Cover Image Uploaded');
                                    $html .= $checkItem($hasPrice, 'Selling Price Configured');
                                    $html .= $checkItem($hasStock, 'Inventory / Variants');
                                    $html .= "</div>";

                                    $statusBadge = $ready
                                        ? "<div class='mt-3 p-2 bg-emerald-50 text-emerald-800 font-bold rounded-lg text-center text-xs border border-emerald-200'>✓ Ready for Storefront & POS</div>"
                                        : "<div class='mt-3 p-2 bg-amber-50 text-amber-800 font-semibold rounded-lg text-center text-xs border border-amber-200'>⚠️ Complete Required Fields</div>";

                                    return new HtmlString($html . $statusBadge);
                                }),
                        ]),

                ])->columnSpan(['default' => 'full', 'lg' => 1]),

            ])->columns(['default' => 1, 'lg' => 3]);
    }

    public static function table(Table $table): Table
    {
        $canViewCost = auth()->user()?->isSuperAdmin() || auth()->user()?->hasRole('Store Manager', 'admin') || auth()->user()?->hasRole('Store Manager', 'web');

        return $table
            ->columns([
                ImageColumn::make('featured_image')
                    ->label('Photo')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl(fn() => 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="%23cbd5e1" stroke-width="1.5"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>')
                    ->getStateUsing(function (Product $record): ?string {
                        $raw = $record->featured_image;
                        if (empty($raw) && is_array($record->images) && count($record->images) > 0) {
                            $raw = $record->images[0];
                        }
                        if (empty($raw)) {
                            return null;
                        }
                        $clean = preg_replace('#^(\/?storage\/)+#', '', (string)$raw);
                        $clean = ltrim($clean, '/');
                        return !empty($clean) ? $clean : null;
                    }),

                TextColumn::make('name')
                    ->label('Product')
                    ->searchable(query: fn(Builder $query, string $search) => $query->searchRetail($search))
                    ->sortable()
                    ->weight('bold')
                    ->description(function (Product $record): string {
                        $parts = [];
                        if ($record->brand) $parts[] = $record->brand;
                        if ($record->model) $parts[] = $record->model;
                        $parts[] = 'SKU: ' . ($record->sku ?? 'N/A');
                        return implode(' • ', $parts);
                    })
                    ->wrap()
                    ->limit(45),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->formatStateUsing(fn(int $state): string => $state > 0 ? "{$state} variants" : 'Single')
                    ->sortable(),

                TextColumn::make('categories.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->limitList(1),

                TextColumn::make('price')
                    ->label('Price (NPR)')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float) ($state ?: 0), 'Rs. ', 2))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('cost_price')
                    ->label('Cost Base')
                    ->formatStateUsing(fn($state) => $state ? \App\Helpers\NepaliNumberHelper::formatCurrency((float) $state, 'Rs. ', 2) : '—')
                    ->color('gray')
                    ->visible($canViewCost)
                    ->toggleable(),

                TextColumn::make('gross_margin')
                    ->label('Margin')
                    ->state(function (Product $record): string {
                        $selling = (float) $record->price;
                        $cost = (float) $record->cost_price;
                        if ($selling <= 0 || $cost <= 0) return '—';
                        $margin = round((($selling - $cost) / $selling) * 100, 1);
                        return "{$margin}%";
                    })
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === '—') return 'gray';
                        $pct = (float) rtrim($state, '%');
                        return match (true) {
                            $pct >= 50 => 'success',
                            $pct >= 25 => 'warning',
                            default => 'danger',
                        };
                    })
                    ->visible($canViewCost)
                    ->toggleable(),

                TextColumn::make('formatted_stock')
                    ->label('Stock')
                    ->state(fn(Product $record) => $record->formatted_stock)
                    ->badge()
                    ->color(function (string $state): string {
                        if (str_contains($state, 'Out of Stock')) return 'danger';
                        if (str_contains($state, 'Low Stock')) return 'warning';
                        return 'success';
                    }),

                TextColumn::make('status_badge')
                    ->label('Catalog')
                    ->state(function (Product $record): string {
                        return $record->is_active ? 'Active' : 'Archived';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Active' => 'success',
                        default => 'slate',
                    })
                    ->tooltip('Master Catalog / Inventory & POS availability'),

                TextColumn::make('public_status')
                    ->label('Storefront')
                    ->state(function (Product $record): string {
                        if (!$record->is_active) {
                            return 'Archived';
                        }
                        return $record->is_published ? 'Published' : 'Unpublished (POS Only)';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Published' => 'success',
                        'Unpublished (POS Only)' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('photo_status')
                    ->label('Photo')
                    ->state(function (Product $record): string {
                        return app(\App\Services\Operational\CatalogSyncService::class)->hasPhysicalPhoto($record) ? 'Verified' : 'Missing';
                    })
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Verified' ? 'success' : 'danger'),

                TextColumn::make('data_readiness')
                    ->label('Readiness')
                    ->state(function (Product $record): string {
                        $res = app(\App\Services\Operational\CatalogSyncService::class)->validatePublicationEligibility($record);
                        return $res['eligible'] ? 'Ready' : 'Incomplete';
                    })
                    ->tooltip(function (Product $record): ?string {
                        $res = app(\App\Services\Operational\CatalogSyncService::class)->validatePublicationEligibility($record);
                        return $res['eligible'] ? null : implode(', ', $res['missing']);
                    })
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Ready' ? 'success' : 'warning')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categories')
                    ->label('Category')
                    ->relationship('categories', 'name', fn(Builder $query) => $query->with('parent'))
                    ->getOptionLabelFromRecordUsing(fn(Category $record) => $record->hierarchy_path)
                    ->preload(),

                SelectFilter::make('brand')
                    ->label('Brand')
                    ->options(fn() => Product::whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand', 'brand')),

                SelectFilter::make('status')
                    ->label('Catalog Status')
                    ->options([
                        'active' => 'Active',
                        'draft' => 'Draft',
                        'archived' => 'Archived',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->where('is_published', true)->where('is_active', true),
                            'draft' => $query->where('is_published', false)->where('is_active', true),
                            'archived' => $query->where('is_active', false),
                            default => $query,
                        };
                    }),

                Filter::make('stock_level')
                    ->label('Stock Status')
                    ->form([
                        Select::make('stock_status')
                            ->label('Stock Status')
                            ->options([
                                'in_stock' => 'In Stock (> 3)',
                                'low_stock' => 'Low Stock (1 - 3)',
                                'out_of_stock' => 'Out of Stock (0)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['stock_status'] ?? null) {
                            'in_stock' => $query->where('quantity', '>', 3),
                            'low_stock' => $query->where('quantity', '>', 0)->where('quantity', '<=', 3),
                            'out_of_stock' => $query->where('quantity', '<=', 0),
                            default => $query,
                        };
                    }),

                Filter::make('has_variants')
                    ->label('Product Architecture')
                    ->form([
                        Select::make('variants_filter')
                            ->label('Variant Type')
                            ->options([
                                'has_variants' => 'Variable Product (Has Variants)',
                                'no_variants' => 'Simple Product (No Variants)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['variants_filter'] ?? null) {
                            'has_variants' => $query->has('variants'),
                            'no_variants' => $query->doesntHave('variants'),
                            default => $query,
                        };
                    }),

                Filter::make('missing_barcode')
                    ->label('Barcode Status')
                    ->query(fn(Builder $query) => $query->whereNull('barcode')->orWhere('barcode', '')),

                SelectFilter::make('is_published')
                    ->label('Webshop Visibility')
                    ->options([
                        '1' => 'Live on Webshop',
                        '0' => 'Unpublished (POS / ERP Only)',
                    ]),

                Filter::make('photo_filter')
                    ->label('Photo Availability')
                    ->form([
                        Select::make('photo_status')
                            ->label('Photo Status')
                            ->options([
                                'has_photo' => 'Has Photo',
                                'missing_photo' => 'Missing Photo',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['photo_status'] ?? null) {
                            'has_photo' => $query->whereNotNull('featured_image')->where('featured_image', '!=', ''),
                            'missing_photo' => $query->where(function ($q) {
                                $q->whereNull('featured_image')->orWhere('featured_image', '');
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                // 1. VIEW PRODUCT OVERVIEW
                \Filament\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Product $record) => ProductResource::getUrl('view', ['record' => $record->id])),

                // 2. EDIT
                \Filament\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square'),

                // 3. QUICK ADD STOCK MODAL
                \Filament\Actions\Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Select::make('warehouse_id')
                            ->label('Destination Warehouse *')
                            ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                            ->default(fn() => Warehouse::where('is_default', true)->first()?->id ?: Warehouse::first()?->id)
                            ->required(),

                        Select::make('variant_id')
                            ->label('Variant (Optional)')
                            ->options(fn(Product $record) => $record->variants->pluck('size', 'id')->map(fn($sz, $vid) => ($record->variants->firstWhere('id', $vid)->color ?? '') . ' / ' . $sz))
                            ->visible(fn(Product $record) => $record->variants->isNotEmpty()),

                        TextInput::make('quantity')
                            ->label('Quantity to Add *')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        TextInput::make('notes')
                            ->label('Receiving Reference / Notes')
                            ->default('Fast Stock Receiving from Product Table'),
                    ])
                    ->action(function (Product $record, array $data) {
                        $inventoryService = app(InventoryService::class);
                        $inventoryService->recordStockMovement([
                            'warehouse_id' => (int) $data['warehouse_id'],
                            'product_id' => $record->id,
                            'variant_id' => !empty($data['variant_id']) ? (int) $data['variant_id'] : null,
                            'quantity' => (int) $data['quantity'],
                            'movement_type' => 'purchase_receive',
                            'notes' => $data['notes'],
                        ], auth()->user());

                        Notification::make()
                            ->title('Stock Received')
                            ->body("+{$data['quantity']} units added to inventory atomically.")
                            ->success()
                            ->send();
                    }),

                // 4. PRINT BARCODE LABEL
                \Filament\Actions\Action::make('print_labels')
                    ->label('Print Barcode')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->url(fn(Product $record) => url('/intadmin/barcodes-labels?product_id=' . $record->id)),

                // 5. MORE ACTIONS DROPDOWN (⋯)
                \Filament\Actions\ActionGroup::make([
                    // DUPLICATE PRODUCT ACTION
                    \Filament\Actions\Action::make('duplicate')
                        ->label('Duplicate Product')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Duplicate Product')
                        ->modalDescription('Creates a safe draft clone of this piece with all categories and variants. Fresh SKUs and barcodes will be generated.')
                        ->action(function (Product $record) {
                            $newProduct = app(ProductService::class)->duplicate($record);

                            Notification::make()
                                ->title('Product Duplicated')
                                ->body("{$newProduct->name} created as draft.")
                                ->success()
                                ->send();

                            return redirect()->to(ProductResource::getUrl('edit', ['record' => $newProduct->id]));
                        }),

                    // PUBLISH TO STOREFRONT WITH VALIDATION
                    \Filament\Actions\Action::make('publish_to_webshop')
                        ->label('Publish to Storefront')
                        ->icon('heroicon-o-globe-alt')
                        ->color('success')
                        ->visible(fn(Product $record) => !$record->is_published && $record->is_active)
                        ->requiresConfirmation()
                        ->modalHeading('Publish Product to Storefront')
                        ->modalDescription('Validates whether this product satisfies all public ecommerce requirements (real verified photo on disk, valid price > Rs. 0, category, and resolved SKU).')
                        ->action(function (Product $record) {
                            $syncService = app(\App\Services\Operational\CatalogSyncService::class);
                            $val = $syncService->validatePublicationEligibility($record);
                            if (!$val['eligible']) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot publish this product')
                                    ->danger()
                                    ->body("Missing required customer-facing information:\n• " . implode("\n• ", $val['missing']))
                                    ->persistent()
                                    ->send();
                                return;
                            }

                            $record->update(['is_published' => true]);
                            \Filament\Notifications\Notification::make()
                                ->title('Product Published to Storefront')
                                ->success()
                                ->body("{$record->name} is now live on the public storefront.")
                                ->send();
                        }),

                    // UNPUBLISH FROM STOREFRONT (RETAIN ERP / POS)
                    \Filament\Actions\Action::make('unpublish_from_webshop')
                        ->label('Unpublish from Storefront')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn(Product $record) => $record->is_published)
                        ->requiresConfirmation()
                        ->modalHeading('Unpublish from Storefront')
                        ->modalDescription('Hides this product from the public online store. It will remain active for Showroom POS, stock management, and accounting.')
                        ->action(function (Product $record) {
                            $record->update(['is_published' => false]);
                            \Filament\Notifications\Notification::make()
                                ->title('Product Unpublished')
                                ->warning()
                                ->body("{$record->name} has been hidden from the public storefront. It remains active for POS, inventory, and accounting.")
                                ->send();
                        }),

                    // VIEW ON STORE
                    \Filament\Actions\Action::make('view_on_store')
                        ->label('View on Storefront')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn(Product $record) => url('/products/' . $record->id))
                        ->openUrlInNewTab(),

                    // ARCHIVE / RESTORE TOGGLE
                    \Filament\Actions\Action::make('toggle_archive')
                        ->label(fn(Product $record) => $record->is_active ? 'Archive Product' : 'Restore Product')
                        ->icon(fn(Product $record) => $record->is_active ? 'heroicon-o-archive-box-arrow-down' : 'heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function (Product $record) {
                            if ($record->is_active) {
                                app(ProductService::class)->archive($record);
                                Notification::make()->title('Product Archived')->info()->send();
                            } else {
                                app(ProductService::class)->unarchive($record);
                                Notification::make()->title('Product Restored')->success()->send();
                            }
                        }),

                    // SAFE DELETION WITH HISTORICAL ORDER CHECK
                    \Filament\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->modalHeading('Delete Product Confirmation')
                        ->modalDescription(function (Product $record) {
                            $orderCount = OrderItem::where('product_id', $record->id)->count();
                            if ($orderCount > 0) {
                                return "⚠️ DANGER: This product has {$orderCount} associated sales orders. Permanent deletion will break historical order references. Please Archive instead!";
                            }
                            return 'Are you sure you want to delete this product?';
                        }),
                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    // Bulk Publish to Storefront
                    \Filament\Actions\BulkAction::make('bulk_publish_storefront')
                        ->label('Publish to Storefront')
                        ->icon('heroicon-o-globe-alt')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Publish to Storefront')
                        ->modalDescription('Publishes selected active products that satisfy storefront eligibility (verified photo, category, and price > 0). Any incomplete items will remain unpublished.')
                        ->action(function (EloquentCollection $records) {
                            $syncService = app(\App\Services\Operational\CatalogSyncService::class);
                            $publishedCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $p) {
                                if (!$p->is_active) {
                                    $skippedCount++;
                                    continue;
                                }
                                $val = $syncService->validatePublicationEligibility($p);
                                if ($val['eligible']) {
                                    $p->update(['is_published' => true]);
                                    $publishedCount++;
                                } else {
                                    $skippedCount++;
                                }
                            }

                            if ($publishedCount > 0) {
                                Notification::make()
                                    ->title("{$publishedCount} products published to storefront")
                                    ->success()
                                    ->send();
                            }
                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->title("{$skippedCount} products skipped (missing photo, price or inactive)")
                                    ->warning()
                                    ->send();
                            }
                        }),

                    // Bulk Unpublish from Storefront
                    \Filament\Actions\BulkAction::make('bulk_unpublish_storefront')
                        ->label('Unpublish from Storefront')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Unpublish from Storefront')
                        ->modalDescription('Hides selected products from the public storefront. All products will remain active internally for POS, inventory, and accounting.')
                        ->action(function (EloquentCollection $records) {
                            $count = 0;
                            foreach ($records as $p) {
                                if ($p->is_published) {
                                    $p->update(['is_published' => false]);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} products unpublished from storefront")
                                ->warning()
                                ->send();
                        }),

                    // Bulk Activate
                    \Filament\Actions\BulkAction::make('bulk_activate')
                        ->label('Set Active & Live')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (EloquentCollection $records) {
                            foreach ($records as $p) {
                                app(ProductService::class)->unarchive($p);
                            }
                            Notification::make()->title('Selected products activated')->success()->send();
                        }),

                    // Bulk Archive
                    \Filament\Actions\BulkAction::make('bulk_archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(function (EloquentCollection $records) {
                            foreach ($records as $p) {
                                app(ProductService::class)->archive($p);
                            }
                            Notification::make()->title('Selected products archived')->info()->send();
                        }),

                    // Bulk Category Assignment
                    \Filament\Actions\BulkAction::make('bulk_category')
                        ->label('Change Category')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Select::make('category_id')
                                ->label('Select New Category')
                                ->options(fn() => Category::where('is_active', true)->with('parent')->get()->mapWithKeys(fn($cat) => [$cat->id => $cat->hierarchy_path]))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (EloquentCollection $records, array $data) {
                            foreach ($records as $product) {
                                $product->categories()->sync([$data['category_id']]);
                            }
                            Notification::make()->title('Categories updated')->success()->send();
                        }),

                    // Bulk Price Update
                    \Filament\Actions\BulkAction::make('bulk_price')
                        ->label('Adjust Prices')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            Select::make('price_field')
                                ->label('Field to Adjust')
                                ->options([
                                    'price' => 'Retail Selling Price',
                                    'cost_price' => 'Cost Price',
                                    'wholesale_price' => 'Wholesale Price',
                                ])
                                ->default('price')
                                ->required(),

                            TextInput::make('adjustment')
                                ->label('Adjustment Amount')
                                ->numeric()
                                ->required()
                                ->helperText('Enter positive number to increase, negative to decrease.'),

                            Toggle::make('is_percentage')
                                ->label('Percentage Adjustment (%)')
                                ->default(false),
                        ])
                        ->action(function (EloquentCollection $records, array $data) {
                            $updated = app(ProductService::class)->bulkUpdatePrices(
                                $records->pluck('id')->all(),
                                (float) $data['adjustment'],
                                (bool) $data['is_percentage'],
                                (string) $data['price_field']
                            );
                            Notification::make()->title("Prices updated for {$updated} products")->success()->send();
                        }),

                    // Bulk Generate Missing Barcodes
                    \Filament\Actions\BulkAction::make('bulk_generate_barcodes')
                        ->label('Generate Missing Barcodes')
                        ->icon('heroicon-o-qr-code')
                        ->color('info')
                        ->action(function (EloquentCollection $records) {
                            $count = app(ProductService::class)->bulkGenerateMissingBarcodes($records->pluck('id')->all());
                            Notification::make()->title("Generated {$count} missing barcodes")->success()->send();
                        }),

                    // Bulk Print Labels
                    \Filament\Actions\BulkAction::make('bulk_print_labels')
                        ->label('Print Barcode Labels')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(function (EloquentCollection $records) {
                            $ids = $records->pluck('id')->implode(',');
                            return redirect()->to(url('/intadmin/barcodes-labels?product_ids=' . $ids));
                        }),

                    // Bulk CSV Export
                    \Filament\Actions\BulkAction::make('bulk_export')
                        ->label('Export Selected to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (EloquentCollection $records) {
                            $ids = $records->pluck('id')->all();
                            return app(\App\Services\ProductImportExportService::class)->exportCsv(
                                Product::whereIn('id', $ids)
                            );
                        }),
                ]),
            ])
            ->emptyStateHeading('No products found matching active filters')
            ->emptyStateDescription('Try switching your filter tabs, or create a new retail product or import via CSV.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->toolbarActions([
                \Filament\Actions\CreateAction::make()->label('+ Add Product'),
            ]);
    }

    public static function getSizeOptions(): array
    {
        return [
            'Footwear (Np / Shoes)' => [
                '35' => '35',
                '36' => '36',
                '37' => '37',
                '38' => '38',
                '39' => '39',
                '40' => '40',
                '41' => '41',
                '42' => '42',
                '43' => '43',
                '44' => '44',
                '45' => '45',
                '46' => '46',
            ],
            'Apparel (Tops & Streetwear)' => [
                'XS' => 'XS (Extra Small)',
                'S' => 'S (Small)',
                'M' => 'M (Medium)',
                'L' => 'L (Large)',
                'XL' => 'XL (Extra Large)',
                'XXL' => 'XXL (2X Large)',
                '3XL' => '3XL (3X Large)',
            ],
            'Pants & Waist' => [
                '28' => '28',
                '30' => '30',
                '32' => '32',
                '34' => '34',
                '36' => '36',
                '38' => '38',
                '40' => '40',
                '42' => '42',
            ],
            'Universal' => [
                'Standard' => 'Standard',
                'Free Size' => 'Free Size',
            ],
        ];
    }

    public static function getColorOptions(): array
    {
        return [
            'Black' => 'Black',
            'White' => 'White',
            'Brown' => 'Brown',
            'Blue' => 'Blue',
            'Red' => 'Red',
            'Grey' => 'Grey / Gray',
            'Green' => 'Green',
            'Navy' => 'Navy Blue',
            'Beige' => 'Beige',
            'Cream' => 'Cream / Ivory',
            'Coffee' => 'Coffee',
            'Tan' => 'Tan',
            'Olive' => 'Olive Green',
            'Maroon' => 'Maroon / Burgundy',
            'Khaki' => 'Khaki',
            'Orange' => 'Orange',
            'Yellow' => 'Yellow',
            'Pink' => 'Pink',
            'Multi' => 'Multi / Patterned',
            'Standard' => 'Standard',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
