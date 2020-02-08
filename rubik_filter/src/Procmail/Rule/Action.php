<?php

namespace Rubik\Procmail\Rule;

final class Action {
    public const FWD = "_fwd";
    public const PIPE = "_pipe";
    public const MAILBOX = "_mailbox";
    public const RULE_BLOCK = "_ruleblock";
    public const DISCARD = "_discard";

    public const values = array(self::FWD, self::PIPE, self::MAILBOX, self::RULE_BLOCK, self::DISCARD);

    public static function isValid($action) {
        return array_search($action, self::values) !== false;
    }
}