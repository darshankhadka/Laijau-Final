<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Products';
    protected static ?string $navigationLabel = 'Categories';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Taxonomy & Details')
                    ->description('Organize your shoes, clothing, and accessories hierarchy.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                        TextInput::make('slug')
                            ->label('Slug / URL Key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL friendly path (e.g. "leather-shoes").'),

                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn($query, ?Category $record) => $record
                                    ? $query->where('id', '!=', $record->id)->whereNotIn('id', $record->getAllChildrenIds())
                                    : $query
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('★ None (Root / Top-Level Category)')
                            ->helperText('Nest under a parent category or leave empty for top-level navigation.'),

                        TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower sequence numbers appear first in catalogues.'),

                        Toggle::make('is_active')
                            ->label('Active for Storefront & POS')
                            ->helperText('When enabled, products under this category appear on the website and POS filters.')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Feature on Homepage')
                            ->helperText('Highlight this category in homepage hero grids and collection banners.')
                            ->default(false),
                    ]),

                Section::make('Banner Visual & Description')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Category Showcase Image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('category')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->helperText('Recommended size: 1200x675px or 16:9 banner format.')
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Category Description')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO & Search Optimization')
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('Meta Title')
                            ->maxLength(60)
                            ->placeholder('e.g. Handcrafted Leather Shoes in Nepal | Laijau')
                            ->helperText('Optimal title length: up to 60 characters.'),

                        Textarea::make('seo_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(160)
                            ->placeholder('Shop genuine footwear, clothing, and accessories online at best prices in Kathmandu...')
                            ->helperText('Optimal description length: up to 160 characters.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->circular(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Category $record) => '/' . $record->slug),

                TextColumn::make('parent.name')
                    ->label('Taxonomy')
                    ->badge()
                    ->color(fn($state) => $state ? 'gray' : 'success')
                    ->formatStateUsing(fn($state) => $state ? "↳ {$state}" : "★ Top Level")
                    ->sortable(),

                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Subcategories')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Direct Products')
                    ->badge()
                    ->color(fn(int $state) => $state > 0 ? 'success' : 'gray')
                    ->alignCenter(),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                ToggleColumn::make('is_featured')
                    ->label('Featured'),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Taxonomy Level')
                    ->options([
                        'top_level' => '★ Root / Top-Level Only',
                        'subcategories' => '↳ Subcategories Only',
                    ])
                    ->query(function ($query, array $data) {
                        if (($data['value'] ?? null) === 'top_level') {
                            $query->whereNull('parent_id');
                        } elseif (($data['value'] ?? null) === 'subcategories') {
                            $query->whereNotNull('parent_id');
                        }
                    }),

                TernaryFilter::make('is_active')
                    ->label('Catalogue Visibility')
                    ->boolean()
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),

                TernaryFilter::make('is_featured')
                    ->label('Homepage Feature')
                    ->boolean()
                    ->trueLabel('Featured Only')
                    ->falseLabel('Standard Only'),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                EditAction::make(),
                Action::make('view_storefront')
                    ->label('Storefront')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Category $record) => route('storefront.category', $record->slug))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Category $record) {
                        if ($record->products()->count() > 0) {
                            Notification::make()
                                ->title('Cannot Delete Category')
                                ->body("Category '{$record->name}' still has {$record->products()->count()} assigned product(s). Please reassign or remove them first.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                        if ($record->children()->count() > 0) {
                            Notification::make()
                                ->title('Cannot Delete Category')
                                ->body("Category '{$record->name}' has {$record->children()->count()} subcategory/subcategories. Please delete or reassign subcategories first.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if ($record->products()->count() > 0 || $record->children()->count() > 0) {
                                    Notification::make()
                                        ->title('Cannot Delete Selected Categories')
                                        ->body("Category '{$record->name}' still has assigned products or subcategories. Please reassign them first.")
                                        ->danger()
                                        ->send();
                                    $action->halt();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
