<?php

require_once __DIR__ . '/common/ProcmailTestBase.php';

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock as ConditionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

class FilterBuilder_CombinationsTest extends ProcmailTestBase
{

    /**
     * @var Filter
     */
    private $builder;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new Filter();
    }

    protected function saveAndRun($text = null)
    {
        if ($text === null) {
            $text = $this->builder->createFilter();
        }

        $this->common->saveAndRun($text);
    }

    public function test_AndBlock_True() {
        $this->common->generateInputMail('anyone','jerry');
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "anyone", false));
        $conditionBlock->addCondition(Condition::create(Field::TO, Operator::EQUALS, "jerry", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

    public function test_AndBlock_False() {
        $this->common->generateInputMail('anyone','jerry');
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "anyone", false));
        $conditionBlock->addCondition(Condition::create(Field::TO, Operator::EQUALS, "jerry", true));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
    }

    public function test_OrBlock_OnlyHeader() {
        $this->common->generateInputMail('anyone','jerry');
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::TO, Operator::EQUALS, "jerry", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

    public function test_OrBlock_HeaderBody_True() {
        $this->common->generateInputMail('anyone','jerry', "subject", "hello mr anderson\nbye");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::STARTS_WITH, "hello", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::STARTS_WITH, "anderson", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

    public function test_OrBlock_HeaderBody_False() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", true));
        $conditionBlock->addCondition(Condition::create(Field::TO, Operator::CONTAINS, "hellothere", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
    }

    public function test_OrBlock_MultipleNegations() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", true));
        $conditionBlock->addCondition(Condition::create(Field::TO, Operator::CONTAINS, "jerryfef", true));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

}
