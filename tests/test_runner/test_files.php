<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\run\file;

use strangetest;
use strangetest\LogBufferer;
use strangetest\Logger;
use strangetest\PathTest;
use strangetest\State;
use strangetest\RunInstance;
use strangetest\_DiscoveryState;

use NoOutputter;


class TestFiles
{
    private $logger;
    private $root;
    private $path;


    public function setup() {
        $this->root =  __DIR__ . '/resources/files/';
        $this->path = '';
        $this->logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    }


    // helper assertions

    private function assert_events($expected)
    {
        $state = new State;
        $state->logger = $this->logger;
        $state->bufferer = new LogBufferer(\TEST_ROOT);
        $tests = strangetest\discover_tests($state, $this->root);

        if ($tests)
        {
            $targets = strangetest\process_specifiers($this->logger, $tests, (array)$this->path);
            strangetest\run_tests($state, $tests, $targets);
        }

        assert_events($expected, $this->logger);
    }


    // tests

    public function test_parses_and_runs_only_tests() {
        $this->root .= 'run_tests/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'test_one', null),
            array(\EVENT_PASS, 'Test1::TestMe', null),
            array(\EVENT_PASS, 'TestTwo', null),
            array(\EVENT_PASS, 'TestTwo::test1', null),
            array(\EVENT_PASS, 'TestTwo::test2', null),
            array(\EVENT_PASS, 'TestTwo::test3', null),
            array(\EVENT_PASS, 'test::test_two', null),
            array(\EVENT_PASS, 'test', null),
        ));
    }


    public function test_parses_and_runs_tests_and_fixtures() {
        $this->root .= 'fixtures/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'setup for file_fixtures\\test_one', '2 4'),
            array(\EVENT_OUTPUT, 'file_fixtures\\test_one', "teardown 2teardown 1"),
            array(\EVENT_OUTPUT, 'teardown for file_fixtures\\test_one', '2 4'),
            array(\EVENT_PASS, 'file_fixtures\\test_one', null),

            array(\EVENT_OUTPUT, 'setup for file_fixtures\\test_two', '2 4'),
            array(\EVENT_OUTPUT, 'teardown for file_fixtures\\test_two', '2 4'),
            array(\EVENT_PASS, 'file_fixtures\\test_two', null),

            array(\EVENT_OUTPUT, 'file_fixtures\\test::setup_object', '2 4'),

            array(\EVENT_OUTPUT, 'setup for file_fixtures\\test::test_one', '2 4'),
            array(\EVENT_OUTPUT, 'file_fixtures\\test::test_one', "teardown 2teardown 1"),
            array(\EVENT_OUTPUT, 'teardown for file_fixtures\\test::test_one', '2 4'),
            array(\EVENT_PASS, 'file_fixtures\\test::test_one', null),

            array(\EVENT_OUTPUT, 'setup for file_fixtures\\test::test_two', '2 4'),
            array(\EVENT_OUTPUT, 'teardown for file_fixtures\\test::test_two', '2 4'),
            array(\EVENT_PASS, 'file_fixtures\\test::test_two', null),

            array(\EVENT_OUTPUT, 'file_fixtures\\test::teardown_object', '2 4'),

            array(\EVENT_OUTPUT, 'file_fixtures\\teardown_file', '2 4'),
        ));
    }


    public function test_names_are_case_insensitive() {
        $this->root .= 'case/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'SetUpFunction for FileCase\\TestOne', '2 4'),
            array(\EVENT_OUTPUT, 'TearDownFunction for FileCase\\TestOne', '2 4'),
            array(\EVENT_PASS, 'FileCase\\TestOne', null),

            array(\EVENT_OUTPUT, 'SetUpFunction for FileCase\\TestTwo', '2 4'),
            array(\EVENT_OUTPUT, 'TearDownFunction for FileCase\\TestTwo', '2 4'),
            array(\EVENT_PASS, 'FileCase\\TestTwo', null),

            array(\EVENT_OUTPUT, 'FileCase\\Test::SetUpObject', '2 4'),

            array(\EVENT_OUTPUT, 'SetUp for FileCase\\Test::TestOne', '2 4'),
            array(\EVENT_OUTPUT, 'TearDown for FileCase\\Test::TestOne', '2 4'),
            array(\EVENT_PASS, 'FileCase\\Test::TestOne', null),

            array(\EVENT_OUTPUT, 'SetUp for FileCase\\Test::TestTwo', '2 4'),
            array(\EVENT_OUTPUT, 'TearDown for FileCase\\Test::TestTwo', '2 4'),
            array(\EVENT_PASS, 'FileCase\\Test::TestTwo', null),

            array(\EVENT_OUTPUT, 'FileCase\\Test::TearDownObject', '2 4'),

            array(\EVENT_OUTPUT, 'FileCase\\TearDownFile', '2 4'),
        ));
    }


    public function test_parses_multiple_simple_namespaces() {
        $this->root .= 'simple_namespaces/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'ns02\\TestNamespaces::test', null),
            array(\EVENT_PASS, 'ns03\\TestNamespaces::test', null),
        ));
    }


    public function test_parses_multiple_bracketed_namespaces() {
        $this->root .= 'bracketed_namespaces/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'ns01\\ns1\\TestNamespaces::test', null),
            array(\EVENT_PASS, 'ns01\\ns2\\TestNamespaces::test', null),
            array(\EVENT_PASS, 'TestNamespaces::test', null),
        ));
    }


    public function test_does_not_discover_anonymous_classes() {
        // @bc 5.6 Check if anonymous classes are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            strangetest\skip('PHP 7 introduced anonymous classes');
        }

        $this->root .= 'anonymous/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'anonymous\\test_anonymous_class', null),
            array(\EVENT_PASS, 'anonymous\\test_i_am_a_function_name', null),
            array(\EVENT_PASS, 'anonymous\\test::test_anonymous_class', null),
        ));
    }


    public function test_handles_failed_tests() {
        $this->root .= 'failures/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'file_failures\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for file_failures\\test_one', '.'),
            array(\EVENT_FAIL, 'file_failures\\test_one', 'I failed'),
            array(\EVENT_OUTPUT, 'file_failures\\test_one', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown_function for file_failures\\test_one', '.'),

            array(\EVENT_OUTPUT, 'setup_function for file_failures\\test_two', '.'),
            array(\EVENT_ERROR, 'file_failures\\test_two', 'An error happened'),
            array(\EVENT_OUTPUT, 'file_failures\\test_two', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown_function for file_failures\\test_two', '.'),

            array(\EVENT_OUTPUT, 'setup_function for file_failures\\test_three', '.'),
            array(\EVENT_OUTPUT, 'file_failures\\test_three', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown_function for file_failures\\test_three', '.'),
            array(\EVENT_PASS, 'file_failures\\test_three', null),

            array(\EVENT_OUTPUT, 'setup_function for file_failures\\test_four', '.'),
            array(\EVENT_ERROR, 'file_failures\\test_four', "I'm exceptional!"),
            array(\EVENT_OUTPUT, 'file_failures\\test_four', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown_function for file_failures\\test_four', '.'),

            array(\EVENT_OUTPUT, 'file_failures\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for file_failures\\test::test_one', '.'),
            array(\EVENT_FAIL, 'file_failures\\test::test_one', 'I failed'),
            array(\EVENT_OUTPUT, 'file_failures\\test::test_one', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown for file_failures\\test::test_one', '.'),

            array(\EVENT_OUTPUT, 'setup for file_failures\\test::test_two', '.'),
            array(\EVENT_ERROR, 'file_failures\\test::test_two', 'An error happened'),
            array(\EVENT_OUTPUT, 'file_failures\\test::test_two', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown for file_failures\\test::test_two', '.'),

            array(\EVENT_OUTPUT, 'setup for file_failures\\test::test_three', '.'),
            array(\EVENT_OUTPUT, 'file_failures\\test::test_three', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown for file_failures\\test::test_three', '.'),
            array(\EVENT_PASS, 'file_failures\\test::test_three', null),

            array(\EVENT_OUTPUT, 'setup for file_failures\\test::test_four', '.'),
            array(\EVENT_ERROR, 'file_failures\\test::test_four', "I'm exceptional!"),
            array(\EVENT_OUTPUT, 'file_failures\\test::test_four', 'teardown'),
            array(\EVENT_OUTPUT, 'teardown for file_failures\\test::test_four', '.'),

            array(\EVENT_OUTPUT, 'file_failures\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'file_failures\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_when_loading_file() {
        $this->root .= 'file_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_ERROR, $this->root . $this->path, 'Skip me'),
        ));
    }


    public function test_logs_error_in_setup_file() {
        $this->root .= 'setup_file_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_ERROR, 'setup_file_error\\setup_file', 'An error happened'),
        ));
    }


    public function test_logs_error_in_constructor() {
        $this->root .= 'constructor_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'constructor_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for constructor_error\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for constructor_error\\test_one', '.'),
            array(\EVENT_PASS, 'constructor_error\\test_one', null),

            array(\EVENT_ERROR, 'constructor_error\\test', 'Skip me'),

            array(\EVENT_OUTPUT, 'setup_function for constructor_error\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for constructor_error\\test_two', '.'),
            array(\EVENT_PASS, 'constructor_error\\test_two', null),

            array(\EVENT_OUTPUT, 'constructor_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_setup_object() {
        $this->root .= 'setup_object_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'setup_object_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for setup_object_error\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for setup_object_error\\test_one', '.'),
            array(\EVENT_PASS, 'setup_object_error\\test_one', null),

            array(\EVENT_ERROR, 'setup_object_error\\test::setup_object', 'An error happened'),

            array(\EVENT_OUTPUT, 'setup_function for setup_object_error\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for setup_object_error\\test_two', '.'),
            array(\EVENT_PASS, 'setup_object_error\\test_two', null),

            array(\EVENT_OUTPUT, 'setup_object_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_setup() {
        $this->root .= 'setup_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'setup_error\\setup_file', '.'),

            array(\EVENT_ERROR, 'setup_function for setup_error\\test_one', 'An error happened'),
            array(\EVENT_ERROR, 'setup_function for setup_error\\test_two', 'An error happened'),

            array(\EVENT_OUTPUT, 'setup_error\\test::setup_object', '.'),

            array(\EVENT_ERROR, 'setup for setup_error\\test::test_one', 'An error happened'),
            array(\EVENT_ERROR, 'setup for setup_error\\test::test_two', 'An error happened'),

            array(\EVENT_OUTPUT, 'setup_error\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'setup_error\\teardown_file', '.'),
        ));
    }


    function test_logs_error_in_function_teardown() {
        $this->root .= 'teardown_test_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'teardown_test_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_test_error\\test_one', '.'),
            array(\EVENT_ERROR, 'teardown_test_error\\test_one', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown_test_error\\test_one', 'teardown 2'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_test_error\\test_one', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_test_error\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_test_error\\test_two', '.'),
            array(\EVENT_PASS, 'teardown_test_error\\test_two', null),

            array(\EVENT_OUTPUT, 'teardown_test_error\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for teardown_test_error\\test::test_one', '.'),
            array(\EVENT_ERROR, 'teardown_test_error\\test::test_one', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown_test_error\\test::test_one', 'teardown 2'),
            array(\EVENT_OUTPUT, 'teardown for teardown_test_error\\test::test_one', '.'),

            array(\EVENT_OUTPUT, 'setup for teardown_test_error\\test::test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown for teardown_test_error\\test::test_two', '.'),
            array(\EVENT_PASS, 'teardown_test_error\\test::test_two', null),

            array(\EVENT_OUTPUT, 'teardown_test_error\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'teardown_test_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown() {
        $this->root .= 'teardown_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'teardown_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_error\\test_one', '.'),
            array(\EVENT_ERROR, 'teardown_function for teardown_error\\test_one', 'Skip me'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_error\\test_two', '.'),
            array(\EVENT_ERROR, 'teardown_function for teardown_error\\test_two', 'Skip me'),

            array(\EVENT_OUTPUT, 'teardown_error\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for teardown_error\\test::test_one', '.'),
            array(\EVENT_ERROR, 'teardown for teardown_error\\test::test_one', 'Skip me'),

            array(\EVENT_OUTPUT, 'setup for teardown_error\\test::test_two', '.'),
            array(\EVENT_ERROR, 'teardown for teardown_error\\test::test_two', 'Skip me'),

            array(\EVENT_OUTPUT, 'teardown_error\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'teardown_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown_object() {
        $this->root .= 'teardown_object_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'teardown_object_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_object_error\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_object_error\\test_one', '.'),
            array(\EVENT_PASS, 'teardown_object_error\\test_one', null),


            array(\EVENT_OUTPUT, 'teardown_object_error\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for teardown_object_error\\test::test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown for teardown_object_error\\test::test_one', '.'),
            array(\EVENT_PASS, 'teardown_object_error\\test::test_one', null),

            array(\EVENT_OUTPUT, 'setup for teardown_object_error\\test::test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown for teardown_object_error\\test::test_two', '.'),
            array(\EVENT_PASS, 'teardown_object_error\\test::test_two', null),

            array(\EVENT_ERROR, 'teardown_object_error\\test::teardown_object', 'Skip me'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_object_error\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_object_error\\test_two', '.'),
            array(\EVENT_PASS, 'teardown_object_error\\test_two', null),

            array(\EVENT_OUTPUT, 'teardown_object_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown_file() {
        $this->root .= 'teardown_file_error/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'teardown_file_error\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_file_error\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_file_error\\test_one', '.'),
            array(\EVENT_PASS, 'teardown_file_error\\test_one', null),


            array(\EVENT_OUTPUT, 'teardown_file_error\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for teardown_file_error\\test::test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown for teardown_file_error\\test::test_one', '.'),
            array(\EVENT_PASS, 'teardown_file_error\\test::test_one', null),

            array(\EVENT_OUTPUT, 'setup for teardown_file_error\\test::test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown for teardown_file_error\\test::test_two', '.'),
            array(\EVENT_PASS, 'teardown_file_error\\test::test_two', null),

            array(\EVENT_OUTPUT, 'teardown_file_error\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'setup_function for teardown_file_error\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for teardown_file_error\\test_two', '.'),
            array(\EVENT_PASS, 'teardown_file_error\\test_two', null),

            array(\EVENT_ERROR, 'teardown_file_error\\teardown_file', 'Skip me'),
        ));
    }


    public function test_logs_error_for_multiple_object_fixtures() {
        $this->root .= 'multiple_object_fixtures/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_ERROR, 'SetUpObject', 'This fixture conflicts with \'setup_object\' defined on line 29'),
            array(\EVENT_ERROR, 'TearDownObject', 'This fixture conflicts with \'teardown_object\' defined on line 37'),

            array(\EVENT_OUTPUT, 'multiple_object_fixtures\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for multiple_object_fixtures\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for multiple_object_fixtures\\test_one', '.'),
            array(\EVENT_PASS, 'multiple_object_fixtures\\test_one', null),

            array(\EVENT_OUTPUT, 'setup_function for multiple_object_fixtures\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for multiple_object_fixtures\\test_two', '.'),
            array(\EVENT_PASS, 'multiple_object_fixtures\\test_two', null),

            array(\EVENT_OUTPUT, 'multiple_object_fixtures\\teardown_file', '.'),
        ));
    }


    public function test_skips_file() {
        $this->root .= 'skip_file/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_SKIP, 'skip_file\\setup_file', 'Skip me'),
        ));
    }


    public function test_skips_object() {
        $this->root .= 'skip_object/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'skip_object\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for skip_object\\test_one', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for skip_object\\test_one', '.'),
            array(\EVENT_PASS, 'skip_object\\test_one', null),

            array(\EVENT_SKIP, 'skip_object\\test::setup_object', 'Skip me'),

            array(\EVENT_OUTPUT, 'setup_function for skip_object\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for skip_object\\test_two', '.'),
            array(\EVENT_PASS, 'skip_object\\test_two', null),

            array(\EVENT_OUTPUT, 'skip_object\\teardown_file', '.'),
        ));
    }


    public function test_skips_in_setup() {
        $this->root .= 'skip_setup/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'skip_setup\\setup_file', '.'),

            array(\EVENT_SKIP, 'setup_function for skip_setup\\test_one', 'Skip me'),
            array(\EVENT_SKIP, 'setup_function for skip_setup\\test_two', 'Skip me'),

            array(\EVENT_OUTPUT, 'skip_setup\\test::setup_object', '.'),

            array(\EVENT_SKIP, 'setup for skip_setup\\test::test_one', 'Skip me'),
            array(\EVENT_SKIP, 'setup for skip_setup\\test::test_two', 'Skip me'),

            array(\EVENT_OUTPUT, 'skip_setup\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'skip_setup\\teardown_file', '.'),
        ));
    }


    public function test_skips_test() {
        $this->root .= 'skip_test/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'skip_test\\setup_file', '.'),

            array(\EVENT_OUTPUT, 'setup_function for skip_test\\test_one', '.'),
            array(\EVENT_SKIP, 'skip_test\\test_one', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown_function for skip_test\\test_one', '.'),

            array(\EVENT_OUTPUT, 'skip_test\\test::setup_object', '.'),

            array(\EVENT_OUTPUT, 'setup for skip_test\\test::test_one', '.'),
            array(\EVENT_SKIP, 'skip_test\\test::test_one', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown for skip_test\\test::test_one', '.'),

            array(\EVENT_OUTPUT, 'setup for skip_test\\test::test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown for skip_test\\test::test_two', '.'),
            array(\EVENT_PASS, 'skip_test\\test::test_two', null),

            array(\EVENT_OUTPUT, 'skip_test\\test::teardown_object', '.'),

            array(\EVENT_OUTPUT, 'setup_function for skip_test\\test_two', '.'),
            array(\EVENT_OUTPUT, 'teardown_function for skip_test\\test_two', '.'),
            array(\EVENT_PASS, 'skip_test\\test_two', null),

            array(\EVENT_OUTPUT, 'skip_test\\teardown_file', '.'),
        ));
    }


    public function test_logs_file_output() {
        $this->root .= 'file_output/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, $this->root . $this->path, '.'),
            array(\EVENT_ERROR, $this->root . $this->path, 'No tests were found in this file'),
            array(\EVENT_ERROR, $this->root, 'No tests were found in this directory'),
        ));
    }


    public function test_logs_constructor_output() {
        $this->root .= 'constructor_output/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'constructor_output\\test_one', null),

            array(\EVENT_OUTPUT, 'constructor_output\\test', '.'),
            array(\EVENT_PASS, 'constructor_output\\test::test_method', null),

            array(\EVENT_PASS, 'constructor_output\\test_two', null),
        ));
    }


    public function test_logs_test_output() {
        $this->root .= 'output/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'test_output\\test', '.'),
            array(\EVENT_PASS, 'test_output\\test', null),

            array(\EVENT_OUTPUT, 'test_output\\test::test_method', '.'),
            array(\EVENT_PASS, 'test_output\\test::test_method', null),
        ));
    }


    public function test_supports_output_buffering() {
        $this->root .= 'buffering/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_OUTPUT, 'setup_function for buffering\\test_skip', 'setup output that should be seen'),
            array(\EVENT_SKIP, 'buffering\\test_skip', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown_function for buffering\\test_skip', 'teardown output that should be seen'),

            array(\EVENT_OUTPUT, 'setup_function for buffering\\test_error', 'setup output that should be seen'),
            array(\EVENT_ERROR, 'buffering\\test_error', 'Did I err?'),
            array(\EVENT_OUTPUT, 'teardown_function for buffering\\test_error', 'teardown output that should be seen'),

            array(\EVENT_OUTPUT, 'setup_function for buffering\\test_fail', 'setup output that should be seen'),
            array(\EVENT_FAIL, 'buffering\\test_fail', 'I failed'),
            array(\EVENT_OUTPUT, 'teardown_function for buffering\\test_fail', 'teardown output that should be seen'),


            array(\EVENT_OUTPUT, 'setup for buffering\\test::test_skip', 'setup output that should be seen'),
            array(\EVENT_SKIP, 'buffering\\test::test_skip', 'Skip me'),
            array(\EVENT_OUTPUT, 'teardown for buffering\\test::test_skip', 'teardown output that should be seen'),

            array(\EVENT_OUTPUT, 'setup for buffering\\test::test_error', 'setup output that should be seen'),
            array(\EVENT_ERROR, 'buffering\\test::test_error', 'Did I err?'),
            array(\EVENT_OUTPUT, 'teardown for buffering\\test::test_error', 'teardown output that should be seen'),

            array(\EVENT_OUTPUT, 'setup for buffering\\test::test_fail', 'setup output that should be seen'),
            array(\EVENT_FAIL, 'buffering\\test::test_fail', 'I failed'),
            array(\EVENT_OUTPUT, 'teardown for buffering\\test::test_fail', 'teardown output that should be seen'),

            array(\EVENT_OUTPUT, 'setup for buffering\\test::test_pass', 'setup output that should be seen'),
            array(\EVENT_OUTPUT, 'teardown for buffering\\test::test_pass', 'teardown output that should be seen'),
            array(\EVENT_PASS, 'buffering\\test::test_pass', null),


            array(\EVENT_OUTPUT, 'setup_function for buffering\\test_pass', 'setup output that should be seen'),
            array(\EVENT_OUTPUT, 'teardown_function for buffering\\test_pass', 'teardown output that should be seen'),
            array(\EVENT_PASS, 'buffering\\test_pass', null),
        ));
    }


    public function test_logs_errors_for_undeleted_output_buffers() {
        $this->root .= 'undeleted_buffers/';
        $this->path .= 'test.php';

        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to dangling output buffers. This seems
        // desirable: errors associated with buffering will get logged and
        // cause the test suite in general to fail (so hopefully people will
        // clean up their tests), but do not otherwise impede testing.
        $this->assert_events(array(
            array(
                \EVENT_ERROR,
                'undeleted_buffers\\setup_file',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\setup_file",
            ),


            array(
                \EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\teardown_function",
            ),
            array(
                \EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: [the output buffer was empty]",
            ),
            array(
                \EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\setup_function",
            ),
            array(\EVENT_PASS, 'undeleted_buffers\\test', null),


            array(
                \EVENT_ERROR,
                'undeleted_buffers\\test::setup_object',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::setup_object",
            ),

            array(
                \EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::teardown",
            ),
            array(
                \EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: [the output buffer was empty]",
            ),
            array(
                \EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::setup",
            ),
            array(\EVENT_PASS, 'undeleted_buffers\\test::test', null),

            array(
                \EVENT_ERROR,
                'undeleted_buffers\\test::teardown_object',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::teardown_object",
            ),


            array(
                \EVENT_ERROR,
                'undeleted_buffers\\teardown_file',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\teardown_file",
            ),
        ));
    }


    public function test_logs_errors_when_deleting_strangetest_output_buffers() {
        $this->root .= 'deleted_buffers/';
        $this->path .= 'test.php';

        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to deleting Dr. Strangetest's output buffers. This
        // seems desirable: errors associated with buffering will get logged
        // and cause the test suite in general to fail (so hopefully people
        // will clean up their tests), but do not otherwise impede testing.
        $message = "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.";
        $this->assert_events(array(
            array(\EVENT_ERROR, 'deleted_buffers\\setup_file', $message),

            array(\EVENT_ERROR, 'setup_function for deleted_buffers\\test', $message),
            array(\EVENT_ERROR, 'deleted_buffers\\test', $message),
            array(\EVENT_ERROR, 'teardown_function for deleted_buffers\\test', $message),
            array(\EVENT_PASS, 'deleted_buffers\\test', null),


            array(\EVENT_ERROR, 'deleted_buffers\\test::setup_object', $message),

            array(\EVENT_ERROR, 'setup for deleted_buffers\\test::test', $message),
            array(\EVENT_ERROR, 'deleted_buffers\\test::test', $message),
            array(\EVENT_ERROR, 'teardown for deleted_buffers\\test::test', $message),
            array(\EVENT_PASS, 'deleted_buffers\\test::test', null),

            array(\EVENT_ERROR, 'deleted_buffers\\test::teardown_object', $message),


            array(\EVENT_ERROR, 'deleted_buffers\\teardown_file', $message),
        ));

        strangetest\assert_identical('', \ob_get_contents());
    }


    public function test_runs_tests_once_per_arg_list() {
        $this->root .= 'multiple_runs/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'multiple_runs\\test_one (0)', null),
            array(\EVENT_PASS, 'multiple_runs\\test_two (0)', null),
            array(\EVENT_PASS, 'multiple_runs\\test::test_one (0)', null),
            array(\EVENT_OUTPUT, 'multiple_runs\\teardown_run0', '2 4'),

            array(\EVENT_PASS, 'multiple_runs\\test_one (1)', null),
            array(\EVENT_PASS, 'multiple_runs\\test_two (1)', null),
            array(\EVENT_PASS, 'multiple_runs\\test::test_one (1)', null),
            array(\EVENT_OUTPUT, 'multiple_runs\\teardown_run1', '8 16'),
        ));
    }


    function test_supports_subtests() {
        $this->root .= 'subtests/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_FAIL, 'subtests\\test_one', 'I fail'),
            array(\EVENT_FAIL, 'subtests\\test_one', 'I fail again'),

            array(\EVENT_PASS, 'subtests\\test_two', null),

            array(\EVENT_FAIL, 'subtests\\test::test_one', 'I fail'),
            array(\EVENT_FAIL, 'subtests\\test::test_one', 'I fail again'),

            array(\EVENT_PASS, 'subtests\\test::test_two', null),
        ));
    }


    function test_runs_only_targeted_tests() {
        $this->root .= 'targets/';
        $this->path = array(
            'test.php',
            '--function=targets\\test_two',
            '--class=targets\\test::test_two',
            '--function=targets\\test_one',
        );

        $this->assert_events(array(
            array(\EVENT_PASS, 'targets\\test_two', null),
            array(\EVENT_PASS, 'targets\\test::test_two', null),
            array(\EVENT_PASS, 'targets\\test_one', null),
        ));

    }

    public function test_parses_and_runs_tests_marked_with_attribute()
    {
        // @bc 7.4 Check if attributes are supported
        if (\version_compare(\PHP_VERSION, '8.0.0', '<'))
        {
            strangetest\skip('Attributes were added in PHP 8.0');
        }

        $this->root .= 'run_attributes/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(\EVENT_PASS, 'function_one', null),
            array(\EVENT_PASS, 'Class1::MethodToTest', null),
            array(\EVENT_PASS, 'NumberTwo', null),
            array(\EVENT_PASS, 'NumberTwo::number1', null),
            array(\EVENT_PASS, 'NumberTwo::number2', null),
            array(\EVENT_PASS, 'NumberTwo::number3', null),
        ));
    }

}



