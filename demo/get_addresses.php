<?php

use Kilvn\GethTokenJsonRpcPhpClient as EthToken;

require_once(__DIR__ . '/../src/Wax.php');

$wax = new EthToken\Wax('192.168.1.6');
$addrs = $wax->getAddresses();

if (empty($addrs)) {
	echo "You don't seem to have any addresses.\n";
} else {
	echo "Addresses:\n";
	foreach ($addrs as $addr) {
		echo "  - " . $wax->getChecksumAddress($addr) . "\n";
	}
}
