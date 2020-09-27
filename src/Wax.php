<?php

namespace Kilvn\GethTokenJsonRpcPhpClient;

use Exception;

/**
 * Forked from waxio/wax-api
 * Link: https://github.com/waxio/wax-api
 *
 * Class Wax
 * @package Kilvn\GethTokenJsonRpcPhpClient
 */
class Wax
{
    const JSON_RPC_VERSION = "2.0";

    public bool $trimTrailingZeroes = false;
    public int $timeout = 60;

    protected string $rpcHost;
    protected int $rpcPort;
    protected string $curl;
    protected string $contract_address;
    protected int $contract_decimals;

    /**
     * Wax constructor.
     * @param string $rpcHost The host where your Geth's JSON-RPC is available
     * @param int $rpcPort The port where your Geth's JSON-RPC is available
     * @param string $contract_address
     * @param int $contract_decimals
     * @param bool $trimTrailingZeroes If true, then balances returned will have their trailing zeroes stripped from the string.
     * @param int $timeout
     * @throws Exception
     */
    public function __construct(
        string $rpcHost = 'localhost',
        int $rpcPort = 8545,
        string $contract_address = '',
        int $contract_decimals = 8,
        bool $trimTrailingZeroes = false,
        int $timeout = 60
    )
    {
        $this->rpcHost = $rpcHost;
        $this->rpcPort = $rpcPort;
        $this->trimTrailingZeroes = $trimTrailingZeroes;
        $this->timeout = $timeout;

        if (!$this->verifyAddressValid($contract_address)) {
            throw new Exception('The contract address is not a WAX transaction.');
        }
        $this->contract_address = $contract_address;
        if (gettype($contract_decimals) == 'int' and $contract_decimals >= 0) {
            $this->contract_decimals = $contract_decimals;
        }
    }

    /**
     * Return the keccak-256 hash of some ASCII data.
     * @param string $data
     * @return string
     * @throws Exception
     */
    public function keccakAscii(string $data)
    {
        $hex_data = '0x';
        for ($i = 0; $i < strlen($data); $i++) {
            $hex_byte = base_convert(ord($data[$i]), 10, 16);
            if (strlen($hex_byte) < 2) {
                $hex_byte = '0' . $hex_byte;
            }
            $hex_data .= $hex_byte;
        }

        return str_replace('0x', '', $this->request('web3_sha3', [$hex_data]));
    }

    /**
     * Get ETH address case-checksummed as per https://github.com/ethereum/EIPs/blob/master/EIPS/eip-55.md
     * @param string $address
     * @return string
     * @throws Exception
     */
    public function getChecksumAddress(string $address)
    {
        $address = str_replace('0x', '', strtolower($address));
        $hash = $this->keccakAscii($address);
        $output = '';

        for ($i = 0; $i < strlen($address); $i++) {
            $hash_char = substr($hash, $i, 1);
            if (base_convert($hash_char, 16, 10) > 7) {
                $output .= strtoupper(substr($address, $i, 1));
            } else {
                $output .= substr($address, $i, 1);
            }
        }

        return '0x' . $output;
    }

