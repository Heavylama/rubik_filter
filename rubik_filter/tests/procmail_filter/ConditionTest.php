<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Condition;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;

class ConditionTest extends ProcmailTestBase
{

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_Escaping() {
        $condition = Condition::create(Field::TO, Operator::EQUALS, '*', false);

        $this->assertEquals('\*', $condition->value);
    }

    public function test_Escaping2() {
        $condition = Condition::create(Field::TO, Operator::EQUALS, '\*', false);

        $this->assertEquals("\\\\\*", $condition->value);
    }

    public function test_Escaping3_Sanity() {

        $escapedVal = '\*';
        $escapedVal = stripslashes($escapedVal);

        $this->assertTrue('\*' === preg_quote($escapedVal));

        $escapedVal = '*';
        $escapedVal = stripslashes($escapedVal);

        $this->assertFalse('*' === preg_quote($escapedVal));
    }

}