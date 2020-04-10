<?php

namespace Rubik\Procmail\Constants;

/**
 * Filter condition operator constants.
 *
 * @package Rubik\Procmail\Constants
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
final class Operator
{
    const CONTAINS = 'contains';
    const EQUALS = 'equals';
    const STARTS_WITH = 'starts_with';
    const PLAIN_REGEX = 'regex';

    /** @var string[] valid operator constants */
    public const values = array(self::CONTAINS, self::EQUALS, self::STARTS_WITH, self::PLAIN_REGEX);

    /**
     * Check if given $op is a valid operator constant.
     *
     * @param $op string
     * @return bool
     */
    public static function isValid($op) {
        return array_search($op, self::values) !== false;
    }
}
