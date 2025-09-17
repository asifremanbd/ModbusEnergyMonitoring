<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;

class DebugFloat32 extends Command
{
    protected $signature = 'debug:float32 {gateway?}';
    protected $description = 'Debug FLOAT32 conversion for register 1025';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        $this->info("Debugging FLOAT32 conversion for register 1025");
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

            // Read 4 registers to see the full data layout
            $packet = new ReadHoldingRegistersRequest(1025, 4, $gateway->unit_id);
            $binaryData = $connection->sendAndReceive($packet);
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            $registers = $response->getData();

            $this->info("Raw registers: [" . implode(', ', $registers) . "]");
            $hexValues = array_map(fn($reg) => sprintf('0x%04X', $reg), $registers);
            $this->info("Hex values: [" . implode(', ', $hexValues) . "]");
            $this->newLine();

            // Try all possible register pairs as FLOAT32
            $this->info("Testing all register pairs as FLOAT32:");
            $this->newLine();
            
            $reasonable = [];
            
            // Test each consecutive pair of registers
            for ($i = 0; $i < count($registers) - 1; $i++) {
                $reg1 = $registers[$i];
                $reg2 = $registers[$i + 1];
                
                $nextIndex = $i + 1;
                $this->info("Registers [{$i}] and [{$nextIndex}]: [{$reg1}, {$reg2}] (0x" . sprintf('%04X', $reg1) . ", 0x" . sprintf('%04X', $reg2) . ")");
                
                // Try different byte orders for this pair
                $interpretations = [];
                
                // Method 1: Word-swapped (reg1 high, reg2 low)
                try {
                    $bytes = pack('n*', $reg1, $reg2);
                    $float = unpack('G', $bytes)[1];
                    $interpretations['word_swapped'] = $float;
                } catch (\Exception $e) {
                    $interpretations['word_swapped'] = 'Error';
                }
                
                // Method 2: Big-endian (reg2 high, reg1 low)
                try {
                    $bytes = pack('n*', $reg2, $reg1);
                    $float = unpack('G', $bytes)[1];
                    $interpretations['big_endian'] = $float;
                } catch (\Exception $e) {
                    $interpretations['big_endian'] = 'Error';
                }
                
                // Method 3: Little-endian
                try {
                    $bytes = pack('v*', $reg1, $reg2);
                    $float = unpack('g', $bytes)[1];
                    $interpretations['little_endian'] = $float;
                } catch (\Exception $e) {
                    $interpretations['little_endian'] = 'Error';
                }
                
                // Method 4: Little-endian swapped
                try {
                    $bytes = pack('v*', $reg2, $reg1);
                    $float = unpack('g', $bytes)[1];
                    $interpretations['little_endian_swapped'] = $float;
                } catch (\Exception $e) {
                    $interpretations['little_endian_swapped'] = 'Error';
                }
                
                foreach ($interpretations as $method => $value) {
                    if ($value !== 'Error') {
                        $this->line("  {$method}: {$value}");
                        
                        // Check if this looks like a reasonable kWh value (like 853.908997)
                        if ($value > 100 && $value < 10000 && !is_infinite($value) && !is_nan($value)) {
                            $reasonable[] = "Registers [{$i}],[{$nextIndex}] with {$method}: {$value}";
                        }
                    } else {
                        $this->line("  {$method}: <fg=red>Error</>");
                    }
                }
                
                $this->newLine();
            }
            
            if (!empty($reasonable)) {
                $this->info("<fg=green>🎯 REASONABLE kWh VALUES FOUND:</>");
                foreach ($reasonable as $val) {
                    $this->line("  ✓ {$val}");
                }
            } else {
                $this->warn("No reasonable kWh values found in any register pairs.");
            }

            $connection->close();

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}