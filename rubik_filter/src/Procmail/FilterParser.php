<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Flags;
use Rubik\Procmail\Rule\Operator;
use Rubik\Procmail\Rule\Rule;
use Rubik\Procmail\Rule\SpecialCondition;

class FilterParser
{
    public const RULES_REGEX =
          "/"
         ."^\s*:0(?'flags'[a-zA-z]*):(?'lockfile'\S*)\\n"
         ."(?'conds'(?:^\*.*\\n)*)^(?:(?:\s*{\s*\\n"
         ."(?'sub_rule_action'(?:.*\\n)*?)\s*})|(?'action'.*))$"
         ."/m";

    public const FILTER_REGEX =
          "/"
         ."^#START:(?'filter_start'.*)\\n"
         ."(?'filter_content'(.*\\n)*?)"
         ."#END:(?'filter_end'.*)$"
         ."/m";

    public const CONDITION_REGEX = "/^\* (?'section'(?:H \?\?)|(?:B \?\?)) *(?'negate'!)? *(?'value'.*)$/m";

    public const CONDITION_HEADER_REGEX = "/\(\^(?'field'.*?) \*\((?'value'.*?)\) \*\\$\)(?'has_or'\|)?/";
    public const CONDITION_BODY_EQUALS = "/^\(\^\^(?'value'.*)\^\^\)$/";
    public const CONDITION_BODY_STARTS_WITH = "/^\(\^\^(?'value'.*)\)$/";
    public const CONDITION_BODY_CONTAINS = "/^\((?'value'.*)\)$/";

    /**
     * @param $input
     * @return array|null
     */
    public function parse($input)
    {
        // Get
        $matches = $this->matchFilters($input);
        if ($matches === null) {
            return null;
        }

        $filters = array();

        foreach ($matches as $filterMatch) {
            $filterContent = $filterMatch['filter_content'][0];

            /** @var Filter $parsedFilter */
            $parsedFilter = $this->parseFilter($filterContent);

            if ($parsedFilter === null) {
                return null;
            } else {
                $parsedFilter->setName($filterMatch['filter_start'][0]);
                $filters[] = $parsedFilter;
            }
        }

        return $filters;
    }

    private function matchFilters($procmail) {
        return $this->matchAtLeastOne(self::FILTER_REGEX, $procmail);
    }

    /**
     * @param $filterContent string
     * @return null
     */
    private function parseFilter($filterContent) {
        $filterContent = trim($filterContent);

        $filter = new Filter();

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
        $matches = $this->matchAtLeastOne(self::RULES_REGEX, $filterContent);
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
        $filter->setConditionBlock($filterConditionBlock);

        // check if filter isn't in fact vacation
        if(isset($filterAction->getActions()[Action::PIPE])) {
            $vac = Vacation::toVacation($filter);
            if ($vac !== null) {
                $filter = $vac;
            }
        }

        return $filter;
    }

    private function parseConditionBlock($rules) {
        $conditionBlock = new ConditionBlock();

        $ruleHasMoreThanOneCondition = false;
        $conditionContainsOrOperator = false;
        $elseFlagsAreSet = null;

        $count = count($rules);

        if ($count === 0) {
            $elseFlagsAreSet = false;
        }

        for ($i = 0; $i < $count; $i++) {

            $rule = $rules[$i];

            $containsElse = strpos($rule['flags'][0], Flags::LAST_NOT_MATCHED) !== false;
            if ($i === 0 && $containsElse) {
                return null;
            }

            if ($i > 0) {
                if ($elseFlagsAreSet !== null && $elseFlagsAreSet !== $containsElse) {
                    return null;
                } else {
                    $elseFlagsAreSet = $containsElse;
                }
            }

            // Condition parsing
            $inputConditionBlock = trim($rule['conds'][0]);

            if (empty($inputConditionBlock)) {
                continue;
            }

            $matches = $this->matchAtLeastOne(self::CONDITION_REGEX, $inputConditionBlock);
            if($matches === null) {
                return null;
            }

            $ruleHasMoreThanOneCondition |= (count($matches) > 1);

            foreach ($matches as $key => $matchedCondition) {
                if (strpos($matchedCondition['section'][0], SpecialCondition::ONLY_HEADER) !== false) {
                    $parsedConditions = $this->parseHeaderCondition($matchedCondition['value'][0]);
                } else if (strpos($matchedCondition['section'][0], SpecialCondition::ONLY_BODY) !== false) {
                    $parsedConditions = $this->parseBodyCondition($matchedCondition['value'][0]);
                } else {
                    $parsedConditions = null;
                }

                if ($parsedConditions === null) {
                    return null;
                }

                $conditionContainsOrOperator |= $parsedConditions[1];

                foreach ($parsedConditions[0] as $parsedCondition) {
                    if ($i < count($parsedConditions[0]) - 1 && !$conditionContainsOrOperator) {
                        return null;
                    }

                    $parsedCondition->negate = !empty($matchedCondition['negate'][0]);

                    $conditionBlock->addCondition($parsedCondition);
                }
            }
        }

        if (!$ruleHasMoreThanOneCondition && ($conditionContainsOrOperator || $elseFlagsAreSet)) {
            $conditionBlock->setType(ConditionBlock::OR);
        } else if (!$elseFlagsAreSet && !$conditionContainsOrOperator) {
            $conditionBlock->setType(ConditionBlock::AND);
        } else {
            return null;
        }

        return $conditionBlock;
    }

