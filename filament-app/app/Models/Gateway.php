<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'unit_id',
        'poll_interval',
        'is_active',
        'last_seen_at',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'poll_interval' => 'integer',
        'port' => 'integer',
        'unit_id' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    /**
     * Get the data points for the gateway.
     */
    public function dataPoints(): HasMany
    {
        return $this->hasMany(DataPoint::class);
    }

    /**
     * Get the readings through data points.
     */
    public function readings(): HasMany
    {
        return $this->hasManyThrough(Reading::class, DataPoint::class);
    }

    /**
     * Scope to get only active gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->success_count + $this->failure_count;
        return $total > 0 ? ($this->success_count / $total) * 100 : 0;
    }

    /**
     * Check if the gateway is online based on last seen timestamp.
     */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        
        // Consider online if seen within 2x poll interval + 30 seconds buffer
        $threshold = now()->subSeconds(($this->poll_interval * 2) + 30);
        return $this->last_seen_at->gt($threshold);
    }
}
