<?php

namespace Rubik\Procmail;

final class Field
{
    public const SUBJECT = "Subject";
    public const FROM = "From";
    public const TO = "To";
    public const CC = "Cc";
    public const LIST_ID = "List-Id";
    public const BODY = "_body_";

    private const values = array(self::SUBJECT, self::FROM, self::CC, self::TO, self::LIST_ID, self::BODY);

    public static function isValid($field) {
        return array_search($field, self::values) !== false;
    }

}
