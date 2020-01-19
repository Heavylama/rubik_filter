<?php

require_once __DIR__ . "/../Common.php";
require_once __DIR__. "/StorageMock.php";

class ProcmailStorageTests extends \PHPUnit\Framework\TestCase {

    /**
     * @var StorageMock
     */
    private $client;
    private $validUser = "admin";
    private $validPw = "admin1234";

    /**
     * @var \Rubik\Storage\ProcmailStorage
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
     * @return \Rubik\Storage\ProcmailStorage
     */
    protected function getValidLoginStorage() {
        return new \Rubik\Storage\ProcmailStorage($this->client, $this->validUser, $this->validPw);
    }

    public function test_InvalidLogin() {
        $this->client->_copyFile(".procmailrc", "valid.procmail");

        $storage = new \Rubik\Storage\ProcmailStorage($this->client, "fefefe", "efef");

        $this->assertEquals($storage->getProcmailRules(), \Rubik\Storage\ProcmailStorage::ERR_NO_CONNECTION);
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

        $this->assertEquals(\Rubik\Storage\ProcmailStorage::ERR_NO_SECTION, $rules);
    }

    public function test_WrongHash() {
        $this->client->_copyFile(".procmailrc", "wrong_hash.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(\Rubik\Storage\ProcmailStorage::ERR_WRONG_HASH, $rules);
    }

    public function test_MissingFooter() {
        $this->client->_copyFile(".procmailrc", "missing_footer.procmail");

        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(\Rubik\Storage\ProcmailStorage::ERR_NO_SECTION, $rules);
    }

    public function test_NoFile() {
        $storage = $this->getValidLoginStorage();

        $rules = $storage->getProcmailRules();

        $this->assertEquals(\Rubik\Storage\ProcmailStorage::ERR_NO_FILE, $rules);
    }

    public function test_SimpleStore() {
        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(".procmailrc"));
        $this->assertEquals(file_get_contents("valid.procmail"), $this->client->_readFile(".procmailrc"));
    }

    public function test_BackupCreated() {
        $this->client->_copyFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE, "no_section.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE));
        $this->assertTrue($this->client->_fileExists(\Rubik\Storage\ProcmailStorage::PROCMAIL_BACKUP_FILE));

        $this->assertEquals(
            file_get_contents("no_section.procmail"),
            $this->client->get(\Rubik\Storage\ProcmailStorage::PROCMAIL_BACKUP_FILE));
    }

    public function test_BackupNotCreated() {
        $this->client->_copyFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE, "content_before.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);
        $this->assertTrue($this->client->_fileExists(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE));
        $this->assertFalse($this->client->_fileExists(\Rubik\Storage\ProcmailStorage::PROCMAIL_BACKUP_FILE));
    }

    public function test_MergeFirst() {
        $this->client->_copyFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE, "no_section.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault2");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents("content_before.procmail"),
            $this->client->get(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE));
    }

    public function test_MergeExistingSectionBefore() {
        $this->client->_copyFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE, "content_before.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault3");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents("content_before2.procmail"),
            $this->client->_readFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE)
        );
    }

    public function test_MergeExistingSectionBeforeAfter() {
        $this->client->_copyFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE, "content_before_after.procmail");

        $storage = $this->getValidLoginStorage();

        $res = $storage->putProcmailRules(":0:\ndefault3");

        $this->assertTrue($res);

        $this->assertEquals(
            file_get_contents("content_before_after2.procmail"),
            $this->client->_readFile(\Rubik\Storage\ProcmailStorage::PROCMAIL_FILE)
        );
    }
}