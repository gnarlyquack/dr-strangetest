<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\run\file;

use strangetest;
use strangetest\BasicLogger;
use strangetest\BufferingLogger;
use strangetest\FileTest;
use strangetest\Logger;
use strangetest\State;


class TestFiles {
    private $logger;
    private $path;


    public function setup() {
        $this->path = __DIR__ . '/sample_files/files/';
        $this->logger = new strangetest\BasicLogger(strangetest\LOG_ALL);
    }


    // helper assertions

    private function assert_events($expected) {
        list($root, $targets) = strangetest\process_user_targets((array)$this->path, $errors);
        strangetest\assert_falsy($errors);

        strangetest\discover_tests(
            new strangetest\BufferingLogger($this->logger),
            $root, $targets
        );
        assert_events($expected, $this->logger);
    }


    // tests

    public function test_parses_and_runs_only_tests() {
        $this->path .= 'run_tests/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'test_one', null),
            array(strangetest\EVENT_PASS, 'Test1::TestMe', null),
            array(strangetest\EVENT_PASS, 'TestTwo', null),
            array(strangetest\EVENT_PASS, 'TestTwo::test1', null),
            array(strangetest\EVENT_PASS, 'TestTwo::test2', null),
            array(strangetest\EVENT_PASS, 'TestTwo::test3', null),
            array(strangetest\EVENT_PASS, 'test::test_two', null),
            array(strangetest\EVENT_PASS, 'test', null),
        ));
    }


    public function test_parses_and_runs_tests_and_fixtures() {
        $this->path .= 'fixtures/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'setup for file_fixtures\\test_one', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'file_fixtures\\test_one', "teardown 1\nteardown 2"),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_fixtures\\test_one', '2 4'),
            array(strangetest\EVENT_PASS, 'file_fixtures\\test_one', null),

            array(strangetest\EVENT_OUTPUT, 'setup for file_fixtures\\test_two', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_fixtures\\test_two', '2 4'),
            array(strangetest\EVENT_PASS, 'file_fixtures\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'file_fixtures\\test::setup_object', '2 4'),

            array(strangetest\EVENT_OUTPUT, 'setup for file_fixtures\\test::test_one', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'file_fixtures\\test::test_one', "teardown 1\nteardown 2"),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_fixtures\\test::test_one', '2 4'),
            array(strangetest\EVENT_PASS, 'file_fixtures\\test::test_one', null),

            array(strangetest\EVENT_OUTPUT, 'setup for file_fixtures\\test::test_two', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_fixtures\\test::test_two', '2 4'),
            array(strangetest\EVENT_PASS, 'file_fixtures\\test::test_two', null),

            array(strangetest\EVENT_OUTPUT, 'file_fixtures\\test::teardown_object', '2 4'),

            array(strangetest\EVENT_OUTPUT, 'file_fixtures\\teardown_file', '2 4'),
        ));
    }


    public function test_names_are_case_insensitive() {
        $this->path .= 'case/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'SetUpFunction for FileCase\\TestOne', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'TearDownFunction for FileCase\\TestOne', '2 4'),
            array(strangetest\EVENT_PASS, 'FileCase\\TestOne', null),

            array(strangetest\EVENT_OUTPUT, 'SetUpFunction for FileCase\\TestTwo', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'TearDownFunction for FileCase\\TestTwo', '2 4'),
            array(strangetest\EVENT_PASS, 'FileCase\\TestTwo', null),

            array(strangetest\EVENT_OUTPUT, 'FileCase\\Test::SetUpObject', '2 4'),

            array(strangetest\EVENT_OUTPUT, 'SetUp for FileCase\\Test::TestOne', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'TearDown for FileCase\\Test::TestOne', '2 4'),
            array(strangetest\EVENT_PASS, 'FileCase\\Test::TestOne', null),

            array(strangetest\EVENT_OUTPUT, 'SetUp for FileCase\\Test::TestTwo', '2 4'),
            array(strangetest\EVENT_OUTPUT, 'TearDown for FileCase\\Test::TestTwo', '2 4'),
            array(strangetest\EVENT_PASS, 'FileCase\\Test::TestTwo', null),

            array(strangetest\EVENT_OUTPUT, 'FileCase\\Test::TearDownObject', '2 4'),

            array(strangetest\EVENT_OUTPUT, 'FileCase\\TearDownFile', '2 4'),
        ));
    }


    public function test_parses_multiple_simple_namespaces() {
        $this->path .= 'simple_namespaces/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'ns02\\TestNamespaces::test', null),
            array(strangetest\EVENT_PASS, 'ns03\\TestNamespaces::test', null),
        ));
    }


    public function test_parses_multiple_bracketed_namespaces() {
        $this->path .= 'bracketed_namespaces/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'ns01\\ns1\\TestNamespaces::test', null),
            array(strangetest\EVENT_PASS, 'ns01\\ns2\\TestNamespaces::test', null),
            array(strangetest\EVENT_PASS, 'TestNamespaces::test', null),
        ));
    }


    public function test_does_not_discover_anonymous_classes() {
        // #BC(5.6): Check if anonymous classes are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            strangetest\skip('PHP 7 introduced anonymous classes');
        }

        $this->path .= 'anonymous/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'anonymous\\test_anonymous_class', null),
            array(strangetest\EVENT_PASS, 'anonymous\\test_i_am_a_function_name', null),
            array(strangetest\EVENT_PASS, 'anonymous\\test::test_anonymous_class', null),
        ));
    }


    public function test_handles_failed_tests() {
        $this->path .= 'failures/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'file_failures\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for file_failures\\test_one', '.'),
            array(strangetest\EVENT_FAIL, 'file_failures\\test_one', 'I failed'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test_one', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for file_failures\\test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for file_failures\\test_two', '.'),
            array(strangetest\EVENT_ERROR, 'file_failures\\test_two', 'An error happened'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test_two', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for file_failures\\test_two', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for file_failures\\test_three', '.'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test_three', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for file_failures\\test_three', '.'),
            array(strangetest\EVENT_PASS, 'file_failures\\test_three', null),

            array(strangetest\EVENT_OUTPUT, 'setup_function for file_failures\\test_four', '.'),
            array(strangetest\EVENT_ERROR, 'file_failures\\test_four', "I'm exceptional!"),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test_four', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for file_failures\\test_four', '.'),

            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for file_failures\\test::test_one', '.'),
            array(strangetest\EVENT_FAIL, 'file_failures\\test::test_one', 'I failed'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::test_one', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_failures\\test::test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for file_failures\\test::test_two', '.'),
            array(strangetest\EVENT_ERROR, 'file_failures\\test::test_two', 'An error happened'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::test_two', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_failures\\test::test_two', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for file_failures\\test::test_three', '.'),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::test_three', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_failures\\test::test_three', '.'),
            array(strangetest\EVENT_PASS, 'file_failures\\test::test_three', null),

            array(strangetest\EVENT_OUTPUT, 'setup for file_failures\\test::test_four', '.'),
            array(strangetest\EVENT_ERROR, 'file_failures\\test::test_four', "I'm exceptional!"),
            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::test_four', 'teardown'),
            array(strangetest\EVENT_OUTPUT, 'teardown for file_failures\\test::test_four', '.'),

            array(strangetest\EVENT_OUTPUT, 'file_failures\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'file_failures\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_when_loading_file() {
        $this->path .= 'file_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_ERROR, $this->path, 'Skip me'),
        ));
    }


    public function test_logs_error_in_setup_file() {
        $this->path .= 'setup_file_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_ERROR, 'setup_file_error\\setup_file', 'An error happened'),
        ));
    }


    public function test_logs_error_in_constructor() {
        $this->path .= 'constructor_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'constructor_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for constructor_error\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for constructor_error\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'constructor_error\\test_one', null),

            array(strangetest\EVENT_ERROR, 'constructor_error\\test', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for constructor_error\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for constructor_error\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'constructor_error\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'constructor_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_setup_object() {
        $this->path .= 'setup_object_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'setup_object_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for setup_object_error\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for setup_object_error\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'setup_object_error\\test_one', null),

            array(strangetest\EVENT_ERROR, 'setup_object_error\\test::setup_object', 'An error happened'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for setup_object_error\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for setup_object_error\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'setup_object_error\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'setup_object_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_setup() {
        $this->path .= 'setup_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'setup_error\\setup_file', '.'),

            array(strangetest\EVENT_ERROR, 'setup_function for setup_error\\test_one', 'An error happened'),
            array(strangetest\EVENT_ERROR, 'setup_function for setup_error\\test_two', 'An error happened'),

            array(strangetest\EVENT_OUTPUT, 'setup_error\\test::setup_object', '.'),

            array(strangetest\EVENT_ERROR, 'setup for setup_error\\test::test_one', 'An error happened'),
            array(strangetest\EVENT_ERROR, 'setup for setup_error\\test::test_two', 'An error happened'),

            array(strangetest\EVENT_OUTPUT, 'setup_error\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_error\\teardown_file', '.'),
        ));
    }


    function test_logs_error_in_function_teardown() {
        $this->path .= 'teardown_test_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_test_error\\test_one', '.'),
            array(strangetest\EVENT_ERROR, 'teardown_test_error\\test_one', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\test_one', 'teardown 2'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_test_error\\test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_test_error\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_test_error\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_test_error\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_test_error\\test::test_one', '.'),
            array(strangetest\EVENT_ERROR, 'teardown_test_error\\test::test_one', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\test::test_one', 'teardown 2'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_test_error\\test::test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_test_error\\test::test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_test_error\\test::test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_test_error\\test::test_two', null),

            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'teardown_test_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown() {
        $this->path .= 'teardown_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'teardown_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_error\\test_one', '.'),
            array(strangetest\EVENT_ERROR, 'teardown_function for teardown_error\\test_one', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_error\\test_two', '.'),
            array(strangetest\EVENT_ERROR, 'teardown_function for teardown_error\\test_two', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'teardown_error\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_error\\test::test_one', '.'),
            array(strangetest\EVENT_ERROR, 'teardown for teardown_error\\test::test_one', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_error\\test::test_two', '.'),
            array(strangetest\EVENT_ERROR, 'teardown for teardown_error\\test::test_two', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'teardown_error\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'teardown_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown_object() {
        $this->path .= 'teardown_object_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'teardown_object_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_object_error\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_object_error\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'teardown_object_error\\test_one', null),


            array(strangetest\EVENT_OUTPUT, 'teardown_object_error\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_object_error\\test::test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_object_error\\test::test_one', '.'),
            array(strangetest\EVENT_PASS, 'teardown_object_error\\test::test_one', null),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_object_error\\test::test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_object_error\\test::test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_object_error\\test::test_two', null),

            array(strangetest\EVENT_ERROR, 'teardown_object_error\\test::teardown_object', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_object_error\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_object_error\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_object_error\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'teardown_object_error\\teardown_file', '.'),
        ));
    }


    public function test_logs_error_in_teardown_file() {
        $this->path .= 'teardown_file_error/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'teardown_file_error\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_file_error\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_file_error\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'teardown_file_error\\test_one', null),


            array(strangetest\EVENT_OUTPUT, 'teardown_file_error\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_file_error\\test::test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_file_error\\test::test_one', '.'),
            array(strangetest\EVENT_PASS, 'teardown_file_error\\test::test_one', null),

            array(strangetest\EVENT_OUTPUT, 'setup for teardown_file_error\\test::test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for teardown_file_error\\test::test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_file_error\\test::test_two', null),

            array(strangetest\EVENT_OUTPUT, 'teardown_file_error\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for teardown_file_error\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for teardown_file_error\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'teardown_file_error\\test_two', null),

            array(strangetest\EVENT_ERROR, 'teardown_file_error\\teardown_file', 'Skip me'),
        ));
    }


    public function test_logs_error_for_multiple_object_fixtures() {
        $this->path .= 'multiple_object_fixtures/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'multiple_object_fixtures\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for multiple_object_fixtures\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for multiple_object_fixtures\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'multiple_object_fixtures\\test_one', null),

            array(strangetest\EVENT_ERROR, 'multiple_object_fixtures\\test', "Multiple setup fixtures found:\n\tsetup_object\n\tSetUpObject"),
            array(strangetest\EVENT_ERROR, 'multiple_object_fixtures\\test', "Multiple teardown fixtures found:\n\tteardown_object\n\tTearDownObject"),

            array(strangetest\EVENT_OUTPUT, 'setup_function for multiple_object_fixtures\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for multiple_object_fixtures\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'multiple_object_fixtures\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'multiple_object_fixtures\\teardown_file', '.'),
        ));
    }


    public function test_skips_file() {
        $this->path .= 'skip_file/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_SKIP, 'skip_file\\setup_file', 'Skip me'),
        ));
    }


    public function test_skips_object() {
        $this->path .= 'skip_object/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'skip_object\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for skip_object\\test_one', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for skip_object\\test_one', '.'),
            array(strangetest\EVENT_PASS, 'skip_object\\test_one', null),

            array(strangetest\EVENT_SKIP, 'skip_object\\test::setup_object', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for skip_object\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for skip_object\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'skip_object\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'skip_object\\teardown_file', '.'),
        ));
    }


    public function test_skips_in_setup() {
        $this->path .= 'skip_setup/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'skip_setup\\setup_file', '.'),

            array(strangetest\EVENT_SKIP, 'setup_function for skip_setup\\test_one', 'Skip me'),
            array(strangetest\EVENT_SKIP, 'setup_function for skip_setup\\test_two', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'skip_setup\\test::setup_object', '.'),

            array(strangetest\EVENT_SKIP, 'setup for skip_setup\\test::test_one', 'Skip me'),
            array(strangetest\EVENT_SKIP, 'setup for skip_setup\\test::test_two', 'Skip me'),

            array(strangetest\EVENT_OUTPUT, 'skip_setup\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'skip_setup\\teardown_file', '.'),
        ));
    }


    public function test_skips_test() {
        $this->path .= 'skip_test/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'skip_test\\setup_file', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for skip_test\\test_one', '.'),
            array(strangetest\EVENT_SKIP, 'skip_test\\test_one', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for skip_test\\test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'skip_test\\test::setup_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for skip_test\\test::test_one', '.'),
            array(strangetest\EVENT_SKIP, 'skip_test\\test::test_one', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown for skip_test\\test::test_one', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup for skip_test\\test::test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown for skip_test\\test::test_two', '.'),
            array(strangetest\EVENT_PASS, 'skip_test\\test::test_two', null),

            array(strangetest\EVENT_OUTPUT, 'skip_test\\test::teardown_object', '.'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for skip_test\\test_two', '.'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for skip_test\\test_two', '.'),
            array(strangetest\EVENT_PASS, 'skip_test\\test_two', null),

            array(strangetest\EVENT_OUTPUT, 'skip_test\\teardown_file', '.'),
        ));
    }


    public function test_logs_file_output() {
        $this->path .= 'file_output/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, $this->path, '.'),
        ));
    }


    public function test_logs_constructor_output() {
        $this->path .= 'constructor_output/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'constructor_output\\test_one', null),

            array(strangetest\EVENT_OUTPUT, 'constructor_output\\test', '.'),
            array(strangetest\EVENT_PASS, 'constructor_output\\test::test_method', null),

            array(strangetest\EVENT_PASS, 'constructor_output\\test_two', null),
        ));
    }


    public function test_logs_test_output() {
        $this->path .= 'output/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'test_output\\test', '.'),
            array(strangetest\EVENT_PASS, 'test_output\\test', null),

            array(strangetest\EVENT_OUTPUT, 'test_output\\test::test_method', '.'),
            array(strangetest\EVENT_PASS, 'test_output\\test::test_method', null),
        ));
    }


    public function test_supports_output_buffering() {
        $this->path .= 'buffering/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_OUTPUT, 'setup_function for buffering\\test_skip', 'setup output that should be seen'),
            array(strangetest\EVENT_SKIP, 'buffering\\test_skip', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for buffering\\test_skip', 'teardown output that should be seen'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for buffering\\test_error', 'setup output that should be seen'),
            array(strangetest\EVENT_ERROR, 'buffering\\test_error', 'Did I err?'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for buffering\\test_error', 'teardown output that should be seen'),

            array(strangetest\EVENT_OUTPUT, 'setup_function for buffering\\test_fail', 'setup output that should be seen'),
            array(strangetest\EVENT_FAIL, 'buffering\\test_fail', 'I failed'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for buffering\\test_fail', 'teardown output that should be seen'),


            array(strangetest\EVENT_OUTPUT, 'setup for buffering\\test::test_skip', 'setup output that should be seen'),
            array(strangetest\EVENT_SKIP, 'buffering\\test::test_skip', 'Skip me'),
            array(strangetest\EVENT_OUTPUT, 'teardown for buffering\\test::test_skip', 'teardown output that should be seen'),

            array(strangetest\EVENT_OUTPUT, 'setup for buffering\\test::test_error', 'setup output that should be seen'),
            array(strangetest\EVENT_ERROR, 'buffering\\test::test_error', 'Did I err?'),
            array(strangetest\EVENT_OUTPUT, 'teardown for buffering\\test::test_error', 'teardown output that should be seen'),

            array(strangetest\EVENT_OUTPUT, 'setup for buffering\\test::test_fail', 'setup output that should be seen'),
            array(strangetest\EVENT_FAIL, 'buffering\\test::test_fail', 'I failed'),
            array(strangetest\EVENT_OUTPUT, 'teardown for buffering\\test::test_fail', 'teardown output that should be seen'),

            array(strangetest\EVENT_OUTPUT, 'setup for buffering\\test::test_pass', 'setup output that should be seen'),
            array(strangetest\EVENT_OUTPUT, 'teardown for buffering\\test::test_pass', 'teardown output that should be seen'),
            array(strangetest\EVENT_PASS, 'buffering\\test::test_pass', null),


            array(strangetest\EVENT_OUTPUT, 'setup_function for buffering\\test_pass', 'setup output that should be seen'),
            array(strangetest\EVENT_OUTPUT, 'teardown_function for buffering\\test_pass', 'teardown output that should be seen'),
            array(strangetest\EVENT_PASS, 'buffering\\test_pass', null),
        ));
    }


    public function test_logs_errors_for_undeleted_output_buffers() {
        $this->path .= 'undeleted_buffers/test.php';

        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to dangling output buffers. This seems
        // desirable: errors associated with buffering will get logged and
        // cause the test suite in general to fail (so hopefully people will
        // clean up their tests), but do not otherwise impede testing.
        $this->assert_events(array(
            array(
                strangetest\EVENT_ERROR,
                'undeleted_buffers\\setup_file',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\setup_file",
            ),


            array(
                strangetest\EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\teardown_function",
            ),
            array(
                strangetest\EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: [the output buffer was empty]",
            ),
            array(
                strangetest\EVENT_ERROR,
                'teardown_function for undeleted_buffers\\test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\setup_function",
            ),
            array(strangetest\EVENT_PASS, 'undeleted_buffers\\test', null),


            array(
                strangetest\EVENT_ERROR,
                'undeleted_buffers\\test::setup_object',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::setup_object",
            ),

            array(
                strangetest\EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::teardown",
            ),
            array(
                strangetest\EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: [the output buffer was empty]",
            ),
            array(
                strangetest\EVENT_ERROR,
                'teardown for undeleted_buffers\\test::test',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::setup",
            ),
            array(strangetest\EVENT_PASS, 'undeleted_buffers\\test::test', null),

            array(
                strangetest\EVENT_ERROR,
                'undeleted_buffers\\test::teardown_object',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\test::teardown_object",
            ),


            array(
                strangetest\EVENT_ERROR,
                'undeleted_buffers\\teardown_file',
                "An output buffer was started but never deleted.\nBuffer contents were: undeleted_buffers\\teardown_file",
            ),
        ));
    }


    public function test_logs_errors_when_deleting_strangetest_output_buffers() {
        $this->path .= 'deleted_buffers/test.php';

        // Note that since the test itself didn't fail, we log a pass, but we
        // also get errors due to deleting Dr. Strangetest's output buffers. This
        // seems desirable: errors associated with buffering will get logged
        // and cause the test suite in general to fail (so hopefully people
        // will clean up their tests), but do not otherwise impede testing.
        $message = "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.";
        $this->assert_events(array(
            array(strangetest\EVENT_ERROR, 'deleted_buffers\\setup_file', $message),

            array(strangetest\EVENT_ERROR, 'setup_function for deleted_buffers\\test', $message),
            array(strangetest\EVENT_ERROR, 'deleted_buffers\\test', $message),
            array(strangetest\EVENT_ERROR, 'teardown_function for deleted_buffers\\test', $message),
            array(strangetest\EVENT_PASS, 'deleted_buffers\\test', null),


            array(strangetest\EVENT_ERROR, 'deleted_buffers\\test::setup_object', $message),

            array(strangetest\EVENT_ERROR, 'setup for deleted_buffers\\test::test', $message),
            array(strangetest\EVENT_ERROR, 'deleted_buffers\\test::test', $message),
            array(strangetest\EVENT_ERROR, 'teardown for deleted_buffers\\test::test', $message),
            array(strangetest\EVENT_PASS, 'deleted_buffers\\test::test', null),

            array(strangetest\EVENT_ERROR, 'deleted_buffers\\test::teardown_object', $message),


            array(strangetest\EVENT_ERROR, 'deleted_buffers\\teardown_file', $message),
        ));

        strangetest\assert_identical('', \ob_get_contents());
    }


    public function test_runs_tests_once_per_arg_list() {
        $this->path .= 'multiple_runs/test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'multiple_runs\\test_one (0)', null),
            array(strangetest\EVENT_PASS, 'multiple_runs\\test_two (0)', null),
            array(strangetest\EVENT_PASS, 'multiple_runs\\test::test_one (0)', null),

            array(strangetest\EVENT_PASS, 'multiple_runs\\test_one (1)', null),
            array(strangetest\EVENT_PASS, 'multiple_runs\\test_two (1)', null),
            array(strangetest\EVENT_PASS, 'multiple_runs\\test::test_one (1)', null),

            array(
                strangetest\EVENT_OUTPUT,
                'multiple_runs\\teardown_runs',
                \print_r(array(array(2, 4), array(8, 16)), true),
            ),
        ));
    }


    function test_runs_only_targeted_tests() {
        $this->path = array(
            "{$this->path}targets/test.php",
            '--function=targets\\test_two',
            '--class=targets\\test::test_two',
            '--function=targets\\test_one',
        );

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'targets\\test_two', null),
            array(strangetest\EVENT_PASS, 'targets\\test::test_two', null),
            array(strangetest\EVENT_PASS, 'targets\\test_one', null),
        ));

    }
}



