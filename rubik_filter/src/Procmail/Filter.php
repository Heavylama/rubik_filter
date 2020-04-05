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
    private $enabled;

    public function __construct()
    {
        $this->actionsBlock = new ActionBlock();
        $this->enabled = true;
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
     * @param $name string|null
     */
    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setFilterEnabled($enabled) {
        $this->enabled = ($enabled == true);
    }

    public function getFilterEnabled() {
        return $this->enabled;
    }

    public function addAction($action, $arg) {
        return $this->actionsBlock->addAction($action, $arg);
    }

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

    public function resetBuilder() {
        $this->conditionBlock = null;
        $this->actionsBlock->clearActions();
        $this->name = null;
    }

    public function createFilter() {
        if (is_null($this->actionsBlock) || $this->actionsBlock->isEmpty()) {
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

        // add action to the rules
        if(!$this->fillAction($this->actionsBlock, $rules)) {
            return null;
        }

        $procmailText = $this->getFilterBorderText(true);


        /** @var Rule $rule */
        foreach ($rules as $rule) {
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
     * @param $actionBlock ActionBlock
     * @param $rules array sets actions from action block for these rules
     * @return bool
     */
    private function fillAction($actionBlock, $rules) {
        $actions = $actionBlock->getActions();

        if (count($actions, COUNT_RECURSIVE) == 2) { // simple line action
            $ruleAction = array_keys($actions)[0];
            $ruleArg = $actions[$ruleAction][0];
        } else { // multiple actions a block is needed
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
                        if ($i2 > 0) $flags .= "e";
                        $actionRule->setFlags($flags);
                    } else {
                        $actionRule->setFlags(Flags::COPY);
                    }
                    $ruleArg[] = $actionRule;
                }

            }

            // remove copy flag on last recipe
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
     * @param $conditions array
     * @return array
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

    private function createCondition($field, $value, $op) {
        if ($field === Field::BODY) {
            return $this->createBodyCondition($value, $op);
        } else {
            return $this->createHeaderCondition($field, $value, $op);
        }
    }

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

    private function getHeaderFieldText($field) {
        return Field::getFieldText($field);
    }

    private function getFilterBorderText($isStart) {
        $start = $isStart ? self::FILTER_START : self::FILTER_END;

        if ($this->name !== null) {
            $start .= "$this->name";
        }

        $start .= "\n";

        return $start;
    }
}