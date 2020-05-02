<?php

// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


function test_logs_multiple_passed_subtests_as_one_passed_test() {
    $true = function() { easytest\assert_identical(true, true); };
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest(
        'test_passing_subtest',
        function(easytest\TestContext $context) use ($true) {
            $context->subtest($true);
            $context->subtest($true);
        }
    );

    easytest\_run_test($logger, $test, null);

    namespace\assert_log(array(easytest\LOG_EVENT_PASS => 1), $logger);
}


function test_logs_failed_subtests_and_continues_tests() {
    $false = function() { easytest\fail("I failed :-("); };
    $name = 'test_failing_subtest';
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest(
        $name,
        function(easytest\TestContext $context) use ($false) {
            $context->subtest($false);
            $context->subtest($false);
        }
    );

    easytest\_run_test($logger, $test, null);

    namespace\assert_log(
        array(
            easytest\LOG_EVENT_FAIL => 2,
            'events' => array(
                array(easytest\LOG_EVENT_FAIL, $name, "I failed :-("),
                array(easytest\LOG_EVENT_FAIL, $name, "I failed :-("),
            ),
        ),
        $logger);
}


function test_provides_assertions_as_subtests() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest(
        'assertion_subtest',
        function(easytest\TestContext $context) {
            $context->assert_identical(true, true);
            $context->assert_equal(1, '1');
        }
    );

    easytest\_run_test($logger, $test, null);

    namespace\assert_log(array(easytest\LOG_EVENT_PASS => 1), $logger);
}
