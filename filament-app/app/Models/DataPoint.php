<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway_id',
        'application',
        'unit',
        'load_type',
        'label',
        'modbus_function',
        'register_address',
        'register_count',
        'data_type',
        'byte_order',
        'scale_factor',
        'is_enabled',
    ];

    protected $casts = [
        'gateway_id' => 'integer',
        'modbus_function' => 'integer',
        'register_address' => 'integer',
        'register_count' => 'integer',
        'scale_factor' => 'float',
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the gateway that owns the data point.
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Get the readings for the data point.
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Scope to get only enabled data points.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to filter by application.
     */
    public function scopeByApplication($query, string $application)
    {
        return $query->where('application', $application);
    }

    /**
     * Get the latest reading for this data point.
     */
    public function getLatestReadingAttribute()
    {
        return $this->readings()->latest('read_at')->first();
    }

    /**
     * Get the register end address (start + count - 1).
     */
    public function getRegisterEndAddressAttribute(): int
    {
        return $this->register_address + $this->register_count - 1;
    }

    /**
     * Check if this is a Modbus input register (function 4).
     */
    public function getIsInputRegisterAttribute(): bool
    {
        return $this->modbus_function === 4;
    }

    /**
     * Check if this is a Modbus holding register (function 3).
     */
    public function getIsHoldingRegisterAttribute(): bool
    {
        return $this->modbus_function === 3;
    }

    /**
     * Convert raw register values to scaled value using this data point's configuration.
     */
    public function convertRawValue(array $registers): float|int
    {
        $conversionService = app(\App\Services\DataTypeConversionService::class);
        
        $rawValue = $conversionService->convertRawData(
            $registers, 
            $this->data_type, 
            $this->byte_order
        );
        
        return $conversionService->applyScaling($rawValue, $this->scale_factor);
    }

    /**
     * Get the required number of registers for this data point.
     */
    public function getRequiredRegisterCountAttribute(): int
    {
        $conversionService = app(\App\Services\DataTypeConversionService::class);
        return $conversionService->getRequiredRegisterCount($this->data_type);
    }
}
