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
            $_SESSION['rubik_filter_last_mod_time'] = -1;
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
        $this->lastModificationTime = -1;
    }

    private function updateCache() {
        $modTime = $this->storage->lastTimeChanged();

        if ($modTime === false) {
            $this->setCache(ProcmailStorage::ERR_CANNOT_READ, -1);
            return false;
        }

        if ($modTime > $this->lastModificationTime) {
            $filters = $this->storage->getProcmailRules();

            if (is_string($filters)) {
                $parser = new FilterParser();

                $filters = $parser->parse($filters);
            }

            $this->setCache($filters, $modTime);
        }

        return true;
    }

    private function setCache($filters, $modTime) {
        $this->filters = $filters;
        $this->lastModificationTime = $modTime;
    }
}