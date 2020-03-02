<?php

require_once __DIR__ . "/common/ProcmailTestBase.php";

use PHPUnit\Framework\TestCase;
use Rubik\Procmail\Vacation;

class VacationTest extends TestCase
{

    private $vac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vac = new Vacation();
    }

    public function test_SanityCheck() {
        $this->vac->setMessagePath("\$HOME/.procmail_messages/message.msg");

        $start = new DateTime();
        $end = new DateTime();
        $end->add(new DateInterval('P2D'));

        $this->vac->setRange($start, $end);

        $output = $this->vac->createFilter();

        $this->assertNotNull($output);
    }

}
