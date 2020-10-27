<?php


namespace Rubik\Procmail\Vacation;


use DateTime;
use Exception;
use Rubik\Procmail\ActionBlock;
use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Filter;
use Rubik\Storage\ProcmailStorage;

/**
 * Extension of {@link Filter} with vacation specific functionality.
 *
 *
 * This includes setting a vacation date range, reply message and email cache location.
 * Email cache is used to auto-reply only after a specific time interval elapsed.
 *
 * @package Rubik\Procmail\Vacation
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class Vacation extends Filter
{
    public const X_LOOP_VALUE = "autoreply@rubik_filter";
    public const VACATION_ACTION = '(formail -rt -A "X-Loop: _LOOP_" -i "Content-Transfer-Encoding: quoted-printable" -i "Content-Type: text/plain; charset=utf-8" ; echo -e "_MSG_";) | $SENDMAIL -t -oi';

    public const VACATION_ALREADY_REPLIED_CHECK = 'formail -x "From:" | (read EMAIL; NOW=$(date +%s); touch "_CACHE_"; awk -v name="*$EMAIL" -v now="$NOW" -v diff=$(expr $NOW - _DIFF_) \'{if (index($0,name)) {if ($NF > diff) {exit 1}} else {print $0}} END{print name" "now}\' "_CACHE_" > "_CACHE_.tmp" && mv "_CACHE_.tmp" "_CACHE_" || (rm "_CACHE_.tmp" && exit 1))';
    private static $VACATION_REPLY_CHECK_REGEX = null;
    private static $VACATION_ACTION_REGEX = null;

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
     * Reply message
     *
     * @var string|null
     */
    private $message;

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
     * @param DateTime $startDate
     * @param DateTime $endDate
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
     * @param int $seconds
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
     * @param Vacation $vacation
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

    /**
     * Set reply message.
     *
     * @param string $message
     */
    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * Get reply message.
     *
     * @return string|null
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * Override filter creation to include date range check and auto-reply/cache-check actions.
     *
     * @return string|null
     */
    public function createFilter()
    {
        if ($this->start === null || $this->end === null || $this->message === null) return null;

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
        try {
            $dateRegex = DateRegex::create($this->start, $this->end);
        } catch (Exception $e) {
            return null;
        }
        $conditionBlock->addCondition(
            Condition::create(Field::DATE, Operator::PLAIN_REGEX, $dateRegex, false)
        );

        $this->setConditionBlock($conditionBlock);

        // Check email cache action
        $actionBlock = new ActionBlock();
        $actionBlock->addAction(
          Action::PIPE,
            $this->getReplyCheckCommand()
        );

        // Send reply action
        $replyMessage = quoted_printable_encode($this->getMessage());
        $replyMessage = str_replace("\r\n", "\\r\\n", $replyMessage);

        $replyAction = str_replace(
            array('_LOOP_', '_MSG_'),
            array(self::X_LOOP_VALUE, $replyMessage),
            self::VACATION_ACTION
        );

        $actionBlock->addAction(Action::PIPE, $replyAction);

        // Gobble up the message copy if it wasn't used in auto-reply
        $actionBlock->addAction(Action::DISCARD, null);

        $this->setActionBlock($actionBlock);

        $this->setPostActionBehaviour(Filter::POST_CONTINUE);

        return parent::createFilter();
    }

    /**
     * Check if filter is in fact a vacation and convert it.
     *
     * @param Filter|null $filter
     * @return Vacation|null converted Vacation or null if not a vacation
     */
    public static function toVacation($filter) {
        if ($filter === null) return null;

        // try to parse auto-reply action
        $actions = $filter->getActionBlock()->getActions()[Action::PIPE];
        if (!preg_match(self::getVacationActionRegex(), $actions[1], $matches)) {
            return null;
        }

        // get reply message from auto-reply action
        $message = str_replace("\\r\\n", "\r\n", $matches['msg']);
        $message = quoted_printable_decode($message);

        // try to parse email cache check action
        if (!preg_match(self::getReplyCheckCommandRegex(), $actions[0], $matches)) {
            return null;
        }
        $replyTime = intval($matches['replyTime']);

        // find date condition
        $conditions = $filter->getConditionBlock()->getConditions();

        $start = null;
        $end = null;

        foreach ($conditions as $cond) {
            if ($cond->field === Field::DATE) {
                if (!DateRegex::toDateTime($cond->value, $start, $end)) {
                    return null;
                }
            }
        }

        if ($start === null || $end === null) return null;


        // create vacation instance
        $vacation = new Vacation();
        $vacation->setMessage($message);
        $vacation->setRange($start, $end);
        $vacation->setReplyTime($replyTime);
        $vacation->setFilterEnabled($filter->getFilterEnabled());

        return $vacation;
    }

    /**
     * Create action line for email cache check.
     *
     * @return string
     */
    private function getReplyCheckCommand() {
        $replace = array(
            '_CACHE_' => $this->cacheName,
            '_DIFF_' => $this->replyTime
        );

        return strtr(self::VACATION_ALREADY_REPLIED_CHECK, $replace);
    }

    /**
     * Get regex for parsing email cache check action line.
     *
     * Named capture groups:
     * <ul>
     *  <li>cacheName - cache file path</li>
     *  <li>replyTime - time before auto-reply is sent again</li>
     * </ul>
     *
     * @return string
     */
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

    /**
     * Get regex for parsing auto-reply action line.
     *
     * Named capture groups
     * <ul>
     *  <li>xloop - X-Loop email header field value</li>
     *  <li>msg - auto reply message content</li>
     * </ul>
     * @return string
     */
    private static function getVacationActionRegex() {
        if (self::$VACATION_ACTION_REGEX === null) {
            $base = "/".preg_quote(self::VACATION_ACTION, "/")."/";

            $base = str_replace('_LOOP_',"(?'xloop'.*)", $base);
            $base = str_replace('_MSG_', "(?'msg'.*)", $base);

            self::$VACATION_ACTION_REGEX = $base;
        }

        return self::$VACATION_ACTION_REGEX;
    }


}