<?php


namespace Rubik\Procmail;


use DateTime;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;
use Rubik\Storage\ProcmailStorage;

class Vacation extends Filter
{
    public const X_LOOP_VALUE = "autoreply@rubik_filter";
    public const VACATION_ACTION_REGEX  =
        "/\(formail -r -A \"X-Loop: ". self::X_LOOP_VALUE ."\"; cat \"(?'path'.*)\"\) \| \\\$SENDMAIL -t -oi/";
    public const VACATION_ALREADY_REPLIED_CHECK = '(read EMAIL; NOW=$(date +%s); touch "_CACHE_"; awk -v name="$EMAIL" -v now="$NOW" -v diff=$(expr $NOW - _DIFF_) \'{if (index($0,name)) {if ($NF > diff) {exit 1}} else {print $0}} END{print name" "now}\' "_CACHE_" > "_CACHE_.tmp" && mv "_CACHE_.tmp" "_CACHE_" || (rm "_CACHE_.tmp" && exit 1))';
    private static $VACATION_REPLY_CHECK_REGEX = null;

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
     * Name of this vacation's email cache file.
     *
     * @var string|null
     */
    private $cacheName = ProcmailStorage::VACATION_CACHE_LOCATION;

    /**
     * Time in seconds before automated reply is sent again to each sender.
     *
     * @var int
     */
    private $replyTime = 0;

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
     * Set time before another reply message is sent to the same sender.
     *
     * @param $seconds int
     */
    public function setReplyTime($seconds) {
        $this->replyTime = $seconds;
    }

    /**
     * Get time period before another reply message is sent to the same sender.
     *
     * @return int seconds
     */
    public function getReplyTime() {
        return $this->replyTime;
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

        $this->setConditionBlock($conditionBlock);

        // Sendmail action
        $actionBlock = new ActionBlock();
        // note: don't forget to change parser regex on vacation change
        $actionBlock->addAction(
          Action::PIPE,
            $this->getReplyCheckCommand()
        );
        $actionBlock->addAction(
            Action::PIPE,
            "(formail -r -A \"X-Loop: ".self::X_LOOP_VALUE."\"; cat \"$this->messagePath\") | \$SENDMAIL -t -oi"
        );

        $this->setActionBlock($actionBlock);

        $this->setPostActionBehaviour(Filter::POST_CONTINUE);

        return parent::createFilter();
    }

    /**
     * @param $filter Filter
     * @return Vacation|null
     */
    public static function toVacation($filter) {
        if ($filter === null) return null;

        $actions = $filter->getActionBlock()->getActions()[Action::PIPE];
        if (!preg_match(self::VACATION_ACTION_REGEX, $actions[1], $matches)) {
            return null;
        }
        $messagePath = $matches['path'];

        if (!preg_match(self::getReplyCheckCommandRegex(), $actions[0], $matches)) {
            return null;
        }
        $cacheName = $matches['cacheName'];
        $replyTime = intval($matches['replyTime']);


        $conditions = $filter->getConditionBlock()->getConditions();

        $start = null;
        $end = null;

        // find date condition and reply check
        /** @var Condition $cond */
        foreach ($conditions as $cond) {
            if ($cond->field === Field::DATE) {
                if (!DateRegex::toDateTime($cond->value, $start, $end)) {
                    return null;
                }
            }
        }

        if ($start === null || $end === null) return null;


        $vacation = new Vacation();
        $vacation->setMessagePath($messagePath);
        $vacation->setRange($start, $end);
        $vacation->setReplyTime($replyTime);
        $vacation->setFilterEnabled($filter->getFilterEnabled());

        return $vacation;
    }

    private function getReplyCheckCommand() {
        $replace = array(
            '_CACHE_' => $this->cacheName,
            '_DIFF_' => $this->replyTime
        );

        return strtr(self::VACATION_ALREADY_REPLIED_CHECK, $replace);
    }

    private static function getReplyCheckCommandRegex() {
        if (self::$VACATION_REPLY_CHECK_REGEX === null) {
            $base = "/" . preg_quote(self::VACATION_ALREADY_REPLIED_CHECK, "/") . "/";

            $base = substr_replace($base, "(?'cacheName'.*)", strpos($base, "_CACHE_"), 7);
            $base = substr_replace($base, "(?'replyTime'.*)", strpos($base, "_DIFF_"), 6);
            $base = str_replace('_CACHE_', '.*', $base);

            self::$VACATION_REPLY_CHECK_REGEX = $base;
        }

        return self::$VACATION_REPLY_CHECK_REGEX;
    }

}