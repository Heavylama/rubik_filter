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
        "/\(formail -r -A \"X-Loop: ". self::X_LOOP_VALUE ."\"; cat \"(?'path'.*)\"\) \| \\\$SENDMAIL -t -oi/";

    /**
     * Vacation start date
     *
     * @var DateTime|null
     */
    private $start;
    /**
     * Vacation end date
     *
     * @var DateTime|null
     */
    private $end;
    /**
     * Message file path
     *
     * @var string|null
     */
    private $messagePath;

    /**
     * @param $startDate DateTime
     * @param $endDate DateTime
     */
    public function setRange($startDate, $endDate) {
        // swap to correct order if needed
        if ($startDate->diff($endDate)->invert) {
            $this->end = $startDate;
            $this->start = $endDate;
        } else {
            $this->start = $startDate;
            $this->end = $endDate;
        }

    }

    /**
     * Get vacation date range.
     *
     * @return DateTime[]|null[]
     */
    public function getRange() {
        return array('start' => $this->start, 'end' => $this->end);
    }

    /**
     * Check if this vacation's date range overlaps with the other vacation.
     *
     * @param $vacation Vacation
     * @return bool true if overlaps
     */
    public function rangeOverlaps($vacation) {
        if ($this->start === null ||
            $this->end === null ||
            $vacation->start === null ||
            $vacation->end === null) {
            return false;
        }

        $startDiff = $this->start->diff($vacation->end)->format('%r%a');
        $endDiff = $this->end->diff($vacation->start)->format('%r%a');

        return $startDiff >= 0 && $endDiff <= 0;
    }

    public function setMessagePath($path) {
        $this->messagePath = $path;
    }

    public function getMessagePath($full = true) {
        return $full ? $this->messagePath : end(explode("/", $this->messagePath));
    }

    public function createFilter()
    {
        if ($this->start === null || $this->end === null || $this->messagePath === null) return null;

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);

        // Don't response to automated messages
        $conditionBlock->addCondition(
            Condition::create(Field::FROM_MAILER, Operator::PLAIN_REGEX, '', true)
        );
        $conditionBlock->addCondition(
            Condition::create(Field::FROM_DAEMON, Operator::PLAIN_REGEX, '', true)
        );

        // Don't get caught in a loop
        $conditionBlock->addCondition(
            Condition::create(Field::X_LOOP_RUBIK, Operator::CONTAINS, self::X_LOOP_VALUE, true)
        );

        // Condition for date field
        $dateRegex = DateRegex::create($this->start, $this->end);
        $conditionBlock->addCondition(
            Condition::create(Field::DATE, Operator::PLAIN_REGEX, $dateRegex, false)
        );

        // Check if we haven't already responded

        $this->setConditionBlock($conditionBlock);

        // Sendmail action
        $actionBlock = new ActionBlock();
        // note: don't forget to change parser regex on vacation change
        $actionBlock->addAction(
            Action::PIPE,
            "(formail -r -A \"X-Loop: ".self::X_LOOP_VALUE."\"; cat \"$this->messagePath\") | \$SENDMAIL -t -oi"
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