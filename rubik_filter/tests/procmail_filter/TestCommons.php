<?php

/**
 * Contains common code for procmail tests.
 */
class TestCommons
{
    public const TEST_WORKSPACE = "./test_workspace";

    public function cleanWorkspace()
    {
        array_map('unlink', glob(TEST_WORKSPACE . "/*"));
    }

    public function writeProcmail(string $filter)
    {
        return $this->writeWorkspaceFile($filter, ".procmailrc");
    }

    public function writeInputMail(string $mail)
    {
        return $this->writeWorkspaceFile($mail, "mail.msg");
    }

    public function generateInputMail(string $from, string $to, string $sub = "Test subject", string $message = "Test message")
    {
        $mail =
            "From $from  Wed Nov 13 18:06:51 2019
        Return-Path: <$from>
        X-Original-To: $to
        Delivered-To: $to
        Received: by $to.lan (Postfix, from userid 1000)
            id 17D63E2933; Wed, 13 Nov 2019 18:06:50 +0100 (CET)
        To: $to
        Subject: $sub
        X-Mailer: mail (GNU Mailutils 3.4)
        Message-Id: <20191113170651.17D63E2933@$to.lan>
        Date: Wed, 13 Nov 2019 18:06:50 +0100 (CET)
        From: $from
        
        $message
        
        
        ";

        $this->writeInputMail($mail);
    }

    public function runProcmail()
    {
        $return_code = 0;
        system("procmail -m " . self::TEST_WORKSPACE . "/.procmailrc < " . self::TEST_WORKSPACE . "/mail.msg", $return_code);
        return $return_code;
    }

    private function writeWorkspaceFile(string $content, string $filename)
    {
        $file = fopen(self::TEST_WORKSPACE . "/$filename", "w+");

        if ($file == false) {
            return false;
        }

        return fwrite($file, $content);
    }
}
