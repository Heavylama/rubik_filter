<?php

use Rubik\Procmail\FilterBuilder;
use Rubik\Procmail\FilterParser;

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

    public function test() {
        $input = '
:0:lockfile
    {
 :0c:lockfile
 mailbox
 
 :0:
 mailbox2
}

:0d:lockfile
* ! kf
* H ??! fe
  {
 :0c:lockfile
 mailbox3
 
 :0:
 mailbox4
       }

:0c:lockfile
* ! kfs
* H ??! fe
! mailbox2     frenk@domain.com    
   f
';

        $res = $this->parser->parse($input);

        $this->assertEquals(0, $res);
    }

    function test_OneRule() {
        $input = "#START:\n#:0:\n#mailbox\n#END:";

        $output = $this->parser->parse($input);

        $this->assertNotNull($output);
    }
}
