<?php

// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


function test_teardown_is_run_after_test() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) {
        $context->teardown(function() { echo "teardown one\n"; });
        $context->teardown(function() { echo "teardown two\n"; });
        echo "test\n";
    };
    $test->teardown = function() { echo 'common teardown'; };
    $test->teardown_name = 'teardown';

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(
        array(
            easytest\EVENT_PASS => 1,
            easytest\EVENT_OUTPUT => 2,
        ),
        $logger
    );
}


function test_teardown_is_run_after_failing_test() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) {
        $context->teardown(function() { echo "teardown one\n"; });
        $context->teardown(function() { echo "teardown two\n"; });
        echo "test\n";
        easytest\fail('f');
    };
    $test->teardown = function() { echo 'common teardown'; };
    $test->teardown_name = 'teardown';

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(
        array(
            easytest\EVENT_FAIL => 1,
            easytest\EVENT_OUTPUT => 2,
            'events' => array(
                array(easytest\EVENT_FAIL, 'test', 'f'),
                array(easytest\EVENT_OUTPUT, 'test', "test\nteardown one\nteardown two\n"),
                array(easytest\EVENT_OUTPUT, 'teardown for test', 'common teardown'),
            ),
        ),
        $logger
    );
}


function test_error_in_function_teardown_causes_test_to_fail() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) {
        $context->teardown(function() {
            echo "teardown one\n";
            trigger_error('I erred');
        });
        $context->teardown(function() { echo "teardown two\n"; });
        echo "test\n";
    };
    $test->teardown = function() { echo 'common teardown'; };
    $test->teardown_name = 'teardown';

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(
        array(
            easytest\EVENT_ERROR => 1,
            easytest\EVENT_OUTPUT => 2,
            'events' => array(
                array(easytest\EVENT_ERROR, 'test', 'I erred'),
                array(easytest\EVENT_OUTPUT, 'test', "test\nteardown one\nteardown two\n"),
                array(easytest\EVENT_OUTPUT, 'teardown for test', 'common teardown'),
            ),
        ),
        $logger
    );
}


function test_skip_is_reported_is_teardown_has_an_error() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) {
        easytest\skip('skip me');
    };
    $test->teardown = function() { \trigger_error('I erred'); };
    $test->teardown_name = 'teardown';

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(
        array(
            easytest\EVENT_ERROR => 1,
            easytest\EVENT_SKIP => 1,
            'events' => array(
                array(easytest\EVENT_SKIP, 'test', 'Although this test was skipped, there was also an error'),
                array(easytest\EVENT_ERROR, 'teardown for test', 'I erred'),
            ),
        ),
        $logger
    );
}


function test_passing_subtests_dont_increase_the_test_count() {
    $true = function() { easytest\assert_identical(true, true); };
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) use ($true) {
        $context->subtest($true);
        $context->subtest($true);
    };

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(array(easytest\EVENT_PASS => 1), $logger);
}


function test_failed_subtests_dont_end_a_test() {
    $logger = new easytest\BasicLogger(false);
    $test = new easytest\FunctionTest();
    $test->name = 'test';
    $test->test = function(easytest\Context $context) {
        $context->subtest(function() { easytest\fail('Fail me once'); });
        $context->subtest(function() { easytest\fail('Fail me twice'); });
    };

    easytest\_run_function_test($logger, $test, array(), null);

    namespace\assert_log(
        array(
            easytest\EVENT_FAIL => 2,
            'events' => array(
                array(easytest\EVENT_FAIL, 'test', 'Fail me once'),
                array(easytest\EVENT_FAIL, 'test', 'Fail me twice'),
            ),
        ),
        $logger
    );
}
