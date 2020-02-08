<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\FilterBuilder;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

class FilterBuilderTests extends ProcmailTestBase
{
    /**
     * @var FilterBuilder
     */
    private $builder;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new FilterBuilder();
    }

    protected function saveAndRun($text = null)
    {
        if ($text === null) {
            $text = $this->builder->createFilter();
        }

        $this->common->saveAndRun($text);
    }

    public function test_NoCondition() {
        $this->common->generateInputMail('anyone','anyone');
        $this->builder->addAction(Action::MAILBOX, 'good');

        $procmail = $this->builder->createFilter();
        $this->assertNotNull($procmail);

        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists('good'));
    }

    public function test_NoAction() {
        $this->builder->resetBuilder();
        $res = $this->builder->createFilter();

        $this->assertNull($res);
    }

    public function test_OneAction() {
        $this->builder->addAction(Action::MAILBOX, "one");

        $procmail = $this->builder->createFilter();
        $this->assertEquals(":0:\none", trim($procmail));
    }

    public function test_MultipleActions() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::MAILBOX, "two");
        $this->builder->addAction(Action::FWD, "otherguy@domain.com");
        $this->builder->addAction(Action::FWD, "thisguy@domain.com");

        $procmail = $this->builder->createFilter();
        $count = substr_count($procmail, "{\n\n:0c:\none\n\n:0c:\ntwo\n\n:0:\n! otherguy@domain.com thisguy@domain.com\n\n}");
        $this->assertEquals(1, $count);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertTrue($this->common->mailboxExists("two"));
    }

    public function test_MultipleActions_MultipleRules() {
        $this->common->generateInputMail('anyone','anyone');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::OR);
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::STARTS_WITH, "bye", false));
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::EQUALS, "frolo", false));
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::STARTS_WITH, "an", false));
        $this->builder->setConditions($conditionBlock);

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::MAILBOX, "two");
        $this->builder->addAction(Action::FWD, "otherguy@domain.com");
        $this->builder->addAction(Action::FWD, "thisguy@domain.com");

        $procmail = $this->builder->createFilter();
        $count = substr_count($procmail, "{\n\n:0c:\none\n\n:0c:\ntwo\n\n:0:\n! otherguy@domain.com thisguy@domain.com\n\n}");
        $this->assertEquals(2, $count);


        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertTrue($this->common->mailboxExists("two"));
    }
}
