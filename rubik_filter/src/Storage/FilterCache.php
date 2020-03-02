<?php


namespace Rubik\Storage;


use rcmail;
use Rubik\Procmail\FilterParser;

class FilterCache
{
    private $storage;
    private $lastModificationTime;
    private $filters;

    /**
     * FilterCache constructor.
     * @param $storage ProcmailStorage
     */
    public function __construct($storage)
    {
        $this->storage = $storage;

        if (!isset($_SESSION['rubik_filter_last_mod_time'])) {
            $_SESSION['rubik_filter_last_mod_time'] = 0;
        }

        if (!isset($_SESSION['rubik_filter_filter_cache'])) {
            $_SESSION['rubik_filter_filter_cache'] = array();
        }

        $this->lastModificationTime = &$_SESSION['rubik_filter_last_mod_time'];
        $this->filters = &$_SESSION['rubik_filter_filter_cache'];
    }

    public function getFilters() {
        if (!$this->updateCache()) {
            return null;
        }

        return $this->filters;
    }

    public function forceUpdate() {
        $this->lastModificationTime = 0;
    }

    private function updateCache() {
        $modTime = $this->storage->lastTimeChanged();

        if ($modTime === false) return false;

        if ($modTime > $this->lastModificationTime) {
            $procmail = $this->storage->getProcmailRules();

            if (!is_string($procmail)) {
                return false;
            }

            $parser = new FilterParser();

            $filters = $parser->parse($procmail);
            if ($filters === null) {
                return false;
            }

            $this->filters = $filters;

            $this->lastModificationTime = $modTime;
        }

        return true;
    }
}