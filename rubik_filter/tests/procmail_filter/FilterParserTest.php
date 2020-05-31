<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\ActionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Vacation\Vacation;

require_once __DIR__ . "/common/ProcmailTestBase.php";

class FilterParserTest extends ProcmailTestBase
{

    /**
     * @var Filter
     */
    private $builder;
    /**
     * @var FilterParser
     */
    private $parser;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new Filter();
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
        $input = "#START:\n:0:\n{\n:0c:\nmailbox\n\n:0c:\nmailbox2\n:0:\n! j@j.j   k@k.k   ffeer@f.f\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        $actions = $output[0]->getActionBlock()->getActions();

        $expected = array(Action::MAILBOX => array("mailbox", "mailbox2"), Action::FWD => array("j@j.j k@k.k ffeer@f.f"));

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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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

        /** @var Filter $filter */
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
        $input .= "* H ??! (^(From|Reply-to|Return-Path): *(.*frolo.*) *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ??! (^(To): *(frolo.*) *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ??! (^(Cc): *(frolo) *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ?? (^(Cc): *(frolo) *$)|(^(To): *(.*frol|ofo.*))";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *(frolo) *$)\n";
        $input .= "good\n\n";
        $input .= ":0E:\n";
        $input .= "* H ?? ! (^(From|Reply-to|Return-Path): *(ferolo.*) *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *(frolo) *$)\n";
        $input .= "* H ?? ! (^(From|Reply-to|Return-Path): *(ferolo.*) *$)";
        $input .= "\ngood\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        /** @var Filter $filter */
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
        $input .= "* H ?? (^(From|Reply-to|Return-Path): *(frolo) *$)\n";
        $input .= "* B ?? ! (^^phoho)";
        $input .= "\ngood\n#END:Prvni\n\n";


        $input .= "#START:Druhy\n#:0:\n";
        $input .= "#* B ?? (^^Get this)|(^^ala|olo^^)\n";
        $input .= "#good\n#\n";
        $input .= "#:0E:\n";
        $input .= "#* H ?? ! (^(From|Reply-to|Return-Path): *(ferolo.*) *$)";
        $input .= "\n#good\n#END:Druhy";

        $output = $this->parser->parse($input);

        $this->assertCount(2, $output);

        /** @var Filter $filter */
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

    function test_Reread_SanityCheck_DecodedVersion() {
        $builder = new Filter();
        $builder->setName("filter1 český");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $condition = Condition::create(Field::FROM, Operator::EQUALS, "jackie", false);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::BODY, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);

        $actionBlock = new ActionBlock();
        $actionBlock->addAction(Action::FWD, "frolo@domain.com");
        $actionBlock->addAction(Action::FWD, "trolo@domain.com");
        $builder->setActionBlock($actionBlock);
        $builder->setFilterEnabled(false);

        $output = $builder->createFilter();

        $builder->setName("filter2 taky český");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $condition = Condition::create(Field::FROM, Operator::CONTAINS, "jarl", true);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);
        $builder->setFilterEnabled(true);

        $actionBlock = new ActionBlock();
        $actionBlock->addAction(Action::MAILBOX, "good");
        $builder->setActionBlock($actionBlock);

        $output .= $builder->createFilter();

        $filters = $this->parser->parse($output);

        $this->assertCount(2, $filters);

        // RULE 1

        /** @var Filter $filter */
        $filter = $filters[0];

        $this->assertEquals("filter1 český", $filter->getName());
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
        $this->assertEquals("frolo@domain.com trolo@domain.com", $actions->getActions()[Action::FWD][0]);

        // RULE 2

        $filter = $filters[1];

        $this->assertEquals("filter2 taky český", $filter->getName());
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

    function test_Reread_SanityCheck_UndecodedVersion() {
        $builder = new Filter();
        $builder->useDecodedCondition(false);

        $builder->setName("filter1 český");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $condition = Condition::create(Field::FROM, Operator::EQUALS, "jackie", false);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::BODY, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);

        $actionBlock = new ActionBlock();
        $actionBlock->addAction(Action::FWD, "frolo@domain.com");
        $actionBlock->addAction(Action::FWD, "trolo@domain.com");
        $builder->setActionBlock($actionBlock);
        $builder->setFilterEnabled(false);

        $output = $builder->createFilter();

        $builder->setName("filter2 taky český");

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $condition = Condition::create(Field::FROM, Operator::CONTAINS, "jarl", true);
        $conditionBlock->addCondition($condition);
        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, "ef.*def", true);
        $conditionBlock->addCondition($condition);
        $builder->setConditionBlock($conditionBlock);
        $builder->setFilterEnabled(true);

        $actionBlock = new ActionBlock();
        $actionBlock->addAction(Action::MAILBOX, "good");
        $builder->setActionBlock($actionBlock);

        $output .= $builder->createFilter();

        $filters = $this->parser->parse($output);

        $this->assertCount(2, $filters);

        // RULE 1

        /** @var Filter $filter */
        $filter = $filters[0];

        $this->assertEquals("filter1 český", $filter->getName());
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
        $this->assertEquals("frolo@domain.com trolo@domain.com", $actions->getActions()[Action::FWD][0]);

        // RULE 2

        $filter = $filters[1];

        $this->assertEquals("filter2 taky český", $filter->getName());
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

    function test_RegexInput_Parentheses_Header() {
        $condition = Condition::create(Field::FROM, Operator::PLAIN_REGEX, "(\((ok) *$)", false);
        $block = new ConditionBlock();
        $block->setType(ConditionBlock::OR);

        $block->addCondition($condition);
        $block->addCondition($condition);

        $this->builder->setConditionBlock($block);

        $this->builder->addAction(Action::MAILBOX, "test");

        $procmail = $this->builder->createFilter();

        $filterOut = $this->parser->parse($procmail)[0];
        $conditionOut = $filterOut->getConditionBlock()->getConditions();

        $this->assertCount(2, $conditionOut);

        $this->assertEquals("(\((ok) *$)", $conditionOut[0]->value);
    }

    function test_RegexInput_Parentheses_Body() {
        $condition = Condition::create(Field::BODY, Operator::PLAIN_REGEX, "(\((ok) *$)", false);
        $block = new ConditionBlock();
        $block->setType(ConditionBlock::OR);

        $block->addCondition($condition);
        $block->addCondition($condition);

        $this->builder->setConditionBlock($block);

        $this->builder->addAction(Action::MAILBOX, "test");

        $procmail = $this->builder->createFilter();

        $filterOut = $this->parser->parse($procmail)[0];
        $conditionOut = $filterOut->getConditionBlock()->getConditions();

        $this->assertCount(2, $conditionOut);

        $this->assertEquals("(\((ok) *$)", $conditionOut[0]->value);
    }


    function test_Vacation() {
        $vac = new Vacation();
        // P=C3=A1r =C5=99=C3=A1dk=C5=AF=0A=0A=C5=99=C3=A1dk=C5=AF,=0A=0ANashledanou =\n=C5=A1=C4=8D=C5=99=C5=BE=C3=BD=C3=A1=C4=9B
        $message = "Pár řádků\n\nřádků,\n\nNashledanou ščřžýáěáíé+ěš          ščř!!!!123456789";
        $vac->setMessage($message);

        $start = new DateTime();
        $end = new DateTime();
        $end->add(new DateInterval('P2D'));

        $vac->setRange($start, $end);
        $vac->setReplyTime(123);

        $output = $vac->createFilter();

        $filters = $this->parser->parse($output);

        $this->assertCount(1, $filters);
        $this->assertEquals(get_class($vac), get_class($filters[0]));
        $this->assertEquals($vac->getMessage(), $filters[0]->getMessage());
        $this->assertEquals($vac->getReplyTime(), $filters[0]->getReplyTime());

        $testFmt = 'd n Y';
        $this->assertEquals($start->format($testFmt), $vac->getRange()['start']->format($testFmt));
        $this->assertEquals($end->format($testFmt), $vac->getRange()['end']->format($testFmt));

    }


    function test_PostAction_end_inbox() {
        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_END_INBOX);

        $procmail = $this->builder->createFilter();

        $filters = $this->parser->parse($procmail);

        $this->assertCount(1, $filters);
        $this->assertCount(1, $filters[0]->getActionBlock()->getActions()[Action::MAILBOX]);
        $this->assertEquals(Filter::POST_END_INBOX, $filters[0]->getPostActionBehaviour());
    }

    function test_PostAction_end_discard() {
        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_END_DISCARD);

        $procmail = $this->builder->createFilter();

        $filters = $this->parser->parse($procmail);

        $this->assertCount(1, $filters);
        $this->assertEquals(Filter::POST_END_DISCARD, $filters[0]->getPostActionBehaviour());
    }

    function test_PostAction_continue() {
        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_CONTINUE);

        $procmail = $this->builder->createFilter();

        $filters = $this->parser->parse($procmail);

        $this->assertCount(1, $filters);
        $this->assertEquals(Filter::POST_CONTINUE, $filters[0]->getPostActionBehaviour());
    }

    function test_PartialParse() {
        $this->builder->reset();
        $this->builder->setConditionBlock(new ConditionBlock());
        $this->builder->addAction(Action::FWD, "broken@domain.com");

        $procmail = $this->builder->createFilter();

        // break it
        $procmail = str_replace("! broken@domain.com", "* H ?? Oh no", $procmail);

        $this->builder->setName("right one");
        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::FWD, "joe@domain.com");
        $this->builder->setPostActionBehaviour(Filter::POST_CONTINUE);

        $procmail .= $this->builder->createFilter();

        $filters = $this->parser->parse($procmail, true);

        $this->assertCount(1, $filters);
        $this->assertEquals("right one", $filters[0]->getName());
    }

    function test_CustomHeaderField() {
        $this->builder->addAction(Action::MAILBOX, 'ok');
        $this->builder->setConditionBlock(new ConditionBlock());
        $this->builder->getConditionBlock()->addCondition(Condition::create(Field::CUSTOM, Operator::CONTAINS, 'dva', false, true, 'X-Custom-Header:'));

        $procmail = $this->builder->createFilter();
        $result = $this->parser->parse($procmail);

        $condition = $result[0]->getConditionBlock()->getConditions()[0];

        $this->assertEquals(Field::CUSTOM, $condition->field);
        $this->assertEquals(Operator::CONTAINS, $condition->op);
        $this->assertEquals('X-Custom-Header', $condition->customField);
    }

}
