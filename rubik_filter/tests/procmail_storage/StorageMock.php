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

    public function login($user, $pw)
    {

        if ($this->loggedIn) {
            return true;
        } else {
            return $this->loggedIn = ($this->validUser === $user && $this->validPw === $pw);
        }
    }

    public function put($path, $content)
    {
        if (!$this->isConnected()) {
            return false;
        }

        return file_put_contents($this->root . "/" . $path, $content) !== false;
    }

    public function get($path)
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $content = file_get_contents($this->root . "/" . $path);

            return $content === null ? false : $content;
        } catch (Exception $e) {
            return false;
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
        $this->mkdir("", false);
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
        $read = file_get_contents(__DIR__ . "/$src");
        return $this->_createFile($dest, $read);
    }

    public function _fileExists($file) {
        return file_exists($this->root . "/" . $file);
    }

    public function _readFile($file) {
        return file_get_contents($this->root . "/" . $file);
    }

    public function lastModificationTime($path)
    {
        return filemtime($this->root . "/$path");
    }

    /**
     * @inheritDoc
     */
    public function mkdir($dir, $recursive = true)
    {
        $dir = $this->root . "/$dir";
        return file_exists($dir) || mkdir($dir, 0777, $recursive);
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

    /**
     * @inheritDoc
     */
    public function delete($path, $recursive)
    {
        unlink("$this->root/$path");
    }
}