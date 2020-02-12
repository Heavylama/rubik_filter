<?php

use Rubik\Procmail\FilterActionBlock;
use Rubik\Procmail\FilterBuilder;
use Rubik\Procmail\FilterParser;
use Rubik\Procmail\Rule\Action;

require_once __DIR__ . "/common/ProcmailTestBase.php";

class FilterParserTests extends ProcmailTestBase
{

    /**
     * @var FilterBuilder
     */
    private $builder;
    /**
     * @var FilterParser
     */
    private $parser;

    protected function setUp(): Void
    {
        parent::setUp();

        $this->builder = new FilterBuilder();
        $this->parser = new FilterParser();
    }

    function test_SimpleAction() {
        $input = "#START:\n#:0:\n#mailbox\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        $actions = $output[0]->getActionBlock()->getActions();

        $expected = array(array(Action::MAILBOX => 'mailbox'));

        $this->assertEqualsCanonicalizing($expected, $actions);
    }

    function test_ActionBlock() {
        $input = "#START:\n:0:\n{\n:0c:\nmailbox\n\n:0c:\nmailbox2\n:0:\n! j   k   ffeer\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertCount(1, $output);

        $actions = $output[0]->getActionBlock()->getActions();

        $expected = array(Action::MAILBOX => array("mailbox", "mailbox2"), Action::FWD => array("j k ffeer"));

        $this->assertEqualsCanonicalizing($expected, $actions);
    }

    function test_invalidActionBlock_hasCond() {
        $input = "#START:\n:0:\n{\n:0:\n* cond\nmailbox\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }

    function test_missingAction_ActionBlock() {
        $input = "#START:\n:0:\n{\n:0c:\n\n}\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }

    function test_missingAction() {
        $input = "#START:\n:0:\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNull($output);
    }
}
