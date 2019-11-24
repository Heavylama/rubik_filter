#!/bin/bash

TESTS_ROOT="$(dirname $(readlink -f $0))"
PROCMAIL_ENV_FILE="$TESTS_ROOT/procmail.env"
PROCMAIL_TEST_FILE=.procmailrc

TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

MAILDIR_RESULT="$(source $TESTS_ROOT/procmail.env; echo $MAILDIR)"

for test_folder in $TESTS_ROOT/*/; do
    cd $test_folder

    printf "\e[999D\e[K> Running: $(basename $test_folder)"
    TOTAL_TESTS=$(($TOTAL_TESTS + 1))

    if [ -d ./$MAILDIR_RESULT ]; then 
        rm -rf ./$MAILDIR_RESULT
    fi
    mkdir $MAILDIR_RESULT


    # Merge test procmail file and common environment variables
    cat $PROCMAIL_ENV_FILE > $PROCMAIL_TEST_FILE
    cat input.procmail >> $PROCMAIL_TEST_FILE

    # Process with procmail
    procmail -m ./"$PROCMAIL_TEST_FILE" < input.mail

    cd $TESTS_ROOT
done
printf "\e[999D\e[K"

printf "\e[1mTest count: $TOTAL_TESTS\n"
printf "\e[32mPassed tests: $PASSED_TESTS\n"
printf "\e[31mFailed tests: $FAILED_TESTS\e[0m\n"