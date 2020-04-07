<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Flags;
use Rubik\Procmail\Rule\Operator;
use Rubik\Procmail\Rule\Rule;
use Rubik\Procmail\Rule\SpecialCondition;

class Filter
{
    public const FILTER_START = "#START:";
    public const FILTER_END = "#END:";
    public const DEFAULT_MAILBOX = '$DEFAULT';
    public const LOCKFILE = ".rubik.lock";

    /** @var string Stops filtering and saves a copy to INBOX folder */
    public const POST_END_INBOX = 'option_end_inbox';
    /** @var string Stops filtering without any further action */
    public const POST_END_DISCARD = 'option_end_discard';
    /** @var string Uses c flag to allow additional filtering with following rules. */
    public const POST_CONTINUE = 'option_continue';

    /**
     * @var string|null
     */
    private $name = null;
    /**
     * @var null|ConditionBlock
     */
    private $conditionBlock = null;
    /**
     * @var ActionBlock
     */
    private $actionsBlock;
    /**
     * @var bool
     */
    private $enabled = true;
    /**
     * Determines behaviour after action block execution.
     *
     * One of constants:
     * <ul>
     * <li>{@link Filter::POST_END_INBOX}</li>
     * <li>{@link Filter::POST_END_DISCARD}</li>
     * <li>{@link Filter::POST_CONTINUE}</li>
     * </ul>
     *
     * @var string
     */
    private $postAction = self::POST_END_DISCARD;

    public function __construct()
    {
        $this->actionsBlock = new ActionBlock();
    }

    /**
     * Reset filter to default state.
     */
    public function reset() {
        $this->conditionBlock = null;
        $this->actionsBlock->clearActions();
        $this->name = null;
        $this->postAction = self::POST_END_DISCARD;
        $this->enabled = true;
    }

    /**
     * Set behaviour after executing action block.
     *
     * @param $postAction string
     * @return bool false if invalid $postAction was supplied
     * @see Filter::$postAction
     */
    public function setPostActionBehaviour($postAction) {
        if ($postAction !== self::POST_CONTINUE
            && $postAction != self::POST_END_DISCARD
            && $postAction != self::POST_END_INBOX) {
            return false;
        }

        $this->postAction = $postAction;

        return true;
    }


    /**
     * Get behaviour after executing action block.
     *
     * @return string
     * @see Filter::$postAction
     */
    public function getPostActionBehaviour() {
        return $this->postAction;
    }

    /**
     * @param $conditions ConditionBlock
     */
    public function setConditionBlock($conditions) {
        $this->conditionBlock = $conditions;
    }

    /**
     * @return ConditionBlock|null
     */
    public function getConditionBlock() {
        return $this->conditionBlock;
    }

    /**
     * @param $name string|null filter name or null to unset
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return string|null filter name or null if unset
     */
    public function getName() {
        return $this->name;
    }

    /**
     * If $enabled set to false, filter output lines are commented out using '#' character.
     *
     * @param $enabled bool
     */
    public function setFilterEnabled($enabled) {
        $this->enabled = ($enabled == true);
    }

    /**
     * @return bool true if enabled
     */
    public function getFilterEnabled() {
        return $this->enabled;
    }

    /**
     * Add action to filter action block.
     *
     * @param $action String one of {@link Action} constants
     * @param $arg String|null action argument
     * @return bool true if valid action was supplied
     */
    public function addAction($action, $arg) {
        return $this->actionsBlock->addAction($action, $arg);
    }

    /**
     * Set actions.
     *
     * @param $actionBlock ActionBlock|null action block or null to clear current block
     */
    public function setActionBlock($actionBlock) {
        if ($actionBlock === null) {
            $this->actionsBlock->clearActions();
        } else {
            $this->actionsBlock = $actionBlock;
        }
    }

    /**
     * @return ActionBlock
     */
    public function getActionBlock() {
        return $this->actionsBlock;
    }

