<?php

namespace App\Models;

use App\Services\NepalLocationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'carrier',
        'zone',
        'price_npr',
        'free_shipping_threshold_npr',
        'estimated_delivery',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price_npr' => 'decimal:2',
        'free_shipping_threshold_npr' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('active_shipping_methods');
        });

        static::deleted(function () {
            Cache::forget('active_shipping_methods');
        });
    }

    /**
     * Get single shipping rate calculation.
     */
    public static function getRateForCountry(string $countryCode = 'NP', float $subtotal = 0.0, string $currency = 'NPR', ?string $methodCode = null, ?string $district = null): ?array
    {
        if ($methodCode) {
            $dbMethod = self::where('code', $methodCode)->where('is_active', true)->first();
            if ($dbMethod) {
                $isFree = false;
                $cost = (float) ($dbMethod->price_npr ?? 100);

                if ($dbMethod->free_shipping_threshold_npr && $subtotal >= (float) $dbMethod->free_shipping_threshold_npr) {
                    $isFree = true;
                }

                return [
                    'id' => $dbMethod->id,
                    'code' => $dbMethod->code,
                    'name' => $dbMethod->name,
                    'carrier' => $dbMethod->carrier ?: 'Courier',
                    'cost' => $isFree ? 0.00 : $cost,
                    'is_free' => (bool) $isFree,
                    'available' => true,
                ];
            }
        }

        $methods = self::getAvailableMethodsForCountry($countryCode, $subtotal, $currency, $district);
        if (empty($methods)) {
            return [
                'available' => false,
                'error' => 'Shipping method not supported for this region.',
            ];
        }

        $rate = $methods[0];
        $rate['available'] = true;
        return $rate;
    }

    /**
     * Get all available shipping methods for Nepal with dynamic pricing and free delivery status.
     */
    public static function getAvailableMethodsForCountry(string $countryCode = 'NP', float $subtotal = 0.0, string $currency = 'NPR', ?string $district = null): array
    {
        $isValley = NepalLocationService::isKathmanduValley($district);

        // Fetch active methods from database matching region
        $targetZone = $isValley ? 'kathmandu_valley' : 'outside_valley';
        $dbMethods = self::where('is_active', true)
            ->where(function ($q) use ($targetZone) {
                $q->where('zone', $targetZone)
                  ->orWhere('zone', 'all_nepal')
                  ->orWhereNull('zone');
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        if ($dbMethods->isNotEmpty()) {
            return $dbMethods->map(function ($m) use ($subtotal, $isValley) {
                $threshold = (float) ($m->free_shipping_threshold_npr ?? 0.0);
                $isFree = $threshold > 0 && $subtotal >= $threshold;
                $cost = (float) ($m->price_npr ?? 100.00);

                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'carrier' => $m->carrier ?: 'Courier',
                    'base_cost' => $cost,
                    'cost' => $isFree ? 0.00 : $cost,
                    'free_threshold' => $threshold,
                    'is_free' => $isFree,
                    'estimated_delivery' => $m->estimated_delivery ?: ($isValley ? '1-2 business days' : '2-4 business days'),
                    'zone' => $m->zone ?: ($isValley ? 'kathmandu_valley' : 'outside_valley'),
                    'is_cod_eligible' => $isValley,
                    'available' => true,
                ];
            })->values()->toArray();
        }

        // Standard Fallback Delivery Definitions for Laijau Nepal
        if ($isValley) {
            $isFree = $subtotal >= 3000;
            return [
                [
                    'id' => 101,
                    'code' => 'inside_valley_standard',
                    'name' => 'Kathmandu Valley Standard Delivery',
                    'carrier' => 'Pathao / Local Rider',
                    'base_cost' => 100.00,
                    'cost' => $isFree ? 0.00 : 100.00,
                    'free_threshold' => 3000.00,
                    'is_free' => $isFree,
                    'estimated_delivery' => '1-2 business days',
                    'zone' => 'kathmandu_valley',
                    'is_cod_eligible' => true,
                    'available' => true,
                ],
                [
                    'id' => 102,
                    'code' => 'inside_valley_express',
                    'name' => 'Same-Day Express (Valley)',
                    'carrier' => 'Pathao Express',
                    'base_cost' => 200.00,
                    'cost' => 200.00,
                    'free_threshold' => 0.00,
                    'is_free' => false,
                    'estimated_delivery' => 'Same day (before 2 PM)',
                    'zone' => 'kathmandu_valley',
                    'is_cod_eligible' => true,
                    'available' => true,
                ],
            ];
        }

        // Outside Valley
        $isFreeOutside = $subtotal >= 4000;
        return [
            [
                'id' => 103,
                'code' => 'outside_valley_courier',
                'name' => 'Nationwide Courier (Outside Valley)',
                'carrier' => 'Nepal Can Move (NCM) / Pathao Parcel',
                'base_cost' => 150.00,
                'cost' => $isFreeOutside ? 0.00 : 150.00,
                'free_threshold' => 4000.00,
                'is_free' => $isFreeOutside,
                'estimated_delivery' => '2-4 business days',
                'zone' => 'outside_valley',
                'is_cod_eligible' => false,
                'available' => true,
            ],
        ];
    }
}
