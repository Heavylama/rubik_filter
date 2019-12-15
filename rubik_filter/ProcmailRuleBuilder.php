<?php

namespace Rubik\Procmail;

class ProcmailRuleBuilder
{

    /**
     * @var ProcmailRule
     */
    private $rule;

    public function __construct()
    {
        $this->rule = new ProcmailRule();
    }

    public function reset()
    {
        $this->rule->resetRule();
    }


    public function addCondition($field, $op, $value, $negate = false)
    {
        // check if valid constant was used for $field and $op
        if (!Field::isValid($field) || !ProcmailOperator::isValid($op)) {
            return false;
        }

        if ($op == ProcmailOperator::PLAIN_REGEX) {
            // validate regex
            if (preg_match($value, null) === false) {
                return false;
            }
        } else {
            // trim whitespace and escape regex special characters otherwise
            $value = preg_quote(trim($value));
        }

        $special_cond = array();

        // we can use special condition for negation
        if ($negate == true) {
            $special_cond[] = SpecialCondition::INVERT;
        }

        if ($field == Field::BODY) {
            // only body
            $special_cond[] = SpecialCondition::ONLY_BODY;

            $condition = $this->createBodyCondition($value, $op);
        } else {
            // all other fields refer to header only
            $special_cond[] = SpecialCondition::ONLY_HEADER;

            switch ($field) {
                case Field::FROM:
                    $fieldText = "(From|Reply-to)";
                    break;
                default:
                    $fieldText = "";
                    break;
            }

            $condition = $this->createHeaderCondition($fieldText, $value, $op);
        }

        return $this->rule->addCondition($condition, $special_cond);
    }

    public function actionMailbox($mailboxName)
    {
        $this->rule->setAction(Action::MAILBOX, $mailboxName);
    }


    public function makeRule()
    {
        return $this->rule->make();
    }

    private function createBodyCondition($value, $op)
    {
        switch ($op) {
            case ProcmailOperator::STARTS_WITH:
                return "^$value";
            case ProcmailOperator::EQUALS:
                return "^$value$";
            case ProcmailOperator::PLAIN_REGEX:
            case ProcmailOperator::CONTAINS:
            default:
                return $value;
        }
    }

    private function createHeaderCondition($fieldText, $value, $op)
    {
        switch ($op) {
            case ProcmailOperator::CONTAINS:
                $value = ".*$value.*";
                break;
            case ProcmailOperator::STARTS_WITH:
                $value = "$value.*";
                break;
            case ProcmailOperator::PLAIN_REGEX:
            case ProcmailOperator::EQUALS:
            default:
                break;
        }

        return "^$fieldText: *<?($value)>?$";
    }
}