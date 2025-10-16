<?php

namespace App\Services;

use App\Models\DataPoint;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SchedulingService
{
    /**
     * Check if a control device should be active based on its schedule
     */
    public function shouldDeviceBeActive(DataPoint $dataPoint): bool
    {
        // If scheduling is not enabled, device follows manual control
        if (!$dataPoint->is_schedulable || !$dataPoint->schedule_enabled) {
            return $dataPoint->is_enabled;
        }

        // If no schedule is configured, default to enabled
        if (empty($dataPoint->schedule_days) || !$dataPoint->schedule_start_time || !$dataPoint->schedule_end_time) {
            return $dataPoint->is_enabled;
        }

        $now = Carbon::now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');

        // Check if today is in the scheduled days
        if (!in_array($currentDay, $dataPoint->schedule_days)) {
            return false;
        }

        // Check if current time is within the scheduled time range
        $startTime = is_string($dataPoint->schedule_start_time) 
            ? $dataPoint->schedule_start_time 
            : Carbon::parse($dataPoint->schedule_start_time)->format('H:i');
        $endTime = is_string($dataPoint->schedule_end_time) 
            ? $dataPoint->schedule_end_time 
            : Carbon::parse($dataPoint->schedule_end_time)->format('H:i');

        // Handle overnight schedules (e.g., 22:00 to 06:00)
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }

        // Normal day schedule
        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Get all schedulable devices that need state changes
     */
    public function getDevicesNeedingStateChange(): array
    {
        $devices = DataPoint::where('device_type', 'control')
            ->where('is_schedulable', true)
            ->where('schedule_enabled', true)
            ->whereNotNull('schedule_days')
            ->whereNotNull('schedule_start_time')
            ->whereNotNull('schedule_end_time')
            ->with('gateway')
            ->get();

        $changes = [];

        foreach ($devices as $device) {
            $shouldBeActive = $this->shouldDeviceBeActive($device);
            
            // Only include devices that need a state change
            if ($device->is_enabled !== $shouldBeActive) {
                $changes[] = [
                    'device' => $device,
                    'current_state' => $device->is_enabled,
                    'target_state' => $shouldBeActive,
                    'reason' => $shouldBeActive ? 'schedule_start' : 'schedule_end',
                ];
            }
        }

        return $changes;
    }

    /**
     * Apply scheduled state changes to devices
     */
    public function applyScheduledChanges(): array
    {
        $changes = $this->getDevicesNeedingStateChange();
        $results = [];

        foreach ($changes as $change) {
            $device = $change['device'];
            $targetState = $change['target_state'];

            try {
                // Update the device state
                $device->update(['is_enabled' => $targetState]);

                // If the device should be active, trigger the control action
                if ($targetState && $device->gateway->is_active) {
                    $this->triggerControlAction($device, true);
                } elseif (!$targetState) {
                    $this->triggerControlAction($device, false);
                }

                $results[] = [
                    'device_id' => $device->id,
                    'device_label' => $device->display_label,
                    'success' => true,
                    'action' => $targetState ? 'enabled' : 'disabled',
                    'reason' => $change['reason'],
                ];

                Log::info("Scheduled control applied", [
                    'device_id' => $device->id,
                    'device_label' => $device->display_label,
                    'action' => $targetState ? 'enabled' : 'disabled',
                    'reason' => $change['reason'],
                ]);

            } catch (\Exception $e) {
                $results[] = [
                    'device_id' => $device->id,
                    'device_label' => $device->display_label,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'reason' => $change['reason'],
                ];

                Log::error("Failed to apply scheduled control", [
                    'device_id' => $device->id,
                    'device_label' => $device->display_label,
                    'error' => $e->getMessage(),
                    'reason' => $change['reason'],
                ]);
            }
        }

        return $results;
    }

    /**
     * Trigger the actual control action via Modbus
     */
    private function triggerControlAction(DataPoint $device, bool $turnOn): void
    {
        // This would integrate with your existing Modbus control service
        // For now, we'll just log the action
        $value = $turnOn ? $device->on_value : $device->off_value;
        
        if ($device->invert) {
            $value = $turnOn ? $device->off_value : $device->on_value;
        }

        Log::info("Control action triggered", [
            'device_id' => $device->id,
            'device_label' => $device->display_label,
            'register' => $device->write_register,
            'function' => $device->write_function,
            'value' => $value,
            'action' => $turnOn ? 'turn_on' : 'turn_off',
        ]);

        // TODO: Integrate with ModbusPollService or create ModbusControlService
        // to actually send the control commands to the device
    }

    /**
     * Get schedule summary for a device
     */
    public function getScheduleSummary(DataPoint $device): array
    {
        if (!$device->is_schedulable || !$device->schedule_enabled) {
            return [
                'status' => 'disabled',
                'message' => 'Scheduling not enabled',
            ];
        }

        if (empty($device->schedule_days)) {
            return [
                'status' => 'incomplete',
                'message' => 'No days selected',
            ];
        }

        $now = Carbon::now();
        $shouldBeActive = $this->shouldDeviceBeActive($device);
        
        return [
            'status' => 'active',
            'currently_scheduled' => $shouldBeActive,
            'days' => $device->schedule_days,
            'start_time' => $device->schedule_start_time,
            'end_time' => $device->schedule_end_time,
            'next_change' => $this->getNextScheduleChange($device),
        ];
    }

    /**
     * Calculate when the next schedule change will occur
     */
    private function getNextScheduleChange(DataPoint $device): ?Carbon
    {
        if (!$device->schedule_days || !$device->schedule_start_time || !$device->schedule_end_time) {
            return null;
        }

        $now = Carbon::now();
        $currentDay = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        // Check today's schedule first
        if (in_array($currentDay, $device->schedule_days)) {
            $startTime = is_string($device->schedule_start_time) 
                ? $device->schedule_start_time 
                : Carbon::parse($device->schedule_start_time)->format('H:i');
            $endTime = is_string($device->schedule_end_time) 
                ? $device->schedule_end_time 
                : Carbon::parse($device->schedule_end_time)->format('H:i');

            // If we haven't reached start time today
            if ($currentTime < $startTime) {
                return Carbon::today()->setTimeFromTimeString($startTime);
            }

            // If we're between start and end time, next change is end time
            if ($currentTime >= $startTime && $currentTime < $endTime) {
                return Carbon::today()->setTimeFromTimeString($endTime);
            }
        }

        // Look for the next scheduled day
        for ($i = 1; $i <= 7; $i++) {
            $checkDate = $now->copy()->addDays($i);
            $checkDay = strtolower($checkDate->format('l'));

            if (in_array($checkDay, $device->schedule_days)) {
                $startTimeStr = is_string($device->schedule_start_time) 
                    ? $device->schedule_start_time 
                    : Carbon::parse($device->schedule_start_time)->format('H:i');
                return $checkDate->setTimeFromTimeString($startTimeStr);
            }
        }

        return null;
    }
}