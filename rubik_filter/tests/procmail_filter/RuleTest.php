<?php

use Rubik\Procmail\Constants\Action;
use Rubik\Procmail\Constants\Flags;
use Rubik\Procmail\Constants\SpecialCondition;
use Rubik\Procmail\Rule;

require_once "common/ProcmailTestBase.php";

class RuleTest extends ProcmailTestBase
{

    /**
     * @var Rule
     */
    private $rule;

    protected function setUp(): Void
    {
        parent::setUp();


        $this->rule = new Rule();
    }

    protected function saveAndRun() {
        $this->common->saveAndRun($this->rule->make(), false);
    }

    public function test_NoConditions_NoAction()
    {
        $this->rule->setAction(null, null);

        $this->assertFalse($this->rule->make());
    }

    public function test_NoConditions_OnlyMailbox()
    {
        $this->common->generateInputMail("tomas", "jerry");

        $this->rule->setAction(Action::MAILBOX, "rightbox");

        $resCode = $this->saveAndRun();

        $this->assertEquals(0, $resCode);
        $this->assertTrue($this->common->mailboxExists("rightbox"));
    }

    public function test_EscapeCondition()
    {
        $res = $this->rule->addCondition('!random', SpecialCondition::INVERT);
        $this->assertTrue($res);

        $this->rule->setAction(Action::MAILBOX, "test");
        $res = $this->rule->make();

        $this->assertStringContainsString("* ! \\!random", $res);
    }

    public function test_InvalidSpecialCondition()
    {
        $res = $this->rule->addCondition("abcd", "L");
        $this->assertFalse($res);
    }

    public function test_InvalidAction()
    {
        $res = $this->rule->setAction("J", "F");
        $this->assertFalse($res);
    }

    public function test_EmptyActionArg()
    {
        $res = $this->rule->setAction(Action::FWD, "");
        $this->assertFalse($res);

        $res = $this->rule->setAction(Action::FWD, null);
        $this->assertFalse($res);
    }

    public function test_DontUseLockfile()
    {
        $this->rule->useLockfile(false);
        $this->rule->setAction(Action::MAILBOX, "mailbox");

        $res = $this->rule->make();
        $this->assertStringContainsString(":0\n", $res);
    }

    public function test_UseLockfile()
    {
        $this->rule->useLockfile(true, "example_lockfile");
        $this->rule->setAction(Action::MAILBOX, "mailbox");

        $res = $this->rule->make();
        $this->assertStringContainsString(":0:example_lockfile\n", $res);
    }

    public function test_InvalidFlag()
    {
        $res = $this->rule->setFlags(Flags::RAW_MODE . "O");

        $this->assertFalse($res);
    }

    public function test_FlagsGenerated()
    {
        $flags = Flags::RAW_MODE . Flags::WAIT_FINISH;
        $res = $this->rule->setFlags($flags);

        $this->assertTrue($res);

        $this->rule->setAction(Action::MAILBOX, "abcd");
        $res = $this->rule->make();

        $this->assertStringContainsString(":0$flags", $res);
    }

    public function test_OneCondition()
    {
        $this->common->generateInputMail("tomas", "jerry");
        $this->rule->addCondition("^From.*tomas");
        $this->rule->setAction(Action::MAILBOX, "rightbox");

        $res = $this->rule->make();

        $this->assertStringContainsString("* ^From.*tomas", $res);
    }

    public function test_MultipleConditions()
    {
        //$this->common->generateInputMail("tomas", "jerry");
        $this->rule->addCondition("^From.*tomas");
        $this->rule->addCondition("^To.*jerry");
        $this->rule->setAction(Action::MAILBOX, "rightbox");

        $res = $this->rule->make();

        $this->assertStringContainsString('* ^From.*tomas', $res);
        $this->assertStringContainsString("* ^To.*jerry", $res);
    }

    public function test_DisabledRule() {
        $this->rule->setAction(Action::MAILBOX, "ok");
        $this->rule->setEnabled(false);

        $res = $this->rule->make();

        $this->assertStringContainsString("#:0\n#\"ok\"", $res);
    }

    public function test_Escaping() {
        $this->rule->setAction(Action::MAILBOX, "ok");
        $this->rule->addCondition("\\\*");

        $res = trim($this->rule->make());

        $this->assertEquals(":0\n* \\\*\n\"ok\"", $res);
    }
}
