<?php

namespace App\Services;

use InvalidArgumentException;

class DataTypeConversionService
{
    /**
     * Convert raw Modbus register data to typed values.
     */
    public function convertRawData(array $registers, string $dataType, string $byteOrder = 'word_swapped'): float|int
    {
        if (empty($registers)) {
            throw new InvalidArgumentException('Registers array cannot be empty');
        }

        // Ensure registers are integers
        $registers = array_map('intval', $registers);

        return match ($dataType) {
            'int16' => $this->convertInt16($registers[0]),
            'uint16' => $this->convertUint16($registers[0]),
            'int32' => $this->convertInt32($registers, $byteOrder),
            'uint32' => $this->convertUint32($registers, $byteOrder),
            'float32' => $this->convertFloat32($registers, $byteOrder),
            'float64' => $this->convertFloat64($registers, $byteOrder),
            default => throw new InvalidArgumentException("Unsupported data type: {$dataType}")
        };
    }

    /**
     * Convert single register to signed 16-bit integer.
     */
    private function convertInt16(int $register): int
    {
        // Convert unsigned 16-bit to signed
        if ($register > 32767) {
            return $register - 65536;
        }
        return $register;
    }

    /**
     * Convert single register to unsigned 16-bit integer.
     */
    private function convertUint16(int $register): int
    {
        return $register & 0xFFFF;
    }

    /**
     * Convert two registers to signed 32-bit integer.
     */
    private function convertInt32(array $registers, string $byteOrder): int
    {
        if (count($registers) < 2) {
            throw new InvalidArgumentException('Int32 requires at least 2 registers');
        }

        $value = $this->combineRegisters32($registers[0], $registers[1], $byteOrder);
        
        // Convert unsigned 32-bit to signed
        if ($value > 2147483647) {
            return $value - 4294967296;
        }
        return $value;
    }

    /**
     * Convert two registers to unsigned 32-bit integer.
     */
    private function convertUint32(array $registers, string $byteOrder): int
    {
        if (count($registers) < 2) {
            throw new InvalidArgumentException('Uint32 requires at least 2 registers');
        }

        return $this->combineRegisters32($registers[0], $registers[1], $byteOrder);
    }

    /**
     * Convert two registers to 32-bit float.
     */
    private function convertFloat32(array $registers, string $byteOrder): float
    {
        if (count($registers) < 2) {
            throw new InvalidArgumentException('Float32 requires at least 2 registers');
        }

        $combined = $this->combineRegisters32($registers[0], $registers[1], $byteOrder);
        
        // Convert to binary string and unpack as float
        $binary = pack('N', $combined);
        $result = unpack('f', $binary);
        
        return $result[1];
    }

    /**
     * Convert four registers to 64-bit float (double).
     */
    private function convertFloat64(array $registers, string $byteOrder): float
    {
        if (count($registers) < 4) {
            throw new InvalidArgumentException('Float64 requires at least 4 registers');
        }

        // Combine registers based on byte order
        $bytes = $this->combineRegisters64($registers, $byteOrder);
        
        // Pack as 8 bytes, then unpack as double
        $packed = pack('C8', ...$bytes);
        $unpacked = unpack('d', $packed);
        
        return $unpacked[1];
    }

