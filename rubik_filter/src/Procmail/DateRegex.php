<?php


namespace Rubik\Procmail;


use DateInterval;
use DateTime;
use Exception;

class DateRegex
{
    private const DAY_NAMES = "(Mon|Tue|Wed|Thu|Fri|Sat|Sun), ";
    private const REGEX_YEARS = "/\(\((?'months'.*?)\) (?'year'\d{4})\)/";
    private const REGEX_MONTHS = "/\(\((?'days'.*?)\) (?'month'\w{3})\)/";
    private const REGEX_DAYS = "/\d+/";

    /**
     * @param $start DateTime
     * @param $end DateTime
     * @return string
     * @throws Exception
     */
    public static function create($start, $end) {
        $oneDay = new DateInterval('P1D');

        $currentDate = clone $start;
        $prevMonth = $currentDate->format("M");
        $prevYear = $currentDate->format("Y");

        $years =  array();

        do {
            $currentMonth = $currentDate->format("M");
            $currentYear = $currentDate->format("Y");

            if ($currentMonth !== $prevMonth) {
                $years[$prevYear][$prevMonth] = "((".implode("|",$years[$prevYear][$prevMonth]).") $prevMonth)";
            }

            if ($currentYear !== $prevYear) {
                $years[$prevYear] = "((".implode("|", $years[$prevYear]).") $prevYear)";
            }

            // add day to current month array of days
            $day = $currentDate->format("d");

            if ($day[0] === "0") { // 0 is sometimes ommited
                $day = "0?".$day[1];
            }

            $years[$currentYear][$currentMonth][] = $day;

            $endDiff = $end->diff($currentDate);

            $prevMonth = $currentMonth;
            $prevYear = $currentYear;
            $currentDate->add($oneDay);

        } while($endDiff->d > 0 || $endDiff->m > 0 || $endDiff->y > 0);

        $years[$currentYear][$currentMonth] = "((".implode("|",$years[$currentYear][$currentMonth]).") $currentMonth)";
        $years[$currentYear] = "((".implode("|",$years[$currentYear]).") $currentYear)";

        return self::DAY_NAMES.implode("|", $years).".*";
    }

    /**
     * @param $regex string
     * @param $start DateTime
     * @param $end DateTime
     * @return bool
     */
    public static function toDateTime($regex, &$start, &$end) {
        $count = preg_match_all(self::REGEX_YEARS, $regex, $matches);

        if (!$count) {
            return false;
        }

        $endYear = 0;
        $startYear = 9999;

        $startMonths = '';
        $endMonths = '';

        foreach ($matches['year'] as $key => $year) {
            $year = intval($year);

            if ($year > $endYear) {
                $endYear = $year;
                $endMonths = $matches['months'][$key];
            }

            if ($year < $startYear) {
                $startYear = $year;
                $startMonths = $matches['months'][$key];
            }
        }

        $startMonth = self::getMonthDay($startMonths, true);
        if ($startMonth === null) return false;

        $endMonth = self::getMonthDay($endMonths, false);
        if ($endMonth === null) return false;

        $start = DateTime::createFromFormat("!d n Y", $startMonth[1]." ".$startMonth[0]." ".$startYear);
        $end = DateTime::createFromFormat("!d n Y", $endMonth[1]." ".$endMonth[0]." ".$endYear);

        return $start !== null && $end !== null;
    }

    private static function getMonthDay($months, $getStart) {
        $count = preg_match_all(self::REGEX_MONTHS, $months, $matches);

        if (!$count) {
            return null;
        }

        $targetMonth = $getStart? 99 : 0;
        $targetDays = null;

        foreach ($matches['month'] as $key => $match) {
            $month = intval(DateTime::createFromFormat("M", $match)->format("n"));

            if (!$month) {
                return null;
            }

            if ($getStart && $month < $targetMonth) {
                $targetMonth = $month;
                $targetDays = $matches['days'][$key];
            }

            if (!$getStart && $month > $targetMonth) {
                $targetMonth = $month;
                $targetDays = $matches['days'][$key];
            }

        }

        $targetDay = self::getDay($targetDays, $getStart);

        if ($targetDay === false) return null;

        return array($targetMonth, $targetDay);
    }

    private static function getDay($days, $getStart) {
        $days = explode("|", (trim(str_replace("?", "", $days))));

        if (count($days) === 0) return false;

        $targetDay = $getStart ? 99 : 0;

        foreach ($days as $day) {
            $day = intval($day);

            if ($getStart && $day < $targetDay) {
                $targetDay = $day;
            }

            if (!$getStart && $day > $targetDay) {
                $targetDay = $day;
            }
        }

        return $targetDay;
    }
}