// helper functions

function filepath($name) {
    $ds = \DIRECTORY_SEPARATOR;
    return  __DIR__ . "{$ds}support{$ds}{$name}";
}



// helper assertions


function assert_run_file($filepath, $events) {
    $state = new State();
    $logger = new BasicLogger(strangetest\LOG_ALL);
    $buffed_logger = new BufferingLogger($logger);

    $file = strangetest\discover_file($state, $buffed_logger, $filepath);
    strangetest\assert_identical(array(), $logger->get_log()->get_events());
    strangetest\assert_true($file instanceof FileTest);

    strangetest\run_test($state, $buffed_logger, $file);
    \assert_events($events, $logger);
}



// tests

function test_logs_error_if_arglist_isnt_iterable() {
    $file = namespace\filepath('test_noniterable_arglist.php');
    $error = "'noniterable_arglist\\setup_file' returned a non-iterable argument set";
    $events = array(
        array(strangetest\EVENT_ERROR, $file, $error),
        array(strangetest\EVENT_OUTPUT, 'noniterable_arglist\\teardown_file', '.'),
    );

    namespace\assert_run_file($file, $events);
}


function test_logs_error_if_arglists_arent_iterable() {
    $file = namespace\filepath('test_noniterable_arglists.php');
    $error = "'noniterable_arglists\\setup_runs' returned a non-iterable argument set";
    $events = array(
        array(strangetest\EVENT_ERROR, $file, $error),
        array(strangetest\EVENT_OUTPUT, 'noniterable_arglists\\teardown_runs', '.'),
    );

    namespace\assert_run_file($file, $events);
}