// helper functions

function filepath($name) {
    $ds = \DIRECTORY_SEPARATOR;
    return  __DIR__ . "{$ds}resources{$ds}files{$ds}{$name}";
}



// helper assertions


function assert_run_file($filepath, $events) {
    $state = new State;
    $logger = new Logger(\TEST_ROOT, strangetest\LOG_ALL, new NoOutputter);
    $state->logger = $logger;
    $state->bufferer = new LogBufferer(\TEST_ROOT);
    $discovery_state = new _DiscoveryState($state, $logger);

    $file = strangetest\_discover_file($discovery_state, $filepath, 0);
    strangetest\assert_identical(array(), $logger->get_log()->events);
    if ($file instanceof strangetest\FileTest)
    {
        strangetest\_run_file($state, $file, new RunInstance(0, ''), array());
    }
    else
    {
        strangetest\_run_test_run_group($state, $file, new RunInstance(0, ''), array());
    }
    \assert_events($events, $logger);
}



// tests

function test_logs_error_if_arglist_isnt_iterable() {
    $file = namespace\filepath('test_noniterable_arglist.php');
    $events = array(
        array(
            \EVENT_ERROR, 'noniterable_arglist\\setup_file',
            "Invalid return value: setup fixtures should return an iterable (or 'null')"),
        array(\EVENT_OUTPUT, 'noniterable_arglist\\teardown_file', '.'),
    );

    namespace\assert_run_file($file, $events);
}


