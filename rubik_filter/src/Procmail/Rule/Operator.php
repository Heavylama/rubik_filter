<?php

namespace Rubik\Procmail\Rule;

final class Operator
{
    const CONTAINS = 'contains';
    const EQUALS = 'equals';
    const STARTS_WITH = 'starts_with';
    const PLAIN_REGEX = 'regex';

    public const values = array(self::CONTAINS, self::EQUALS, self::STARTS_WITH, self::PLAIN_REGEX);

    public static function isValid($op) {
        return array_search($op, self::values) !== false;
    }
}
