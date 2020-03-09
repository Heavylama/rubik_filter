<?php


namespace Rubik\Storage;


use phpseclib\Net\SFTP;


/**
 * SFTP StorageInterface implementation.
 *
 * @package Rubik\Storage
 */
class RubikSftpClient extends SFTP implements StorageInterface
{

    public function lastModificationTime($path)
    {
        return $this->filemtime($path);
    }

    public function mkdir($dir)
    {
        return $this->file_exists($dir) || parent::mkdir($dir);
    }

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