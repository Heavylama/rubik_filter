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

    protected function saveAndRun($text = null, $useDecodedVariant = true)
    {
        if ($text === null) {
            $this->builder->useDecodedCondition($useDecodedVariant);
            $text = $this->builder->createFilter();
        }

        $this->common->saveAndRun($text, $useDecodedVariant);
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
        $this->assertEquals("#START:\n:0:\n\"one\"\n\n#END:", trim($procmail));
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

    public function test_EmailEncoding_EncodedVariant() {
        $this->common->generateInputMail("=?UTF-8?B?xaBwYW7Em2wgVG9tw6HFoQ==?=",
            "abcd",
            "=?UTF-8?B?xaBwYW7Em2wgVG9tw6HFoQ==?= =?iso-8859-2?Q? probl=E9m je vy=F8e=B9en?=",
            "Dobr=FD den",
            "Content-Type: text/plain; charset=iso-8859-2; format=flowed\n"
            ."Content-Transfer-Encoding: quoted-printable\n"
            ."Content-Language: cs\n");

        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::CONTAINS, "Tomáš", false));
        $conditionBlock->addCondition(Condition::create(Field::SUBJECT, Operator::CONTAINS, "Tomáš problém", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "Dobrý den", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists('good'));
    }

    public function test_EmailEncoding_UnencodedVariant() {
        $this->common->generateInputMail("=?UTF-8?B?xaBwYW7Em2wgVG9tw6HFoQ==?=",
            "abcd",
            "=?UTF-8?B?xaBwYW7Em2wgVG9tw6HFoQ==?= =?iso-8859-2?Q? probl=E9m je vy=F8e=B9en?=",
            "Dobr=FD den",
            "Content-Type: text/plain; charset=iso-8859-2; format=flowed\n"
            ."Content-Transfer-Encoding: quoted-printable\n"
            ."Content-Language: cs\n");

        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::FROM, Operator::CONTAINS, "Tomáš", false));
        $conditionBlock->addCondition(Condition::create(Field::SUBJECT, Operator::CONTAINS, "Tomáš problém", false));
        $conditionBlock->addCondition(Condition::create(Field::BODY, Operator::CONTAINS, "Dobrý den", false));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun(null, false);

        $this->assertFalse($this->common->mailboxExists('good'));
    }

    public function test_CustomHeaderField() {
        $this->common->generateInputMail("fedef",
            "subject",
            "hello",
            "hello there",
            "X-Custom-Header: yes\n");

        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::CUSTOM, Operator::EQUALS, "yes", false, true, "X-Custom-Header"));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists('good'));
    }


    public function test_CustomHeader_ProcmailMacro() {
        $this->common->generateInputMail("daemon",
            "jerry",
            "hello",
            "hello there",
            "X-Custom-Header: yes\n");

        $this->builder->addAction(Action::MAILBOX, 'good');

        $conditionBlock = new ConditionBlock();
        $conditionBlock->setType(ConditionBlock::AND);
        $conditionBlock->addCondition(Condition::create(Field::FROM_MAILER, Operator::CONTAINS, "", false, true));
        $conditionBlock->addCondition(Condition::create(Field::FROM_DAEMON, Operator::CONTAINS, "", false, true));
        $this->builder->setConditionBlock($conditionBlock);

        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists('good'));
    }
}
