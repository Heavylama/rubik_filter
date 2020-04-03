<?php

namespace Rubik\Storage;

/**
 * Simple storage interface used by {@link ProcmailStorage}.
 *
 * @package Rubik\Storage
 */
interface StorageInterface
{
    /**
     * Authenticate to storage server.
     *
     * @param $username string
     * @param $password string
     * @return bool
     */
    public function login($username, $password);

    /**
     * Write $content to file at $path overwriting existing  content.
     *
     * @param $path string
     * @param $content string
     * @return bool
     */
    public function put($path, $content);

    /**
     * Read contents of a file at $path.
     *
     * @param $path string
     * @return string|bool
     */
    public function get($path);

    /**
     * @return bool
     */
    public function isConnected();

    /**
     * @return void
     */
    public function disconnect();


    /**
     * Get last modification time of a file at $path.
     *
     * @param $path string
     * @return int unix timestamp
     */
    public function lastModificationTime($path);

    /**
     * Create a directory.
     *
     * @param $dir string
     * @param $recursive boolean
     * @return bool true if already exists or was successfully created
     */
    public function mkdir($dir, $recursive);

    /**
     * List files non-recursively in $dir directory.
     *
     * @param $dir string
     * @return null|array
     */
    public function listFiles($dir);

    /**
     * Remove file/directory tree at $path.
     *
     * @param $path string
     * @param $recursive bool
     * @return bool
     */
    public function delete($path, $recursive);
}