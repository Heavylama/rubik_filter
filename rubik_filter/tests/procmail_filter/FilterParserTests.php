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

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

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

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

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

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

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

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("^^k|f", $condition->value);
        $this->assertFalse($condition->negate);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
    }

    function test_Body_multiple_rows() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? ! (^^k\|f)\n";
        $input .= "* B ?? (^^fef|eere^^)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals("k\|f", $condition->value);
        $this->assertTrue($condition->negate);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("^^fef|eere^^", $condition->value);
        $this->assertFalse($condition->negate);
    }

    function test_Body_multiple_onerow() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (^^k\|f)|(^^fef|eere^^)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::OR, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("k\|f", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("^^fef|eere^^", $condition->value);
        $this->assertFalse($condition->negate);
    }

    function test_Body_multiple_multirule() {
        $input = "#START:\n:0:\n";
        $input .= "* B ?? (^^k\|f)\n";
        $input .= "good\n\n";
        $input .= ":0E:\n";
        $input .= "* B ?? (^^fef|eere^^)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::OR, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("k\|f", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("^^fef|eere^^", $condition->value);
        $this->assertFalse($condition->negate);
    }

    function test_Header_contains() {
        $input = "#START:\n:0:\n";
        $input .= "* H ??! (^(From|Reply-to|Return-Path): *<?(.*frolo.*)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::CONTAINS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_Header_startsWith() {
        $input = "#START:\n:0:\n";
        $input .= "* H ??! (^(To): *<?(frolo.*)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::TO, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_Header_equals() {
        $input = "#START:\n:0:\n";
        $input .= "* H ??! (^(Cc): *<?(frolo)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(1, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::CC, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_Header_multiple_onerow() {
        $input = "#START:\n:0:\n";
        $input .= "* H ?? (^(Cc): *<?(frolo)>? *$)|(^(To): *<?(.*frol|ofo.*)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::OR, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::CC, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::TO, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals(".*frol|ofo.*", $condition->value);
        $this->assertFalse($condition->negate);
    }

    function test_Header_multiple_multirule() {
        $input = "#START:\n:0:\n";
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *<?(frolo)>? *$)\n";
        $input .= "good\n\n";
        $input .= ":0E:\n";
        $input .= "* H ?? ! (^(From|Reply-to|Return-Path): *<?(ferolo.*)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::OR, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("ferolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_Header_multiple_rows() {
        $input = "#START:\n:0:\n";
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *<?(frolo)>? *$)\n";
        $input .= "* H ?? ! (^(From|Reply-to|Return-Path): *<?(ferolo.*)>? *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("ferolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_MultipleFilters() {
        $input = "#START:Prvni\n:0:\n";
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *<?(frolo)>? *$)\n";
        $input .= "* B ?? ! (^^phoho)";
        $input .= "\ngood\n#END:Prvni\n\n";


        $input .= "#START:Druhy\n#:0:\n";
        $input .= "#* B ?? (^^Get this)|(^^ala|olo^^)\n";
        $input .= "#good\n#\n";
        $input .= "#:0E:\n";
        $input .= "#* H ?? ! (^(From|Reply-to|Return-Path): *<?(ferolo.*)>? *$)";
        $input .= "\n#good\n#END:Druhy";

        $output = $this->parser->parse($input);

        $this->assertCount(2, $output);

        /** @var FilterBuilder $filter */
        $filter = $output[0];

        $this->assertEquals("Prvni", $filter->getName());
        $this->assertTrue($filter->getFilterEnabled());
        $this->assertEquals(2, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::AND, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("frolo", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("phoho", $condition->value);
        $this->assertTrue($condition->negate);

        $filter = $output[1];

        $this->assertEquals("Druhy", $filter->getName());
        $this->assertFalse($filter->getFilterEnabled());
        $this->assertEquals(3, $filter->getConditionBlock()->count());
        $this->assertEquals(ConditionBlock::OR, $filter->getConditionBlock()->getType());

        /** @var Condition $condition */
        $condition = $filter->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("Get this", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("^^ala|olo^^", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $filter->getConditionBlock()->getConditions()[2];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::STARTS_WITH, $condition->op);
        $this->assertEquals("ferolo", $condition->value);
        $this->assertTrue($condition->negate);
    }

    function test_Reread_SanityCheck() {
        $builder = new FilterBuilder();
        $builder->setName("filter1");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $condition = Condition::create(Field::FROM, Operator::EQUALS, "jackie", false);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::BODY, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);

        $actionBlock = new FilterActionBlock();
        $actionBlock->addAction(Action::FWD, "frolo");
        $actionBlock->addAction(Action::FWD, "trolo");
        $builder->setActionBlock($actionBlock);
        $builder->setFilterEnabled(false);

        $output = $builder->createFilter();

        $builder->setName("filter2");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $condition = Condition::create(Field::FROM, Operator::CONTAINS, "jarl", true);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);
        $builder->setFilterEnabled(true);

        $actionBlock = new FilterActionBlock();
        $actionBlock->addAction(Action::MAILBOX, "good");
        $builder->setActionBlock($actionBlock);

        $output .= $builder->createFilter();

        $filters = $this->parser->parse($output);

        $this->assertCount(2, $filters);

        // RULE 1

        /** @var FilterBuilder $filter */
        $filter = $filters[0];

        $this->assertEquals("filter1", $filter->getName());
        $this->assertFalse($filter->getFilterEnabled());

        $parsedConditions = $filter->getConditionBlock();

        $this->assertEquals(2, $parsedConditions->count());
        $this->assertEquals(ConditionBlock::AND, $parsedConditions->getType());

        /** @var Condition $condition */
        $condition = $parsedConditions->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::EQUALS, $condition->op);
        $this->assertEquals("jackie", $condition->value);
        $this->assertFalse($condition->negate);

        $condition = $parsedConditions->getConditions()[1];

        $this->assertEquals(Field::BODY, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("ef.*def", $condition->value);
        $this->assertTrue($condition->negate);

        $actions = $filter->getActionBlock();

        $this->assertCount(1, $actions->getActions()[Action::FWD]);
        $this->assertEquals("frolo trolo", $actions->getActions()[Action::FWD][0]);

        // RULE 2

        $filter = $filters[1];

        $this->assertEquals("filter2", $filter->getName());
        $this->assertTrue($filter->getFilterEnabled());

        $parsedConditions = $filter->getConditionBlock();

        $this->assertEquals(2, $parsedConditions->count());
        $this->assertEquals(ConditionBlock::OR, $parsedConditions->getType());

        /** @var Condition $condition */
        $condition = $parsedConditions->getConditions()[0];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::CONTAINS, $condition->op);
        $this->assertEquals("jarl", $condition->value);
        $this->assertTrue($condition->negate);

        $condition = $parsedConditions->getConditions()[1];

        $this->assertEquals(Field::FROM, $condition->field);
        $this->assertEquals(Operator::PLAIN_REGEX, $condition->op);
        $this->assertEquals("ef.*def", $condition->value);
        $this->assertTrue($condition->negate);

        $actions = $filter->getActionBlock();

        $this->assertCount(1, $actions->getActions()[Action::MAILBOX]);
        $this->assertEquals("good", $actions->getActions()[Action::MAILBOX][0]);

    }
}
