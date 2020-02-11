<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

class Condition
{
    /** @var string */
    public $field;
    /** @var string */
    public $op;
    /** @var string */
    public $value;
    /** @var bool */
    public $negate;

    private function __construct($field, $op, $value, $negate)
    {
        $this->field = $field;
        $this->op = $op;
        $this->value = $value;
        $this->negate = $negate;
    }

    public static function create($field, $op, $value, $negate) {
        if (!Field::isValid($field) || !Operator::isValid($op)) {
            return null;
        }

        if ($op === Operator::PLAIN_REGEX) {
            // validate regex
            if (preg_match($value, null) === false) {
                return null;
            }
        } else {
            // trim whitespace and escape regex special characters otherwise
            $value = preg_quote(trim($value));

            if ($field == Field::BODY) {
                // replace \n with procmail ^ for multiline body
                $value = str_replace("\n", "^", $value);
            }

            $value = stripslashes($value);
        }

        return new Condition($field, $op, $value, $negate);
    }
}