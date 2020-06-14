<?php


namespace Rubik\Procmail;


use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Flags;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Constants\SpecialCondition;

/**
 * Main class for building Procmail filters.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class Filter
{
    /** @var string Filter block start, followed by filter name */
    public const FILTER_START = "#START:";
    /** @var string Filter block end, followed by filter name */
    public const FILTER_END = "#END:";
    /** @var string Default mailbox name */
    public const DEFAULT_MAILBOX = '$DEFAULT';
    /** @var string Default lockfile name */
    public const LOCKFILE = ".rubik.lock";

    /** @var string Stops filtering and saves a copy to INBOX folder */
    public const POST_END_INBOX = 'option_end_inbox';
    /** @var string Stops filtering without any further action */
    public const POST_END_DISCARD = 'option_end_discard';
    /** @var string Uses c flag to allow additional filtering with following rules. */
    public const POST_CONTINUE = 'option_continue';
    public const SAFE_FWD_HEADER = 'X-Loop-Rubik';
    public const SAFE_FWD_HEADER_VALUE = 'rubik';
    public const SAFE_FWD_ACTION
        = ' formail -a "Resent-From: <_SENDER_>" -a "'.self::SAFE_FWD_HEADER.': '.self::SAFE_FWD_HEADER_VALUE.'"|$SENDMAIL -oi -f "$ODES" ';

    /** @var string|null Filter name, can be null */
    private $name = null;
    /** @var ConditionBlock|null Filter conditions, can be null */
    private $conditionBlock = null;
    /** @var ActionBlock Filter actions */
    private $actionsBlock;
    /** @var bool If set to false resulting procmail text will be commented out using # */
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
    /** @var bool whether to compare condition regex against plugin decoded variant or procmail inbuilt variant */
    private $useDecodedConditions = true;

    /**
     * Filter constructor.
     */
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
        $this->useDecodedConditions = true;
    }

    /**
     * Set whether to compare condition regex against procmail inbuilt variables (H?? and B??)
     * or plugin decoded variants (HEADER_D?? and BODY_D??).
     *
     * If set to true (which is default),
     * filters must be preceded by decode variables setup code provided by {@link Filter::generateSetupBlock()}
     *
     * @param $use bool
     */
    public function useDecodedCondition($use) {
        $this->useDecodedConditions = ($use == true);
    }

    /**
     * Set behaviour after executing action block.
     *
     * @param $postAction string {@link Filter::POST_END_DISCARD}, {@link Filter::POST_CONTINUE} or {@link Filter::POST_END_INBOX}
     * @return bool false if invalid $postAction was supplied
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
     * @return string {@link Filter::POST_END_DISCARD}, {@link Filter::POST_CONTINUE} or {@link Filter::POST_END_INBOX}
     */
    public function getPostActionBehaviour() {
        return $this->postAction;
    }

    /**
     * @param $conditions ConditionBlock|null
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
        $this->name = Condition::strip_unprintable_utf8(str_replace("\n", " ", $name));
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
     * @param $rules Rule[] sets actions from action block for these rules
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

            if ($ruleAction === Action::FWD_SAFE) {

                if ($actionBlock->getSenderAddress() === null) return false;

                // change action to pipe
                $ruleAction = Action::PIPE;
                $ruleArg = $this->getSafeFwdAction($actionBlock->getSenderAddress(), $ruleArg);

                // insert condition for safe fwd
                foreach ($rules as $rule) {
                    $this->insertSafeFwdCondition($rule);
                }
            }
        } else { // multiple actions a sub-block is needed
            $ruleAction = Action::RULE_BLOCK;
            $ruleArg = array(); // will contain rules

            $keys = array_keys($actions);
            for ($i = 0; $i < count($actions); $i++) {
                $action = $keys[$i];

                foreach ($actions[$action] as $i2 => $arg) {
                    $actionRule = new Rule();
                    if ($action === Action::PIPE) {
                        $flags = Flags::WAIT_FINISH_NO_MSG;
                        if ($i2 > 0) $flags .= Flags::LAST_MATCHED_SUCCESS; // execute subsequent actions only if previous succeeded
                        if ($i2 < (sizeof($actions[$action]) - 1)) $flags .= Flags::COPY;
                        $actionRule->setFlags($flags);
                    } else {
                        $actionRule->setFlags(Flags::COPY);
                    }

                    // insert safe forwarding condition
                    if ($action === Action::FWD_SAFE) {
                        if ($actionBlock->getSenderAddress() === null) return false;

                        $this->insertSafeFwdCondition($actionRule);

                        // change action to pipe
                        $action = Action::PIPE;
                        $arg = $this->getSafeFwdAction($actionBlock->getSenderAddress(), $arg);
                    }

                    $actionRule->setAction($action, $arg);


                    $ruleArg[] = $actionRule;
                }

            }

            // remove copy flag on last recipe if present
            /** @var Rule $lastRule */
            $lastRule = array_values(array_slice($ruleArg, -1))[0];
            $lastRule->setFlags(str_replace(Flags::COPY, "", $lastRule->getFlags()));

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
     * Create safe forward action line.
     *
     * @param $sender string sender's email address
     * @param $recipients string forward recipients
     * @return string|string[]
     */
    private function getSafeFwdAction($sender, $recipients) {
        $action = str_replace("_SENDER_", $sender, self::SAFE_FWD_ACTION);
        $action .= $recipients;

        return $action;
    }

    /**
     * Insert condition for safe forward action.
     *
     * @param $rule Rule
     */
    private function insertSafeFwdCondition(&$rule) {
//        // insert condition for safe fwd
//        $safeCondition = $this->createHeaderCondition(
//            Field::FROM_MAILER,
//            "",
//            Operator::CONTAINS,
//            null);
//        $rule->addCondition($safeCondition, array($this->getSpecialCondition(false), SpecialCondition::INVERT));

        $safeCondition = $this->createHeaderCondition(
            Field::CUSTOM,
            self::SAFE_FWD_HEADER_VALUE,
            Operator::CONTAINS,
            self::SAFE_FWD_HEADER);
        $rule->addCondition($safeCondition, array($this->getSpecialCondition(false), SpecialCondition::INVERT));
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

            $specialCond[] = $this->getSpecialCondition($cond->field == Field::BODY);

            if ($cond->negate) {
                $specialCond[] = SpecialCondition::INVERT;
            }

            $conditionText = $this->createConditionText($cond);

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
            $conditionText = $this->createConditionText($cond);


            if ($cond->field === Field::BODY) {
                $createdConditions['body'][$cond->negate][] = "$conditionText";
            } else {
                $createdConditions['header'][$cond->negate][] = "$conditionText";
            }
        }

        $rules = array();

        foreach ($createdConditions as $type => $negation) {

            $specialCondSection = $this->getSpecialCondition($type === 'body');

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
     * Get special condition targeting either body or header.
     *
     * Whether decoded version or procmail version is returned is controlled by {@link Filter::$useDecodedConditions}
     *
     * @param $isBody bool
     * @return string special condition
     */
    private function getSpecialCondition($isBody) {
        if ($isBody) {
            return ($this->useDecodedConditions ? SpecialCondition::ONLY_BODY_DECODED : SpecialCondition::ONLY_BODY);
        } else {
            return ($this->useDecodedConditions ? SpecialCondition::ONLY_HEADER_DECODED : SpecialCondition::ONLY_HEADER);
        }
    }

    /**
     * Generate rule condition line.
     *
     * @param $condition Condition condition
     * @return string condition text
     */
    private function createConditionText($condition) {
        if ($condition->field === Field::BODY) {
            return $this->createBodyCondition($condition->value, $condition->op);
        } else {
            return $this->createHeaderCondition(
                $condition->field,
                $condition->value,
                $condition->op,
                $condition->customField
            );
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
     * @param $customField string|null used if $field is {@link Field::CUSTOM}
     * @return string condition text
     */
    private function createHeaderCondition($field, $value, $op, $customField)
    {
        switch ($op) {
            case Operator::STARTS_WITH:
                $value = "($value.*) *$";
                break;
            case Operator::CONTAINS:
                $value = "(.*$value.*) *$";
                break;
            case Operator::PLAIN_REGEX:
                $value = "($value)";
                break;
            case Operator::EQUALS:
                $value = "($value) *$";
                break;
            default:
                break;
        }

        if ($field === Field::CUSTOM) {
            $fieldText = "$customField:";
        } else {
            $fieldText = $this->getHeaderFieldText($field);
        }

        // update parser when changing format
        return "(^$fieldText *$value)";
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

    /**
     * Generate block for plugin filtering setup.
     *
     * @param string $decodeScriptPath
     * @return string decode block
     */
    public static function generateSetupBlock($decodeScriptPath = null) {
        $block = "#DECODE_BLOCK_START\nLINEBUF=32000\nODES=`formail -x Return-Path`\n";
        if ($decodeScriptPath !== null) {
            $block .= "HEADER_D=`$decodeScriptPath -h`\n";
            $block .= "BODY_D=`$decodeScriptPath -b`\n";
        }
        $block .= "#DECODE_BLOCK_END\n\n";
        return $block;
    }
}