    /**
     * Create procmail code for this filter.
     *
     * @return string|null procmail code or null on error
     */
    public function createFilter() {
        if ($this->postAction !== self::POST_END_INBOX && $this->actionsBlock->isEmpty()) {
            return null;
        }

        // generate rules with conditions
        if ($this->conditionBlock === null) {
            $rules = array(new Rule());
        } else if ($this->conditionBlock->getType() == ConditionBlock::AND) {
            $rules = $this->createAndBlockRules($this->conditionBlock->getConditions());
        } else {
            $rules = $this->createOrBlockRules($this->conditionBlock->getConditions());
        }

        if ($rules === null) {
            return null;
        }

        /*
         * END + INBOX => no C flag on condition rule and INBOX action
         * END + !INBOX => no C flag on condition rule
         * CONTINUE => C flag
         */

        // continue filtering by using c flag
        if ($this->postAction === self::POST_CONTINUE) {
            foreach ($rules as $rule) {
                $rule->addFlags(Flags::COPY);
            }
        }

        // add action to the rules
        if(!$this->fillAction($this->actionsBlock, $rules, $this->postAction === self::POST_END_INBOX)) {
            return null;
        }

        $procmailText = $this->getFilterBorderText(true);


        /** @var Rule $rule */
        foreach ($rules as $rule) {
            $rule->useLockfile(true, self::LOCKFILE);

            $ruleText = $rule->make();

            if ($ruleText === false) {
                return null;
            }

            if (!$this->enabled) {
                $ruleText = "#".str_replace("\n", "\n#", $ruleText);
                // remove '#' after last line
                $ruleText = substr($ruleText, 0,strlen($ruleText) - 1);
            }

            $procmailText .= $ruleText;
        }

        $procmailText .= $this->getFilterBorderText(false);

        return $procmailText;
    }

