<?php

require_once __DIR__ . '/../Common.php';

class StorageMock implements \Rubik\Storage\StorageInterface
{
    private $root = null;
    private $validUser = null;
    private $validPw = null;
    private $loggedIn = false;

    public function __construct($rootFolder, $validUser, $validPw)
    {
        $this->root = $rootFolder;
        $this->validUser = $validUser;
        $this->validPw = $validPw;
    }

    public function login($user, $pw)
    {
        if ($this->loggedIn) {
            return true;
        } else {
            return $this->loggedIn = ($this->validUser === $user && $this->validPw === $pw);
        }
    }

    public function put($filename, $content)
    {
        if (!$this->isConnected()) {
            return false;
        }

        file_put_contents($this->root . "/" . $filename, $content);

        return true;
    }

    public function get($filename)
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            return file_get_contents($this->root . "/" . $filename);
        } catch (Exception $e) {
            return null;
        }
    }

    public function isConnected()
    {
        return $this->loggedIn;
    }

    public function disconnect()
    {
        $this->loggedIn = false;
    }

    public function _clean() {
        $files = glob($this->root . "/{,.}*", GLOB_BRACE);

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function _createFile($file, $content) {
        return file_put_contents($this->root . "/" . $file, $content);
    }

    public function _copyFile($dest, $src) {
        $read = file_get_contents($src);
        return $this->_createFile($dest, $read);
    }

    public function _fileExists($file) {
        return file_exists($this->root . "/" . $file);
    }
}