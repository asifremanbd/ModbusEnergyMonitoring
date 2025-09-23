<?php

namespace App\Services;

use App\Events\NewReadingReceived;
use App\Models\Gateway;
use App\Models\DataPoint;
use App\Models\Reading;
use ModbusTcpClient\Network\BinaryStreamConnection;
use ModbusTcpClient\Packet\ModbusFunction\ReadHoldingRegistersRequest;
use ModbusTcpClient\Packet\ModbusFunction\ReadInputRegistersRequest;
use ModbusTcpClient\Packet\ResponseFactory;
use ModbusTcpClient\Utils\Endian;
use ModbusTcpClient\Utils\Types;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ModbusPollService
{
    private array $connections = [];
    private int $connectionTimeout = 5;
    private int $maxRetries = 3;
    private array $retryDelays = [1, 2, 4]; // seconds
    
    public function __construct(
        private ErrorHandlingService $errorHandler
    ) {}

    /**
     * Poll all data points for a specific gateway
     */
    public function pollGateway(Gateway $gateway): PollResult
    {
        $startTime = microtime(true);
        $results = [];
        $errors = [];
        
        try {
            $connection = $this->getConnection($gateway);
            
            // Get all enabled data points for this gateway
            $dataPoints = $gateway->dataPoints()->where('is_enabled', true)->get();
            
            foreach ($dataPoints as $dataPoint) {
                try {
                    $reading = $this->readDataPoint($connection, $gateway, $dataPoint);
                    if ($reading) {
                        $results[] = $reading;
                    }
                } catch (Exception $e) {
                    $errorInfo = $this->errorHandler->handleModbusError($e, $gateway, $dataPoint);
                    $errors[] = [
                        'data_point_id' => $dataPoint->id,
                        'error' => $errorInfo['user_message'],
                        'error_type' => $errorInfo['type'],
                        'severity' => $errorInfo['severity'],
                        'diagnostic_info' => $errorInfo['diagnostic_info'],
                    ];
                }
            }
            
            // Update gateway success/failure counters
            if (empty($errors)) {
                $gateway->increment('success_count');
            } else {
                $gateway->increment('failure_count');
            }
            
            $gateway->update(['last_seen_at' => Carbon::now()]);
            
        } catch (Exception $e) {
            $gateway->increment('failure_count');
            $errorInfo = $this->errorHandler->handleModbusError($e, $gateway);
            
            return new PollResult(
                success: false,
                readings: [],
                errors: [[
                    'gateway' => $gateway->id,
                    'error' => $errorInfo['user_message'],
                    'error_type' => $errorInfo['type'],
                    'severity' => $errorInfo['severity'],
                    'diagnostic_info' => $errorInfo['diagnostic_info'],
                ]],
                duration: microtime(true) - $startTime
            );
        }
        
        return new PollResult(
            success: empty($errors),
            readings: $results,
            errors: $errors,
            duration: microtime(true) - $startTime
        );
    }

    /**
     * Test connection to a gateway
     */
    public function testConnection(string $ip, int $port, int $unitId, int $testRegister = 1): ConnectionTest
    {
        $startTime = microtime(true);
        
        try {
            $connection = BinaryStreamConnection::getBuilder()
                ->setHost($ip)
                ->setPort($port)
                ->setConnectTimeoutSec($this->connectionTimeout)
                ->setReadTimeoutSec($this->connectionTimeout)
                ->build();
            
            // Try to read a single register to test connectivity
            $packet = new ReadHoldingRegistersRequest($testRegister, 1, $unitId);
            $binaryData = $connection->connect()->sendAndReceive($packet);
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            
            $latency = round((microtime(true) - $startTime) * 1000, 2); // ms
            
            return new ConnectionTest(
                success: true,
                latency: $latency,
                testValue: $response->getData()[0] ?? null,
                error: null
            );
            
        } catch (Exception $e) {
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            
            // Create a temporary gateway object for error handling
            $tempGateway = new Gateway([
                'name' => 'Test Connection',
                'ip_address' => $ip,
                'port' => $port,
                'unit_id' => $unitId,
            ]);
            
            $errorInfo = $this->errorHandler->handleModbusError($e, $tempGateway);
            
            return new ConnectionTest(
                success: false,
                latency: $latency,
                testValue: null,
                error: $errorInfo['user_message'],
                errorType: $errorInfo['type'],
                diagnosticInfo: $errorInfo['diagnostic_info']
            );
        }
    }

    /**
     * Read a single register for preview/testing
     */
    public function readRegister(Gateway $gateway, DataPoint $point): ReadingResult
    {
        try {
            $connection = $this->getConnection($gateway);
            $reading = $this->readDataPoint($connection, $gateway, $point);
            
            return new ReadingResult(
                success: true,
                rawValue: $reading?->raw_value,
                scaledValue: $reading?->scaled_value,
                quality: $reading?->quality ?? 'good',
                error: null
            );
            
        } catch (Exception $e) {
            $errorInfo = $this->errorHandler->handleModbusError($e, $gateway, $point);
            
            return new ReadingResult(
                success: false,
                rawValue: null,
                scaledValue: null,
                quality: 'bad',
                error: $errorInfo['user_message'],
                errorType: $errorInfo['type'],
                diagnosticInfo: $errorInfo['diagnostic_info']
            );
        }
    }

    /**
     * Get or create connection for gateway with retry logic
     */
    private function getConnection(Gateway $gateway): BinaryStreamConnection
    {
        $connectionKey = "{$gateway->ip_address}:{$gateway->port}";
        
        if (!isset($this->connections[$connectionKey])) {
            $this->connections[$connectionKey] = $this->createConnectionWithRetry($gateway);
        }
        
        return $this->connections[$connectionKey];
    }

    /**
     * Create connection with retry logic
     */
    private function createConnectionWithRetry(Gateway $gateway): BinaryStreamConnection
    {
        $lastException = null;
        
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                $connection = BinaryStreamConnection::getBuilder()
                    ->setHost($gateway->ip_address)
                    ->setPort($gateway->port)
                    ->setConnectTimeoutSec($this->connectionTimeout)
                    ->setReadTimeoutSec($this->connectionTimeout)
                    ->build();
                
                // Test the connection
                $connection->connect();
                
                return $connection;
                
            } catch (Exception $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries - 1) {
                    sleep($this->retryDelays[$attempt]);
                }
            }
        }
        
        throw new Exception("Failed to connect after {$this->maxRetries} attempts: " . $lastException->getMessage());
    }

    /**
     * Read a single data point and create Reading record
     */
    private function readDataPoint(BinaryStreamConnection $connection, Gateway $gateway, DataPoint $dataPoint): ?Reading
    {
        try {
            // Create appropriate Modbus request based on function code
            // Convert UI register address to PDU address (subtract 1)
            $pduAddress = $dataPoint->register_address - 1;
            
            $packet = match ($dataPoint->modbus_function) {
                3 => new ReadHoldingRegistersRequest(
                    $pduAddress,
                    $dataPoint->register_count,
                    $gateway->unit_id
                ),
                4 => new ReadInputRegistersRequest(
                    $pduAddress,
                    $dataPoint->register_count,
                    $gateway->unit_id
                ),
                default => throw new Exception("Unsupported Modbus function: {$dataPoint->modbus_function}")
            };
            
            $binaryData = $connection->sendAndReceive($packet);
            $response = ResponseFactory::parseResponseOrThrow($binaryData);
            $registers = $response->getData();
            
            // Convert raw register data to typed value
            // For FLOAT32, use the response object directly for better accuracy
            if ($dataPoint->data_type === 'float32' && count($registers) >= 2) {
                $rawValue = $this->parseFloat32FromResponse($response, $dataPoint->byte_order);
            } else {
                $rawValue = $this->convertRegistersToValue($registers, $dataPoint);
            }
            $scaledValue = $rawValue * $dataPoint->scale_factor;
            
            // Create and save reading with duplicate handling
            try {
                $reading = Reading::create([
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => json_encode($registers),
                    'scaled_value' => $scaledValue,
                    'quality' => 'good',
                    'read_at' => Carbon::now()
                ]);
                
                // Broadcast new reading event
                NewReadingReceived::dispatch($reading);
                
                return $reading;
                
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Handle duplicate reading gracefully - this can happen with concurrent polling
                Log::info("Duplicate reading detected for data point {$dataPoint->id} at " . Carbon::now() . ", skipping");
                
                // Return the existing reading instead
                $existingReading = Reading::where('data_point_id', $dataPoint->id)
                    ->where('read_at', Carbon::now())
                    ->first();
                    
                return $existingReading;
            }
            
        } catch (Exception $e) {
            Log::warning("Failed to read data point {$dataPoint->id}: " . $e->getMessage());
            
            // Create failed reading record with duplicate handling
            try {
                $reading = Reading::create([
                    'data_point_id' => $dataPoint->id,
                    'raw_value' => null,
                    'scaled_value' => null,
                    'quality' => 'bad',
                    'read_at' => Carbon::now()
                ]);
                
                // Broadcast new reading event (even for failed readings)
                NewReadingReceived::dispatch($reading);
                
                return $reading;
                
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // Handle duplicate reading gracefully - this can happen with concurrent polling
                Log::info("Duplicate failed reading detected for data point {$dataPoint->id} at " . Carbon::now() . ", skipping");
                
                // Return the existing reading instead
                $existingReading = Reading::where('data_point_id', $dataPoint->id)
                    ->where('read_at', Carbon::now())
                    ->first();
                    
                return $existingReading;
            }
        }
    }

    /**
     * Convert register array to typed value based on data point configuration
     */
    private function convertRegistersToValue(array $registers, DataPoint $dataPoint): float|int
    {
        if (empty($registers)) {
            throw new Exception("No register data received");
        }
        
        // Convert based on data type
        return match ($dataPoint->data_type) {
            'int16' => $this->parseInt16($registers[0]),
            'uint16' => $registers[0],
            'int32' => count($registers) >= 2 ? $this->parseInt32($registers, $dataPoint->byte_order) : throw new Exception("Insufficient registers for int32"),
            'uint32' => count($registers) >= 2 ? $this->parseUInt32($registers, $dataPoint->byte_order) : throw new Exception("Insufficient registers for uint32"),
            'float32' => count($registers) >= 2 ? $this->parseFloat32($registers, $dataPoint->byte_order) : throw new Exception("Insufficient registers for float32"),
            'float64' => count($registers) >= 4 ? $this->parseFloat64($registers, $dataPoint->byte_order) : throw new Exception("Insufficient registers for float64"),
            default => throw new Exception("Unsupported data type: {$dataPoint->data_type}")
        };
    }

    /**
     * Parse INT16 from single register
     */
    private function parseInt16(int $register): int
    {
        // Convert unsigned 16-bit to signed
        return $register > 32767 ? $register - 65536 : $register;
    }

    /**
     * Parse INT32 from two registers with byte order handling
     */
    private function parseInt32(array $registers, string $byteOrder): int
    {
        $value = $this->parseUInt32($registers, $byteOrder);
        // Convert unsigned 32-bit to signed
        return $value > 2147483647 ? $value - 4294967296 : $value;
    }

    /**
     * Parse UINT32 from two registers with byte order handling
     */
    private function parseUInt32(array $registers, string $byteOrder): int
    {
        return match ($byteOrder) {
            'word_swapped' => ($registers[0] << 16) | $registers[1],
            'big_endian' => ($registers[1] << 16) | $registers[0],
            'little_endian' => ($registers[0] << 16) | $registers[1], // Same as word_swapped for this case
            default => ($registers[0] << 16) | $registers[1]
        };
    }

    /**
     * Parse FLOAT32 from response using proper library methods
     */
    private function parseFloat32FromResponse($response, string $byteOrder): float
    {
        try {
            // Use the library's proper FLOAT32 handling
            $doubleWord = $response->getDoubleWordAt(0);
            
            // Map our byte_order to library endian constants
            $endian = match ($byteOrder) {
                'big_endian' => \ModbusTcpClient\Utils\Endian::BIG_ENDIAN,
                'little_endian' => \ModbusTcpClient\Utils\Endian::LITTLE_ENDIAN,
                'word_swapped' => \ModbusTcpClient\Utils\Endian::BIG_ENDIAN_LOW_WORD_FIRST,
                default => \ModbusTcpClient\Utils\Endian::BIG_ENDIAN
            };
            
            return $doubleWord->getFloat($endian);
            
        } catch (Exception $e) {
            // Fallback to manual parsing if library method fails
            $registers = $response->getData();
            return $this->parseFloat32($registers, $byteOrder);
        }
    }

    /**
     * Parse FLOAT32 from two registers with byte order handling (fallback method)
     */
    private function parseFloat32(array $registers, string $byteOrder): float
    {
        // Manual FLOAT32 parsing based on our test results
        $binaryData = match ($byteOrder) {
            'big_endian' => pack('n*', $registers[0], $registers[1]),   // Correct for Teltonika (ABCD)
            'word_swapped' => pack('n*', $registers[0], $registers[1]),
            'little_endian' => pack('v*', $registers[0], $registers[1]),
            default => pack('n*', $registers[0], $registers[1])
        };
        
        $result = unpack('G', $binaryData);
        if ($result === false) {
            throw new Exception("Failed to unpack FLOAT32 data");
        }
        
        return $result[1];
    }

    /**
     * Parse FLOAT64 from four registers with byte order handling
     */
    private function parseFloat64(array $registers, string $byteOrder): float
    {
        // Pack registers into binary data based on byte order
        $binaryData = match ($byteOrder) {
            'word_swapped' => pack('n*', $registers[0], $registers[1], $registers[2], $registers[3]),
            'big_endian' => pack('n*', $registers[3], $registers[2], $registers[1], $registers[0]),
            'little_endian' => pack('v*', $registers[0], $registers[1], $registers[2], $registers[3]),
            default => pack('n*', $registers[0], $registers[1], $registers[2], $registers[3])
        };
        
        // Unpack as IEEE 754 double
        $result = unpack('E', $binaryData); // Big-endian double
        if ($result === false) {
            throw new Exception("Failed to unpack FLOAT64 data");
        }
        
        return $result[1];
    }

    /**
     * Close all connections
     */
    public function closeConnections(): void
    {
        foreach ($this->connections as $connection) {
            try {
                $connection->close();
            } catch (Exception $e) {
                Log::warning("Error closing connection: " . $e->getMessage());
            }
        }
        
        $this->connections = [];
    }
}

/**
 * Result classes for type safety
 */
class PollResult
{
    public function __construct(
        public bool $success,
        public array $readings,
        public array $errors,
        public float $duration
    ) {}
}

class ConnectionTest
{
    public function __construct(
        public bool $success,
        public float $latency,
        public ?int $testValue,
        public ?string $error,
        public ?string $errorType = null,
        public ?array $diagnosticInfo = null
    ) {}
}

class ReadingResult
{
    public function __construct(
        public bool $success,
        public ?string $rawValue,
        public ?float $scaledValue,
        public string $quality,
        public ?string $error,
        public ?string $errorType = null,
        public ?array $diagnosticInfo = null
    ) {}
}