<?php

namespace Rubik\Procmail;

use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Flags;
use Rubik\Procmail\Constants\SpecialCondition;

/**
 * Single procmail rule.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class Rule
{
    private const KEY_SPECIAL_CONDITION = 'special';
    private const KEY_CONDITION = 'condition';
    private const KEY_ACTION = 'action';
    private const KEY_ACTION_ARG = 'arg';

    public const DISCARD_ACTION_ARG = "/dev/null";

    /**
     * @var bool
     */
    private $useLockfile;
    /**
     * Lockfile name, used in rule header
     * @var string|null
     */
    private $lockfile;
    /**
     * Filter conditions array
     * @var array
     */
    private $conditions;
    /**
     * Action to be taken if conditions match
     * @var string|null
     */
    private $action;
    /**
     * Rule flags, used in rule header
     * @var string|null
     */
    private $flags;

    /** @var bool whether rule is enabled */
    private $enabled;

    /**
     * Rule constructor.
     */
    function __construct()
    {
        $this->resetRule();
    }

    public function resetRule()
    {
        $this->flags = null;
        $this->useLockfile = false;
        $this->lockfile = null;
        $this->conditions = array();
        $this->action = null;
        $this->enabled = true;
        return $this;
    }

    /**
     * If set to false, resulting rule is commented out using #.
     * This call also propagates to sub-rules if any are set.
     *
     * @param $enable bool
     */
    public function setEnabled($enable) {
        $this->enabled = $enable === true;

        if ($this->action !== null && $this->action[self::KEY_ACTION] === Action::RULE_BLOCK) {

            /** @var Rule $subRules */
            foreach($this->action[self::KEY_ACTION_ARG] as $subRules) {
                $subRules->setEnabled($this->enabled);
            }
        }
    }

    /**
     * Set rule's lockfile.
     *
     * @param $useLockfile bool whether to use lockfile
     * @param $lockfileName null|string lockfile name or null to unset
     * @return $this
     */
    public function useLockfile($useLockfile, $lockfileName = null)
    {
        $this->useLockfile = $useLockfile;
        $this->lockfile = $lockfileName;
        return $this;
    }

    /**
     * Add condition to this rule.
     *
     * @param string $condValue actual condition text
     * @param array $specialCondType use one of {@link SpecialCondition} constants
     * @return bool whether condition was successfully added to the rule
     */
    public function addCondition($condValue, $specialCondType = array())
    {
        if (empty($condValue)) {
            return false;
        }

        if (!is_array($specialCondType)) {
            $specialCondType = array($specialCondType);
        }

        foreach ($specialCondType as $specialCond) {
            if (SpecialCondition::isValid($specialCond) === false) {
                return false;
            }
        }


        $cond = array(
            self::KEY_SPECIAL_CONDITION => $specialCondType,
            self::KEY_CONDITION => $condValue
        );

        $this->conditions[] = $cond;

        return true;
    }

    /**
     * Set rule flags.
     *
     * @param string|null $flags use combination of {@link Flags} constants or null to unset
     * @return bool whether flags were valid and successfully set on the rule
     */
    public function setFlags($flags)
    {
        if (Flags::isValid($flags)) {
            $this->flags = $flags;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add flags if not already present.
     *
     * @param $flags string composed of {@link Flags} constants
     * @return bool true on success, false if flags used are invalid.
     */
    public function addFlags($flags) {

        $current = $this->flags === null ? array() : str_split($this->flags);
        $new = str_split($flags);


        return $this->setFlags(implode(array_unique($current + $new)));
    }

    /**
     * Get rule flags.
     *
     * @return string|null
     */
    public function getFlags() {
        return $this->flags;
    }

    /**
     * Set rule action.
     *
     * @param string $action use one of {@link Action} constants
     * @param string $arg action argument
     * @return bool
     */
    public function setAction($action, $arg)
    {
        if (Action::isValid($action) && ($action === Action::DISCARD || !empty($arg))) {
            $this->action = array(
                self::KEY_ACTION => $action,
                self::KEY_ACTION_ARG => $arg
            );

            if ($action === Action::RULE_BLOCK) {
                // to refresh enabled status on sub-rules
                $this->setEnabled($this->enabled);
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if rule is valid and can be built.
     * @return bool
     */
    public function isValid() {
        return !empty($this->action);
    }

    /**
     * Create procmail text for this rule.
     *
     * @return string|false procmail rule or false if rule is not valid
     */
    public function make()
    {
        // Check for validity
        if (!$this->isValid()) {
            return false;
        }

        // Start building
        $rule = ":0";

        // Flags
        $rule .= $this->flags;

        // Lockfile
        if ($this->useLockfile) {
            $rule .= ":".$this->lockfile;
        }
        $rule .= "\n";

        // Conditions
        foreach ($this->conditions as $cond) {

            $rule .= "* ";
            if (!empty($cond[self::KEY_SPECIAL_CONDITION])) {
                $rule .= implode($cond[self::KEY_SPECIAL_CONDITION])." ";
            }

            // escape any chars which would collide with special condition flag
            if (SpecialCondition::isValid($cond[self::KEY_CONDITION][0])) {
                $rule .= "\\";
            }

            $rule .= $cond[self::KEY_CONDITION];

            $rule .= "\n";
        }

        // Action
        switch ($this->action[self::KEY_ACTION]) {
            case Action::MAILBOX:
                $rule .= "\"".$this->action[self::KEY_ACTION_ARG]."\"";
                break;
            case Action::RULE_BLOCK:
                $rule .= "{\n\n";
                /** @var Rule $subRule */
                foreach($this->action[self::KEY_ACTION_ARG] as $subRule) {
                    $subRule = $subRule->make();

                    if ($subRule === false) {
                        return false;
                    } else {
                        $rule .= $subRule;
                    }
                }
                $rule .= "}\n";
                break;
            case Action::DISCARD:
                $rule .= self::DISCARD_ACTION_ARG;
                break;
            case Action::FWD:
            case Action::FWD_SAFE:
                $rule .= "! ".$this->action[self::KEY_ACTION_ARG];
                break;
            case Action::PIPE:
                $rule .= "| ".$this->action[self::KEY_ACTION_ARG];
                break;
        }
        $rule .= "\n\n";

        // to disable the rule, comment out the lines
        if (!$this->enabled) {
            $rule = "#".str_replace("\n","\n#", $rule);
        }

        return $rule;
    }
}
