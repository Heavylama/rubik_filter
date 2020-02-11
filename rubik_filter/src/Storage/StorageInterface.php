<?php

namespace Rubik\Storage;

use phpseclib\Net\SFTP;

interface StorageInterface
{
    /**
     * Login to server
     *
     * @param string $username
     * @return bool
     */
    public function login($user);

    /**
     * @param $filename
     * @param $content
     * @return bool
     */
    public function put($filename, $content);

    /**
     * @param $filename
     * @return mixed
     */
    public function get($filename);

    /**
     * @return bool
     */
    public function isConnected();

    /**
     * @return void
     */
    public function disconnect();
}