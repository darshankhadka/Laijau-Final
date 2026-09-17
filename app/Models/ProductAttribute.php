<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $table = 'product_attributes';

    protected $fillable = [
        'type',
        'name',
        'code',
        'value',
        'category_group',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public static array $types = [
        'size' => 'Size (Apparel & Tops)',
        'shoe_size' => 'Shoe Size (Footwear)',
        'color' => 'Colorway / Palette',
        'material' => 'Fabric / Material',
        'fit' => 'Fit / Cut',
        'style' => 'Style / Pattern',
        'custom' => 'Custom Attribute',
    ];

    public static array $categoryGroups = [
        'Footwear & Shoes' => 'Footwear & Shoes',
        'Men\'s Apparel' => 'Men\'s Apparel',
        'Women\'s Apparel' => 'Women\'s Apparel',
        'Unisex & Streetwear' => 'Unisex & Streetwear',
        'Accessories & Belts' => 'Accessories & Belts',
        'Universal' => 'Universal',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductAttribute $attribute) {
            if (empty($attribute->code) && !empty($attribute->name)) {
                $prefix = match ($attribute->type) {
                    'size' => 'SZ',
                    'dimension' => 'DIM',
                    'color' => 'CLR',
                    'material' => 'MAT',
                    'pattern' => 'PAT',
                    default => 'ATTR',
                };
                $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $attribute->name), 0, 4));
                $attribute->code = "{$prefix}-{$slug}";
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSizes(Builder $query): Builder
    {
        return $query->where('type', 'size');
    }

    public function scopeDimensions(Builder $query): Builder
    {
        return $query->where('type', 'dimension');
    }

    public function scopeColors(Builder $query): Builder
    {
        return $query->where('type', 'color');
    }

    public function scopeMaterials(Builder $query): Builder
    {
        return $query->where('type', 'material');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::$types[$this->type] ?? ucfirst($this->type);
    }

    public function getColorHexAttribute(): ?string
    {
        if ($this->type === 'color' && !empty($this->value) && str_starts_with($this->value, '#')) {
            return $this->value;
        }
        return null;
    }
}
