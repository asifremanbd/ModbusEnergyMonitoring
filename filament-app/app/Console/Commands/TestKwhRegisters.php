<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ModbusPollService;
use App\Models\Gateway;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Types;
use Exception;

class TestKwhRegisters extends Command
{
    protected $signature = 'test:kwh-registers {gateway?}';
    protected $description = 'Test all 4 Total kWh registers for a gateway';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            $this->info("Available gateways:");
            Gateway::all()->each(function ($gw) {
                $this->line("  - {$gw->name} ({$gw->ip_address}:{$gw->port})");
            });
            return 1;
        }

        $this->info("Testing Total kWh registers for gateway: {$gateway->name}");
        $this->info("Connection: {$gateway->ip_address}:{$gateway->port}, Unit ID: {$gateway->unit_id}");
        $this->newLine();

        // Define the 4 kWh registers based on your screenshot
        $kwhRegisters = [
            ['name' => 'Meter_1_Total_kWh', 'register' => 1025],
            ['name' => 'Meter_2_Total_kWh', 'register' => 1033],
            ['name' => 'Meter_3_Total_kWh', 'register' => 1035],
            ['name' => 'Meter_4_Total_kWh', 'register' => 1037],
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

            // Test each register
            foreach ($kwhRegisters as $register) {
                $this->testRegister($connection, $gateway, $register['name'], $register['register']);
            }

            $connection->close();
            $this->newLine();
            $this->info("✓ All tests completed");

        } catch (Exception $e) {
            $this->error("Connection failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function testRegister(BinaryStreamConnection $connection, Gateway $gateway, string $name, int $registerAddress)
    {
        try {
            // First try reading 2 registers for FLOAT32
            $packet = new ReadHoldingRegistersRequest($registerAddress, 2, $gateway->unit_id);
            $binaryData = $connection->sendAndReceive($packet);
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            $registers = $response->getData();

            if (count($registers) < 2) {
                $this->error("  ✗ {$name} (Reg {$registerAddress}): Insufficient data received - got " . count($registers) . " registers");
                return;
            }

            $this->info("  ✓ {$name} (Reg {$registerAddress}):");
            $this->line("    Raw registers: [{$registers[0]}, {$registers[1]}] (0x" . sprintf('%04X', $registers[0]) . ", 0x" . sprintf('%04X', $registers[1]) . ")");
            
            // Try different interpretations
            $interpretations = [];
            
            // 1. Manual FLOAT32 conversion (since library has issues)
            // Word-swapped (registers[0] = high word, registers[1] = low word)
            $bytes1 = pack('n*', $registers[0], $registers[1]); // Big-endian 16-bit values
            $float1 = unpack('G', $bytes1)[1]; // Big-endian float
            $interpretations['FLOAT32 (word-swapped)'] = $float1;
            
            // Big-endian (registers[1] = high word, registers[0] = low word)  
            $bytes2 = pack('n*', $registers[1], $registers[0]); // Big-endian 16-bit values
            $float2 = unpack('G', $bytes2)[1]; // Big-endian float
            $interpretations['FLOAT32 (big-endian)'] = $float2;
            
            // Little-endian interpretation
            $bytes3 = pack('v*', $registers[0], $registers[1]); // Little-endian 16-bit values
            $float3 = unpack('g', $bytes3)[1]; // Little-endian float
            $interpretations['FLOAT32 (little-endian)'] = $float3;
            
            // 2. Try as two separate UINT16 values
            $interpretations['UINT16[0]'] = $registers[0];
            $interpretations['UINT16[1]'] = $registers[1];
            
            // 3. Try as INT32
            try {
                $int32_1 = Types::parseInt32($registers[0], $registers[1]);
                $interpretations['INT32 (word-swapped)'] = $int32_1;
            } catch (Exception $e) {
                $interpretations['INT32 (word-swapped)'] = "Error: " . $e->getMessage();
            }
            
            try {
                $int32_2 = Types::parseInt32($registers[1], $registers[0]);
                $interpretations['INT32 (big-endian)'] = $int32_2;
            } catch (Exception $e) {
                $interpretations['INT32 (big-endian)'] = "Error: " . $e->getMessage();
            }
            
            // 4. Try as UINT32
            try {
                $uint32_1 = Types::parseUInt32($registers[0], $registers[1]);
                $interpretations['UINT32 (word-swapped)'] = $uint32_1;
            } catch (Exception $e) {
                $interpretations['UINT32 (word-swapped)'] = "Error: " . $e->getMessage();
            }
            
            try {
                $uint32_2 = Types::parseUInt32($registers[1], $registers[0]);
                $interpretations['UINT32 (big-endian)'] = $uint32_2;
            } catch (Exception $e) {
                $interpretations['UINT32 (big-endian)'] = "Error: " . $e->getMessage();
            }
            
            // Display all interpretations
            foreach ($interpretations as $type => $value) {
                if (is_string($value) && str_starts_with($value, 'Error:')) {
                    $this->line("    {$type}: <fg=red>{$value}</>");
                } else {
                    $this->line("    {$type}: {$value}");
                }
            }
            
            // Add practical interpretations
            $this->line("    <fg=yellow>Practical interpretations:</>");
            $this->line("    Combined as decimal: " . ($registers[0] * 256 + $registers[1]) . " (if scaled)");
            $this->line("    Combined as decimal: " . ($registers[1] * 256 + $registers[0]) . " (if scaled, reversed)");
            $this->line("    As separate values: {$registers[0]} and {$registers[1]}");
            
            // Check if values look reasonable for kWh
            $combined1 = $registers[0] * 256 + $registers[1];
            $combined2 = $registers[1] * 256 + $registers[0];
            
            if ($combined1 > 0 && $combined1 < 1000000) {
                $this->line("    → Possible kWh value: " . ($combined1 / 1000) . " kWh (if scaled by 1000)");
                $this->line("    → Possible kWh value: " . ($combined1 / 100) . " kWh (if scaled by 100)");
                $this->line("    → Possible kWh value: " . ($combined1 / 10) . " kWh (if scaled by 10)");
            }
            
            $this->newLine();

        } catch (Exception $e) {
            $this->error("  ✗ {$name} (Reg {$registerAddress}): " . $e->getMessage());
            
            // Try to get more diagnostic info
            if (str_contains($e->getMessage(), 'Illegal data address')) {
                $this->line("    → Register {$registerAddress} may not exist or be accessible");
            } elseif (str_contains($e->getMessage(), 'not enough input')) {
                $this->line("    → Incomplete data received - connection or protocol issue");
            }
            
            $this->newLine();
        }
    }
}