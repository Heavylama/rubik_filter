<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\FilterActionBlock;
use Rubik\Procmail\FilterBuilder;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

require_once __DIR__ . "/common/ProcmailTestBase.php";

class FilterParserTests extends ProcmailTestBase
{

    /**
     * @var FilterBuilder
     */
    private $builder;
    /**
     * @var FilterParser
     */
    private $parser;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new FilterBuilder();
        $this->parser = new FilterParser();
    }

    function test_SimpleAction() {
        $input = "#START:\n#:0:\n#mailbox\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        $actions = $output[0]->getActionBlock()->getActions();

        $expected = array(array(Action::MAILBOX => 'mailbox'));

        $this->assertEqualsCanonicalizing($expected, $actions);
    }

    function test_ActionBlock() {
        $input = "#START:\n:0:\n{\n:0c:\nmailbox\n\n:0c:\nmailbox2\n:0:\n! j   k   ffeer\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        $actions = $output[0]->getActionBlock()->getActions();

        $expected = array(Action::MAILBOX => array("mailbox", "mailbox2"), Action::FWD => array("j k ffeer"));

        $this->assertEqualsCanonicalizing($expected, $actions);
    }

    function test_invalidActionBlock_hasCond() {
        $input = "#START:\n:0:\n{\n:0:\n* cond\nmailbox\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }

    function test_missingAction_ActionBlock() {
        $input = "#START:\n:0:\n{\n:0c:\n\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }

    function test_missingAction() {
        $input = "#START:\n:0:\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }

    function test_Body_contains() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (test)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditions()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditions()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditions()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("test", $condition->value);
        $this->assertFalse($condition->negate);
        $this->assertEquals(Operator::CONTAINS, $condition->op);
    }

    function test_Body_equals() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (^^test^^)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditions()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditions()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditions()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("test", $condition->value);
        $this->assertFalse($condition->negate);
        $this->assertEquals(Operator::EQUALS, $condition->op);
    }

    function test_Body_startsWith() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (^^test)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditions()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditions()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditions()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("test", $condition->value);
        $this->assertFalse($condition->negate);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
    }

    function test_Body_regex() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (^^k|f)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditions()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditions()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditions()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("^^k|f", $condition->value);
        $this->assertFalse($condition->negate);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
    }

    function test_Body_multiple() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? ! (^^k\|f)\n";
        $input .= "* B ?? (^^fef|eere^^)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditions()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditions()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditions()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("k\|f", $condition->value);
        $this->assertTrue($condition->negate);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);

        $condition = $filter->getConditions()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("^^fef|eere^^", $condition->value);
        $this->assertFalse($condition->negate);
    }
}
