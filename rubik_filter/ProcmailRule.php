<?php

namespace Rubik\Procmail;

require_once "ProcmailSpecialConditions.php";
require_once "ProcmailAction.php";
require_once "ProcmailFlags.php";

class ProcmailRule
{
    private const KEY_SPECIAL_CONDITION = 'special';
    private const KEY_CONDITION = 'condition';
    private const KEY_ACTION = 'action';
    private const KEY_ACTION_ARG = 'arg';

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

    function __construct()
    {
        $this->resetRule();
    }

    public function resetRule()
    {
        $this->flags = null;
        $this->useLockfile = true;
        $this->lockfile = null;
        $this->conditions = array();
        $this->action = null;
        return $this;
    }

    public function useLockfile($useLockfile, $lockfileName = null)
    {
        $this->useLockfile = $useLockfile;
        $this->lockfile = $lockfileName;
        return $this;
    }

    /**
     * @param string $condValue actual condition text
     * @param string|null $specialCondType use one of {@link SpecialCondition} constants
     * @return bool whether condition was successfully added to the rule
     */
    public function addCondition($condValue, $specialCondType = null)
    {
        if (!SpecialCondition::isValid($specialCondType) || empty($condValue)) {
            return false;
        }

        $cond = array(
            self::KEY_SPECIAL_CONDITION => $specialCondType,
            self::KEY_CONDITION => $condValue
        );

        $this->conditions[] = $cond;

        return true;
    }

    /**
     * @param string|null $flags use combination of {@link Flags} constants
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
     * @param string $action use one of {@link Action} constants
     * @param string $arg action argument
     * @return bool
     */
    public function setAction($action, $arg)
    {
        if (Action::isValid($action) && !empty($arg)) {
            $this->action = array(
                self::KEY_ACTION => $action,
                self::KEY_ACTION_ARG => $arg
            );
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return string|false procmail rule or false if rule is not valid
     */
    public function make()
    {
        // Check for validity
        if (empty($this->action)) {
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
            $rule .= "* ".$cond[self::KEY_SPECIAL_CONDITION];

            // escape any chars which would collide with special condition flag
            if (SpecialCondition::isValid($cond[self::KEY_CONDITION][0])) {
                $rule .= "\\";
            }

            $rule .= $cond[self::KEY_CONDITION];

            $rule .= "\n";
        }

        // Action
        $rule .= $this->action[self::KEY_ACTION]." ".$this->action[self::KEY_ACTION_ARG];
        $rule .= "\n";

        return $rule;
    }
}