    /**
     * @param $condition string
     * @return null|array containing created conditions and whether conditions were separated by OR
     * @see Filter::createHeaderCondition()
     */
    private function parseHeaderCondition($condition) {
        $condVal = trim($condition);

        $matches = $this->matchAtLeastOne(self::CONDITION_HEADER_REGEX, $condVal);
        if ($matches === null) {
            return null;
        }

        $shouldHaveOr = count($matches) > 1;

        $parsedConditions = array();

        foreach ($matches as $key => $match) {
            $field = Field::getFieldFromText($match['field'][0]);
            if ($field === null) {
                return null;
            }

            $text = $match['value'][0];
            $textMatches = array();
            $op = null;

            if (preg_match('/^\.\*(?\'value\'.*)\.\*$/', $text, $textMatches)) {
                $op = Operator::CONTAINS;
                $text = $textMatches['value'];
            } else if (preg_match('/^(?\'value\'.*)\.\*$/', $text, $textMatches)) {
                $op = Operator::STARTS_WITH;
                $text = $textMatches['value'];
            } else {
                $op = Operator::EQUALS;
            }

            if ($this->containsUnescapedRegex($text)) {
                $op = Operator::PLAIN_REGEX;
                $text = $match['value'][0];
            }

            if ($shouldHaveOr && !isset($match['has_or']) && $key < count($matches) - 1) {
                return null;
            }

            $parsedCondition = Condition::create($field, $op, $text, false, false);

            if ($parsedCondition === null) {
                return null;
            }

            $parsedConditions[] = $parsedCondition;
        }

        return array($parsedConditions, $shouldHaveOr);
    }

    private function parseBodyCondition($condVal) {
        $condVal = trim($condVal);

        $conditions = $this->splitBodyConditions($condVal);
        if ($conditions === null) {
            return null;
        }

        $parsedConditions = array();

        foreach ($conditions[0] as $condition) {
            if (preg_match(self::CONDITION_BODY_EQUALS, $condition, $match)) {
                $op = Operator::EQUALS;
            } else if (preg_match(self::CONDITION_BODY_STARTS_WITH, $condition, $match)) {
                $op = Operator::STARTS_WITH;
            } else if (preg_match(self::CONDITION_BODY_CONTAINS, $condition, $match)) {
                $op = Operator::CONTAINS;
            } else {
                return null;
            }

            if ($this->containsUnescapedRegex($match['value'])) {
                $op = Operator::PLAIN_REGEX;
                $match['value'] = substr($condition, 1, strlen($condition) - 2);
            }

            if (!isset($op)) {
                return null;
            }

            $parsedCondition = Condition::create(Field::BODY, $op, $match['value'], false, false);

            $parsedConditions[] = $parsedCondition;
        }

        return array($parsedConditions, $conditions[1]);
    }

    private function containsUnescapedRegex($val) {
        return $val !== preg_quote(stripslashes($val));
    }

    /**
     * @param $condition string
     * @return null|array
     */
    private function splitBodyConditions($condition) {
        $condition = trim($condition);

        if ($condition[0] !== "(") {
            return null;
        }

        $conditions = array();

        $nestedLevel = 0;
        $condIndex = -1;

        $isOrBlock = false;

        foreach (str_split($condition) as  $key => $ch) {
            if ($ch === "(") {
                $nestedLevel++;

                if ($nestedLevel === 1) {
                    // each '(' (except the first one) on first level must be preceded by '|'
                    if ($key > 0) {
                        if ($condition[$key - 1] !== "|") {
                            return null;
                        } else {
                            $isOrBlock = true;
                        }
                    }

                    $condIndex++;
                    $conditions[] = "";
                }
            }

            if ($nestedLevel > 0) {
                $conditions[$condIndex] .= $ch;
            }

            if ($ch === ")") {
                $nestedLevel--;
            }
        }

        if ($nestedLevel !== 0) {
            return null;
        }

        return array($conditions, $isOrBlock);
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
            } else if($action[0] === "|") { // pipe
                if(!$actionBlock->addAction(Action::PIPE, trim(substr($action, 1)))) {
                    return null;
                }
            } else { // mailbox name
                if(!$actionBlock->addAction(Action::MAILBOX, $action)) {
                    return null;
                }
            }

            return $actionBlock;
        } else if (!empty($rule['sub_rule_action'])) {
            $matches = $this->matchAtLeastOne(self::RULES_REGEX, $rule['sub_rule_action'][0]);
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

    private function matchAtLeastOne($regex, $input) {
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