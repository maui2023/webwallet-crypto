<?php

class WalletController {
    private $rpc;

    public function __construct(Client $rpcClient) {
        $this->rpc = $rpcClient;
    }

    /**
     * Get total balance for current user
     
    public function getBalance() {
        $username = $_SESSION['user_session'] ?? '';
        return $this->rpc->getBalance($username);
    }
    */
    public function getBalanceByLabel($label) {
    return $this->rpc->getBalance($label);
}


    /**
     * Get latest transactions for current user
     */
    public function getTransactionList($count = 10) {
        $username = $_SESSION['user_session'] ?? '';
        return $this->rpc->getTransactionList($username, $count);
    }

    /**
     * Generate new address under a label (e.g. Semasa_id_4)
     */
    public function getNewAddress($label) {
        return $this->rpc->getNewAddress($label);
    }

    /**
     * Send funds using sendtoaddress (default wallet) — legacy, not used
     */
    public function sendToAddress($toAddress, $amount) {
        return $this->rpc->sendToAddress($toAddress, $amount);
    }

    /**
     * Send from user-specific account (label), e.g. Semasa_id_4
     */
    public function sendFromUser($fromAccount, $toAddress, $amount, $notes = '') {
        // NOTE: sendfrom tidak menyokong notes secara langsung
        return $this->rpc->sendFrom($fromAccount, $toAddress, $amount);
    }
    
    /**
     * Return all user-specific addresses by labels like Semasa_id_4, etc.
     */
    public function getAddresses() {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return [];

        $username = $_SESSION['user_session'] ?? null;
        return $this->rpc->getAllUserAddresses($userId, $username);
    }
}
