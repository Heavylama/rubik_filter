<?php

namespace Rubik\Procmail\Constants;

/**
 * Constants for changing default behaviour of procmail condition line.
 *
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
final class SpecialCondition
{
    /** @var string Negate condition result */
    public const INVERT = "!";
    /** @var string First substitute variables starting with $ then reparse */
    public const EVAL_FIRST = "$";
    /** @var string Use command exit code as condition result */
    public const USE_EXITCODE = "?";
    /** @var string Check email length less than X */
    public const LENGTH_LESS = "<";
    /** @var string Check email length more than X */
    public const LENGTH_MORE = ">";
    /** @var string Use condition regex only on email header fields */
    public const ONLY_HEADER = "H ??";
    /** @var string Use condition regex only on email body */
    public const ONLY_BODY = "B ??";
    /** @var string Use condition regex on both body and header fields */
    public const BOTH_HEADER_BODY = "BH ??";

    /** @var array Valid special condition constants */
    private const values = array(
        self::INVERT, self::EVAL_FIRST, self::USE_EXITCODE, self::LENGTH_LESS, self::LENGTH_MORE,
        self::ONLY_BODY, self::ONLY_HEADER, self::BOTH_HEADER_BODY
    );

    /**
     * Check if $special_cond_type is one of valid constants.
     *
     * @param $special_cond_type string
     * @return bool
     */
    public static function isValid($special_cond_type)
    {
        return empty($special_cond_type) || array_search($special_cond_type, self::values) !== false;
    }
}