function test_logs_error_if_any_arglist_isnt_iterable() {
    $file = namespace\filepath('test_any_noniterable_arglist.php');
    $error = "'any_noniterable_arglist\\setup_runs' returned a non-iterable argument set\nfor argument set '%d'";
    $events = array(
        array(strangetest\EVENT_ERROR, $file, \sprintf($error, 0)),
        array(strangetest\EVENT_PASS, 'any_noniterable_arglist\\test_one (1)', null),
        array(strangetest\EVENT_OUTPUT, 'any_noniterable_arglist\\teardown_file (1)', '.'),
        array(strangetest\EVENT_ERROR, $file, \sprintf($error, 2)),
        array(strangetest\EVENT_OUTPUT, 'any_noniterable_arglist\\teardown_runs', '.'),
    );

    namespace\assert_run_file($file, $events);
}


function test_converts_arglist_to_array() {
    $file = namespace\filepath('test_converts_arglist.php');
    $events = array(
        array(strangetest\EVENT_PASS, 'converts_arglist\\test_one', null),
        array(strangetest\EVENT_PASS, 'converts_arglist\\test_two', null),
        array(strangetest\EVENT_OUTPUT, 'converts_arglist\\teardown_file', '.'),
    );

    namespace\assert_run_file($file, $events);
}
