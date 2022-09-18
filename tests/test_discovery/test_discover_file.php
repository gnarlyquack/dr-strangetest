<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\discover\file;

use strangetest;
use strangetest\LogBufferer;
use strangetest\Logger;
use strangetest\State;
use strangetest\_DiscoveryState;

use NoOutputter;


// helper functions

function filepath($name) {
    $ds = \DIRECTORY_SEPARATOR;
    return  __DIR__ . "{$ds}support{$ds}{$name}";
}



// helper assertions

function assert_file_discovery($filepath, array $events) {
    $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    $state = new _DiscoveryState(new State);
    $state->global->logger = $logger;
    $state->global->bufferer = new LogBufferer(\TEST_ROOT);
    strangetest\_discover_file($state, $filepath, 0);

    \assert_events($events, $logger);
}


function assert_fixture_error($file, $source, $message) {
    $filepath = namespace\filepath($file);
    $events = array(
        array(\EVENT_ERROR, $source, $message),
    );
    namespace\assert_file_discovery($filepath, $events);
}



// tests

function test_logs_error_on_multiple_file_setup_fixtures() {
    $file = 'test_multiple_setup_file.php';
    $source = 'multiple_setup_file\\setup_file_two';
    $error = 'This fixture conflicts with \'multiple_setup_file\\setup_file_one\' defined on line 7';
    namespace\assert_fixture_error($file, $source, $error);
}


function test_logs_error_on_multiple_function_setup_fixtures() {
    $file = 'test_multiple_setup_function.php';
    $source = 'multiple_setup_function\\setup_two';
    $error = 'This fixture conflicts with \'multiple_setup_function\\setup_one\' defined on line 9';
    namespace\assert_fixture_error($file, $source, $error);
}


function test_logs_error_on_multiple_file_teardown_fixtures() {
    $file = 'test_multiple_teardown_file.php';
    $source = 'multiple_teardown_file\\teardown_file_two';
    $error = 'This fixture conflicts with \'multiple_teardown_file\\teardown_file_one\' defined on line 6';
    namespace\assert_fixture_error($file, $source, $error);
}


function test_logs_error_on_multiple_run_teardown_fixtures() {
    $file = 'test_multiple_teardown_run.php';
    $source = 'multiple_teardown_run\\teardownRunOne';
    $error = 'This fixture conflicts with \'multiple_teardown_run\\teardown_run_one\' defined on line 7';
    namespace\assert_fixture_error($file, $source, $error);
}


function test_logs_error_on_multiple_function_teardown_fixtures() {
    $file = 'test_multiple_teardown_function.php';
    $source = 'multiple_teardown_function\\teardown_two';
    $error = 'This fixture conflicts with \'multiple_teardown_function\\teardown_one\' defined on line 8';
    namespace\assert_fixture_error($file, $source, $error);
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
    $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    $state = new _DiscoveryState(new State);
    $state->global->logger = $logger;
    $state->global->bufferer = new LogBufferer(\TEST_ROOT);
    $result = strangetest\_discover_file($state, $filepath, 0);

    strangetest\assert_identical(array(), $logger->get_log()->events);
    strangetest\assert_true(
        $result instanceof strangetest\FileTest,
        'result is ' . (\is_object($result) ? get_class($result) : gettype($result))
    );
    strangetest\assert_identical(
        array('class definitions\\Test'),
        \array_keys($result->tests)
    );
}


function test_does_not_discover_enumerations()
{
    // @bc 8.0 Check if enumerations are supported
    if (\version_compare(\PHP_VERSION, '8.1', '<'))
    {
        strangetest\skip('Enumerations were added in PHP 8.1');
    }

    $file = 'test_enumeration.php';
    $filepath = namespace\filepath($file);
    $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    $state = new _DiscoveryState(new State);
    $state->global->logger = $logger;
    $state->global->bufferer = new LogBufferer(\TEST_ROOT);
    $result = strangetest\_discover_file($state, $filepath, 0);

    strangetest\assert_identical(array(), $logger->get_log()->events);
    strangetest\assert_true(
        $result instanceof strangetest\FileTest,
        'result is ' . (\is_object($result) ? get_class($result) : gettype($result))
    );
    strangetest\assert_identical(
        array('class TestClass'),
        \array_keys($result->tests)
    );
}


function test_discovers_tests_marked_with_attributes()
{
    // @bc 7.4 Check if attributes are supported
    if (\version_compare(\PHP_VERSION, '8.0.0', '<'))
    {
        strangetest\skip('Attributes were added in PHP 8.0');
    }

    $file = 'test_attributes.php';
    $filepath = namespace\filepath($file);
    $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    $state = new _DiscoveryState(new State);
    $state->global->logger = $logger;
    $state->global->bufferer = new LogBufferer(\TEST_ROOT);
    $result = strangetest\_discover_file($state, $filepath, 0);

    strangetest\assert_identical(array(), $logger->get_log()->events);
    strangetest\assert_true(
        $result instanceof strangetest\FileTest,
        'result is ' . (\is_object($result) ? get_class($result) : gettype($result))
    );
    strangetest\assert_identical(
        array(
            'function test_attribute\\function_is_found',
            'class test_attribute\\ClassIsFound',
        ),
        \array_keys($result->tests)
    );
}
