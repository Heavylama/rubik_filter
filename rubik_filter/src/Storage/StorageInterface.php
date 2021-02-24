<?php

namespace Rubik\Storage;

/**
 * Simple storage interface used by {@link ProcmailStorage}.
 *
 * @package Rubik\Storage
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
interface StorageInterface
{
    /**
     * Authenticate to storage server.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function authenticate($username, $password);

    /**
     * Write $content to file at $path overwriting existing  content.
     *
     * @param string $path
     * @param string $content
     * @return bool
     */
    public function put($path, $content);

    /**
     * Read contents of a file at $path.
     *
     * @param string $path
     * @return string|bool
     */
    public function get($path);

    /**
     * Check if storage is connected.
     *
     * @return bool
     */
    public function isConnected();

    /**
     * Disconnect from storage.
     *
     * @return void
     */
    public function disconnect();


    /**
     * Get last modification time of a file at $path.
     *
     * @param string $path
     * @return int unix timestamp
     */
    public function lastModificationTime($path);

    /**
     * Create a directory.
     *
     * @param string $dir
     * @param boolean $recursive
     * @return bool true if already exists or was successfully created
     */
    public function makeDir($dir, $recursive);

    /**
     * List files non-recursively in $dir directory.
     *
     * @param string $dir
     * @return null|array
     */
    public function listFiles($dir);

    /**
     * Remove file/directory tree at $path.
     *
     * @param string $path
     * @param bool $recursive
     * @return bool
     */
    public function delete($path, $recursive);

    /**
     * Change file permissions.
     *
     * @param string $path path to file
     * @param int $mode permissions
     * @return bool true on success, false otherwise
     */
    public function chmod($mode, $path);
}