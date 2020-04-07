<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Vacation;

class VacationTest extends TestCase
{
    private $vac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vac = new Vacation();
    }

    public function test_RangeOverlaps_before_no_overlap() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-10');
        $firstEnd = new DateTime('2020-01-15');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertFalse($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_after_no_overlap() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-26');
        $firstEnd = new DateTime('2020-01-30');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertFalse($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_sameDate() {
        $otherStart = new DateTime('10-05-2020');
        $otherEnd = new DateTime('10-08-2020');

        $this->vac->setRange($otherStart, $otherEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertTrue($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_inside() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-21');
        $firstEnd = new DateTime('2020-01-23');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertTrue($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_startBefore() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-10');
        $firstEnd = new DateTime('2020-01-20');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertTrue($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_endAfter() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-25');
        $firstEnd = new DateTime('2020-01-30');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertTrue($this->vac->rangeOverlaps($otherVac));
    }

    public function test_RangeOverlaps_startBefore_endAfter() {
        $otherStart = new DateTime('2020-01-20');
        $otherEnd = new DateTime('2020-01-25');
        $firstStart = new DateTime('2020-01-10');
        $firstEnd = new DateTime('2020-02-10');

        $this->vac->setRange($firstStart, $firstEnd);
        $otherVac = new Vacation();
        $otherVac->setRange($otherStart, $otherEnd);

        $this->assertTrue($this->vac->rangeOverlaps($otherVac));
    }

    public function test_SanityCheck() {
        $this->vac->setMessage("\$HOME/.procmail_messages/message.msg");

        $start = new DateTime();
        $end = new DateTime();
        $end->add(new DateInterval('P2D'));

        $this->vac->setRange($start, $end);

        $output = $this->vac->createFilter();

        $this->assertNotNull($output);
    }

    public function test_SetRange_wrong_order() {
        $range = array(
            'start' => new DateTime('2020-01-15'),
            'end' => new DateTime('2020-01-10')
        );

        $this->vac->setRange($range['start'], $range['end']);

        $range = array(
            'start' => $range['end'],
            'end' => $range['start']
        );

        $this->assertEquals($range, $this->vac->getRange());
    }

    public function test_SetRange_correct_order() {
        $range = array(
            'start' => new DateTime('2020-01-10'),
            'end' => new DateTime('2020-01-15')
        );

        $this->vac->setRange($range['start'], $range['end']);

        $this->assertEquals($range, $this->vac->getRange());
    }

}
