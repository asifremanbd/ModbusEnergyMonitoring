<?php

namespace App\Models;

use App\Services\GatewayStatusService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
    public function readings(): HasManyThrough
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
     * Get the enhanced status using GatewayStatusService.
     */
    public function getEnhancedStatusAttribute(): string
    {
        return app(GatewayStatusService::class)->computeStatus($this);
    }

    /**
     * Get the recent error rate using GatewayStatusService.
     */
    public function getRecentErrorRateAttribute(): float
    {
        return app(GatewayStatusService::class)->getRecentErrorRate($this);
    }

    /**
     * Check if the gateway is online based on enhanced status logic.
     */
    public function getIsOnlineAttribute(): bool
    {
        $status = $this->enhanced_status;
        return $status === GatewayStatusService::STATUS_ONLINE;
    }
}
