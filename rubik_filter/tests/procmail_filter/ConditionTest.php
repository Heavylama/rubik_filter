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
        $inputValueNoEscape = "<>/arbitrary_text";

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

    public function test_ParenthesesPairs_CustomField() {
        $input = "(\())";

        $condition = Condition::create(Field::CUSTOM, Operator::PLAIN_REGEX, "a", true, true, $input);

        $this->assertNull($condition);
    }

    public function test_unprintableCharacters_value() {
        $input = "\\nok\nbad4č'";

        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, $input, true);

        $this->assertEquals("\\nokbad4č'", $condition->value);
    }

    public function test_unprintableCharacters_CustomField() {
        $input = "\\nok\nbad";

        $condition = Condition::create(Field::CUSTOM, Operator::PLAIN_REGEX, "a", true, true, $input);

        $this->assertEquals("\\nokbad", $condition->customField);
    }
}