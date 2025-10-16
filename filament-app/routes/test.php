<?php

use Illuminate\Support\Facades\Route;
use App\Models\Device;

Route::get('/test-device-registers/{device}', function ($device) {
    $deviceModel = Device::with(['gateway', 'registers'])->findOrFail($device);
    
    return response()->json([
        'device_id' => $deviceModel->id,
        'device_name' => $deviceModel->device_name,
        'gateway' => $deviceModel->gateway->name,
        'register_count' => $deviceModel->registers()->count(),
        'registers' => $deviceModel->registers->map(function ($register) {
            return [
                'id' => $register->id,
                'technical_label' => $register->technical_label,
                'register_address' => $register->register_address,
                'function' => $register->function,
                'enabled' => $register->enabled,
            ];
        })
    ]);
})->name('test.device.registers');