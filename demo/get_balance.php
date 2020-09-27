<?php

use Kilvn\GethTokenJsonRpcPhpClient as EthToken;

require_once(__DIR__ . '/../src/Wax.php');

if (empty($argv[1])) {
	echo "Usage: php get_balance.php <address>\n";
	exit(1);
}

$wax = new EthToken\Wax('192.168.1.6');
$bal = $wax->getWaxBalance($argv[1]);

echo "Address {$argv[1]} WAX balance: $bal\n";
