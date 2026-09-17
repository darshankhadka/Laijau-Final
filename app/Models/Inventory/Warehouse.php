<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'inventory_warehouses';

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'postal_code',
        'city',
        'country',
        'manager_name',
        'contact_email',
        'contact_phone',
        'is_default',
        'allow_sales',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'allow_sales' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'warehouse_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'warehouse_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'source_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'destination_warehouse_id');
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class, 'warehouse_id');
    }

    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->first()
            ?? self::where('is_active', true)->first();
    }

    public static function getShowroom(): ?self
    {
        return self::where('code', \App\Services\Inventory\InventoryService::SHOWROOM_WH_CODE)->first()
            ?? self::where('code', 'STORE-KTM-01')->first()
            ?? self::where('type', 'showroom_pos')->first()
            ?? self::getDefault();
    }
}
