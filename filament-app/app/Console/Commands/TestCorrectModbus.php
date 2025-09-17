<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gateway;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Endian;
use Exception;

class TestCorrectModbus extends Command
{
    protected $signature = 'test:correct-modbus {gateway?}';
    protected $description = 'Test Modbus with correct addressing and endian handling for Teltonika RUT956';

    public function handle()
    {
        $gatewayName = $this->argument('gateway') ?? 'TestGateway';
        
        // Find the gateway
        $gateway = Gateway::where('name', $gatewayName)->first();
        
        if (!$gateway) {
            $this->error("Gateway '{$gatewayName}' not found!");
            return 1;
        }

        $this->info("Testing Teltonika RUT956 Modbus TCP Server");
        $this->info("Gateway: {$gateway->name} ({$gateway->ip_address}:{$gateway->port}, Unit ID: {$gateway->unit_id})");
        $this->info("Expected first register value: ≈853 kWh");
        $this->newLine();

        try {
            // 1. Connect to Modbus TCP server with retry logic
            $connection = null;
            $maxRetries = 3;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $connection = BinaryStreamConnection::getBuilder()
                        ->setHost($gateway->ip_address)
                        ->setPort($gateway->port)
                        ->setConnectTimeoutSec(10)
                        ->setReadTimeoutSec(10)
                        ->build();

                    $connection->connect();
                    $this->info("✓ Connected to Modbus TCP server (attempt {$attempt})");
                    break;
                    
                } catch (Exception $e) {
                    $this->warn("Connection attempt {$attempt} failed: " . $e->getMessage());
                    if ($attempt < $maxRetries) {
                        $this->line("Retrying in 2 seconds...");
                        sleep(2);
                    } else {
                        throw $e;
                    }
                }
            }

            // 2. Read 12 registers starting at 1024 (UI registers 1025-1036)
            // Teltonika UI shows 1025, but Modbus PDU uses 0-based addressing
            $startAddress = 1024; // UI register 1025
            $registerCount = 12;   // Covers UI registers 1025-1036 (6 FLOAT32 values)
            
            $this->info("Reading {$registerCount} registers starting at PDU address {$startAddress} (UI: " . ($startAddress + 1) . "-" . ($startAddress + $registerCount) . ")");
            
            $response = null;
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $packet = new ReadHoldingRegistersRequest($startAddress, $registerCount, $gateway->unit_id);
                    $binaryData = $connection->sendAndReceive($packet);
                    $response = ResponseFactory::parseResponseOrThrow($binaryData);
                    $this->info("✓ Successfully read {$registerCount} registers (attempt {$attempt})");
                    break;
                    
                } catch (Exception $e) {
                    $this->warn("Read attempt {$attempt} failed: " . $e->getMessage());
                    if ($attempt < $maxRetries) {
                        $this->line("Retrying in 1 second...");
                        sleep(1);
                    } else {
                        throw $e;
                    }
                }
            }
            $this->newLine();

            // 3. Display raw register values
            $registers = $response->getData();
            $this->info("Raw register values:");
            for ($i = 0; $i < count($registers); $i += 2) {
                $uiRegister = $startAddress + $i + 1; // Convert back to UI addressing
                $reg1 = $registers[$i] ?? 0;
                $reg2 = $registers[$i + 1] ?? 0;
                $this->line("  UI Registers {$uiRegister}-" . ($uiRegister + 1) . ": [{$reg1}, {$reg2}] (0x" . sprintf('%04X', $reg1) . ", 0x" . sprintf('%04X', $reg2) . ")");
            }
            $this->newLine();

            // 4. Test all 4 common endian/word orders for FLOAT32 decoding
            $endianTypes = [
                Endian::BIG_ENDIAN => 'ABCD (Big Endian)',
                Endian::LITTLE_ENDIAN => 'CDAB (Little Endian)', 
                Endian::BIG_ENDIAN_LOW_WORD_FIRST => 'DCBA (Big Endian, Low Word First)',
                Endian::LITTLE_ENDIAN | Endian::LOW_WORD_FIRST => 'BADC (Little Endian, Low Word First)',
            ];

            $this->info("Testing all 4 endian/word orders for FLOAT32 decoding:");
            $this->newLine();

            $bestMatches = [];

            foreach ($endianTypes as $endian => $description) {
                $this->info("🔍 Testing: {$description}");
                
                try {
                    // Decode 6 FLOAT32 values (each uses 2 registers)
                    $floatValues = [];
                    for ($i = 0; $i < 6; $i++) {
                        $registerIndex = $i * 2;
                        if ($registerIndex + 1 < count($registers)) {
                            // Use getDoubleWordAt() to get 32-bit value, then convert to float
                            $doubleWord = $response->getDoubleWordAt($registerIndex);
                            $floatValue = $doubleWord->getFloat($endian);
                            $floatValues[] = $floatValue;
                            
                            $uiRegister = $startAddress + $registerIndex + 1;
                            $this->line("    UI Reg {$uiRegister}-" . ($uiRegister + 1) . ": {$floatValue}");
                            
                            // Check if this looks like the expected ≈853 kWh for first register
                            if ($i === 0 && abs($floatValue - 853) < 100) {
                                $bestMatches[] = [
                                    'endian' => $endian,
                                    'description' => $description,
                                    'value' => $floatValue,
                                    'difference' => abs($floatValue - 853)
                                ];
                                $this->line("    → <fg=green>🎯 POTENTIAL MATCH! Value {$floatValue} is close to expected 853</>");
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    $this->line("    <fg=red>Error: " . $e->getMessage() . "</>");
                }
                
                $this->newLine();
            }

            // 5. Summary of best matches
            if (!empty($bestMatches)) {
                $this->info("🏆 BEST MATCHES (closest to expected 853 kWh):");
                usort($bestMatches, fn($a, $b) => $a['difference'] <=> $b['difference']);
                
                foreach ($bestMatches as $match) {
                    $this->line("  ✓ {$match['description']}: {$match['value']} (diff: " . round($match['difference'], 3) . ")");
                }
                
                $this->newLine();
                $this->info("🔧 RECOMMENDED CONFIGURATION:");
                $best = $bestMatches[0];
                $this->line("  Endian Type: {$best['description']}");
                $this->line("  Expected Value: {$best['value']} kWh");
                
                // Map endian constant to our byte_order field
                $byteOrderMapping = [
                    Endian::BIG_ENDIAN => 'big_endian',
                    Endian::LITTLE_ENDIAN => 'little_endian',
                    Endian::BIG_ENDIAN_LOW_WORD_FIRST => 'word_swapped',
                    (Endian::LITTLE_ENDIAN | Endian::LOW_WORD_FIRST) => 'little_endian_swapped',
                ];
                
                $byteOrder = $byteOrderMapping[$best['endian']] ?? 'word_swapped';
                $this->line("  Byte Order Setting: {$byteOrder}");
                
            } else {
                $this->warn("❌ No values close to expected 853 kWh found.");
                $this->line("This might indicate:");
                $this->line("  - The register values are different than expected");
                $this->line("  - The data format is not standard IEEE 754 FLOAT32");
                $this->line("  - Additional scaling or processing is needed");
            }

            $connection->close();
            $this->info("✓ Connection closed");

        } catch (Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}