<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Services\InventoryBroadcastService;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'alert_type',
        'threshold_value',
        'current_value',
        'message',
        'is_resolved',
        'resolved_at',
        'resolved_by'
    ];

    protected $casts = [
        'threshold_value' => 'decimal:3',
        'current_value' => 'decimal:3',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::created(function ($alert) {
            $broadcastService = app(InventoryBroadcastService::class);
            $broadcastService->broadcastStockAlert($alert);
        });

        static::updated(function ($alert) {
            // Broadcast when alert is resolved/unresolved
            if ($alert->isDirty('is_resolved')) {
                $broadcastService = app(InventoryBroadcastService::class);
                $broadcastService->broadcastStockAlert($alert);
            }
        });
    }

    // Alert types constants
    const ALERT_TYPE_LOW_STOCK = 'low_stock';
    const ALERT_TYPE_OUT_OF_STOCK = 'out_of_stock';
    const ALERT_TYPE_EXPIRY_WARNING = 'expiry_warning';
    const ALERT_TYPE_EXPIRY_CRITICAL = 'expiry_critical';
    const ALERT_TYPE_OVERSTOCK = 'overstock';

    const ALERT_TYPES = [
        self::ALERT_TYPE_LOW_STOCK,
        self::ALERT_TYPE_OUT_OF_STOCK,
        self::ALERT_TYPE_EXPIRY_WARNING,
        self::ALERT_TYPE_EXPIRY_CRITICAL,
        self::ALERT_TYPE_OVERSTOCK
    ];

    // Relationships
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    public function scopeByMaterial($query, $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('alert_type', [
            self::ALERT_TYPE_OUT_OF_STOCK,
            self::ALERT_TYPE_EXPIRY_CRITICAL
        ]);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors & Mutators
    public function getIsCriticalAttribute()
    {
        return in_array($this->alert_type, [
            self::ALERT_TYPE_OUT_OF_STOCK,
            self::ALERT_TYPE_EXPIRY_CRITICAL
        ]);
    }

    public function getPriorityAttribute()
    {
        $priorities = [
            self::ALERT_TYPE_OUT_OF_STOCK => 5,
            self::ALERT_TYPE_EXPIRY_CRITICAL => 4,
            self::ALERT_TYPE_LOW_STOCK => 3,
            self::ALERT_TYPE_EXPIRY_WARNING => 2,
            self::ALERT_TYPE_OVERSTOCK => 1
        ];

        return $priorities[$this->alert_type] ?? 1;
    }

    public function getFormattedMessageAttribute()
    {
        return $this->message;
    }

    public function getAgeInHoursAttribute()
    {
        return $this->created_at->diffInHours(now());
    }

    // Methods
    public function resolve($userId = null)
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? auth()->id()
        ]);

        return $this;
    }

    public function unresolve()
    {
        $this->update([
            'is_resolved' => false,
            'resolved_at' => null,
            'resolved_by' => null
        ]);

        return $this;
    }

    public function isExpired()
    {
        return in_array($this->alert_type, [
            self::ALERT_TYPE_EXPIRY_WARNING,
            self::ALERT_TYPE_EXPIRY_CRITICAL
        ]);
    }

    public function isStockRelated()
    {
        return in_array($this->alert_type, [
            self::ALERT_TYPE_LOW_STOCK,
            self::ALERT_TYPE_OUT_OF_STOCK,
            self::ALERT_TYPE_OVERSTOCK
        ]);
    }

    // Static methods for creating alerts
    public static function createLowStockAlert(Material $material)
    {
        $existingAlert = static::where('material_id', $material->id)
            ->where('alert_type', self::ALERT_TYPE_LOW_STOCK)
            ->unresolved()
            ->first();

        if ($existingAlert) {
            // Update existing alert with current values
            $existingAlert->update([
                'threshold_value' => $material->minimum_stock_level,
                'current_value' => $material->quantity,
                'message' => "Low stock alert: {$material->name} is below minimum level ({$material->quantity} < {$material->minimum_stock_level})"
            ]);
            return $existingAlert;
        }

        return static::create([
            'material_id' => $material->id,
            'alert_type' => self::ALERT_TYPE_LOW_STOCK,
            'threshold_value' => $material->minimum_stock_level,
            'current_value' => $material->quantity,
            'message' => "Low stock alert: {$material->name} is below minimum level ({$material->quantity} < {$material->minimum_stock_level})"
        ]);
    }

    public static function createOutOfStockAlert(Material $material)
    {
        $existingAlert = static::where('material_id', $material->id)
            ->where('alert_type', self::ALERT_TYPE_OUT_OF_STOCK)
            ->unresolved()
            ->first();

        if ($existingAlert) {
            $existingAlert->update([
                'current_value' => $material->quantity,
                'message' => "Out of stock: {$material->name} is completely out of stock"
            ]);
            return $existingAlert;
        }

        return static::create([
            'material_id' => $material->id,
            'alert_type' => self::ALERT_TYPE_OUT_OF_STOCK,
            'threshold_value' => 0,
            'current_value' => $material->quantity,
            'message' => "Out of stock: {$material->name} is completely out of stock"
        ]);
    }

    public static function createOverstockAlert(Material $material)
    {
        $existingAlert = static::where('material_id', $material->id)
            ->where('alert_type', self::ALERT_TYPE_OVERSTOCK)
            ->unresolved()
            ->first();

        if ($existingAlert) {
            $existingAlert->update([
                'threshold_value' => $material->maximum_stock_level,
                'current_value' => $material->quantity,
                'message' => "Overstock alert: {$material->name} exceeds maximum level ({$material->quantity} > {$material->maximum_stock_level})"
            ]);
            return $existingAlert;
        }

        return static::create([
            'material_id' => $material->id,
            'alert_type' => self::ALERT_TYPE_OVERSTOCK,
            'threshold_value' => $material->maximum_stock_level,
            'current_value' => $material->quantity,
            'message' => "Overstock alert: {$material->name} exceeds maximum level ({$material->quantity} > {$material->maximum_stock_level})"
        ]);
    }

    public static function createExpiryWarningAlert(StockBatch $batch)
    {
        $existingAlert = static::where('material_id', $batch->material_id)
            ->where('alert_type', self::ALERT_TYPE_EXPIRY_WARNING)
            ->unresolved()
            ->first();

        $daysUntilExpiry = $batch->expiry_date->diffInDays(now());

        if ($existingAlert) {
            $existingAlert->update([
                'threshold_value' => 7, // Warning threshold in days
                'current_value' => $daysUntilExpiry,
                'message' => "Expiry warning: {$batch->material->name} batch {$batch->batch_number} expires in {$daysUntilExpiry} days"
            ]);
            return $existingAlert;
        }

        return static::create([
            'material_id' => $batch->material_id,
            'alert_type' => self::ALERT_TYPE_EXPIRY_WARNING,
            'threshold_value' => 7,
            'current_value' => $daysUntilExpiry,
            'message' => "Expiry warning: {$batch->material->name} batch {$batch->batch_number} expires in {$daysUntilExpiry} days"
        ]);
    }

    public static function createExpiryCriticalAlert(StockBatch $batch)
    {
        $existingAlert = static::where('material_id', $batch->material_id)
            ->where('alert_type', self::ALERT_TYPE_EXPIRY_CRITICAL)
            ->unresolved()
            ->first();

        $daysUntilExpiry = $batch->expiry_date->diffInDays(now());

        if ($existingAlert) {
            $existingAlert->update([
                'threshold_value' => 2, // Critical threshold in days
                'current_value' => $daysUntilExpiry,
                'message' => "CRITICAL: {$batch->material->name} batch {$batch->batch_number} expires in {$daysUntilExpiry} days"
            ]);
            return $existingAlert;
        }

        return static::create([
            'material_id' => $batch->material_id,
            'alert_type' => self::ALERT_TYPE_EXPIRY_CRITICAL,
            'threshold_value' => 2,
            'current_value' => $daysUntilExpiry,
            'message' => "CRITICAL: {$batch->material->name} batch {$batch->batch_number} expires in {$daysUntilExpiry} days"
        ]);
    }
}
