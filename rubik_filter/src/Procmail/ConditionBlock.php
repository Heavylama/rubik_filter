<?php

namespace Rubik\Procmail;

/**
 * Holder class for filter conditions.
 *
 * @package Rubik\Procmail
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class ConditionBlock
{
    /** @var string Conditions have logical OR relation */
    public const OR = "_or";
    /** @var string Conditions have logical AND relation */
    public const AND = "_and";

    /** @var string Condition block type */
    private $type = self::AND;
    /** @var Condition[] conditions array */
    private $conditions = array();

    /**
     * Set condition block type.
     *
     * @param $type string either {@link ConditionBlock::AND} or {@link ConditionBlock::OR}
     * @return bool false if invalid condition block type was supplied
     */
    public function setType($type) {
        if ($type !== self::OR && $type !== self::AND) {
            return false;
        }

        $this->type = $type;

        return true;
    }

    /**
     * Add condition to this block.
     *
     * @param $cond Condition
     */
    public function addCondition($cond) {
        if ($cond !== null) {
            $this->conditions[] = $cond;
        }
    }

    /**
     * Remove condition with given index.
     *
     * @param $index int
     */
    public function removeCondition($index) {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    /**
     * Get conditions array.
     *
     * @return Condition[]
     */
    public function getConditions() {
        return $this->conditions;
    }

    /**
     * Get condition block type.
     *
     * @return string {@link ConditionBlock::AND} or {@link ConditionBlock::OR}
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Get condition count.
     *
     * @return int
     */
    public function count() {
        return count($this->conditions);
    }
}