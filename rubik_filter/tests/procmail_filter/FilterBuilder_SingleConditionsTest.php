<?php

use Rubik\Procmail\Condition;
use Rubik\Procmail\ConditionBlock;
use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Field;
use Rubik\Procmail\Constants\Operator;
use Rubik\Procmail\Filter;

require_once __DIR__ . "/common/ProcmailTestBase.php";

class FilterBuilder_SingleConditionsTest extends ProcmailTestBase
{
    /**
     * @var Filter
     */
    private $builder = null;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new Filter();
    }

    protected function saveAndRun($useDecodedVariant = true)
    {
        $text = $this->builder->createFilter();

        $this->common->saveAndRun($text, $useDecodedVariant);
    }

    protected function addSingleCondition($field, $op, $value, $negate = false) {
        $conditionBlock = new ConditionBlock();
        $condition = Condition::create($field, $op, $value, $negate);

        $this->assertNotNull($condition);

        $conditionBlock->addCondition($condition);

        $this->builder->setConditionBlock($conditionBlock);
    }

    protected function actionMailbox($mailboxName) {
        $this->builder->addAction(Action::MAILBOX, $mailboxName);
    }

    public function test_NoAction() {
        $res = $this->builder->createFilter();

        $this->assertNull($res);
    }

    public function test_Equals() {
        $this->common->generateInputMail("jerry", "tomas");

        $this->addSingleCondition(Field::FROM, Operator::EQUALS, "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

    public function test_NotEquals() {
        $this->common->generateInputMail("jerry2", "tomas");

        $this->addSingleCondition(Field::FROM, Operator::EQUALS, "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_SpecialCondition_Invert() {
        $this->common->generateInputMail("jerry", "tomas");

        $this->addSingleCondition(Field::FROM, Operator::EQUALS, "jerry", true);
        
        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_Contains() {
        $this->common->generateInputMail("jerry-dimarzio", "tomas");

        $this->addSingleCondition(Field::FROM, Operator::CONTAINS, "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }

    public function test_NotContains() {
        $this->common->generateInputMail("jefrry-dimarzio", "tomas");

        $this->addSingleCondition(Field::FROM, Operator::CONTAINS, "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_StartsWith() {
        $this->common->generateInputMail("jerry-dimarzio", "tomas");

        $this->addSingleCondition(Field::FROM,
                Operator::STARTS_WITH,
                "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }

    public function test_NotStartsWith() {
        $this->common->generateInputMail("ejerry-dimarzio", "tomas");

        $this->addSingleCondition(Field::FROM,
                Operator::STARTS_WITH,
                "jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_EscapedCondition1() {
        $this->common->generateInputMail("!jerry", "tomas");

        $this->addSingleCondition(Field::FROM,
                Operator::STARTS_WITH,
                "!jerry", true);

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_EscapedCondition2() {
        $this->common->generateInputMail("!jerry", "tomas");

        $this->addSingleCondition(Field::FROM,
                Operator::STARTS_WITH,
                "!jerry");

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }

    public function test_Email_sanityCheck() {
        $this->common->generateInputMail("jerry@domainkcom", "tomas");

        $this->addSingleCondition(Field::FROM,
            Operator::EQUALS,
            "jerry@domain.com", true);

        $this->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }
}