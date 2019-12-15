<?php

namespace Rubik\Procmail;

class Operator
{
    const CONTAINS = 'contains';
    const EQUALS = 'equals';
    const EXISTS = 'exists';
    const STARTS_WITH = 'starts_with';
    const PLAIN_REGEX = 'regex';

    private const values = array(self::CONTAINS, self::EQUALS, self::EXISTS,
        self::STARTS_WITH, self::PLAIN_REGEX);

    public static function isValid($op) {
        return array_search($op, self::values) !== false;
    }
}
