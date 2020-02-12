<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;
use Rubik\Procmail\Rule\Rule;
use Rubik\Procmail\Rule\SpecialCondition;

class FilterParser
{
    public const RULES_REGEX =
          "/"
         ."^\s*:0(?'flags'[a-zA-z]*):(?'lockfile'\S*)\n"
         ."(?'conds'(?:^\*.*\n)*)^(?:(?:\s*{\s*\n"
         ."(?'sub_rule_action'(?:.*\n)*?)\s*})|(?'action'.*))$"
         ."/m";

    public const FILTER_REGEX =
          "/"
         ."^#START:(?'filter_start'.*)\n"
         ."(?'filter_content'(.*\n)*?)"
         ."#END:(?'filter_end'.*)$"
         ."/m";

    public const CONDITION_REGEX = "/^\* (?'section'(?:H \?\?)|(?:B \?\?))(?'negate'!)? (?'value'.*)$/";

    public const CONDITION_HEADER_REGEX = "/\(\^(?'field'.*?): \*<\?\((?'value'.*?)\)>\? \*\\$\)(?'has_or'\|)?/";

    /**
     * @param $input
     * @return array|null
     */
    public function parse($input)
    {
        // Get
        $matches = $this->matchAll(self::FILTER_REGEX, $input);
        if ($matches === null) {
            return null;
        }

        $filters = array();

        foreach ($matches as $filterMatch) {
            $filterContent = $filterMatch['filter_content'][0];

            $parsedFilter = $this->parseFilter($filterContent);

            if ($parsedFilter === null) {
                return null;
            } else {
                $filters[] = $parsedFilter;
            }
        }

        return $filters;
    }

    /**
     * @param $filterContent string
     * @return null
     */
    private function parseFilter($filterContent) {
        $filterContent = trim($filterContent);

        $filter = new FilterBuilder();

        // check if filter is enabled, all lines are commented out using # otherwise
        $isEnabled = $this->isEnabled($filterContent);
        if ($isEnabled === null) {
            return null;
        } else if (!$isEnabled) {
            // all lines commented out => uncomment
            $filterContent = substr(str_replace("\n#", "\n", $filterContent), 1);
        }

        $filter->setFilterEnabled($isEnabled);


        // extract individual rules forming one filter using regex
        $matches = $this->matchAll(self::RULES_REGEX, $filterContent);
        if ($matches === null) {
            return null;
        }

        // extract action common for all rules in a filter
        $filterAction = $this->parseAction($matches[0]);
        if ($filterAction === null || $filterAction->isEmpty()) {
            return null;
        }
        $filter->setActionBlock($filterAction);

        $filterConditionBlock = $this->parseConditionBlock($matches);
        if ($filterConditionBlock === null) {
            return null;
        }
        $filter->setConditions($filterConditionBlock);

        return $filter;
    }

    private function parseConditionBlock($rules) {
        $conditionBlock = new ConditionBlock();

        for ($i = 0; $i < count($rules); $i++) {

            $conds = trim($rules[$i]['conds'][0]);

            if (empty($conds)) {
                continue;
            }

            $matches = $this->matchAll(self::CONDITION_REGEX, $conds);
            if($matches === null) {
                return null;
            }

            $condition = null;

            if ($matches['section'][0] === SpecialCondition::ONLY_HEADER) {
                $condition = $this->parseHeaderCondition($matches['value'][0]);
            } else if ($matches['section'][0] === SpecialCondition::ONLY_BODY) {
                $condition = $this->parseBodyCondition($matches['value'][0]);
            }

            if ($condition === null) {
                return null;
            }

            $condition->negate = !empty($matches['negate']);

            $conditionBlock->addCondition($condition);
        }

        return $conditionBlock;
    }

