<?php

require_once __DIR__ . "/../../Common.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Rule;

abstract class ProcmailTestBase extends TestCase
{
    /** @var TestCommons */
    protected $common;

    protected function setUp(): Void
    {
        parent::setUp();

        chdir(dirname(__FILE__));
        require_once("TestCommons.php");
        $this->common = new TestCommons();
        $this->common->cleanWorkspace();
    }
}