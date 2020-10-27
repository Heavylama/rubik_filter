<?php

namespace Rubik\Procmail\Constants;

/**
 * Recipe flag constants.
 *
 * Flag source: https://linux.die.net/man/5/procmailrc
 *
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 * @package Rubik\Procmail\Constants
 */
final class Flags
{
    const GREP_HEADER = "H";
    const GREP_BODY = "B";
    const CASE_SENSITIVE = "D";
    const LAST_MATCHED = "A";
    const LAST_MATCHED_SUCCESS = "a";
    const LAST_NOT_MATCHED = "E";
    const LAST_NOT_MATCHED_SUCCESS = "e";
    const FEED_PIPE_HEADER = "h";
    const FEED_PIPE_BODY = "b";
    const PIPE_IS_FILTER = "f";
    const COPY = "c";
    const WAIT_FINISH = "w";
    const WAIT_FINISH_NO_MSG = "W";
    const IGNORE_WRITE_ERR = "i";
    const RAW_MODE = "r";

    /** @var string[] valid flags */
    private const values = array(
        self::GREP_BODY, self::GREP_HEADER, self::COPY, self::CASE_SENSITIVE, self::LAST_MATCHED,
        self::LAST_MATCHED_SUCCESS, self::LAST_NOT_MATCHED, self::LAST_NOT_MATCHED_SUCCESS, self::FEED_PIPE_BODY,
        self::FEED_PIPE_HEADER, self::PIPE_IS_FILTER, self::WAIT_FINISH, self::WAIT_FINISH_NO_MSG,
        self::IGNORE_WRITE_ERR, self::RAW_MODE
    );

    /**
     * Check if characters of given $flag string are all valid flags.
     *
     * @param string $flag
     * @return bool
     */
    public static function isValid($flag) {
        for ($i = 0; $i < strlen($flag); $i++)
        {
            if (array_search($flag[$i], self::values) === false) return false;
        }
        return true;
    }
}
