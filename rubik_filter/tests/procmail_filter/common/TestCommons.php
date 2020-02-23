<?php

/**
 * Contains common code for procmail tests.
 */
final class TestCommons
{
    public const TEST_WORKSPACE = "../test_workspace";

    public function cleanWorkspace()
    {
        array_map('unlink', glob(self::TEST_WORKSPACE . "/*"));
    }

    public function writeProcmail(string $filter)
    {
        // include some extra settings for debug
        $filter = "ORGMAIL=default\nVERBOSE=on\n\n".$filter;

        return $this->writeWorkspaceFile($filter, "procmailrc");
    }

    public function writeInputMail(string $mail)
    {
        return $this->writeWorkspaceFile($mail, "mail.msg");
    }

    public function saveAndRun($rule)
    {
        $this->writeProcmail($rule);

        return $this->runProcmail();
    }

    public function generateInputMail(string $from, string $to, string $sub = "Test subject", string $message = "Test message")
    {
        $mail =
            "From $from  Wed Nov 13 18:06:51 2019\n" .
            "Return-Path: <$from>\n" .
            "X-Original-To: $to\n" .
            "Delivered-To: $to\n" .
            "Received: by $to.lan (Postfix, from userid 1000)\n" .
            "    id 17D63E2933; Wed, 13 Nov 2019 18:06:50 +0100 (CET)\n" .
            "To: $to\n" .
            "Subject: $sub\n" .
            "X-Mailer: mail (GNU Mailutils 3.4)\n" .
            "Message-Id: <20191113170651.17D63E2933@$to.lan>\n" .
            "Date: Wed, 13 Nov 2019 18:06:50 +0100 (CET)\n" .
            "From: $from\n" .
            "\n" .
            $message .
            "\n\n";

        $this->writeInputMail($mail);
    }

    public function runProcmail()
    {
        $return_code = 0;
        system("cd " . self::TEST_WORKSPACE . ";procmail -m ./procmailrc < ./mail.msg", $return_code);
        return $return_code;
    }

    public function mailboxExists($mailbox)
    {
        return file_exists(self::TEST_WORKSPACE."/$mailbox");
    }

    public function defaultMailboxExists()
    {
        return $this->mailboxExists("default");
    }

    private function writeWorkspaceFile(string $content, string $filename)
    {
        if (!file_exists(self::TEST_WORKSPACE)) {
            mkdir(self::TEST_WORKSPACE);
        }

        $file = fopen(self::TEST_WORKSPACE . "/$filename", "a+");

        if ($file == false) {
            return false;
        }

        return fwrite($file, $content);
    }
}
