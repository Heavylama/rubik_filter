<?php


namespace Rubik\Storage;

use phpseclib\Net\SFTP;

class ProcmailStorage
{

    public const PROCMAIL_FILE = ".procmailrc";
    public const PROCMAIL_BACKUP_FILE = ".bak.procmailrc";
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

    public const ERR_NO_FILE = 0;
    public const ERR_WRONG_HASH = 1;
    public const ERR_NO_SECTION = 2;
    public const ERR_NO_CONNECTION = 3;
    public const ERR_EMPTY_RULES = 4;

    /**
     * @var StorageInterface
     */
    private $client;
    private $user;
    private $password;

    public function __construct($client, $login, $pw)
    {
        $this->client = $client;
        $this->user = $login;
        $this->password = $pw;
    }

    public function __destruct()
    {
        $this->client->disconnect();
    }

    public function getProcmailRules() {
        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        if (!($procmail = $this->getProcmailFile())) {
            return self::ERR_NO_FILE;
        }

        $section = $this->getRubikSection($procmail);

        return is_numeric($section) ? $section : $section[0];
    }

    public function putProcmailRules($rules) {
        if (empty($rules)) {
            return self::ERR_EMPTY_RULES;
        }

        if (!$this->ensureConnection()) {
            return self::ERR_NO_CONNECTION;
        }

        $contentStart = "";
        $contentEnd = "";

        if (($procmail = $this->getProcmailFile())) {
            $section = $this->getRubikSection($procmail);

            if (!is_numeric($section)) {
                // section was found, note start and end so we can replace the section content
                $contentStart = substr($procmail, 0, $section[1]);
                $contentEnd = substr($procmail, $section[2] + 1, strlen($procmail));
            } else {
                // with no section assume first write, make a backup
                $this->backupProcmail($procmail);
                // put filter section at the end
                $contentStart = $procmail;
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

        return $this->client->put(self::PROCMAIL_FILE, $content);
    }

    public function hashRules($rules) {
        return hash("md5", $rules);
    }

    public function getRubikSection($procmailrc) {
        $regex = "/". self::RUBIK_HEADER_REGEX . self::RUBIK_CONTENT_REGEX . self::RUBIK_FOOTER_REGEX . "/m";
        //$regex = str_replace("#","\#", $regex);

        if (!preg_match($regex, $procmailrc, $matches, PREG_OFFSET_CAPTURE)) {
            return self::ERR_NO_SECTION;
        }

        $hash = $matches[1][0];
        $rules = $matches[2][0];

        if (strtolower($this->hashRules($rules)) !== strtolower($hash)) {
            return self::ERR_WRONG_HASH;
        } else {
            return array($rules, $matches[0][1], $matches[0][1] + strlen($matches[0][0]));
        }
    }

    private function backupProcmail($content) {
        return $this->client->put(self::PROCMAIL_BACKUP_FILE, $content);
    }

    private function getProcmailFile() {
        return $this->client->get(self::PROCMAIL_FILE);
    }

    private function ensureConnection() {
        return $this->client->isConnected() || $this->client->login($this->user, $this->password);
    }
}