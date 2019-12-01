<?php

require_once "common/ProcmailTestBase.php";

echo "\n" . getcwd() . "\n";

class ProcmailRuleTests extends ProcmailTestBase
{
    public function test_NoConditions_NoAction()
    {
        $this->rule->setAction(null, null);

        $this->assertFalse($this->rule->make());
    }

    public function test_NoConditions_OnlyMailbox()
    {
        $this->common->generateInputMail("tomas", "jerry");

        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "rightbox");

        $resCode = $this->saveAndRun();

        $this->assertEquals(0, $resCode);
        $this->assertTrue($this->common->mailboxExists("rightbox"));
    }

    public function test_EscapeCondition()
    {
        $res = $this->rule->addCondition('!random', \Rubik\Procmail\SpecialCondition::INVERT);
        $this->assertTrue($res);

        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "test");
        $res = $this->rule->make();

        $this->assertStringContainsString("* !\\!random", $res);
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
        $res = $this->rule->setAction(\Rubik\Procmail\Action::FWD, "");
        $this->assertFalse($res);

        $res = $this->rule->setAction(\Rubik\Procmail\Action::FWD, null);
        $this->assertFalse($res);
    }

    public function test_DontUseLockfile()
    {
        $this->rule->useLockfile(false);
        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "mailbox");

        $res = $this->rule->make();
        $this->assertStringContainsString(":0\n", $res);
    }

    public function test_UseLockfile()
    {
        $this->rule->useLockfile(true, "example_lockfile");
        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "mailbox");

        $res = $this->rule->make();
        $this->assertStringContainsString(":0:example_lockfile\n", $res);
    }

    public function test_InvalidFlag()
    {
        $res = $this->rule->setFlags(\Rubik\Procmail\Flags::RAW_MODE . "O");

        $this->assertFalse($res);
    }

    public function test_FlagsGenerated()
    {
        $flags = \Rubik\Procmail\Flags::RAW_MODE . \Rubik\Procmail\Flags::WAIT_FINISH;
        $res = $this->rule->setFlags($flags);

        $this->assertTrue($res);

        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "abcd");
        $res = $this->rule->make();

        $this->assertStringContainsString(":0$flags:", $res);
    }

    public function test_OneCondition()
    {
        $this->common->generateInputMail("tomas", "jerry");
        $this->rule->addCondition("^From.*tomas");
        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "rightbox");

        $res = $this->rule->make();

        $this->assertStringContainsString("* ^From.*tomas", $res);
//        $resCode = $this->saveAndRun();
//
//        $this->assertEquals(0, $resCode);
//        $this->assertTrue($this->common->mailboxExists("rightbox"));
    }

    public function test_MultipleConditions()
    {
        $this->common->generateInputMail("tomas", "jerry");
        $this->rule->addCondition("^From.*tomas");
        $this->rule->addCondition("^To.*jerry");
        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "rightbox");

        $res = $this->rule->make();

        $this->assertStringContainsString("* ^From.*tomas", $res);
        $this->assertStringContainsString("* ^To.*jerry", $res);
//        $resCode = $this->saveAndRun();
//
//        $this->assertEquals(0, $resCode);
//        $this->assertTrue($this->common->mailboxExists("rightbox"));
    }


//    public function test_OneCondition_Fail()
//    {
//        $this->common->generateInputMail("tomas", "jerry");
//
//        $this->rule->addCondition("^From.*jack");
//        $this->rule->setAction(\Rubik\Procmail\Action::MAILBOX, "rightbox");
//
//        $resCode = $this->saveAndRun();
//
//        $this->assertEquals(0, $resCode);
//        $this->assertFalse($this->common->mailboxExists("rightbox"));
//    }
}