    /**
     * Combine two 16-bit registers into a 32-bit value based on byte order.
     */
    private function combineRegisters32(int $reg1, int $reg2, string $byteOrder): int
    {
        return match ($byteOrder) {
            'big_endian' => ($reg1 << 16) | $reg2,
            'little_endian' => ($reg2 << 16) | $reg1,
            'word_swapped' => ($reg2 << 16) | $reg1, // Same as little endian for 32-bit
            default => throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}")
        };
    }

    /**
     * Combine four 16-bit registers into 8 bytes based on byte order.
     */
    private function combineRegisters64(array $registers, string $byteOrder): array
    {
        $bytes = [];
        
        foreach ($registers as $register) {
            // Split each 16-bit register into 2 bytes
            $highByte = ($register >> 8) & 0xFF;
            $lowByte = $register & 0xFF;
            
            switch ($byteOrder) {
                case 'big_endian':
                    $bytes[] = $highByte;
                    $bytes[] = $lowByte;
                    break;
                case 'little_endian':
                    $bytes[] = $lowByte;
                    $bytes[] = $highByte;
                    break;
                case 'word_swapped':
                    // Word-swapped: swap the order of 16-bit words, but keep byte order within words
                    $bytes[] = $lowByte;
                    $bytes[] = $highByte;
                    break;
                default:
                    throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}");
            }
        }

        // For word-swapped 64-bit, we need to swap the word pairs
        if ($byteOrder === 'word_swapped') {
            $swapped = [];
            for ($i = 0; $i < 8; $i += 4) {
                // Swap pairs of words (4 bytes each)
                $swapped[] = $bytes[$i + 2];
                $swapped[] = $bytes[$i + 3];
                $swapped[] = $bytes[$i];
                $swapped[] = $bytes[$i + 1];
            }
            $bytes = $swapped;
        }

        return $bytes;
    }

    /**
     * Apply scale factor to converted value.
     */
    public function applyScaling(float|int $value, float $scaleFactor): float
    {
        return $value * $scaleFactor;
    }

    /**
     * Get the required number of registers for a data type.
     */
    public function getRequiredRegisterCount(string $dataType): int
    {
        return match ($dataType) {
            'int16', 'uint16' => 1,
            'int32', 'uint32', 'float32' => 2,
            'float64' => 4,
            default => throw new InvalidArgumentException("Unsupported data type: {$dataType}")
        };
    }

    /**
     * Validate that enough registers are provided for the data type.
     */
    public function validateRegisterCount(array $registers, string $dataType): bool
    {
        $required = $this->getRequiredRegisterCount($dataType);
        return count($registers) >= $required;
    }

    /**
     * Convert a value back to raw registers (for testing/validation).
     */
    public function convertToRawRegisters(float|int $value, string $dataType, string $byteOrder = 'word_swapped'): array
    {
        return match ($dataType) {
            'int16' => [$this->int16ToRegister($value)],
            'uint16' => [$this->uint16ToRegister($value)],
            'int32' => $this->int32ToRegisters($value, $byteOrder),
            'uint32' => $this->uint32ToRegisters($value, $byteOrder),
            'float32' => $this->float32ToRegisters($value, $byteOrder),
            'float64' => $this->float64ToRegisters($value, $byteOrder),
            default => throw new InvalidArgumentException("Unsupported data type: {$dataType}")
        };
    }

    private function int16ToRegister(int $value): int
    {
        if ($value < 0) {
            return $value + 65536;
        }
        return $value & 0xFFFF;
    }

    private function uint16ToRegister(int $value): int
    {
        return $value & 0xFFFF;
    }

    private function int32ToRegisters(int $value, string $byteOrder): array
    {
        if ($value < 0) {
            $value = $value + 4294967296;
        }
        
        $high = ($value >> 16) & 0xFFFF;
        $low = $value & 0xFFFF;
        
        return match ($byteOrder) {
            'big_endian' => [$high, $low],
            'little_endian', 'word_swapped' => [$low, $high],
            default => throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}")
        };
    }

    private function uint32ToRegisters(int $value, string $byteOrder): array
    {
        $high = ($value >> 16) & 0xFFFF;
        $low = $value & 0xFFFF;
        
        return match ($byteOrder) {
            'big_endian' => [$high, $low],
            'little_endian', 'word_swapped' => [$low, $high],
            default => throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}")
        };
    }

    private function float32ToRegisters(float $value, string $byteOrder): array
    {
        $packed = pack('f', $value);
        $unpacked = unpack('N', $packed);
        $combined = $unpacked[1];
        
        $high = ($combined >> 16) & 0xFFFF;
        $low = $combined & 0xFFFF;
        
        return match ($byteOrder) {
            'big_endian' => [$high, $low],
            'little_endian', 'word_swapped' => [$low, $high],
            default => throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}")
        };
    }

    private function float64ToRegisters(float $value, string $byteOrder): array
    {
        $packed = pack('d', $value);
        $bytes = unpack('C8', $packed);
        
        $registers = [];
        for ($i = 1; $i <= 8; $i += 2) {
            $register = ($bytes[$i] << 8) | $bytes[$i + 1];
            $registers[] = $register;
        }
        
        // Apply byte order transformations
        switch ($byteOrder) {
            case 'big_endian':
                return $registers;
            case 'little_endian':
                return array_reverse($registers);
            case 'word_swapped':
                // Swap pairs of registers
                return [$registers[1], $registers[0], $registers[3], $registers[2]];
            default:
                throw new InvalidArgumentException("Unsupported byte order: {$byteOrder}");
        }
    }
}