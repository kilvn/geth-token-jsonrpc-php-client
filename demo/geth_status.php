<?php

use Kilvn\GethTokenJsonRpcPhpClient as EthToken;

require_once(__DIR__ . '/../src/Wax.php');

$wax = new EthToken\Wax('192.168.1.6');
$syncing = $wax->getSyncStatus();
$peers = $wax->getPeerCount();
$highest_block = $wax->getBlockNumber();

echo "Syncing: ";
if (!$syncing) {
	echo "NO\n";
} else {
	echo "\n" . json_encode($syncing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "Peers: $peers\n";
echo "Highest block: $highest_block\n";
