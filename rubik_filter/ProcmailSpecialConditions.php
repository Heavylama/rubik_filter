<?php

namespace Rubik\Procmail;

/**
 * To be used as plain text, special conditions can be escaped by a leading '\\'
 * @todo variablename ?? special condition missing
 */
final class SpecialCondition
{
    public const INVERT = "!";
    public const EVAL_FIRST = "$";
    public const USE_EXITCODE = "?";
    public const LENGTH_LESS = "<";
    public const LENGTH_MORE = ">";
    public const ONLY_HEADER = "B ??";
    public const ONLY_BODY = "H ??";
    public const BOTH_HEADER_BODY = "BH ??";

    private const values = array(
        self::INVERT, self::EVAL_FIRST, self::USE_EXITCODE, self::LENGTH_LESS, self::LENGTH_MORE,
        self::ONLY_BODY, self::ONLY_HEADER, self::BOTH_HEADER_BODY
    );

    public static function isValid($special_cond_type)
    {
        return empty($special_cond_type) || array_search($special_cond_type, self::values) !== false;
    }
}