function test_logs_error_if_arglists_arent_iterable() {
    $file = namespace\filepath('test_noniterable_arglists.php');
    $events = array(
        array(
            \EVENT_ERROR, 'noniterable_arglists\\setup_runs',
            "Invalid return value: setup fixtures should return an iterable (or 'null')"),
        array(\EVENT_OUTPUT, 'noniterable_arglists\\teardown_runs', '.'),
    );

    namespace\assert_run_file($file, $events);
}


function test_logs_error_if_any_arglist_isnt_iterable() {
    $file = namespace\filepath('test_any_noniterable_arglist.php');
    $events = array(
        array(
            \EVENT_ERROR, 'any_noniterable_arglist\\setup_run0',
            "Invalid return value: setup fixtures should return an iterable (or 'null')"),
        array(\EVENT_OUTPUT, 'any_noniterable_arglist\\teardown_run0', '1'),
        array(\EVENT_PASS, 'any_noniterable_arglist\\test_one (1)', null),
        array(\EVENT_OUTPUT, 'any_noniterable_arglist\\teardown_run1', '2 3'),
        array(
            \EVENT_ERROR, 'any_noniterable_arglist\\setup_run2',
            "Invalid return value: setup fixtures should return an iterable (or 'null')"),
        array(\EVENT_OUTPUT, 'any_noniterable_arglist\\teardown_run2', '4'),
    );

    namespace\assert_run_file($file, $events);
}


function test_converts_arglist_to_array() {
    $file = namespace\filepath('test_converts_arglist.php');
    $events = array(
        array(\EVENT_PASS, 'converts_arglist\\test_one', null),
        array(\EVENT_PASS, 'converts_arglist\\test_two', null),
        array(\EVENT_OUTPUT, 'converts_arglist\\teardown_file', '.'),
    );

    namespace\assert_run_file($file, $events);
}
