<?php
require 'vendor/autoload.php';

use ModbusTcpClient\Utils\Endian;

$reflection = new ReflectionClass(Endian::class);
echo "Available Endian constants:\n";
foreach($reflection->getConstants() as $name => $value) {
    echo "  {$name} = {$value}\n";
}