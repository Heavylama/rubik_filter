<?php

namespace Rubik\Procmail\Rule;

final class Field
{
    public const SUBJECT = "_subject";
    public const FROM = "_from";
    public const TO = "_to";
    public const CC = "_cc";
    public const LIST_ID = "_list-id";
    public const BODY = "_body_";

    public const values = array(self::SUBJECT, self::FROM, self::CC, self::TO, self::LIST_ID, self::BODY);
    public const headerFieldMap = array(
        Field::FROM => "(From|Reply-to|Return-Path)",
        Field::SUBJECT => "(Subject)",
        Field::TO => "(To)",
        Field::CC => "(Cc)",
        Field::LIST_ID => "(List-Id)"
    );

    public static function getFieldFromText($text) {
        return array_search($text, self::headerFieldMap);
    }

    public static function getFieldText($field) {
        return self::headerFieldMap[$field];
    }

    public static function isValid($field) {
        return array_search($field, self::values) !== false;
    }
}
