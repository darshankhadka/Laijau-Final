<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CollectionResource\Pages;
use App\Models\Collection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Str;

class CollectionResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = Collection::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Commerce';
    protected static ?string $navigationLabel = 'Collections';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Section::make('Collection Overview')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_published')
                            ->label('Published for Customers')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Feature on Homepage')
                            ->default(false),
                    ]),

                Section::make('Editorial Showcase Pieces')
                    ->schema([
                        Select::make('products')
                            ->label('Assigned Handcrafted Pieces')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Hero Imagery & Narrative')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Showcase Hero Image')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('public')
                            ->directory('collections')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:10')
                            ->columnSpanFull(),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO Metadata')
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('Meta Title')
                            ->maxLength(60),
                        Textarea::make('seo_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(160),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->disk('public'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Assigned Pieces')
                    ->badge(),
                ToggleColumn::make('is_published')->label('Published'),
                ToggleColumn::make('is_featured')->label('Featured'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}
