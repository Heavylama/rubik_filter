<?php

require_once "common/ProcmailTestBase.php";
require_once "../../ProcmailRuleBuilder.php";
require_once "../../ProcmailField.php";
require_once "../../ProcmailOperator.php";

class ProcmailRuleBuilderTests extends ProcmailTestBase
{
    /**
     * @var \Rubik\Procmail\ProcmailRuleBuilder
     */
    private $builder = null;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new \Rubik\Procmail\ProcmailRuleBuilder();
    }

    protected function saveAndRun()
    {
        $this->common->saveAndRun($this->builder->makeRule());
    }

    public function test_EmptyRule() {
        $res = $this->builder->makeRule();

        $this->assertFalse($res);
    }

    public function test_Equals() {
        $this->common->generateInputMail("jerry", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM, \Rubik\Procmail\ProcmailOperator::EQUALS, "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
    }

    public function test_NotEquals() {
        $this->common->generateInputMail("jerry2", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM, \Rubik\Procmail\ProcmailOperator::EQUALS, "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_SpecialCondition_Invert() {
        $this->common->generateInputMail("jerry", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM, \Rubik\Procmail\ProcmailOperator::EQUALS, "jerry", true);
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_Contains() {
        $this->common->generateInputMail("jerry-dimarzio", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM, \Rubik\Procmail\ProcmailOperator::CONTAINS, "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }

    public function test_NotContains() {
        $this->common->generateInputMail("jefrry-dimarzio", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM, \Rubik\Procmail\ProcmailOperator::CONTAINS, "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_StartsWith() {
        $this->common->generateInputMail("jerry-dimarzio", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM,
                \Rubik\Procmail\ProcmailOperator::STARTS_WITH,
                "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }

    public function test_NotStartsWith() {
        $this->common->generateInputMail("ejerry-dimarzio", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM,
                \Rubik\Procmail\ProcmailOperator::STARTS_WITH,
                "jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_EscapedCondition1() {
        $this->common->generateInputMail("!jerry", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM,
                \Rubik\Procmail\ProcmailOperator::STARTS_WITH,
                "!jerry", true);
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertFalse($this->common->mailboxExists("good"));
        $this->assertTrue($this->common->defaultMailboxExists());
    }

    public function test_EscapedCondition2() {
        $this->common->generateInputMail("!jerry", "tomas");

        $res = $this->builder
            ->addCondition(\Rubik\Procmail\Field::FROM,
                \Rubik\Procmail\ProcmailOperator::STARTS_WITH,
                "!jerry");
        $this->assertNotFalse($res);

        $this->builder->actionMailbox("good");
        $this->saveAndRun();

        $this->assertTrue($this->common->mailboxExists("good"));
        $this->assertFalse($this->common->defaultMailboxExists());
    }
}