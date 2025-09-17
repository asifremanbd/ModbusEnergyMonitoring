<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;

class ReverseEngineerFloat extends Command
{
    protected $signature = 'reverse:float {gateway?} {--expected=853.908997}';
    protected $description = 'Reverse engineer how to get the expected kWh value from raw registers';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        $expectedValue = (float) $this->option('expected');
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        $this->info("Reverse engineering FLOAT32 conversion for register 1025");
        $this->info("Expected value: {$expectedValue} kWh");
        $this->info("Gateway: {$gateway->name} ({$gateway->ip_address}:{$gateway->port})");
        $this->newLine();

        try {
            // Create connection
            $connection = BinaryStreamConnection::getBuilder()
                ->setHost($gateway->ip_address)
                ->setPort($gateway->port)
                ->setConnectTimeoutSec(5)
                ->setReadTimeoutSec(5)
                ->build();

            $connection->connect();

            // Read register 1025 (2 registers for FLOAT32)
            $packet = new ReadHoldingRegistersRequest(1025, 2, $gateway->unit_id);
            $binaryData = $connection->sendAndReceive($packet);
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            $registers = $response->getData();

            $this->info("Current raw registers: [" . implode(', ', $registers) . "]");
            $this->info("Hex values: [0x" . sprintf('%04X', $registers[0]) . ", 0x" . sprintf('%04X', $registers[1]) . "]");
            $this->info("Binary: [" . sprintf('%016b', $registers[0]) . ", " . sprintf('%016b', $registers[1]) . "]");
            $this->newLine();

            // Try to reverse engineer the expected value
            $this->info("Trying to reverse engineer the conversion:");
            
            // Method 1: Maybe it's the IEEE 754 representation of the expected value
            $expectedBytes = pack('G', $expectedValue); // Pack expected value as big-endian float
            $expectedRegisters = unpack('n*', $expectedBytes); // Unpack as big-endian 16-bit values
            $this->line("1. If {$expectedValue} was stored as IEEE 754 FLOAT32:");
            $this->line("   Expected registers would be: [" . implode(', ', $expectedRegisters) . "]");
            $this->line("   Hex: [0x" . sprintf('%04X', $expectedRegisters[1]) . ", 0x" . sprintf('%04X', $expectedRegisters[2]) . "]");
            
            // Method 2: Try different interpretations of current registers
            $this->newLine();
            $this->line("2. Different interpretations of current registers:");
            
            // As combined integer with different scaling
            $combined1 = ($registers[0] << 16) | $registers[1];
            $combined2 = ($registers[1] << 16) | $registers[0];
            
            $this->line("   Combined (high,low): {$combined1}");
            $this->line("   Combined (low,high): {$combined2}");
            
            // Try different scale factors
            $scales = [1, 10, 100, 1000, 10000, 0.1, 0.01, 0.001, 0.0001];
            foreach ($scales as $scale) {
                $scaled1 = $combined1 * $scale;
                $scaled2 = $combined2 * $scale;
                
                if (abs($scaled1 - $expectedValue) < 1) {
                    $this->line("   → <fg=green>MATCH! Combined1 * {$scale} = {$scaled1} ≈ {$expectedValue}</>");
                }
                if (abs($scaled2 - $expectedValue) < 1) {
                    $this->line("   → <fg=green>MATCH! Combined2 * {$scale} = {$scaled2} ≈ {$expectedValue}</>");
                }
            }
            
            // Method 3: Try as separate values with math operations
            $this->newLine();
            $this->line("3. Mathematical combinations:");
            
            $val1 = $registers[0];
            $val2 = $registers[1];
            
            $combinations = [
                "val1 + val2" => $val1 + $val2,
                "val1 - val2" => $val1 - $val2,
                "val2 - val1" => $val2 - $val1,
                "val1 * val2" => $val1 * $val2,
                "val1 / val2" => $val2 != 0 ? $val1 / $val2 : 0,
                "val2 / val1" => $val1 != 0 ? $val2 / $val1 : 0,
                "val1 + val2 * 256" => $val1 + ($val2 * 256),
                "val2 + val1 * 256" => $val2 + ($val1 * 256),
            ];
            
            foreach ($combinations as $formula => $result) {
                foreach ($scales as $scale) {
                    $scaled = $result * $scale;
                    if (abs($scaled - $expectedValue) < 1) {
                        $this->line("   → <fg=green>MATCH! ({$formula}) * {$scale} = {$scaled} ≈ {$expectedValue}</>");
                    }
                }
            }
            
            // Method 4: Check if it's actually stored as a different data type
            $this->newLine();
            $this->line("4. Alternative data type interpretations:");
            
            // Try as BCD (Binary Coded Decimal)
            $bcd1 = $this->bcdToDecimal($registers[0]);
            $bcd2 = $this->bcdToDecimal($registers[1]);
            $this->line("   BCD interpretation: [{$bcd1}, {$bcd2}]");
            
            $bcdCombined = $bcd1 * 1000 + $bcd2; // Common BCD format
            foreach ($scales as $scale) {
                $scaled = $bcdCombined * $scale;
                if (abs($scaled - $expectedValue) < 1) {
                    $this->line("   → <fg=green>BCD MATCH! {$bcdCombined} * {$scale} = {$scaled} ≈ {$expectedValue}</>");
                }
            }

            $connection->close();

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function bcdToDecimal(int $bcd): int
    {
        $decimal = 0;
        $multiplier = 1;
        
        while ($bcd > 0) {
            $digit = $bcd & 0x0F;
            if ($digit > 9) return -1; // Invalid BCD
            $decimal += $digit * $multiplier;
            $multiplier *= 10;
            $bcd >>= 4;
        }
        
        return $decimal;
    }
}