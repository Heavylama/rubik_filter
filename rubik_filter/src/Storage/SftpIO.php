<?php

namespace Rubik\Storage;

use phpseclib\Net\SFTP;

class SftpIO
{
    /**
     * @var SFTP
     */
    private $client;

    public function __construct($host)
    {
        $this->client = new SFTP($host);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function login($user, $pw)
    {
        return $this->client->login($user, $pw);
    }

    public function file_exists($filename) {

    }

    public function isConnected() {
        return $this->client->isConnected();
    }

    public function disconnect() {
        $this->client->disconnect();
    }
}