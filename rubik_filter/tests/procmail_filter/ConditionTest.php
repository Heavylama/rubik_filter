<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

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

    public function test_Escaping3() {
        $condition = Condition::create(Field::FROM, Operator::CONTAINS, "/usr/bin/daemon", false);

        $this->assertEquals("/usr/bin/daemon", $condition->value);
    }

    public function test_Escaping3_Sanity() {

        $escapedVal = '\*';
        $escapedVal = stripslashes($escapedVal);

        $this->assertTrue('\*' === preg_quote($escapedVal));

        $escapedVal = '*';
        $escapedVal = stripslashes($escapedVal);

        $this->assertFalse('*' === preg_quote($escapedVal));
    }

    public function test_Escaping4() {
        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, "/usr/bin/daemon", false);

        $this->assertEquals("/usr/bin/daemon", $condition->value);
    }

    public function test_Escaping_ProcmailExtension() {
        $inputValueEscape = ".*+?^$[]-|()\\";
        $inputValueNoEscape = "<>/arbitrary_text\n\rk";

        $input = $inputValueEscape.$inputValueNoEscape;
        $expectedOutput = "\\".join("\\", str_split($inputValueEscape)).$inputValueNoEscape;

        $condition = Condition::create(Field::FROM, Operator::CONTAINS, $input, false);

        $this->assertEquals($expectedOutput, $condition->value);
    }

    public function test_ParenthesesPairs_ok() {
        $input = "((\\\\\(())\(\)\(\()";

        $this->assertTrue(Condition::checkParenthesesPairs($input));
    }

    public function test_ParenthesesPairs_fail1() {
        $input = "()(";

        $this->assertFalse(Condition::checkParenthesesPairs($input));
    }

    public function test_ParenthesesPairs_fail2() {
        $input = "(\\\()";

        $this->assertFalse(Condition::checkParenthesesPairs($input));
    }
}