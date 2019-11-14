#!/bin/bash
set -e

TESTS_ROOT=$(dirname $(readlink -f $0))
PROCMAIL_ENV_FILE=$TESTS_ROOT/procmail.env
PROCMAIL_TEST_FILE=./.procmailrc

TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

MAILDIR_RESULT=$(source $TESTS_ROOT/procmail.env; echo $MAILDIR)

for test_folder in $TESTS_ROOT/*/; do
    cd $test_folder

    printf "\e[999D\e[K> Running: $(basename $test_folder)"
    TOTAL_TESTS=$(($TOTAL_TESTS + 1))

    # Clear previous test outputs
    rm ./$MAILDIR_RESULT/*

    # Merge test procmail file and common environment variables
    cat $PROCMAIL_ENV_FILE > ./$PROCMAIL_TEST_FILE
    cat input.procmail >> ./$PROCMAIL_TEST_FILE

    # Process with procmail
    for test_email in ./*.mail; do
        printf "Feeding mail: $test_email\n"
        procmail -m ./$PROCMAIL_TEST_FILE < input.mail
    done

    # Compare results

    cd $TESTS_ROOT
done
printf "\e[999D\e[K"

printf "\e[1mTest count: $TOTAL_TESTS\n"
printf "\e[32mPassed tests: $PASSED_TESTS\n"
printf "\e[31mFailed tests: $FAILED_TESTS\e[0m\n"