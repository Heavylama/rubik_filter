<?php

namespace Rubik\Procmail\Constants;

/**
 * Filter email fields.
 *
 * @package Rubik\Procmail\Constants
 * @author Tomas Spanel <tomas.spanel@gmail.com>
 */
final class Field
{
    public const SUBJECT = "_subject";
    public const FROM = "_from";
    public const TO = "_to";
    public const CC = "_cc";
    public const LIST_ID = "_list-id";
    public const DATE = "_date";
    /** @var string email text content */
    public const BODY = "_body_";
    /** @var string special procmail macro - expands to catch emails from most mailer daemons */
    public const FROM_MAILER = "_from_mailer";
    /** @var string special procmail macro - expands to catch email from most daemons */
    public const FROM_DAEMON = "_from_daemon";
    /** @var string special plugin macro - expands to X-Loop header field */
    public const X_LOOP_RUBIK = "_xloop";

    /** @var string[] valid fields */
    public const values = array(self::SUBJECT, self::FROM, self::CC, self::TO, self::LIST_ID, self::BODY, self::DATE,
        self::FROM_DAEMON, self::FROM_MAILER, self::X_LOOP_RUBIK);

    /** @var string[] maps field to condition regex field text */
    public const headerFieldMap = array(
        Field::FROM => "(From|Reply-to|Return-Path):",
        Field::SUBJECT => "(Subject):",
        Field::TO => "(To):",
        Field::CC => "(Cc):",
        Field::LIST_ID => "(List-Id):",
        Field::DATE => "(Date):",
        Field::FROM_DAEMON => "FROM_MAILER",
        Field::FROM_MAILER => "FROM_DAEMON",
        Field::X_LOOP_RUBIK => "(X-Loop):",
    );

    /**
     * Get field constant from condition field text.
     *
     * @param $text string condition field text
     * @return string|null field constant or null if not found
     */
    public static function getFieldFromText($text) {
        $field = array_search($text, self::headerFieldMap);

        return $field === false ? null : $field;
    }

    /**
     * Get condition field text for given field constant.
     *
     * @param $field string field constant
     * @return string|null field condition text or null if field is invalid
     */
    public static function getFieldText($field) {
        return isset(self::headerFieldMap[$field]) ? self::headerFieldMap[$field] : null;
    }

    /**
     * Check if given $field is a valid field constant.
     *
     * @param $field string
     * @return bool
     */
    public static function isValid($field) {
        return array_search($field, self::values) !== false;
    }
}
