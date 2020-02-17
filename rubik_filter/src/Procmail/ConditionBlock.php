<?php


namespace Rubik\Procmail;


class ConditionBlock
{
    public const OR = "_or";
    public const AND = "_and";

    private $type = self::AND;
    private $conditions = array();

    public function setType($type) {
        if ($type !== self::OR && $type !== self::AND) {
            return false;
        }

        $this->type = $type;

        return true;
    }

    /**
     * @param $cond Condition
     */
    public function addCondition($cond) {
        $this->conditions[] = $cond;
    }

    public function getConditions() {
        return $this->conditions;
    }

    public function getType() {
        return $this->type;
    }

    public function count() {
        return count($this->conditions);
    }
}