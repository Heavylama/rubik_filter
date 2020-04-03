<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Filter;
use Rubik\Procmail\Rule\Action;
use Rubik\Procmail\Rule\Field;
use Rubik\Procmail\Rule\Operator;

class FilterTest extends ProcmailTestBase
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
        $this->assertEquals("#START:\n:0:\n\"one\"\n\n#END:", trim($procmail));
    }

    public function test_MultipleActions() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::MAILBOX, "two");
        $this->builder->addAction(Action::FWD, "otherguy@domain.com");
        $this->builder->addAction(Action::FWD, "thisguy@domain.com");

        $procmail = $this->builder->createFilter();
        $count = substr_count($procmail, "{\n\n:0c:\n\"one\"\n\n:0c:\n\"two\"\n\n:0:\n! otherguy@domain.com thisguy@domain.com\n\n}");
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
        $this->builder->setConditionBlock($conditionBlock);

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::MAILBOX, "two");
        $this->builder->addAction(Action::FWD, "otherguy@domain.com");
        $this->builder->addAction(Action::FWD, "thisguy@domain.com");

        $procmail = $this->builder->createFilter();
        $count = substr_count($procmail, "{\n\n:0c:\n\"one\"\n\n:0c:\n\"two\"\n\n:0:\n! otherguy@domain.com thisguy@domain.com\n\n}");
        $this->assertEquals(2, $count);


        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertTrue($this->common->mailboxExists("two"));
    }
}
