<?php


require_once("../../ProcmailRule.php");

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\ProcmailRule;

abstract class ProcmailTestBase extends TestCase
{
    /** @var ProcmailRule */
    protected $rule;
    /** @var TestCommons */
    protected $common;

    protected function setUp(): Void
    {
        parent::setUp();
        $this->rule = new ProcmailRule();

        chdir(dirname(__FILE__));
        require_once("TestCommons.php");
        $this->common = new TestCommons();
        $this->common->cleanWorkspace();
    }

    protected function saveAndRun()
    {
        $this->common->saveAndRun($this->rule->make());
    }
}