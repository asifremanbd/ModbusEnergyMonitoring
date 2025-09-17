<?php

namespace App\Services;

use App\Models\Gateway;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class GatewayManagementService
{
    /**
     * Create a new gateway with validation.
     */
    public function createGateway(array $config): Gateway
    {
        $validatedConfig = $this->validateConfiguration($config);
        
        return Gateway::create($validatedConfig);
    }

    /**
     * Update an existing gateway with validation.
     */
    public function updateGateway(Gateway $gateway, array $config): Gateway
    {
        $validatedConfig = $this->validateConfiguration($config, $gateway->id);
        
        $gateway->update($validatedConfig);
        
        return $gateway->fresh();
    }

    /**
     * Delete a gateway and its related data.
     */
    public function deleteGateway(Gateway $gateway): bool
    {
        // Pause polling before deletion
        $this->pausePolling($gateway);
        
        return $gateway->delete();
    }

    /**
     * Validate gateway configuration.
     */
    public function validateConfiguration(array $config, ?int $excludeId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'unit_id' => 'required|integer|min:1|max:255',
            'poll_interval' => 'required|integer|min:1|max:3600',
            'is_active' => 'boolean',
        ];

        // Add unique constraint for IP/Port/Unit combination
        $uniqueRule = 'unique:gateways,ip_address,NULL,id,port,' . ($config['port'] ?? 'NULL') . ',unit_id,' . ($config['unit_id'] ?? 'NULL');
        if ($excludeId) {
            $uniqueRule = 'unique:gateways,ip_address,' . $excludeId . ',id,port,' . ($config['port'] ?? 'NULL') . ',unit_id,' . ($config['unit_id'] ?? 'NULL');
        }
        $rules['ip_address'] .= '|' . $uniqueRule;

        $validator = Validator::make($config, $rules, [
            'ip_address.unique' => 'A gateway with this IP address, port, and unit ID combination already exists.',
            'port.min' => 'Port must be between 1 and 65535.',
            'port.max' => 'Port must be between 1 and 65535.',
            'unit_id.min' => 'Unit ID must be between 1 and 255.',
            'unit_id.max' => 'Unit ID must be between 1 and 255.',
            'poll_interval.min' => 'Poll interval must be at least 1 second.',
            'poll_interval.max' => 'Poll interval cannot exceed 3600 seconds (1 hour).',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Pause polling for a gateway.
     */
    public function pausePolling(Gateway $gateway): void
    {
        $gateway->update(['is_active' => false]);
    }

    /**
     * Resume polling for a gateway.
     */
    public function resumePolling(Gateway $gateway): void
    {
        $gateway->update(['is_active' => true]);
    }

    /**
     * Update gateway health status after a poll attempt.
     */
    public function updateHealthStatus(Gateway $gateway, bool $success, ?Carbon $timestamp = null): void
    {
        $timestamp = $timestamp ?? now();
        
        if ($success) {
            $gateway->increment('success_count');
            $gateway->update(['last_seen_at' => $timestamp]);
        } else {
            $gateway->increment('failure_count');
        }

        // Check if gateway should be automatically disabled due to consecutive failures
        $this->checkCircuitBreaker($gateway);
    }

    /**
     * Reset gateway counters.
     */
    public function resetCounters(Gateway $gateway): void
    {
        $gateway->update([
            'success_count' => 0,
            'failure_count' => 0,
        ]);
    }

    /**
     * Get gateway status information.
     */
    public function getGatewayStatus(Gateway $gateway): array
    {
        return [
            'is_online' => $gateway->is_online,
            'success_rate' => $gateway->success_rate,
            'total_polls' => $gateway->success_count + $gateway->failure_count,
            'last_seen' => $gateway->last_seen_at,
            'consecutive_failures' => $this->getConsecutiveFailures($gateway),
            'health_status' => $this->determineHealthStatus($gateway),
        ];
    }

    /**
     * Get all gateways with their health status.
     */
    public function getGatewaysWithStatus(): array
    {
        return Gateway::all()->map(function ($gateway) {
            return array_merge($gateway->toArray(), [
                'status' => $this->getGatewayStatus($gateway)
            ]);
        })->toArray();
    }

    /**
     * Check circuit breaker pattern - disable gateway after too many failures.
     */
    protected function checkCircuitBreaker(Gateway $gateway): void
    {
        $consecutiveFailures = $this->getConsecutiveFailures($gateway);
        
        // Disable gateway after 10 consecutive failures
        if ($consecutiveFailures >= 10 && $gateway->is_active) {
            $this->pausePolling($gateway);
            
            // Log the automatic disabling
            \Log::warning("Gateway {$gateway->name} ({$gateway->ip_address}:{$gateway->port}) automatically disabled due to {$consecutiveFailures} consecutive failures.");
        }
    }

    /**
     * Get consecutive failure count (simplified - in production might use a more sophisticated approach).
     */
    protected function getConsecutiveFailures(Gateway $gateway): int
    {
        // For now, we'll use a simple heuristic based on success rate and recent activity
        $totalPolls = $gateway->success_count + $gateway->failure_count;
        
        if ($totalPolls === 0) {
            return 0;
        }

        // If success rate is very low and we haven't seen the gateway recently, assume consecutive failures
        if ($gateway->success_rate < 10 && !$gateway->is_online) {
            return min(10, $gateway->failure_count);
        }

        return 0;
    }

    /**
     * Determine overall health status.
     */
    protected function determineHealthStatus(Gateway $gateway): string
    {
        if (!$gateway->is_active) {
            return 'disabled';
        }

        if (!$gateway->is_online) {
            return 'offline';
        }

        $successRate = $gateway->success_rate;
        
        if ($successRate >= 95) {
            return 'healthy';
        } elseif ($successRate >= 80) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
}