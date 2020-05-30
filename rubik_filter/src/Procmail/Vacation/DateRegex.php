<?php


namespace Rubik\Procmail\Vacation;


use DateInterval;
use DateTime;
use Exception;

/**
 * Handles creating/parsing procmail rule date range condition.
 *
 * @package Rubik\Procmail\Vacation
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
class DateRegex
{
    private const DAY_NAMES = "(Mon|Tue|Wed|Thu|Fri|Sat|Sun), ";
    private const REGEX_YEARS = "/\(\((?'months'.*?)\) (?'year'\d{4})\)/";
    private const REGEX_MONTHS = "/\(\((?'days'.*?)\) (?'month'\w{3})\)/";

    /**
     * Create date range condition regex.
     *
     * @param $start DateTime
     * @param $end DateTime
     * @return string condition text
     * @throws Exception on date handling error
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

            if ($day[0] === "0") { // 0 is sometimes omitted
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
     * Parse date range condition regex to DateTime.
     *
     * @param $regex string
     * @param $start DateTime start date
     * @param $end DateTime end date
     * @return bool true if conversion was successful
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

    /**
     * Get first or last month and day of the date range.
     *
     * @param $months string condition months text
     * @param $getStart bool true to get first day/month or false to get last
     * @return array|null [month, day] array or null on error
     */
    private static function getMonthDay($months, $getStart) {
        $count = preg_match_all(self::REGEX_MONTHS, $months, $matches);

        if (!$count) {
            return null;
        }

        $targetMonth = $getStart? 99 : 0;
        $targetDays = null;

        foreach ($matches['month'] as $key => $match) {
            $month = intval(DateTime::createFromFormat("d M", "1 $match")->format("n"));

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

    /**
     * Get first or last day from day range.
     *
     * @param $days string condition days string
     * @param $getStart bool true to get first day, false to get last
     * @return false|int day in month number or false on error
     */
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

//    private static function getMonthNumber($month) {
//        switch ($month) {
//            case "Jan":
//                return 1;
//            case "Feb":
//                return 2;
//            case "Mar":
//                return 3;
//            case "Apr":
//                return 4;
//            case "May":
//                return 5;
//            case "Jun":
//                return 6;
//            case "Jul":
//                return 7;
//            case "Aug":
//                return 8;
//            case "Sep":
//                return 9;
//            case "Oct":
//                return 10;
//
//        }
//    }
}