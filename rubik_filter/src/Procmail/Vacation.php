<?php


namespace Rubik\Procmail;


use DateTime;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

class Vacation extends Filter
{
    public const X_LOOP_VALUE = "autoreply@rubik_filter";
    public const VACATION_ACTION_REGEX  =
        "/\(formail -r -A \"X-Loop: ". self::X_LOOP_VALUE ."\"; cat (?'path'.*)\) \| \\\$SENDMAIL -t -oi/";

    private $start;
    private $end;
    private $messagePath;

    /**
     * @param $startDate DateTime
     * @param $endDate DateTime
     */
    public function setRange($startDate, $endDate) {
        $this->start = $startDate;
        $this->end = $endDate;
    }

    public function getRange() {
        return array('start' => $this->start, 'end' => $this->end);
    }

    public function setMessagePath($path) {
        $this->messagePath = $path;
    }

    public function getMessagePath() {
        return $this->messagePath;
    }

    public function createFilter()
    {
        if ($this->start === null || $this->end === null || $this->messagePath === null) return null;

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);

        // don't response to automated messages
        $conditionBlock->addCondition(
            Condition::create(Field::FROM_MAILER, Operator::PLAIN_REGEX, '', true)
        );
        $conditionBlock->addCondition(
            Condition::create(Field::FROM_DAEMON, Operator::PLAIN_REGEX, '', true)
        );

        // don't get caught in a loop
        $conditionBlock->addCondition(
            Condition::create(Field::X_LOOP_RUBIK, Operator::CONTAINS, self::X_LOOP_VALUE, true)
        );

        // Condition for date field
        $dateRegex = DateRegex::create($this->start, $this->end);
        $conditionBlock->addCondition(
            Condition::create(Field::DATE, Operator::PLAIN_REGEX, $dateRegex, false)
        );

        $this->setConditionBlock($conditionBlock);

        // Sendmail action
        $actionBlock = new FilterActionBlock();
        // don't forget to change parser regex on vacation change
        $actionBlock->addAction(
            Action::PIPE,
            "(formail -r -A \"X-Loop: ".self::X_LOOP_VALUE."\"; cat $this->messagePath) | \$SENDMAIL -t -oi"
        );

        $this->setActionBlock($actionBlock);

        return parent::createFilter();
    }

    /**
     * @param $filter Filter
     * @return Vacation|null
     */
    public static function toVacation($filter) {
        if ($filter === null) return null;

        $action = $filter->getActionBlock()->getActions()[Action::PIPE][0];

        if (!preg_match(self::VACATION_ACTION_REGEX, $action, $matches)) {
            return null;
        }

        $messagePath = $matches['path'];

        $conditions = $filter->getConditionBlock()->getConditions();

        $start = null;
        $end = null;

        // find date condition
        /** @var Condition $cond */
        foreach ($conditions as $cond) {
            if ($cond->field === Field::DATE) {
                if (!DateRegex::toDateTime($cond->value, $start, $end)) {
                    return null;
                }
            }
        }

        $vacation = new Vacation();
        $vacation->setMessagePath($messagePath);
        $vacation->setRange($start, $end);
        $vacation->setFilterEnabled($filter->getFilterEnabled());

        return $vacation;
    }

}