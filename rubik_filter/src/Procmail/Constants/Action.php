<?php

namespace Rubik\Procmail\Constants;

/**
 * Rule actions.
 *
 * @package Rubik\Procmail\Constants
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
final class Action {
    /** @var string Forward to email */
    public const FWD = "_fwd";
    /** @var string Forward to email without mailer */
    public const FWD_SAFE = "_fwd_safe";
    /** @var string Pipe to command */
    public const PIPE = "_pipe";
    /** @var string Save to mailbox */
    public const MAILBOX = "_mailbox";
    /** @var string Multiple actions in a sub-block */
    public const RULE_BLOCK = "_ruleblock";
    /** @var string Discard email */
    public const DISCARD = "_discard";

    /** @var string[] valid actions */
    public const values = array(self::FWD, self::PIPE, self::MAILBOX, self::RULE_BLOCK, self::DISCARD, self::FWD_SAFE);

    /**
     * Check if $action is valid.
     *
     * @param string $action
     * @return bool
     */
    public static function isValid($action) {
        return array_search($action, self::values) !== false;
    }
}