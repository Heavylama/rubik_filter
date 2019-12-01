<?php

namespace Rubik\Procmail;

final class Action {
    public const FWD = "!";
    public const PIPE = "|";
    public const MAILBOX = "";


    private const values = array(self::FWD, self::PIPE, self::MAILBOX);

    public static function isValid($action) {
        return array_search($action, self::values) !== false;
    }
}