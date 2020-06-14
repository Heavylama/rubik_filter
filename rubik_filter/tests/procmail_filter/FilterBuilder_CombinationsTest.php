<?php

require_once __DIR__ . '/common/ProcmailTestBase.php';

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock as ConditionBlock;
use Rubik\Procmail\Constants;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Filter;

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

    protected function saveAndRun($text = null, $useDecodedVariant = true)
    {
        if ($text === null) {
            $this->builder->useDecodedCondition($useDecodedVariant);
            $text = $this->builder->createFilter();
        }

        $this->common->saveAndRun($text, $useDecodedVariant);
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

    public function test_OrBlock_TwoRules_first() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "nope", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $mailbox = $this->common->readMailbox("good");
        $this->assertEquals(1, substr_count($mailbox, 'hello mr anderson'));
    }

    public function test_OrBlock_TwoRules_second() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "nope", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "hello mr", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $mailbox = $this->common->readMailbox("good");
        $this->assertEquals(1, substr_count($mailbox, 'hello mr anderson'));
    }

    public function test_OrBlock_TwoRules_both() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "hello", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $mailbox = $this->common->readMailbox("good");
        $this->assertEquals(1, substr_count($mailbox, 'hello mr anderson'));
    }


    public function test_OrBlock_TwoRules_both_multipleActions() {
        $this->common->generateInputMail('frolo','jerry', "subject", "hello mr anderson");
        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "hello", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $mailbox = $this->common->readMailbox("good");
        $this->assertEquals(1, substr_count($mailbox, 'hello mr anderson'));
    }
}
