<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_point_id',
        'raw_value',
        'scaled_value',
        'quality',
        'read_at',
    ];

    protected $casts = [
        'data_point_id' => 'integer',
        'scaled_value' => 'float',
        'read_at' => 'datetime',
    ];

    /**
     * Get the data point that owns the reading.
     */
    public function dataPoint(): BelongsTo
    {
        return $this->belongsTo(DataPoint::class);
    }

    /**
     * Get the gateway through the data point.
     */
    public function gateway(): BelongsTo
    {
        return $this->dataPoint->gateway();
    }

    /**
     * Scope to get readings with good quality.
     */
    public function scopeGoodQuality($query)
    {
        return $query->where('quality', 'good');
    }

    /**
     * Scope to get recent readings within specified minutes.
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('read_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope to get readings for a specific data point.
     */
    public function scopeForDataPoint($query, int $dataPointId)
    {
        return $query->where('data_point_id', $dataPointId);
    }

    /**
     * Check if the reading quality is good.
     */
    public function getIsGoodQualityAttribute(): bool
    {
        return $this->quality === 'good';
    }

    /**
     * Check if the reading is recent (within last poll interval).
     */
    public function getIsRecentAttribute(): bool
    {
        $gateway = $this->dataPoint->gateway;
        $threshold = now()->subSeconds($gateway->poll_interval + 30); // Add 30s buffer
        return $this->read_at->gt($threshold);
    }

    /**
     * Get a formatted display value with units if available.
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->scaled_value === null) {
            return 'N/A';
        }
        
        return number_format($this->scaled_value, 2);
    }
}
