<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;

class TestBothFunctions extends Command
{
    protected $signature = 'test:both-functions {gateway?}';
    protected $description = 'Test both Holding (3) and Input (4) registers for kWh values';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        $this->info("Testing both Holding (3) and Input (4) registers");
        $this->info("Gateway: {$gateway->name} ({$gateway->ip_address}:{$gateway->port})");
        $this->newLine();

        // Test registers from your Teltonika configuration
        $registers = [
            ['name' => 'Meter_1_Total_kWh', 'address' => 1025],
            ['name' => 'Meter_2_Total_kWh', 'address' => 1033],
            ['name' => 'Meter_3_Total_kWh', 'address' => 1035],
            ['name' => 'Meter_4_Total_kWh', 'address' => 1037],
        ];

        try {
            // Create connection
            $connection = BinaryStreamConnection::getBuilder()
                ->setHost($gateway->ip_address)
                ->setPort($gateway->port)
                ->setConnectTimeoutSec(5)
                ->setReadTimeoutSec(5)
                ->build();

            $connection->connect();
            $this->info("✓ Connected successfully");
            $this->newLine();

            foreach ($registers as $register) {
                $this->info("Testing {$register['name']} (Register {$register['address']}):");
                
                // Test Holding Registers (Function 3)
                $this->line("  Function 3 (Holding Registers):");
                try {
                    $packet = new ReadHoldingRegistersRequest($register['address'], 2, $gateway->unit_id);
                    $binaryData = $connection->sendAndReceive($packet);
                    $response = ResponseFactory::parseResponseOrThrow($binaryData);
                    $registers3 = $response->getData();
                    
                    $this->line("    Raw: [" . implode(', ', $registers3) . "] (0x" . sprintf('%04X', $registers3[0]) . ", 0x" . sprintf('%04X', $registers3[1]) . ")");
                    
                    // Convert to FLOAT32 using different byte orders
                    $float1 = $this->parseFloat32($registers3, 'word_swapped');
                    $float2 = $this->parseFloat32($registers3, 'big_endian');
                    
                    $this->line("    FLOAT32 (word_swapped): {$float1}");
                    $this->line("    FLOAT32 (big_endian): {$float2}");
                    
                    // Check if any value is reasonable
                    if ($this->isReasonableKwh($float1)) {
                        $this->line("    → <fg=green>Word-swapped looks good: {$float1} kWh</>");
                    }
                    if ($this->isReasonableKwh($float2)) {
                        $this->line("    → <fg=green>Big-endian looks good: {$float2} kWh</>");
                    }
                    
                } catch (\Exception $e) {
                    $this->line("    <fg=red>Error: " . $e->getMessage() . "</>");
                }
                
                // Test Input Registers (Function 4)
                $this->line("  Function 4 (Input Registers):");
                try {
                    $packet = new ReadInputRegistersRequest($register['address'], 2, $gateway->unit_id);
                    $binaryData = $connection->sendAndReceive($packet);
                    $response = ResponseFactory::parseResponseOrThrow($binaryData);
                    $registers4 = $response->getData();
                    
                    $this->line("    Raw: [" . implode(', ', $registers4) . "] (0x" . sprintf('%04X', $registers4[0]) . ", 0x" . sprintf('%04X', $registers4[1]) . ")");
                    
                    // Convert to FLOAT32 using different byte orders
                    $float1 = $this->parseFloat32($registers4, 'word_swapped');
                    $float2 = $this->parseFloat32($registers4, 'big_endian');
                    
                    $this->line("    FLOAT32 (word_swapped): {$float1}");
                    $this->line("    FLOAT32 (big_endian): {$float2}");
                    
                    // Check if any value is reasonable
                    if ($this->isReasonableKwh($float1)) {
                        $this->line("    → <fg=green>Word-swapped looks good: {$float1} kWh</>");
                    }
                    if ($this->isReasonableKwh($float2)) {
                        $this->line("    → <fg=green>Big-endian looks good: {$float2} kWh</>");
                    }
                    
                } catch (\Exception $e) {
                    $this->line("    <fg=red>Error: " . $e->getMessage() . "</>");
                }
                
                $this->newLine();
            }

            $connection->close();

        } catch (\Exception $e) {
            $this->error("Connection error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function parseFloat32(array $registers, string $byteOrder): float
    {
        // Pack registers into binary data based on byte order
        $binaryData = match ($byteOrder) {
            'word_swapped' => pack('n*', $registers[0], $registers[1]), // Big-endian 16-bit values
            'big_endian' => pack('n*', $registers[1], $registers[0]),   // Swap register order
            'little_endian' => pack('v*', $registers[0], $registers[1]), // Little-endian 16-bit values
            default => pack('n*', $registers[0], $registers[1])
        };
        
        // Unpack as IEEE 754 float
        $result = unpack('G', $binaryData); // Big-endian float
        return $result ? $result[1] : 0.0;
    }

    private function isReasonableKwh(float $value): bool
    {
        return $value > 0 && $value < 100000 && !is_infinite($value) && !is_nan($value) && $value > 1e-10;
    }
}