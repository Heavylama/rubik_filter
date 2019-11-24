<?php

require_once("procmail_filter.php");

use PHPUnit\Framework\TestCase;

class SimpleRulesTest extends TestCase
{
    /** @var ProcmailFilterBuilder */
    private $builder;
    /** @var TestCommons */
    private $common;

    protected function setUp()
    {
        parent::setUp();
        $this->builder = new ProcmailFilterBuilder();


        chdir(dirname(__FILE__));
        require_once("TestCommons.php");
        $this->common = new TestCommons();
    }


    public function testSimple()
    {
        $this->common->generateInputMail("tomas", "jerry");
        //$this->common->writeProcmail($this->builder->build());
        $this->common->runProcmail();

        $this->assertTrue(1 === 1);
    }
}
