<?php


namespace Rubik\Storage;

/**
 * Handles Procmail filters storage IO.
 *
 * @package Rubik\Storage
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class ProcmailStorage
{
    /** @var string Procmail rules file path */
    public const PROCMAIL_FILE = ".procmailrc";
    /** @var string Procmail rules backup file path */
    public const PROCMAIL_BACKUP_FILE = ".bak.procmailrc";
    /** @var string Vacation replies and replied addresses list is stored here */
    public const RUBIK_DATA_FOLDER = ".roundcube_rubik_filter/";
    /** @var string Vacations reply files directory */
    public const VACATION_REPLIES_LOCATION = self::RUBIK_DATA_FOLDER."procmail_messages";
    /** @var string Already-replied email addresses cache directory */
    public const VACATION_CACHE_LOCATION = ".rubik_vacation_cache";

    public const RUBIK_SECTION_HEADER
        = "########################################################################################\n".
          "# GENERATED BY ROUNDCUBE RUBIK FILTER PLUGIN, DO NOT CHANGE THIS SECTION               #\n".
          "# HASH:%s                                                #\n".
          "########################################################################################\n";
    public const RUBIK_SECTION_FOOTER
        = "########################################################################################\n".
          "# END OF GENERATED SECTION                                                             #\n".
          "########################################################################################\n";
    public const RUBIK_HEADER_REGEX
        = "#+\n".
          "# GENERATED BY ROUNDCUBE RUBIK FILTER PLUGIN, DO NOT CHANGE THIS SECTION *#\n".
          "# HASH:([a-f0-9A-F]{32}) *#\n".
          "#+\n";
    public const RUBIK_FOOTER_REGEX
        = "#+\n".
          "# END OF GENERATED SECTION *#\n".
          "#+";
    public const RUBIK_CONTENT_REGEX = "((.*\n)*)";

    /** @var int Error reading file from storage */
    public const ERR_CANNOT_READ = 1;
    /** @var int Wrong plugin filter section hash */
    public const ERR_INVALID_HASH = 2;
    /** @var int No plugin filter section found */
    public const ERR_NO_SECTION = 4;
    /** @var int No connection to the storage */
    public const ERR_NO_CONNECTION = 8;
    /** @var int Error writing to file in storage */
    public const ERR_CANNOT_WRITE = 16;

    /** @var StorageInterface Storage client*/
    private $client;
    /** @var string Storage username */
    private $user;
    /** @var string Storage password */
    private $password;

    /**
     * ProcmailStorage constructor.
     *
     * @param StorageInterface $client storage client
     * @param string $login storage username
     * @param string $pw storage password
     */
    public function __construct($client, $login, $pw)
    {
        $this->client = $client;
        $this->user = $login;
        $this->password = $pw;
    }

    /**
     * ProcmailStorage destructor
     */
    public function __destruct()
    {
        $this->client->disconnect();
    }

    /**
     * Clear vacation email cache used for sending automated replies with specific time difference.
     */
    public function clearVacationCache() {
        $this->client->put(self::VACATION_CACHE_LOCATION, "");
    }

    /**
     * Read plugin section from procmail file.
     *
     * Error codes:
     * <ul>
     *  <li>{@link ProcmailStorage::ERR_NO_CONNECTION}</li>
     *  <li>{@link ProcmailStorage::ERR_CANNOT_READ}</li>
     *  <li>{@link ProcmailStorage::ERR_INVALID_HASH}</li>
     *  <li>{@link ProcmailStorage::ERR_NO_SECTION}</li>
     * </ul>
     *
     * @return string|int procmail content or one of error codes
     * @see ProcmailStorage::getRubikSection()
     */
    public function getProcmailRules() {
        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        if (($procmail = $this->getProcmailFile()) === false) {
            return self::ERR_CANNOT_READ;
        }

        $section = $this->getRubikSection($procmail);

        return is_numeric($section) ? $section : $section[0];
    }

    /**
     * Write rules to procmail file.
     *
     * Error codes:
     * <ul>
     *  <li>{@link ProcmailStorage::ERR_NO_CONNECTION}</li>
     *  <li>{@link ProcmailStorage::ERR_CANNOT_WRITE}</li>
     *  <li>{@link ProcmailStorage::ERR_INVALID_HASH}</li>
     * </ul>
     *
     * @param string $rules
     * @return true|int true or one of error codes
     * @see ProcmailStorage::getRubikSection()
     */
    public function putProcmailRules($rules) {
        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        $contentStart = "";
        $contentEnd = "";

        if (($procmail = $this->getProcmailFile()) !== false) {

            $section = $this->getRubikSection($procmail);

            if (!is_numeric($section)) {
                // section was found, note start and end so we can replace the section content
                $contentStart = substr($procmail, 0, $section[1]);
                $contentEnd = substr($procmail, $section[2] + 1, strlen($procmail));
            } else if ($section === self::ERR_NO_SECTION) {
                // with no section assume first write, make a backup
                $this->backupProcmail($procmail);
                // put filter section at the end
                $contentStart = $procmail;
            } else {
                return $section;
            }
        }

        // ensure new line after rules
        if ($rules[strlen($rules) - 1] !== "\n") {
            $rules .= "\n";
        }

        // store hash in header
        $header = sprintf(self::RUBIK_SECTION_HEADER, $this->hashRules($rules));
        $footer = self::RUBIK_SECTION_FOOTER;

        // ensure new line before section
        if (!empty(trim($contentStart)) && $contentStart[strlen($contentStart) - 1] !== "\n") {
            $contentStart .= "\n";
        }

        // write new procmail content
        $content = $contentStart . $header . $rules . $footer . $contentEnd;

        if(!$this->client->put(self::PROCMAIL_FILE, $content)) {
            return self::ERR_CANNOT_WRITE;
        }

        if (!$this->client->chmod(0600, self::PROCMAIL_FILE)) {
            return self::ERR_CANNOT_WRITE;
        } else {
            return true;
        }
    }

    /**
     * Hash given string.
     *
     * @param string $rules
     * @return string hash
     */
    public function hashRules($rules) {
        return hash("md5", $rules);
    }

    /**
     * Extract filter plugin section from procmail content.
     *
     * Error codes:
     * <ul>
     *  <li>{@link ProcmailStorage::ERR_NO_SECTION} - procmail doesn't contain plugin section</li>
     *  <li>{@link ProcmailStorage::ERR_INVALID_HASH} - wrong section hash</li>
     * </ul>
     *
     * @param string $procmailrc file content
     * @return array|int array in format [content, startOffset, endOffset] or one of error codes
     */
    public function getRubikSection($procmailrc) {
        $regex = "/". self::RUBIK_HEADER_REGEX . self::RUBIK_CONTENT_REGEX . self::RUBIK_FOOTER_REGEX . "/m";

        if (!preg_match($regex, $procmailrc, $matches, PREG_OFFSET_CAPTURE)) {
            return self::ERR_NO_SECTION;
        }

        $hash = $matches[1][0];
        $rules = $matches[2][0];

        if (strtolower($this->hashRules($rules)) !== strtolower($hash)) {
            return self::ERR_INVALID_HASH;
        } else {
            return array($rules, $matches[0][1], $matches[0][1] + strlen($matches[0][0]));
        }
    }


    /**
     * Remove section from procmail file.
     */
    public function removeSection() {
        if (!$this->ensureConnection()) {
            return;
        }

        $procmail = $this->getProcmailFile();

        $regex = "/". self::RUBIK_HEADER_REGEX . self::RUBIK_CONTENT_REGEX . self::RUBIK_FOOTER_REGEX . "/m";


        if ($procmail !== false && !!preg_match($regex, $procmail, $matches, PREG_OFFSET_CAPTURE)) {
            $procmail = substr_replace($procmail, '', $matches[0][1], strlen($matches[0][0]));

            $this->client->put(self::PROCMAIL_FILE, $procmail);
        }
    }

    /**
     * Get list of vacation reply message in storage.
     *
     * Error codes:
     * <ul>
     *  <li>{@link ProcmailStorage::ERR_NO_CONNECTION}</li>
     *  <li>{@link ProcmailStorage::ERR_CANNOT_WRITE} - cannot create replies folder</li>
     *  <li>{@link ProcmailStorage::ERR_CANNOT_READ} - cannot read replies folder</li>
     *
     * @return string[]|int array of reply filenames or one of error codes
     * @see ProcmailStorage::VACATION_REPLIES_LOCATION
     */
    public function listVacationMessages() {
        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        if (!$this->client->makeDir(self::VACATION_REPLIES_LOCATION, true)) {
            return self::ERR_CANNOT_WRITE;
        }

        $files = $this->client->listFiles(self::VACATION_REPLIES_LOCATION);

        return $files !== null ? $files : self::ERR_CANNOT_READ;
    }

    /**
     * Read reply message from $filename.
     *
     * Error codes:
     * <ul>
     *  <li>{@link ProcmailStorage::ERR_NO_CONNECTION}</li>
     *  <li>{@link ProcmailStorage::ERR_CANNOT_READ}</li>
     * </ul>
     *
     * @param string $filename
     * @return string|int file content or one of error codes
     * @see ProcmailStorage::VACATION_REPLIES_LOCATION
     */
    public function getReply($filename) {
        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        $content = $this->client->get($this->getReplyPath($filename));

        if ($content === false) {
            return self::ERR_CANNOT_READ;
        } else {
            return $content;
        }
    }

    /**
     * Get complete path for reply message file.
     *
     * @param string $filename
     * @return string path
     * @see ProcmailStorage::VACATION_REPLIES_LOCATION
     */
    private function getReplyPath($filename) {
        return self::VACATION_REPLIES_LOCATION . "/$filename";
    }

    /**
     * Write $content to procmail backup file.
     *
     * @param string $content
     * @return bool success
     * @see ProcmailStorage::PROCMAIL_BACKUP_FILE
     */
    private function backupProcmail($content) {
        return $this->client->put(self::PROCMAIL_BACKUP_FILE, $content);
    }

    /**
     * Read procmail file.
     *
     * @return false|string file content or false on error
     * @see ProcmailStorage::PROCMAIL_FILE
     */
    private function getProcmailFile() {
        return $this->client->get(self::PROCMAIL_FILE);
    }

    /**
     * Ensure client is connected and logged in.
     *
     * @return bool
     */
    private function ensureConnection() {
        return $this->client->isConnected() || $this->client->authenticate($this->user, $this->password);
    }

}