<?php

use PHPUnit\Framework\TestCase;
use Rubik\Storage\ProcmailStorage;

require_once __DIR__ . "/../Common.php";
require_once __DIR__. "/StorageMock.php";

class ProcmailStorageTest extends TestCase {

    /**
     * @var StorageMock
     */
    private $client;
    private $validUser = "admin";
    private $validPw = "admin1234";

    /**
     * @var ProcmailStorage
     */
    private $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new StorageMock(__DIR__ . "/workspace", $this->validUser, $this->validPw);

        $this->client->_clean();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->client->_clean();
    }

    /**
     * @return ProcmailStorage
     */
    protected function getValidLoginStorage() {
        return new ProcmailStorage($this->client, $this->validUser, $this->validPw);
    }

    public function test_InvalidLogin() {
        $this->client->_copyFile(".procmailrc", "valid.procmail");

        $storage = new ProcmailStorage($this->client, "fefefe", "efef");

        $this->assertEquals($storage->getProcmailRules(), ProcmailStorage::ERR_NO_CONNECTION);
    }

    public function test_Hash_SanityCheck() {
        $storage = $this->getValidLoginStorage();

        $text = ":0:\ndefault";

        $hash = $storage->hashRules($text);
        $expected = "1538C8454FE70F0A70FBE5D05EBC5DF3";

        $this->assertEqualsIgnoringCase($expected, $hash);
    }

    public function test_ValidSection() {
        $this->client->_copyFile(".procmailrc", "valid.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(":0:\ndefault\n", $rules);
    }

    public function test_EmptySection() {
        $this->client->_copyFile(".procmailrc", "empty_section.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(ProcmailStorage::ERR_NO_SECTION, $rules);
    }

    public function test_WrongHash() {
        $this->client->_copyFile(".procmailrc", "wrong_hash.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(ProcmailStorage::ERR_INVALID_HASH, $rules);
    }

    public function test_MissingFooter() {
        $this->client->_copyFile(".procmailrc", "missing_footer.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(ProcmailStorage::ERR_NO_SECTION, $rules);
    }

    public function test_NoFile() {
        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(ProcmailStorage::ERR_CANNOT_READ, $rules);
    }

    public function test_SimpleStore() {
        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(".procmailrc"));
        $this->assertEquals(file_get_contents(__DIR__ . "/valid.procmail"), $this->client->_readFile(".procmailrc"));
    }

    public function test_BackupCreated() {
        $this->client->_copyFile(ProcmailStorage::PROCMAIL_FILE, "no_section.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(ProcmailStorage::PROCMAIL_FILE));
        $this->assertTrue($this->client->_fileExists(ProcmailStorage::PROCMAIL_BACKUP_FILE));

        $this->assertEquals(
            file_get_contents(__DIR__ . "/no_section.procmail"),
            $this->client->get(ProcmailStorage::PROCMAIL_BACKUP_FILE));
    }

    public function test_BackupNotCreated() {
        $this->client->_copyFile(ProcmailStorage::PROCMAIL_FILE, "content_before.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(ProcmailStorage::PROCMAIL_FILE));
        $this->assertFalse($this->client->_fileExists(ProcmailStorage::PROCMAIL_BACKUP_FILE));
    }

    public function test_MergeFirst() {
        $this->client->_copyFile(ProcmailStorage::PROCMAIL_FILE, "no_section.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents(__DIR__ . "/content_before.procmail"),
            $this->client->get(ProcmailStorage::PROCMAIL_FILE));
    }

    public function test_MergeExistingSectionBefore() {
        $this->client->_copyFile(ProcmailStorage::PROCMAIL_FILE, "content_before.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault3");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents(__DIR__ . "/content_before2.procmail"),
            $this->client->_readFile(ProcmailStorage::PROCMAIL_FILE)
        );
    }

    public function test_MergeExistingSectionBeforeAfter() {
        $this->client->_copyFile(ProcmailStorage::PROCMAIL_FILE, "content_before_after.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault3");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents(__DIR__ . "/content_before_after2.procmail"),
            $this->client->_readFile(ProcmailStorage::PROCMAIL_FILE)
        );
    }

    public function test_listMessages_empty() {
        $this->client->mkdir(ProcmailStorage::VACATION_REPLIES_LOCATION);

        $storage = $this->getValidLoginStorage();

        $messages = $storage->listVacationMessages();

        $this->assertCount(0, $messages);
    }

    public function test_listMessages_nonEmpty() {
        $this->client->mkdir(ProcmailStorage::VACATION_REPLIES_LOCATION);
        $this->client->_createFile(ProcmailStorage::VACATION_REPLIES_LOCATION . "/one", "one");
        $this->client->_createFile(ProcmailStorage::VACATION_REPLIES_LOCATION . "/two", "two");

        $storage = $this->getValidLoginStorage();

        $messages = $storage->listVacationMessages();

        $this->assertCount(2, $messages);
    }

    public function test_readVacationMessage() {
        $this->client->mkdir(ProcmailStorage::VACATION_REPLIES_LOCATION);
        $this->client->_createFile(ProcmailStorage::VACATION_REPLIES_LOCATION."/test_message.msg", "Hello");

        $storage = $this->getValidLoginStorage();

        $vacationMessage = $storage->getReply("test_message.msg");

        $this->assertEquals("Hello", $vacationMessage);
    }

    public function test_readVacationMessage_missingFolder() {
        $storage = $this->getValidLoginStorage();

        $vacationMessage = $storage->getReply("test_message.msg");

        $this->assertEquals(ProcmailStorage::ERR_CANNOT_READ, $vacationMessage);
    }

    public function test_readVacationMessage_missingFile() {
        $this->client->mkdir(ProcmailStorage::VACATION_REPLIES_LOCATION);

        $storage = $this->getValidLoginStorage();

        $vacationMessage = $storage->getReply("test_message.msg");

        $this->assertEquals(ProcmailStorage::ERR_CANNOT_READ, $vacationMessage);
    }
}