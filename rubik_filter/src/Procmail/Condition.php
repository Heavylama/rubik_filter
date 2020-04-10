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

    /**
     * Condition constructor.
     *
     * @param $field string
     * @param $op string
     * @param $value string
     * @param $negate bool
     */
    private function __construct($field, $op, $value, $negate)
    {
        $this->field = $field;
        $this->op = $op;
        $this->value = $value;
        $this->negate = $negate;
    }

    /**
     * Check if given arguments are valid and if so create and instance of Condition.
     *
     * @param $field string one of {@link Field} constant
     * @param $op string one of {@link Operator} constant
     * @param $value string condition value
     * @param $negate bool true to negate the condition
     * @param bool $escape true (default) to trim whitespace and quote special regex characters
     * @return Condition|null Condition instance if arguments are valid, null on error
     */
    public static function create($field, $op, $value, $negate, $escape = true) {
        if (!Field::isValid($field) || !Operator::isValid($op)) {
            return null;
        }

        if ($op === Operator::PLAIN_REGEX) {
            // validate regex
            if (preg_match("/$value/", null) === false) {
                return null;
            }
        } else if ($escape) {
            // trim whitespace and escape regex special characters otherwise
            $value = preg_quote(trim($value), "/");

            if ($field == Field::BODY) {
                // replace \n with procmail ^ for multiline body
                $value = str_replace("\n", "^", $value);
            }
        }

        return new Condition($field, $op, $value, $negate);
    }
}