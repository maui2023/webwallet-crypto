<?php

class Client {
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
            'id' => 'ayucoin',
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

    public function getBalance($account = '') {
        return $this->call('getbalance', [$account]);
    }

    public function getTransactionList($account = '', $count = 10) {
        return $this->call('listtransactions', [$account, $count]);
    }

    public function getNewAddress($account = '') {
        $address = $this->call('getnewaddress', [$account]);
        $this->call('setaccount', [$address, $account]); // Ensure address is linked to account
        return $address;
    }

    public function sendToAddress($toAddress, $amount) {
        return $this->call('sendtoaddress', [$toAddress, (float)$amount]);
    }

    public function sendFrom($fromAccount, $toAddress, $amount, $minconf = 1, $comment = '') {
        return $this->call('sendfrom', [$fromAccount, $toAddress, (float)$amount, $minconf, $comment]);
    }
/** 
 * Hantar dari akaun tertentu
 */

    public function getAddressesByAccount($account) {
        return $this->call('getaddressesbyaccount', [$account]);
    }

    public function getReceivedByAddress($address, $minconf = 1) {
        return $this->call('getreceivedbyaddress', [$address, $minconf]);
    }

    public function listAddressGroupings() {
        return $this->call('listaddressgroupings');
    }

    public function getAllUserAddresses($userId, $username = null) {
        $result = [];

        $labels = [
            "Semasa_id_$userId",
            "Simpanan_id_$userId",
            "Stake_id_$userId",
            "Outside_id_$userId"
        ];

        foreach ($labels as $label) {
            $addresses = $this->getAddressesByAccount($label);
            if (empty($addresses) && $label === "Outside_id_$userId" && is_string($username) && $username !== '') {
                $addresses = $this->getAddressesByAccount($username);
            }
            if (!empty($addresses)) {
                $result[$label] = $addresses[0];
            }
        }

        return $result;
    }
}
