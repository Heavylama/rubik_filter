<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Filter;

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
        $this->builder->reset();
        $res = $this->builder->createFilter();

        $this->assertNull($res);
    }

    public function test_OneAction() {
        $this->builder->addAction(Action::MAILBOX, "one");

        $procmail = $this->builder->createFilter();
        $this->assertEquals("#START:\n:0:".Filter::LOCKFILE."\n\"one\"\n\n#END:", trim($procmail));
    }

    public function test_MultipleActions() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->addAction(Action::MAILBOX, "two");
        $this->builder->addAction(Action::FWD, "otherguy@domain.com");
        $this->builder->addAction(Action::FWD, "thisguy@domain.com");

        $procmail = $this->builder->createFilter();
        $count = substr_count($procmail, "{\n\n:0c\n\"one\"\n\n:0c\n\"two\"\n\n:0\n! otherguy@domain.com thisguy@domain.com\n\n}");
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
        $count = substr_count($procmail, "{\n\n:0c\n\"one\"\n\n:0c\n\"two\"\n\n:0\n! otherguy@domain.com thisguy@domain.com\n\n}");
        $this->assertEquals(2, $count);

        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertTrue($this->common->mailboxExists("two"));
    }

    public function test_PostBehaviour_end_inbox() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_END_INBOX);

        $procmail = $this->builder->createFilter();

        $this->builder->reset();
        $this->builder->addAction(Action::MAILBOX, 'nope');

        $procmail .= $this->builder->createFilter();

        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertTrue($this->common->mailboxExists('default'));
        $this->assertFalse($this->common->mailboxExists('nope'));
    }

    public function test_PostBehaviour_discard() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_END_DISCARD);

        $procmail = $this->builder->createFilter();

        $this->builder->reset();
        $this->builder->addAction(Action::MAILBOX, 'nope');

        $procmail .= $this->builder->createFilter();

        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertFalse($this->common->mailboxExists("default"));
        $this->assertFalse($this->common->mailboxExists('nope'));
    }

    public function test_PostBehaviour_continue() {
        $this->common->generateInputMail('anyone','anyone');

        $this->builder->addAction(Action::MAILBOX, "one");
        $this->builder->setPostActionBehaviour(Filter::POST_CONTINUE);

        $procmail = $this->builder->createFilter();

        $this->builder->reset();
        $this->builder->addAction(Action::MAILBOX, 'yep');

        $procmail .= $this->builder->createFilter();

        $this->saveAndRun($procmail);

        $this->assertTrue($this->common->mailboxExists("one"));
        $this->assertFalse($this->common->mailboxExists("default"));
        $this->assertTrue($this->common->mailboxExists('yep'));
    }

    function test_DiscardOnlyOnce() {
        $this->assertTrue($this->builder->addAction(Action::DISCARD, null));
        $this->assertFalse($this->builder->addAction(Action::DISCARD,null));
    }

    function test_Discard_WithPipe() {
        $this->builder->addAction(Action::PIPE, "cmd");

        $this->assertTrue($this->builder->addAction(Action::DISCARD, null));
    }

    function test_Discard_OtherThanPipe() {
        $this->builder->addAction(Action::MAILBOX, "cmd");
        $this->builder->addAction(Action::PIPE, "cmd");

        $this->assertFalse($this->builder->addAction(Action::DISCARD,null));
    }

    function test_Discard_Last() {
        $this->assertTrue($this->builder->addAction(Action::DISCARD, null));
        $this->assertFalse($this->builder->addAction(Action::PIPE, "cmd"));
    }

    function test_Name_UTF8() {
        $name = "ěščřžýáíéúů filtr";
        $this->builder->setName($name);

        $this->assertEquals($name, $this->builder->getName());
    }
}
