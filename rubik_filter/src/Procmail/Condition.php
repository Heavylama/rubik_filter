<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;

/**
 * Holder class for one procmail rule condition.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 * @see ConditionBlock
 */
class Condition
{
    /** @var string one of {@link Field} constants */
    public $field;
    /** @var string one of {@link Operator} constants */
    public $op;
    /** @var string condition value */
    public $value;
    /** @var bool true to negate the condition result */
    public $negate;
    /** @var null|string in case $field is set to {@link Field::CUSTOM} this contains the field name */
    public $customField;

    /**
     * Condition constructor.
     *
     * @param $field string
     * @param $op string
     * @param $value string
     * @param $negate bool
     * @param $customField null
     * @see Condition::create()
     */
    private function __construct($field, $op, $value, $negate, $customField = null)
    {
        $this->field = $field;
        $this->op = $op;
        $this->value = $value;
        $this->negate = $negate;
        $this->customField = $customField;
    }

    /**
     * Check if given arguments are valid and if so create and instance of Condition.
     *
     * @param $field string one of {@link Field} constant
     * @param $op string one of {@link Operator} constant
     * @param $value string|string[] condition value
     * @param $negate bool true to negate the condition
     * @param $escape bool true (default) to trim whitespace and quote special regex characters
     * @param $customField string if $field is set to {@link Field::CUSTOM} this is used as header field name
     * @return Condition|null Condition instance if arguments are valid, null on error
     */
    public static function create($field, $op, $value, $negate, $escape = true, $customField = null) {
        if (!Field::isValid($field) || !Operator::isValid($op)) {
            return null;
        }

        if ($op === Operator::PLAIN_REGEX) {
            if (!self::checkParenthesesPairs($value)) {
                return null;
            }
        } else if ($escape) {
            // trim whitespace and escape regex special characters otherwise
            $value = self::ere_quote(trim($value));

            if ($field === Field::BODY) {
                // replace \n with procmail ^ for multiline body
                $value = str_replace("\n", "^", $value);
            }
        }

        // strip value of unprintable characters
        $value = self::strip_unprintable($value);

        if ($customField !== null) {
            $customField = self::strip_unprintable(rtrim(trim($customField), ":"));

            if (!self::checkParenthesesPairs($customField)) {
                return null;
            }
        }

        return new Condition($field, $op, $value, $negate, $customField);
    }

    /**
     * Remove unprintable characters from string.
     *
     * @param $string string unfiltered input
     * @return string filtered output
     */
    public static function strip_unprintable($string) {
        return filter_var($string, FILTER_UNSAFE_RAW,FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
    }

    /**
     * Quote special characters according to egrep used by procmail.
     *
     * @param $input string input string
     * @return string quoted string
     */
    public static function ere_quote($input) {
        return addcslashes($input, ".*+?^$[]-|()\\");
    }

    /**
     * Unquotes special characters according to egrep used by procmail.
     *
     * @param $input string input string
     * @return string unquoted string
     */
    public static function ere_unquote($input) {
        return stripcslashes($input);
    }

    /**
     * Check if all parentheses in input $string are closed ignoring escaped ones.
     *
     * @param $string string regex pattern
     * @return bool
     */
    public static function checkParenthesesPairs($string) {
        $level = 0;

        foreach (str_split($string) as $key => $ch) {
            if ($ch === "(" && !self::isEscapedInRegex($string, $key)) $level++;
            if ($ch === ")" && !self::isEscapedInRegex($string, $key)) $level--;
        }

        return $level === 0;
    }

    /**
     * Check if character at given $startIndex is escaped in regex pattern.
     * Goes through string until start or a different character than '\' is encountered.
     *
     * @param $input string pattern to search through
     * @param $startIndex int index of target character
     * @return bool true if escaped, false otherwise
     */
    public static function isEscapedInRegex($input, $startIndex)
    {
        $escaped = false;

        while (($startIndex--) >= 0) {
            if ($input[$startIndex] === "\\") {
                $escaped = !$escaped;
            } else {
                break;
            }
        }

        return $escaped;
    }
}
