<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\discover\file;

use easytest;
use easytest\BasicLogger;
use easytest\BufferingLogger;
use easytest\State;



// helper functions

function filepath($name) {
    $ds = \DIRECTORY_SEPARATOR;
    return  __DIR__ . "{$ds}support{$ds}{$name}";
}



// helper assertions

function assert_file_discovery($filepath, array $events) {
    $state = new State();
    $logger = new BasicLogger(easytest\LOG_ALL);
    easytest\discover_file($state, new BufferingLogger($logger), $filepath);

    \assert_events($events, $logger);
}


function assert_fixture_error($file, $message) {
    $filepath = namespace\filepath($file);
    $events = array(
        array(easytest\EVENT_ERROR, $filepath, $message),
    );
    namespace\assert_file_discovery($filepath, $events);
}



// tests

function test_logs_error_on_multiple_file_setup_fixtures() {
    $file = 'test_multiple_setup_file.php';
    $error = <<<'EXPECTED'
Multiple conflicting fixture functions found:
    1) multiple_setup_file\setup_file_one
    2) multiple_setup_file\setup_file_two
EXPECTED;

    namespace\assert_fixture_error($file, $error);
}


function test_logs_error_on_multiple_function_setup_fixtures() {
    $file = 'test_multiple_setup_function.php';
    $error = <<<'EXPECTED'
Multiple conflicting fixture functions found:
    1) multiple_setup_function\setup_one
    2) multiple_setup_function\setup_two
EXPECTED;

    namespace\assert_fixture_error($file, $error);
}


function test_logs_error_on_multiple_file_teardown_fixtures() {
    $file = 'test_multiple_teardown_file.php';
    $error = <<<'EXPECTED'
Multiple conflicting fixture functions found:
    1) multiple_teardown_file\teardown_file_one
    2) multiple_teardown_file\teardown_file_two
EXPECTED;

    namespace\assert_fixture_error($file, $error);
}


function test_logs_error_on_multiple_run_teardown_fixtures() {
    $file = 'test_multiple_teardown_run.php';
    $error = <<<'EXPECTED'
Multiple conflicting fixture functions found:
    1) multiple_teardown_run\teardown_run_one
    2) multiple_teardown_run\teardown_run_two
EXPECTED;

    namespace\assert_fixture_error($file, $error);
}


function test_logs_error_on_multiple_function_teardown_fixtures() {
    $file = 'test_multiple_teardown_function.php';
    $error = <<<'EXPECTED'
Multiple conflicting fixture functions found:
    1) multiple_teardown_function\teardown_one
    2) multiple_teardown_function\teardown_two
EXPECTED;

    namespace\assert_fixture_error($file, $error);
}


function test_handles_non_test_definition() {
    // @bc 5.3 Don't test trait definitions
    if (\version_compare(\PHP_VERSION, '5.4.0', '<')) {
        $file = 'test_definitions5.3.php';
    }
    // @bc 5.5 Don't test 'use function ...' statements
    elseif (\version_compare(\PHP_VERSION, '5.6.0', '<')) {
        $file = 'test_definitions5.4.php';
    }
    else {
        $file = 'test_definitions5.6.php';
    }

    $filepath = namespace\filepath($file);
    $state = new State();
    $logger = new BasicLogger(easytest\LOG_ALL);
    $result = easytest\discover_file($state, new BufferingLogger($logger), $filepath);

    easytest\assert_identical(array(), $logger->get_log()->get_events());
    easytest\assert_true(
        $result instanceof easytest\FileTest,
        'result is ' . (\is_object($result) ? get_class($result) : gettype($result))
    );
    easytest\assert_identical(
        array('class definitions\\Test'),
        \array_keys($result->tests)
    );
}
