<?php

class AdminClient {
    private $url;
    private $username;
    private $password;

    public function __construct($host, $port, $username, $password) {
        $this->url = "http://{$host}:{$port}/";
        $this->username = $username;
        $this->password = $password;
    }

    private function call($method, $params = []) {
        $payload = json_encode([
            'jsonrpc' => '1.0',
            'id' => 'admin',
            'method' => $method,
            'params' => $params
        ]);

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('RPC Error: ' . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['error']) && $result['error'] !== null) {
            throw new Exception('RPC Error: ' . $result['error']['message']);
        }

        return $result['result'];
    }

    public function listSinceBlock($blockhash = null, $targetConfirmations = 1) {
        return $this->call('listsinceblock', $blockhash ? [$blockhash, $targetConfirmations] : []);
    }

    public function listAddressGroupings() {
        return $this->call('listaddressgroupings');
    }

    public function setAccount($address, $account = '') {
        return $this->call('setaccount', [$address, $account]);
    }

    public function getAccount($address) {
        return $this->call('getaccount', [$address]);
    }

    public function getAddressesByAccount($account = '') {
        return $this->call('getaddressesbyaccount', [$account]);
    }

    public function getInfo() {
        return $this->call('getinfo');
    }

    public function repairWallet() {
        return $this->call('repairwallet');
    }

    public function getBalance($account = '') {
        return $this->call('getbalance', [$account]);
    }
}