    /**
     * @param $condition string
     * @return null|Condition
     * @see FilterBuilder::createHeaderCondition()
     */
    private function parseHeaderCondition($condition) {
        $condVal = trim($condition);

        $matches = $this->matchAll(self::CONDITION_HEADER_REGEX, $condVal);
        if ($matches === null) {
            return null;
        }

        $field = Field::getFieldFromText($matches['field']);
        if ($field === null) {
            return null;
        }

        $text = $matches['value'][0];
        $textMatches = array();
        $op = null;

        if (preg_match('^\.\*(?\'value\'.*)\.\*$', $text, $textMatches)) {
            $op = Operator::CONTAINS;
            $text = $textMatches['value'];
        } else if (preg_match('^(?\'value\'.*)\.\*$', $text, $textMatches)) {
            $op = Operator::STARTS_WITH;
            $text = $textMatches['value'];
        } else {
            $op = Operator::EQUALS;
        }

        if ($text !== preg_quote(stripslashes($text))) {
            $op = Operator::PLAIN_REGEX;
        }

        return Condition::create($field, $op, $text, false);
    }

    private function parseBodyCondition($condVal) {
        $condVal = trim($condVal);
    }

    /**
     * @param $rule array
     * @param FilterActionBlock|null $actionBlock
     * @return FilterActionBlock|null
     */
    private function parseAction($rule, &$actionBlock = null) {
        if (!empty($rule['action'])) {
            $action = trim($rule['action'][0]);

            if ($action === null) {
                return null;
            }

            if ($actionBlock === null) {
                $actionBlock = new FilterActionBlock();
            }

            if ($action === Rule::DISCARD_ACTION_ARG) { // discard to /dev/null
                if(!$actionBlock->addAction(Action::DISCARD, null)) {
                    return null;
                }
            } else if ($action[0] === "!") { // forward action
                $forwardEmails = explode(" ", trim(substr($action, 1)));

                foreach ($forwardEmails as $email) {
                    if (empty($email)) continue;

                    if(!$actionBlock->addAction(Action::FWD, $email)) {
                        return null;
                    }
                }
            } else { // mailbox name
                if(!$actionBlock->addAction(Action::MAILBOX, $action)) {
                    return null;
                }
            }

            return $actionBlock;
        } else if (!empty($rule['sub_rule_action'])) {
            $matches = $this->matchAll(self::RULES_REGEX, $rule['sub_rule_action'][0]);
            if ($matches === null) {
                return null;
            }

            $count = count($matches);
            for ($i = 0; $i < $count; $i++) {
                $rule = $matches[$i];
                $conds = trim($rule['conds'][0]);
                $flags = trim($rule['flags'][0]);

                // action sub-rules must have no conditions
                if (!empty($conds)) {
                    return null;
                }

                // action rules should have c(copy) flag except the last one
                if (!(($flags === 'c' && $i < $count - 1) || ($i === $count - 1 && empty($flags)))) {
                    return null;
                }

                if ($this->parseAction($rule, $actionBlock) === null) {
                    return null;
                }
            }

            return $actionBlock;
        } else {
            return null;
        }
    }

    private function isEnabled($filterContent) {
        $filterContent = trim($filterContent);

        $commented = $filterContent[0] === '#';

        $lines = explode("\n", $filterContent);

        foreach ($lines as $line) {

            $lineCommented = !empty($line) && $line[0] === '#';

            if ($lineCommented !== $commented) {
                return null;
            }
        }

        return !$commented;
    }

    private function matchAll($regex, $input) {
        $input = trim($input);

        $match = preg_match_all($regex, $input, $matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE|PREG_UNMATCHED_AS_NULL);

        if ($match === FALSE || $match === 0) {
            return null;
        }

        if ($match > 0) {
            $lastMatch = $matches[count($matches) - 1];
            $lastMatchEnd = $lastMatch[0][1] + strlen($lastMatch[0][0]);

            // check if all input was consumed
            if ($lastMatchEnd != strlen($input)) {
                return null;
            }
        }

        return $matches;
    }
}