<?php

namespace Rubik\Procmail;

final class Field
{
    public const SUBJECT = "Subject";
    public const FROM = "From";
    public const TO = "To";
    public const CC = "Cc";
    public const LIST_ID = "List-Id";
    public const REPLY_TO = "Reply-To";
    public const BODY = "";

    private const values = array(SUBJECT, FROM, CC, TO, LIST_ID, REPLY_TO, BODY);

    public static function isValid($field) {
        return array_search($field, self::values) !== false;
    }

}