    /**
     * Fill actions for base rules. May be single actions or sub-blocks if needed.
     *
     * @param $actionBlock ActionBlock
     * @param $rules array sets actions from action block for these rules
     * @param $extraInboxAction bool whether to include extra mailbox action delivering to default mailbox
     * @return bool true on success
     */
    private function fillAction($actionBlock, $rules, $extraInboxAction) {
        $actions = $actionBlock->getActions();

        if ($extraInboxAction) {
            $actions[Action::MAILBOX][] = self::DEFAULT_MAILBOX;
        }

        if (count($actions, COUNT_RECURSIVE) == 2) { // simple line action
            $ruleAction = array_keys($actions)[0];
            $ruleArg = $actions[$ruleAction][0];
        } else { // multiple actions a sub-block is needed
            $ruleAction = Action::RULE_BLOCK;
            $ruleArg = array(); // will contain rules

            $keys = array_keys($actions);
            for ($i = 0; $i < count($actions); $i++) {
                $action = $keys[$i];

                foreach ($actions[$action] as $i2 => $arg) {
                    $actionRule = new Rule();
                    $actionRule->setAction($action, $arg);
                    if ($action === Action::PIPE) {
                        $flags = "W";
                        if ($i2 > 0) $flags .= "a"; // execute subsequent actions only if previous succeeded
                        if ($i2 < sizeof($actions[$action])) $flags .= "c";
                        $actionRule->setFlags($flags);
                    } else {
                        $actionRule->setFlags(Flags::COPY);
                    }
                    $ruleArg[] = $actionRule;
                }

            }

            // remove copy flag on last recipe if present
            /** @var Rule $lastRule */
            $lastRule = array_values(array_slice($ruleArg, -1))[0];
            $lastRule->setFlags(str_replace("c", "", $lastRule->getFlags()));

        }

        /** @var Rule $rule */
        foreach ($rules as $rule) {
            if(!$rule->setAction($ruleAction, $ruleArg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create rules for conditions which are AND joined.
     * In case of AND all conditions can be contained within single rule.
     *
     * @param $conditions Condition[]
     * @return Rule[]|null rule array or null on error
     */
    private function createAndBlockRules($conditions) {
        $rule = new Rule();

        /** @var Condition $cond */
        foreach ($conditions as $cond) {
            $specialCond = array();

            if ($cond->field == Field::BODY) {
                $specialCond[] = SpecialCondition::ONLY_BODY;
            } else {
                $specialCond[] = SpecialCondition::ONLY_HEADER;
            }

            if ($cond->negate) {
                $specialCond[] = SpecialCondition::INVERT;
            }

            $conditionText = $this->createCondition($cond->field, $cond->value ,$cond->op);

            if(!$rule->addCondition($conditionText, $specialCond)) {
                return null;
            }
        }

        return array($rule);
    }

    /**
     * Create rules for conditions which are OR joined.
     * May create more than one rule.
     *
     * @param $conditions Condition[]
     * @return Rule[]|null array of rules or null on error
     */
    private function createOrBlockRules($conditions) {

        $createdConditions = array(
            'header' => array(array(), array()),
            'body' => array(array(), array())
        );

        /** @var Condition $cond */
        foreach ($conditions as $cond) {
            $conditionText = $this->createCondition($cond->field, $cond->value, $cond->op);


            if ($cond->field === Field::BODY) {
                $createdConditions['body'][$cond->negate][] = "$conditionText";
            } else {
                $createdConditions['header'][$cond->negate][] = "$conditionText";
            }
        }

        $rules = array();

        foreach ($createdConditions as $type => $negation) {

            if ($type === 'body') {
                $specialCondSection = SpecialCondition::ONLY_BODY;
            } else {
                $specialCondSection = SpecialCondition::ONLY_HEADER;
            }

            foreach ($negation as $negate => $conditionTextArray) {
                if (empty($conditionTextArray)) {
                    continue;
                }

                // we can compress un-negated conditions to one line
                // negated conditions in or block cannot be compressed since (!A || !B) != !(A || B)
                if ($negate != 1) {
                    $conditionTextArray = array(implode("|", $conditionTextArray));
                }

                foreach ($conditionTextArray as $conditionText) {
                    $rule = new Rule();

                    $specialCondition = array($specialCondSection);

                    if (!empty($rules)) {
                        $rule->setFlags(Flags::LAST_NOT_MATCHED);
                    }

                    if ($negate == 1) {
                        $specialCondition[] = SpecialCondition::INVERT;
                    }

                    if (!$rule->addCondition($conditionText, $specialCondition)) {
                        return null;
                    }

                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Generate rule condition line.
     *
     * @param $field string one of {@link Field} constants
     * @param $value string condition value
     * @param $op string one of {@link Operator} constants
     * @return string condition text
     */
    private function createCondition($field, $value, $op) {
        if ($field === Field::BODY) {
            return $this->createBodyCondition($value, $op);
        } else {
            return $this->createHeaderCondition($field, $value, $op);
        }
    }

    /**
     * Create rule condition line for body condition.
     *
     * @param $value string condition value
     * @param $op string one of {@link Operator} constants
     * @return string condition text
     */
    private function createBodyCondition($value, $op)
    {
        switch ($op) {
            case Operator::STARTS_WITH:
                $value = "^^$value";
                break;
            case Operator::EQUALS:
                $value = "^^$value^^";
                break;
            case Operator::PLAIN_REGEX:
            case Operator::CONTAINS:
            default:
                break;
        }

        return "($value)";
    }

    /**
     * Create rule condition line for header field condition.
     *
     * @param $field string one of {@link Field} constants
     * @param $value string condition value
     * @param $op string one of {@link Operator} constants
     * @return string condition text
     */
    private function createHeaderCondition($field, $value, $op)
    {
        switch ($op) {
            case Operator::STARTS_WITH:
                $value = "$value.*";
                break;
            case Operator::CONTAINS:
                $value = ".*$value.*";
                break;
            case Operator::PLAIN_REGEX:
            case Operator::EQUALS:
            default:
                break;
        }

        $fieldText = $this->getHeaderFieldText($field);
        // update parser when changing format
        return "(^$fieldText *($value) *$)";
    }

    /**
     * Translate field to its mail header prefix.
     *
     * @param $field string one of {@link Field} constants
     * @return string
     */
    private function getHeaderFieldText($field) {
        return Field::getFieldText($field);
    }

    /**
     * Get text bordering start or end of a single filter section.
     *
     * @param $isStart bool
     * @return string border text
     */
    private function getFilterBorderText($isStart) {
        $start = $isStart ? self::FILTER_START : self::FILTER_END;

        if ($this->name !== null) {
            $start .= "$this->name";
        }

        $start .= "\n";

        return $start;
    }
}