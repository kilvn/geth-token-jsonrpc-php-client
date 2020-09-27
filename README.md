# ETH Contact For PHP Json-Rpc API

forked from [wax-api](https://github.com/waxio/wax-api)

This class allows anyone to send or receive WAX easily from PHP. It can be used either on the command line, or in a web
server. It will work on PHP 5.6 or higher.

To get started, you will need these PHP modules:
- bcmath
- curl

You will also need to install [Geth](https://geth.ethereum.org/downloads/) and enable its JSON-RPC API.

Due to precision concerns, all amounts are represented as strings. For example, 1.2345 WAX will be returned as `"1.2345"`.

# Install
```shell
composer require kilvn/geth-token-jsonrpc-php-client
```

# Demos

There are some demos available under demo/

# Usage

```php
use Kilvn\GethTokenJsonRpcPhpClient;

new Wax($rpcHost, $rpcPort, $contract_address, $contract_decimals [, $trimTrailingZeroes, $timeout])
```

- `$rpcHost` - This should be the host where your Geth JSON-RPC is available, e.g. `"127.0.0.1"`, Or you can use
 someone else's node, such as: `https://mainnet.infura.io/v3/xxxx`, In this case, you need to pass in an empty string
  as `$rpcPort`, because this method does not need to tell what the interface port is
- `$rpcPort` - This should be the port where your Geth JSON-RPC is available. This is optional and defaults to `8545` or empty string
- `$contract_address` Contract address, you must tell the contract location to make the api work properly
- `$contract_decimals` The number of decimal places when the contract is released, The type must be of type int
- `$trimTrailingZeroes` - If true, then WAX balances and amounts will have trailing zeroes removed
- `$timeout` The time for curl to send request timeout, the default is 60 seconds

Instantiates a new `Wax` instance.

### getChecksumAddress($address)
- `$address` - The WAX address that you wish to checksum

Returns the address you input, with capitalization in the standard
[EIP-55](https://github.com/ethereum/EIPs/blob/master/EIPS/eip-55.md) format.

### verifyAddressValid($address)
- `$address` - The WAX address that you wish to validate

Verifies that the address you input is valid by making sure its checksum checks out. If it does not have a
case-checksum, simply returns true. Returns `true` or `false`.

### getPeerCount()

Gets and returns the number of peers connected to your `Geth`.

### getSyncStatus()

Gets and returns an array containing your `Geth`'s sync status, or `null` if it's not syncing.

### getBlockNumber()

Gets and returns the number of the highest block your `Geth` has.

### getAddresses()

Gets and returns an array of all addresses with private keys stored by your `Geth`.

### getWaxBalance($address)
- `$address` - The address whose WAX balance you're interested in

Gets and returns the WAX balance of a particular address, which doesn't need to be your own. The return is a string.

### sendWax($fromAddress, $toAddress, $amount)
- `$fromAddress` - The address from which you want to send WAX
- `$toAddress` - The address to which you want to send WAX
- `$amount` - The amount of WAX you want to send, as a string (e.g. "1.23456")

Sends some WAX from an address you control to another address immediately. Returns the hash of the resulting transaction.

The address must be unlocked. You can automatically unlock addresses using the Geth command line.

### createNewPendingTransactionFilter()

Creates a new filter in Geth for pending transactions. Get the new transactions using `getNewWaxTransactions`.
Returns a string containing the filter's ID.

### getNewWaxTransactions($filterId)
- `$filterId` - The ID of the filter you wish to receive data from

Returns an array containing transaction data for every new WAX transaction that has been broadcast since you last called
`getNewWaxTransactions` for this `$filterId`. Each array element contains the same elements as `getTransactionByHash`.

### getTransactionByHash($hash)
- `$hash` - The hash of the transaction you want details for

Returns an array containing these elements:
- `fromAddress` - The address which sent the WAX
- `toAddress` - The address which received the WAX
- `amount` - The amount of WAX transacted
- `hash` - The hash of the transaction
- `blockHash` - The hash of the block in which the transaction was included
- `blockNumber` - The number of the block in which the transaction was included
- `gas` - The amount of gas used to send this transaction
- `gasPrice` - The price in wei of gas for this transaction
- `confirmations` - How many confirmations this transaction has
