<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Flags;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Constants\SpecialCondition;
use Rubik\Procmail\Vacation\Vacation;

/**
 * Used for parsing plugin-generated procmail code.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class FilterParser
{

    /** @var string extracts individual filters forming a plugin section */
    private const FILTER_REGEX =
        "/"
        ."^#START:(?'filter_start'.*)\\n"
        ."(?'filter_content'(.*\\n)*?)"
        ."#END:(?'filter_end'.*)$"
        ."/m";

    /** @var string extracts individual rules forming a filter */
    private const RULES_REGEX =
          "/"
         ."^\s*:0(?'flags'[a-zA-z]*)(:(?'lockfile'\S*))?\\n"
         ."(?'conds'(?:^\*.*\\n)*)^(?:(?:\s*{\s*\\n"
         ."(?'sub_rule_action'(?:.*\\n)*?)\s*})|(?'action'.*))$"
         ."/m";

    /** @var string matches block containing decode variables */
    private  const DECODE_BLOCK_REGEX =
         "/^#DECODE_BLOCK_START\\n"
         .".*"
         ."#DECODE_BLOCK_END$"
         ."/ms";

    /** @var string extracts individual conditions forming a rule */
    private const CONDITION_REGEX = "/^\* (?'section'(?:H(EADER_D)? \?\?)|(?:B(ODY_D)? \?\?)) *(?'negate'!)? *(?'value'.*)$/m";
    /** @var string extracts individual parts forming a header condition */
    private const CONDITION_HEADER_REGEX = "/\(\^(?'field'.*?) \*(?'value'.*)\)/";
    /** @var string matches body condition with 'equals' operator */
    private const CONDITION_BODY_EQUALS = "/^\(\^\^(?'value'.*)\^\^\)$/";
    /** @var string matches body condition with 'starts with' operator */
    private const CONDITION_BODY_STARTS_WITH = "/^\(\^\^(?'value'.*)\)$/";
    /** @var string matches body condition with 'contains' operator */
    private const CONDITION_BODY_CONTAINS = "/^\((?'value'.*)\)$/";

    /**
     * Shorthand for creating an instance of parser and parsing the input.
     *
     * @param $input string procmail
     * @param $continueOnError bool
     * @return Filter[]|null filters or null on error
     * @see FilterParser::parse
     */
    public static function parseFilters($input,  $continueOnError = false) {
        $parser = new FilterParser();
        return $parser->parse($input, $continueOnError);
    }

    /**
     * Parse plugin filters from procmail code.
     *
     * @param $input string procmail text
     * @param $continueOnError bool if set to true instead of immediately returning null on error tries to parse rest of filters
     * @return Filter[]|null array of parsed filters or null on parse error
     */
    public function parse($input, $continueOnError = false)
    {
        // remove decoding section if present, not important for parsing
        $input = $this->removeDecodeSection($input);

        // trim whitespace
        $input = trim($input);

        if (empty($input)) {
            return array();
        }

        // Extract individual filters
        $matches = $this->matchFilters($input);
        if ($matches === null) {
            return null;
        }

        $filters = array();

        // try to parse individual matches as filters
        foreach ($matches as $filterMatch) {
            $filterContent = $filterMatch['filter_content'][0];

            /** @var Filter $parsedFilter */
            $parsedFilter = $this->parseFilter($filterContent);

            if ($parsedFilter !== null) {
                $parsedFilter->setName($filterMatch['filter_start'][0]);
                $filters[] = $parsedFilter;
            } else if (!$continueOnError){
                // error parsing
                return null;
            }
        }

        return $filters;
    }

    /**
     * Remove decoding block if present, as it isn't important for filter parsing.
     *
     * @param $procmail string input procmail code
     * @return string procmail code without decoding block
     */
    private function removeDecodeSection($procmail) {
        return preg_replace(self::DECODE_BLOCK_REGEX, "", $procmail);
    }

    /**
     * Match provided text with filter regex.
     *
     * @param $procmail
     * @return null
     */
    private function matchFilters($procmail) {
        return $this->matchAtLeastOne(self::FILTER_REGEX, $procmail);
    }

    /**
     * Parse single filter.
     *
     * @param $filterContent string procmail text containing one filter
     * @return Filter|null parsed filter or null on error
     */
    private function parseFilter($filterContent) {
        $filterContent = trim($filterContent);

        $filter = new Filter();

        // check if filter is enabled, all lines are commented out using # otherwise
        $isEnabled = $this->isEnabled($filterContent);
        if ($isEnabled === null) { // some lines commented out and some not
            return null;
        }
        $filter->setFilterEnabled($isEnabled);

        if (!$isEnabled) {
            // all lines commented out => uncomment for parsing
            $filterContent = substr(str_replace("\n#", "\n", $filterContent), 1);
        }

        // extract individual rules forming one filter using regex
        $matches = $this->matchAtLeastOne(self::RULES_REGEX, $filterContent);
        if ($matches === null) {
            return null;
        }

        // parse conditions
        $filterConditionBlock = $this->parseConditionBlock($matches);
        if ($filterConditionBlock === null) {
            return null;
        }

        // if top-level rule contains c flag, post action behaviour is continue
        if (strpos($matches[0]['flags'][0], Flags::COPY) !== FALSE) {
            $filter->setPostActionBehaviour(Filter::POST_CONTINUE);
        }

        // extract action common for all rules in a filter
        $filterAction = $this->parseAction($matches[0], $filterConditionBlock);
        if ($filterAction === null || $filterAction->isEmpty()) {
            return null;
        }

        // if post action behaviour isn't set to continue and filter contains action for default mailbox
        // the behaviour is END_INBOX, END_DISCARD otherwise
        if ($filter->getPostActionBehaviour() !== Filter::POST_CONTINUE) {
            if (isset($filterAction->getActions()[Action::MAILBOX])
                && ($index = array_search(Filter::DEFAULT_MAILBOX, $filterAction->getActions()[Action::MAILBOX])) !== FALSE) {
                $filter->setPostActionBehaviour(Filter::POST_END_INBOX);
                $filterAction->removeAction(Action::MAILBOX, Filter::DEFAULT_MAILBOX);
            } else {
                $filter->setPostActionBehaviour(Filter::POST_END_DISCARD);
            }
        }


        $filter->setActionBlock($filterAction);
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

    /**
     * Check if given action matches safe forwarding action and if so, remove companion condition from block.
     *
     * @param $conditionBlock ConditionBlock
     * @param $actionBlock ActionBlock
     * @param $action string
     * @return bool true if matches safe forward action, false otherwise
     */
    private function checkForSafeFwd(&$conditionBlock, &$actionBlock, $action) {

        if ($action[0] === '|') {
            $action = substr($action, 1);
        }

        $regex = "/".preg_quote(Filter::SAFE_FWD_ACTION)."/";
        $regex = str_replace('_SENDER_', "(?'sender'.*)", $regex);

        if(!preg_match($regex, $action, $matches)) {
            return false;
        }

        $actionBlock->setSenderAddress($matches['sender']);

        $recipients = explode(' ', substr($action, strlen($matches[0])));

        foreach ($recipients as $recipient) {
            if (empty($recipient)) continue;

            $actionBlock->addAction(Action::FWD_SAFE, $recipient);
        }

        foreach ($conditionBlock->getConditions() as $key => $cond) {
            if ($cond->field === Field::CUSTOM
                && $cond->customField === Filter::SAFE_FWD_HEADER
                && $cond->value === Filter::SAFE_FWD_HEADER_VALUE) {
                // this combination means that forward action is actually safe forward
                $conditionBlock->removeCondition($key);
                break;
            }
        }

        return true;
    }

    /**
     * Parse filter conditions (can be split between multiple rules.)
     *
     * @param $rules array of individual rule matches
     * @return ConditionBlock|null condition block or null on parsing error
     */
    private function parseConditionBlock($rules) {
        $conditionBlock = new ConditionBlock();

        // some flags to help determine which condition block rules represent
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
                // first rule can't contain else flag
                return null;
            }

            if ($i > 0) {
                if ($elseFlagsAreSet !== null && $elseFlagsAreSet !== $containsElse) {
                    // either subsequent rules all contain else or none
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

            // extract individual conditions of a rule
            $matches = $this->matchAtLeastOne(self::CONDITION_REGEX, $inputConditionBlock);
            if($matches === null) {
                return null;
            }

            $ruleHasMoreThanOneCondition |= (count($matches) > 1);

            foreach ($matches as $key => $matchedCondition) {
                if ($this->isHeaderCondition($matchedCondition['section'][0])) {
                    $parsedConditions = $this->parseHeaderCondition($matchedCondition['value'][0]);
                } else if ($this->isBodyCondition($matchedCondition['section'][0])) {
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
     * Check if condition contains header only special condition.
     *
     * @param $condition string condition text
     * @return bool true if is header only condition, false otherwise
     */
    private function isHeaderCondition($condition) {
        return strpos($condition, SpecialCondition::ONLY_HEADER_DECODED) !== false
            || strpos($condition, SpecialCondition::ONLY_HEADER) !== false;
    }

    /**
     * Check if condition contains body only special condition.
     *
     * @param $condition string condition text
     * @return bool true if is body only condition, false otherwise
     */
    private function isBodyCondition($condition) {
        return strpos($condition, SpecialCondition::ONLY_BODY_DECODED) !== false
            || strpos($condition, SpecialCondition::ONLY_BODY) !== false;
    }

    /**
     * Parse header condition line.
     *
     * @param $condition string
     * @return null|array array [Conditions[], separated by or?] or null on parsing error
     * @see Filter::createHeaderCondition()
     */
    private function parseHeaderCondition($condition) {
        // split conditions separated by '|'
        $splitConditions = $this->splitConditions(trim($condition));
        if ($splitConditions === null) return null;

        $hasOr = $splitConditions[1];

        $parsedConditions = array();

        foreach ($splitConditions[0] as $key => $subCondition) {
            $match = $this->matchAtLeastOne(self::CONDITION_HEADER_REGEX, $subCondition);
            if ($match === null || count($match) > 1) {
                return null;
            }
            $match = $match[0];

            $field = Field::getFieldFromText($match['field'][0]);
            if ($field === null) {
                $field = Field::CUSTOM;
                $customField = $match['field'][0];
            } else {
                $customField = null;
            }

            $text = $match['value'][0];

            if (preg_match('/^\(\.\*(?\'value\'.*)\.\*\) \*\$$/', $text, $textMatches)) {
                $op = Operator::CONTAINS;
                $text = $textMatches['value'];
            } else if (preg_match('/^\((?\'value\'.*)\.\*\) \*\$$/', $text, $textMatches)) {
                $op = Operator::STARTS_WITH;
                $text = $textMatches['value'];
            } else if (preg_match('/^\((?\'value\'.*)\) \*\$$/', $text, $textMatches)) {
                $op = Operator::EQUALS;
                $text = $textMatches['value'];
            } else if (preg_match('/^\((?\'value\'.*)\)$/', $text, $textMatches)) {
                $op = Operator::PLAIN_REGEX;
                $text = $textMatches['value'];
            } else {
                return null;
            }

            $parsedCondition = Condition::create($field, $op, $text, false, false, $customField);

            if ($parsedCondition === null) {
                return null;
            }

            if ($parsedCondition === null) return null;

            $parsedConditions[] = $parsedCondition;
        }

        return array($parsedConditions, $hasOr);
    }

    /**
     * Parse body condition line.
     *
     * @param $condVal string
     * @return null|array [Conditions[], is separated by or?] or null on parsing error
     */
    private function parseBodyCondition($condVal) {
        $condVal = trim($condVal);

        $conditions = $this->splitConditions($condVal);
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

    /**
     * Check whether text contains unescaped regex special characters.
     *
     * @param $val string
     * @return bool
     */
    private function containsUnescapedRegex($val) {
        return $val !== Condition::ere_quote(Condition::ere_unquote($val));
    }

    /**
     * Split condition line concatenated in 'or' fashion since one line can contain multiple conditions.
     *
     * @param $condition string
     * @return null|array array [array of condition texts, was separated by 'or'] or null on parsing error
     */
    private function splitConditions($condition) {
        $condition = trim($condition);

        $conditions = array();

        $nestedLevel = 0;
        $condIndex = -1;

        $isOrBlock = false;

        $prevCh = null;

        foreach (str_split($condition) as  $key => $ch) {
            if ($ch === "(" && !Condition::isEscapedInRegex($condition, $key)) {
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

            if ($ch === ")" && !Condition::isEscapedInRegex($condition, $key)) {
                $nestedLevel--;
            }
        }

        if ($nestedLevel !== 0) {
            return null;
        }

        return array($conditions, $isOrBlock);
    }

    /**
     * Parse action filter action.
     *
     * @param $rule array matched rule array
     * @param ConditionBlock $conditionBlock condition block of given rule or null, used for checking if fwd is safe fwd
     * @param ActionBlock|null $actionBlock action block to place actions in or null to create a new one
     * @return ActionBlock|null action block or null on parsing error
     */
    private function parseAction($rule, &$conditionBlock, &$actionBlock = null) {
        if (!empty($rule['action'])) { // single line action
            $action = trim($rule['action'][0]);

            if ($action === null || $action[0] === "*") {
                return null;
            }

            if ($actionBlock === null) {
                $actionBlock = new ActionBlock();
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

                // might be safe forward action
                $isSafeFwd = $this->checkForSafeFwd($conditionBlock, $actionBlock, $action);

                if(!$isSafeFwd && !$actionBlock->addAction(Action::PIPE, trim(substr($action, 1)))) {
                    return null;
                }
            } else { // mailbox name
                // strip starting and ending "
                $action = trim($action, "\"");

                if(!$actionBlock->addAction(Action::MAILBOX, $action)) {
                    return null;
                }
            }

            return $actionBlock;
        } else if (!empty($rule['sub_rule_action'])) { // action is actually a rule block

            $matches = $this->matchAtLeastOne(self::RULES_REGEX, $rule['sub_rule_action'][0]);
            if ($matches === null) {
                return null;
            }

            $count = count($matches);
            for ($i = 0; $i < $count; $i++) {
                $rule = $matches[$i];

                $conds = $this->parseConditionBlock(array($rule));
                if ($conds === null) return null;

                if ($this->parseAction($rule, $conds, $actionBlock) === null) {
                    return null;
                }
            }

            return $actionBlock;
        } else {
            return null;
        }
    }

    /**
     * Check if filter was enabled = all lines commented or not.
     * Returns null if some lines were commented out and some not.
     *
     * @param $filterContent string filter text
     * @return bool|null
     */
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

    /**
     * Match at least one in input and check if all input was matched or return null.
     *
     * @param $regex string regex
     * @param $input string text
     * @return array of matches
     */
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