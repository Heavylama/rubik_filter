<?php

namespace Rubik\Procmail\Rule;

final class Field
{
    public const SUBJECT = "_subject";
    public const FROM = "_from";
    public const TO = "_to";
    public const CC = "_cc";
    public const LIST_ID = "_list-id";
    public const DATE = "_date";
    public const BODY = "_body_";
    public const FROM_MAILER = "_from_mailer";
    public const FROM_DAEMON = "_from_daemon";
    public const X_LOOP_RUBIK = "_xloop";

    public const values = array(self::SUBJECT, self::FROM, self::CC, self::TO, self::LIST_ID, self::BODY, self::DATE,
        self::FROM_DAEMON, self::FROM_MAILER, self::X_LOOP_RUBIK);
    public const headerFieldMap = array(
        Field::FROM => "(From|Reply-to|Return-Path):",
        Field::SUBJECT => "(Subject):",
        Field::TO => "(To):",
        Field::CC => "(Cc):",
        Field::LIST_ID => "(List-Id):",
        Field::DATE => "(Date):",
        Field::FROM_DAEMON => "FROM_MAILER",
        Field::FROM_MAILER => "FROM_DAEMON",
        Field::X_LOOP_RUBIK => "(X-Loop):"
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
