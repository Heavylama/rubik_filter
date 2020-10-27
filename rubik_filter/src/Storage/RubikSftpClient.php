<?php


namespace Rubik\Storage;


use phpseclib\Net\SFTP;


/**
 * SFTP StorageInterface implementation.
 *
 * @package Rubik\Storage
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class RubikSftpClient extends SFTP implements StorageInterface
{
    /**
     * @inheritDoc
     */
    public function authenticate($user, $password) {
        return parent::login($user, $password);
    }

    /**
     * @inheritDoc
     */
    public function lastModificationTime($path)
    {
        return $this->filemtime($path);
    }

    /**
     * @inheritDoc
     */
    public function makeDir($dir, $recursive)
    {
        return $this->file_exists($dir) || parent::mkdir($dir, -1, $recursive);
    }

    /**
     * @inheritDoc
     */
    public function listFiles($dir)
    {
        $allFiles = $this->nlist($dir);

        if (is_array($allFiles)) {
            $onlyFiles = array();

            foreach ($allFiles as $file) {
                if ($this->is_file($dir."/$file")) $onlyFiles[] = $file;
            }

            return $onlyFiles;
        } else {
            return null;
        }
    }
}