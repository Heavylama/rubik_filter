<?php

use Rubik\Storage\StorageInterface;

require_once __DIR__ . '/../Common.php';

class StorageMock implements StorageInterface
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

    public function login($user)
    {

        $pw = func_get_arg(1);

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
        $this->_deleteDir($this->root);
        $this->mkdir("");
    }

    private function _deleteDir($dir) {
        if (!file_exists($dir)) return;

        $files = array_diff(scandir($dir), array(".", ".."));

        foreach ($files as $file) {
            if (is_file("$dir/$file")) {
                unlink("$dir/$file");
            } else {
                $this->_deleteDir("$dir/$file");
            }
        }

        rmdir($dir);
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

    public function _readFile($file) {
        return file_get_contents($this->root . "/" . $file);
    }

    public function lastModificationTime($filename)
    {
        return filemtime($this->root . "/$filename");
    }

    /**
     * @inheritDoc
     */
    public function mkdir($dir)
    {
        $dir = $this->root . "/$dir";
        return file_exists($dir) || mkdir($dir);
    }

    /**
     * @inheritDoc
     */
    public function listFiles($dir)
    {
        $dir = $this->root . "/$dir";

        $list = scandir($dir);
        $onlyFiles = array();
        foreach ($list as $file) {
            if (is_file($dir . "/$file")) {
                $onlyFiles[] = $file;
            }
        }

        return $onlyFiles;
    }
}