    /**
     * Verify that an ETH address is valid.
     * @param string $address
     * @return bool
     * @throws Exception
     */
    public function verifyAddressValid(string $address)
    {
        if (!preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $address)) {
            // Make sure it's 40 hex chars optionally prefixed with "0x"
            return false;
        } elseif (preg_match('/^(0x)?[0-9a-f]{40}$/', $address) || preg_match('/^(0x)?[0-9A-F]{40}$/', $address)) {
            // All the same case; not checksummed
            return true;
        } else {
            // Make sure the case-checksum matches
            $checksummed = $this->getChecksumAddress($address);
            return $checksummed == $address || $checksummed == '0x' . $address;
        }
    }

    /**
     * Get number of connected peers.
     * @return int
     * @throws Exception
     */
    public function getPeerCount()
    {
        return self::parseInt($this->request('net_peerCount'));
    }

    /**
     * Get Ethereum sync status, or null if not syncing.
     * @return array|null
     * @throws Exception
     */
    public function getSyncStatus()
    {
        $res = $this->request('eth_syncing');
        if (!$res) {
            return null;
        }

        foreach ($res as &$val) {
            $val = self::parseInt($val);
        }

        return $res;
    }

    /**
     * Get the highest block number we have.
     * @return int
     * @throws Exception
     */
    public function getBlockNumber()
    {
        return self::parseInt($this->request('eth_blockNumber'));
    }

    /**
     * Get list of addresses we own.
     * @return string[]
     * @throws Exception
     */
    public function getAddresses()
    {
        return $this->request('eth_accounts');
    }

    /**
     * Get the WAX balance for a specific address.
     * @param string $address The address we're interested in
     * @return string The balance as a string
     * @throws Exception
     */
    public function getWaxBalance(string $address)
    {
        $balance_hex = $this->callWaxMethodLocally('balanceOf(address)', [$address]);
        return $this->addDecimal(self::parseInt($balance_hex, true));
    }

    /**
     * Send some WAX from one address to another.
     * @param string $fromAddress The address which will be sending the WAX
     * @param string $toAddress The address which will be receiving the WAX
     * @param string $amount The amount to send as a string, e.g. "1.234"
     * @return string Transaction hash
     * @throws Exception
     */
    public function sendWax(string $fromAddress, string $toAddress, string $amount)
    {
        $amount = $this->removeDecimal($amount);
        return $this->callWaxMethod($fromAddress, 'transfer(address,uint256)', [$toAddress, self::makeInt($amount)]);
    }

    /**
     * @return string The filter ID
     * @throws Exception
     */
    public function createNewPendingTransactionFilter()
    {
        return $this->request('eth_newPendingTransactionFilter');
    }

    /**
     * Get changes in a filter.
     * @param string $filterId
     * @return array
     * @throws Exception
     */
    public function getNewWaxTransactions(string $filterId)
    {
        $txns = $this->request('eth_getFilterChanges', [$filterId]);
        $output = [];
        foreach ($txns as $hash) {
            try {
                $txn = $this->getTransactionByHash($hash);
            } catch (Exception $ex) {
                // Not a WAX transaction
                continue;
            }

            $output[] = $txn;
        }

        return $output;
    }

    /**
     * Get the full details of a WAX transaction by its hash.
     * @param string $hash
     * @return array
     * @throws Exception if the hash does not belong to a WAX transaction
     */
    public function getTransactionByHash(string $hash)
    {
        $txn = $this->request('eth_getTransactionByHash', [$hash]);
        $wax_txn = $this->decodeErc20Transaction($txn);

        foreach (['hash', 'blockHash', 'blockNumber', 'gas', 'gasPrice'] as $item) {
            $wax_txn[$item] = $txn[$item];
        }

        $highest_block = $this->getBlockNumber();
        foreach ($wax_txn as $key => &$val) {
            switch ($key) {
                case 'blockNumber':
                    $val = self::parseInt($val);
                    break;

                case 'gas':
                case 'gasPrice':
                    $val = self::parseInt($val, true);
                    break;
            }
        }

        $wax_txn['confirmations'] = empty($wax_txn['blockNumber']) ? 0 : max(0, $highest_block - $wax_txn['blockNumber']);
        return $wax_txn;
    }

    /**
     * Decode a WAX transfer transaction.
     * @param array $txn Transaction data from getTransactionByHash
     * @return array
     * @throws Exception
     */
    public function decodeErc20Transaction(array $txn)
    {
        $required = ['from', 'to', 'input'];
        foreach ($required as $param) {
            if (empty($txn[$param])) {
                throw new Exception("Missing required input $param");
            }
        }

        if (strtolower($txn['to']) != strtolower($this->contract_address)) {
            throw new Exception('The provided transaction is not a WAX transaction.');
        }

        $input_len = strlen($txn['input']);
        if ($input_len != 138 && $input_len != 202) {
            throw new Exception("Input ($input_len) is not of the correct length 138 or 178");
        }

        // strip off the 0x
        $input = substr($txn['input'], 2);

        $pos = 0;
        $signature_hash = substr($input, $pos, 8);
        $pos += 8;

        // Make sure that the signature matches what we expect
        $sig_xfer = substr($this->keccakAscii('transfer(address,uint256)'), 0, 8);
        $sig_xfer_from = substr($this->keccakAscii('transferFrom(address,address,uint256)'), 0, 8);
        switch ($signature_hash) {
            case $sig_xfer:
                $pos += 24; // addresses are always padded by 24 0's
                $to = '0x' . substr($input, $pos, 40);
                $pos += 40 + 24;
                $amount = '0x' . substr($input, $pos, 40);

                return [
                    'fromAddress' => $txn['from'],
                    'toAddress' => $to,
                    'amount' => $this->addDecimal(self::parseInt($amount, true))
                ];

                break;

            case $sig_xfer_from:
                $pos += 24;
                $from = '0x' . substr($input, $pos, 40);
                $pos += 40 + 24;
                $to = '0x' . substr($input, $pos, 40);
                $pos += 40 + 24;
                $amount = '0x' . substr($input, $pos, 64);

                return [
                    'fromAddress' => $from,
                    'toAddress' => $to,
                    'amount' => $this->addDecimal(self::parseInt($amount, true))
                ];

                break;

            default:
                throw new Exception("Method signature $signature_hash does not match any expected hash");
        }
    }

    /**
     * Call a WAX contract method locally (no broadcast).
     * @param string $methodSignature
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    protected function callWaxMethodLocally(string $methodSignature, array $args = [])
    {
        return $this->request('eth_call', [[
            'to' => $this->contract_address,
            'data' => $this->encodeABI($methodSignature, $args)
        ], 'latest']);
    }

    /**
     * Call a WAX contract method and broadcast it.
     * @param string $accountAddress Account that will be spending, etc.
     * @param string $methodSignature
     * @param array $args
     * @return mixed
     * @throws Exception
     */
    protected function callWaxMethod(string $accountAddress, string $methodSignature, array $args = [])
    {
        return $this->request('eth_sendTransaction', [[
            'from' => $accountAddress,
            'to' => $this->contract_address,
            'data' => $this->encodeABI($methodSignature, $args)
        ]]);
    }

    /**
     * Encode a request in ABI format.
     * @param string $methodSignature
     * @param array $args
     * @return string
     * @throws Exception
     */
    protected function encodeABI(string $methodSignature, array $args = [])
    {
        $method_selector = substr($this->keccakAscii($methodSignature), 0, 8);
        $abi = '0x' . $method_selector;
        foreach ($args as $arg) {
            $abi .= self::encodeContractMethodCallArgument($arg);
        }

        return $abi;
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    protected function request(string $method, array $params = [])
    {
        $req_id = $this->getRequestId();
        $req = json_encode([
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $req_id,
            'method' => $method,
            'params' => $params
        ]);

        $url = "http://{$this->rpcHost}:{$this->rpcPort}";
        if (strpos($this->rpcHost, 'http://') === true or strpos($this->rpcHost, 'https://') === true) {
            $url = $this->rpcHost;
        }

        $ch = $this->getCurl();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($req)]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        $res = curl_exec($ch);

        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response_code != 200) {
            throw new Exception('Geth error: HTTP status ' . $response_code, $response_code);
        }

        $res = json_decode($res, true);

        if (empty($res['jsonrpc'])) {
            throw new Exception('jsonrpc missing from response');
        }

        if (empty($res['id']) || $res['id'] != $req_id) {
            throw new Exception('Response ID does not match request ID');
        }

        if (!empty($res['error'])) {
            throw new Exception($res['error']['message'], $res['error']['code']);
        }

        return $res['result'];
    }

    /**
     * No real reason this needs to be random, but why not.
     * @return int
     */
    protected function getRequestId()
    {
        return rand(1, pow(2, 31) - 1);
    }

    /**
     * @return resource
     */
    protected function getCurl()
    {
        if ($this->curl) {
            curl_reset($this->curl);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
            return $this->curl;
        }

        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        return $this->curl;
    }

    /**
     * Parse an int from Ethereum's hex format.
     * @param string $int
     * @param bool $isBigInt If true, return the result as a base-10 string
     * @return int|string
     */
    public static function parseInt(string $int, bool $isBigInt = false)
    {
        if (strpos($int, '0x') !== 0) {
            return $int;
        }

        if (!$isBigInt) {
            return (int)base_convert(substr($int, 2), 16, 10);
        } else {
            return self::baseConvertHighPrecision($int, 16, 10);
        }
    }

    /**
     * Turns an int into Ethereum's hex format.
     * @param int $int
     * @return string
     */
    public static function makeInt(int $int)
    {
        return '0x' . self::baseConvertHighPrecision($int, 10, 16);
    }

    /**
     * Turn this int into a float.
     * @param int|string $int
     * @return string
     */
    public function addDecimal($int)
    {
        $val = bcdiv($int, bcpow(10, $this->contract_decimals, $this->contract_decimals), $this->contract_decimals);
        return $this->trimTrailingZeroes ? preg_replace('/\.$/', '.0', rtrim($val, '0')) : $val;
    }

    /**
     * Remove the decimal point from this float. That is, turn it into an int of its smallest unit
     * @param float|string $float
     * @return string
     */
    public function removeDecimal($float)
    {
        return bcmul($float, bcpow(10, $this->contract_decimals, $this->contract_decimals), 0);
    }

    /**
     * http://php.net/manual/en/function.base-convert.php#106546
     * @param string $numberInput
     * @param int $srcBase
     * @param int $dstBase
     * @return string
     */
    protected static function baseConvertHighPrecision(string $numberInput, int $srcBase, int $dstBase)
    {
        $fromBaseInput = self::generateBaseString($srcBase);
        $toBaseInput = self::generateBaseString($dstBase);

        if ($fromBaseInput == $toBaseInput) {
            return $numberInput;
        }

        $fromBase = str_split($fromBaseInput, 1);
        $toBase = str_split($toBaseInput, 1);
        $number = str_split($numberInput, 1);
        $fromLen = strlen($fromBaseInput);
        $toLen = strlen($toBaseInput);
        $numberLen = strlen($numberInput);
        $revival = '';

        if ($toBaseInput == '0123456789') {
            $revival = 0;
            for ($i = 1; $i <= $numberLen; $i++) {
                $revival = bcadd($revival, bcmul(array_search($number[$i - 1], $fromBase), bcpow($fromLen, $numberLen - $i)));
            }
            return $revival;
        }

        if ($fromBaseInput != '0123456789') {
            $base10 = self::baseConvertHighPrecision($numberInput, $srcBase, 10);
        } else {
            $base10 = $numberInput;
        }

        if ($base10 < strlen($toBaseInput)) {
            return $toBase[$base10];
        }

        while ($base10 != '0') {
            $revival = $toBase[bcmod($base10, $toLen)] . $revival;
            $base10 = bcdiv($base10, $toLen, 0);
        }

        return $revival;
    }

    /**
     * @param int $base
     * @return string
     */
    protected static function generateBaseString(int $base)
    {
        return substr('0123456789abcdefghijklmnopqrstuvwxyz', 0, $base);
    }

    /**
     * Pad a hex string to a specific (decoded) byte length
     * @param string $hex
     * @param int $length
     * @return string
     */
    protected static function padToByteLength(string $hex, $length = 32)
    {
        if (strlen($hex) % 2 == 1) {
            // odd length; make it whole
            $hex = '0' . $hex;
        }

        while (strlen($hex) < $length * 2) {
            $hex = '00' . $hex;
        }

        return $hex;
    }

    /**
     * Encode an argument for a contract method call.
     * @param mixed $arg
     * @return string
     * @throws Exception
     */
    protected static function encodeContractMethodCallArgument($arg)
    {
        if (is_bool($arg)) {
            return self::padToByteLength($arg ? '01' : '00');
        } elseif (is_int($arg)) {
            return self::padToByteLength(base_convert($arg, 10, 16));
        } elseif (is_string($arg) && preg_match('/^(0x)?[0-9a-fA-F]*$/', $arg)) {
            // it's already hex
            return self::padToByteLength(preg_replace('/^0x/', '', $arg));
        } else {
            throw new Exception('Argument type not implemented');
        }
    }
}
