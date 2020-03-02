<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\DateRegex;

// ((0[1-9])|(1[0])

class DateRegexTest extends TestCase
{
    public function test_Days_success() {
        $start = new DateTime('2020-01-01');
        $end = new DateTime('2020-01-10');

        $regex = DateRegex::create($start, $end);

        $this->assertEquals("(Mon|Tue|Wed|Thu|Fri|Sat|Sun), ((((0?1|0?2|0?3|0?4|0?5|0?6|0?7|0?8|0?9|10) Jan)) 2020).*", $regex);

        $res = preg_match("/$regex/", "Mon, 01 Jan 2020");

        $this->assertEquals(1, $res);
    }

    public function test_Days_failAfter() {
        $start = new DateTime('2020-01-01');
        $end = new DateTime('2020-01-10');

        $regex = DateRegex::create($start, $end);

        $res = preg_match("/$regex/", "Mon, 11 Jan 2020");

        $this->assertEquals(0, $res);
    }

    public function test_Days_failBefore() {
        $start = new DateTime('2020-01-02');
        $end = new DateTime('2020-01-10');

        $regex = DateRegex::create($start, $end);

        $res = preg_match("/$regex/", "Mon, 01 Jan 2020");

        $this->assertEquals(0, $res);
    }

    public function test_differentMonth() {
        $start = new DateTime('2020-01-30');
        $end = new DateTime('2020-02-03');

        $regex = DateRegex::create($start, $end);

        $this->assertEquals("(Mon|Tue|Wed|Thu|Fri|Sat|Sun), ((((30|31) Jan)|((0?1|0?2|0?3) Feb)) 2020).*", $regex);

        $res = preg_match("/$regex/", "Mon, 01 Feb 2020");

        $this->assertEquals(1, $res);
    }

    public function test_differentYear() {
        $start = new DateTime('2020-12-30');
        $end = new DateTime('2021-01-03');

        $regex = DateRegex::create($start, $end);

        $this->assertEquals("(Mon|Tue|Wed|Thu|Fri|Sat|Sun), ((((30|31) Dec)) 2020)|((((0?1|0?2|0?3) Jan)) 2021).*", $regex);

        $res = preg_match("/$regex/", "Mon, 01 Jan 2021");

        $this->assertEquals(1, $res);
    }

    public function test_reparser() {
        $start = new DateTime('2020-02-24');
        $end = new DateTime('2022-05-03');

        $regex = DateRegex::create($start, $end);

        $res = DateRegex::toDateTime($regex, $startParsed, $endParsed);

        $this->assertTrue($res);

        $testFmt = "d n Y";

        $this->assertEquals($start->format($testFmt), $startParsed->format($testFmt));
        $this->assertEquals($end->format($testFmt), $endParsed->format($testFmt));
    }
}
