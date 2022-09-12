<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\runner;

use strangetest;
use strangetest\BasicLogger;
use strangetest\State;

use NoOutputter;


class TestRunDirectories {
    private $logger;
    private $path;


    public function setup() {
        $this->logger = new BasicLogger(strangetest\LOG_ALL, new NoOutputter);
        $this->path = __DIR__ . '/resources/directories/';
    }


    // helper assertions

    private function assert_events($directories, strangetest\Context $context) {
        $root = $this->path . \DIRECTORY_SEPARATOR;

        $logger = new strangetest\BufferingLogger($this->logger);
        $state = new State;

        $tests = strangetest\discover_tests($state, $logger, $root);
        strangetest\assert_truthy($tests, "Failed to discover tests for {$root}");

        strangetest\run_tests($state, $logger, $tests, $tests);

        $root = null;
        $current = null;
        $directory = null;

        foreach ($this->logger->get_log()->get_events() as $event) {
            list($type, $source, $reason) = $event;
            // @bc 5.6 Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }
            $event = array($type, $source, $reason);

            switch ($type) {

            case strangetest\EVENT_PASS:
            case strangetest\EVENT_FAIL:
            case strangetest\EVENT_ERROR:
            case strangetest\EVENT_SKIP:
                if (!$current) {
                    strangetest\fail(
                        \sprintf(
                            "Got event but never entered a directory\nevent: %s",
                            \print_r($event, true)
                        )
                    );
                }

                if (!isset($directory['events'][$source])) {
                    strangetest\fail(
                        \sprintf(
                            "Unexpected event\ncurrent: %s\nevent: %s",
                            $current,
                            \print_r($event, true)
                        )
                    );
                }

                $expected = $directory['events'][$source];
                if ($reason !== $expected[1]) {
                    $reason = \substr($reason, 0, \strlen($expected[1]));
                }
                if ($expected !== array($type, $reason)) {
                    strangetest\fail(
                        \sprintf(
                            "Unexpected event\ncurrent: %s\nevent: %s",
                            $current,
                            \print_r($event, true)
                        )
                    );
                }
                unset($directory['events'][$source]);
                break;

            case strangetest\EVENT_OUTPUT:
                if (false !== \stripos($source, 'setup')) {
                    if ($current && !isset($directory['dirs'][$reason])) {
                        strangetest\fail(
                            \sprintf(
                                "Got directory setup for a nonexistent child directory?\ncurrent: %s\nevent %s",
                                $current,
                                \print_r($event, true)
                            )
                        );
                    }

                    if ($current) {
                        $directories[$current] = $directory;
                    }
                    $directory = $directories[$reason];
                    --$directory['setup'];
                    $current = $reason;
                    if (!$root) {
                        $root = $reason;
                    }
                    if (!isset($directory['initialized'])) {
                        $directory['initialized'] = true;
                        if (!isset($directory['dirs'])) {
                            $directory['dirs'] = array();
                        }
                        else {
                            $dirs = $directory['dirs'];
                            $directory['dirs'] = array();
                            foreach ($dirs as $dir) {
                                $directory['dirs'][$dir] = true;
                            }
                        }
                        if (!isset($directory['events'])) {
                            $directory['events'] = array();
                        }
                    }
                }

                elseif (false !== \stripos($source, 'teardown')) {
                    if (!$current) {
                        strangetest\fail(
                            \sprintf(
                                "Got directory teardown but we're not in one?\nevent: %s",
                                \print_r($event, true)
                            )
                        );
                    }
                    if ($current !== $reason) {
                        strangetest\fail(
                            \sprintf(
                                "Got directory teardown for a directory we're not in?\ncurrent: %s\nevent: %s",
                                $current,
                                \print_r($event, true)
                            )
                        );
                    }

                    --$directory['teardown'];
                    $directories[$current] = $directory;
                    if ($root === $current) {
                        $current = null;
                        break;
                    }

                    $parent = $directory['parent'];
                    if (!isset($directories[$parent])) {
                        strangetest\fail(
                            "Ascended into an invalid parent directory?\ncurrent: $current\nparent:  $parent"
                        );
                    }

                    $directory = $directories[$parent];
                    if (!($directories[$current]['dirs'] || $directories[$current]['events'])) {
                        unset($directory['dirs'][$current]);
                    }
                    $current = $parent;
                }
                elseif (isset($directory['events'][$source])) {
                    $expected = $directory['events'][$source];
                    if ($expected !== array($type, $reason)) {
                        strangetest\fail(
                            \sprintf(
                                "Unexpected event\ncurrent: %s\nevent: %s",
                                $current,
                                \print_r($event, true)
                            )
                        );
                    }
                    unset($directory['events'][$source]);
                }
                else {
                    strangetest\fail(
                        \sprintf(
                            "Unexpected event\ncurrent: %s\nevent: %s",
                            $current,
                            \print_r($event, true)
                        )
                    );
                }
                break;
            }
        }

        foreach ($directories as $path => $directory) {
            $context->subtest(
                function() use ($directory, $path)
                {
                    strangetest\assert_falsy($directory['setup'], "setup for $path");
                }
            );
            $context->subtest(
                function() use ($directory, $path)
                {
                    strangetest\assert_falsy($directory['teardown'], "teardown for $path");
                }
            );
            $context->subtest(
                function() use ($directory, $path)
                {
                    strangetest\assert_falsy($directory['dirs'], "dirs for $path");
                }
            );
            $context->subtest(
                function() use ($directory, $path)
                {
                    strangetest\assert_falsy($directory['events'], "events for $path");
                }
            );
        }
    }


    private function assert_log($expected) {
        $root = $this->path . \DIRECTORY_SEPARATOR;

        $logger = new strangetest\BufferingLogger($this->logger);
        $state = new State;

        $tests = strangetest\discover_tests($state, $logger, $root);
        strangetest\assert_truthy($tests, "Failed to discover tests for {$root}");

        strangetest\run_tests($state, $logger, $tests, $tests);

        $actual = $this->logger->get_log()->get_events();
        foreach ($actual as $i => $event) {
            list($type, $source, $reason) = $event;
            // @bc 5.6 Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }

            $actual[$i] = array($type, $source, $reason);
        }
        strangetest\assert_identical($expected, $actual);
    }


    private function assert_run($tests, $expected)
    {
        $tests = make_test($tests);
        $logger = new strangetest\BufferingLogger($this->logger);
        $state = new State;
        strangetest\run_tests($state, $logger, $tests, $tests);

        $actual = $this->logger->get_log()->get_events();
        foreach ($actual as $i => $event)
        {
            list($type, $source, $reason) = $event;
            // @bc 5.6 Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }

            $actual[$i] = array($type, $source, $reason);
        }
        strangetest\assert_identical($expected, $actual);
    }


    // tests

    public function test_individual_paths()
    {
        $tests = array(
            'directory' => 'tests/',
            'setup' => 'test\\runner\\dir_setup',
            'teardown' => 'test\\runner\\dir_teardown',
            'tests' => array(
                array(
                    'directory' => 'test_dir1/',
                    'setup' => 'test\\runner\\dir1_setup',
                    'teardown' => 'test\\runner\\dir1_teardown',
                    'tests' => array(
                        array(
                            'file' => 'test2.php',
                            'tests' => array(
                                array(
                                    'function' => 'dir1_test3',
                                    'namespace' => __NAMESPACE__,
                                ),
                                array(
                                    'function' => 'dir1_test4',
                                    'namespace' => __NAMESPACE__,
                                ),
                            ),
                        ),
                        array(
                            'file' => 'test3.php',
                            'tests' => array(
                                array(
                                    'function' => 'dir1_test5',
                                    'namespace' => __NAMESPACE__,
                                ),
                                array(
                                    'function' => 'dir1_test6',
                                    'namespace' => __NAMESPACE__,
                                ),
                            ),
                        ),
                    ),
                ),
                array(
                    'directory' => 'test_dir2/',
                    'setup' => 'test\\runner\\dir2_setup',
                    'teardown' => 'test\\runner\\dir2_teardown',
                    'tests' => array(
                        array(
                            'directory' => 'test_subdir/',
                            'setup' => 'test\\runner\\subdir_setup',
                            'teardown' => 'test\\runner\\subdir_teardown',
                            'tests' => array(
                                array(
                                    'file' => 'test1.php',
                                    'tests' => array(
                                        array(
                                            'function' => 'subdir_test1',
                                            'namespace' => __NAMESPACE__,
                                        ),
                                        array(
                                            'function' => 'subdir_test2',
                                            'namespace' => __NAMESPACE__,
                                        ),
                                    ),
                                ),
                                array(
                                    'file' => 'test2.php',
                                    'tests' => array(
                                        array(
                                            'function' => 'subdir_test3',
                                            'namespace' => __NAMESPACE__,
                                        ),
                                        array(
                                            'function' => 'subdir_test4',
                                            'namespace' => __NAMESPACE__,
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
        $expected = array(
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir_setup', 'dir_setup'),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir1_setup', 'dir1_setup'),
            array(strangetest\EVENT_PASS, 'test\\runner\\dir1_test3', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\dir1_test4', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\dir1_test5', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\dir1_test6', null),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir1_teardown', 'dir1_teardown'),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir2_setup', 'dir2_setup'),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\subdir_setup', 'subdir_setup'),
            array(strangetest\EVENT_PASS, 'test\\runner\\subdir_test1', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\subdir_test2', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\subdir_test3', null),
            array(strangetest\EVENT_PASS, 'test\\runner\\subdir_test4', null),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\subdir_teardown', 'subdir_teardown'),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir2_teardown', 'dir2_teardown'),
            array(strangetest\EVENT_OUTPUT, 'test\\runner\\dir_teardown', 'dir_teardown'),
        );

        $this->assert_run($tests, $expected);
    }

    public function test_handles_error_in_setup_directory() {
        $this->path .= 'setup_directory_error';

        $this->assert_log(array(
             array(
                strangetest\EVENT_ERROR,
                'setup_directory_setup_error',
                'An error happened',
            ),
        ));
    }

    public function test_teardown_error(strangetest\Context $context) {
        $this->path .= 'teardown_directory_error';

        $expected = array(
            $this->path => array(
                'setup' => 1,
                'teardown' => 1,
                'events' => array(
                    'test_teardown_error::test' => array(strangetest\EVENT_PASS, null),
                    'teardown_directory_teardown_error' => array(
                        strangetest\EVENT_ERROR,
                        'Skip is an error here',
                    ),
                ),
            ),
        );
        $this->assert_events($expected, $context);
    }


    public function test_passes_arguments_to_tests_and_subdirectories(strangetest\Context $context) {
        $this->path .= 'argument_passing';
        $path = $this->path;

        $expected = array(
            $path => array(
                'setup' => 1,
                'teardown' => 1,
                'dirs' => array("$path/test_subdir1"),
                'events' => array(
                    'TestArgumentsOne::test' => array(strangetest\EVENT_PASS, null),
                    'TestArgumentsThree::test' => array(strangetest\EVENT_PASS, null),
                ),
            ),

            "$path/test_subdir1" => array(
                'setup' => 1,
                'teardown' => 1,
                'parent' => $path,
                'events' => array(
                    'TestArgumentsTwo::test' => array(strangetest\EVENT_PASS, null),
                ),
            ),
        );
        $this->assert_events($expected, $context);
    }


    public function test_errors_on_insufficient_arguments(strangetest\Context $context) {
        $this->path .= 'insufficient_arguments';
        $path = $this->path;

        $expected = array(
            $path => array(
                'setup' => 1,
                'teardown' => 1,
                'events' => array(
                    'TestInsufficientArguments' => array(
                        strangetest\EVENT_ERROR,
                        // @bc 7.0 Check format of expected error message
                        version_compare(PHP_VERSION, '7.1', '<')
                            ? 'Missing argument 2 for TestInsufficientArguments::__construct()'
                            : 'Too few arguments to function TestInsufficientArguments::__construct()',
                    ),
                ),
            ),
        );
        $this->assert_events($expected, $context);
    }


    public function test_runs_directory_tests_once_per_arg_list() {
        $this->path .= 'dir_params';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run_0',
                $path,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_directory (0)',
                '2 4',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run0 (0)',
                'dir_params\\setup_run0',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_file (0, 0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\test_function (0, 0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\test_function (0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\TestClass::test (0, 0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\TestClass::test (0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_file (0, 0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run0 (0)',
                '2 4',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run1 (0)',
                'dir_params\\setup_run1',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_file (0, 1)',
                '4 2',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\test_function (0, 1)',
                '4 2',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\test_function (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\TestClass::test (0, 1)',
                '4 2',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\TestClass::test (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_file (0, 1)',
                '4 2',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run1 (0)',
                '4 2',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_directory (0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run_0',
                '2 4',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run_1',
                $path,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_directory (1)',
                '8 16',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run0 (1)',
                'dir_params\\setup_run0',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_file (1, 0)',
                '8 16',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\test_function (1, 0)',
                '8 16',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\test_function (1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\TestClass::test (1, 0)',
                '8 16',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\TestClass::test (1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_file (1, 0)',
                '8 16',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run0 (1)',
                '8 16',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_run1 (1)',
                'dir_params\\setup_run1',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_file (1, 1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\test_function (1, 1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\test_function (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\TestClass::test (1, 1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_PASS,
                'dir_params\\TestClass::test (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_file (1, 1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run1 (1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_directory (1)',
                '8 16',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_run_1',
                '8 16',
            ),
        );
        $this->assert_log($expected);
    }


    public function test_runs_subdirectory_tests_once_per_arg_list() {
        $this->path .= 'subdir_params';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\setup_run_0',
                $path,
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_run_0 (0)',
                "$path/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\test_function (0, 0)',
                '2 4 8',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\test_function (0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\TestClass::test (0, 0)',
                '2 4',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\TestClass::test (0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\teardown_run_0 (0)',
                '2 4',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_run_1 (0)',
                "$path/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\test_function (0, 1)',
                '4 2 8',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\test_function (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\TestClass::test (0, 1)',
                '4 2',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\TestClass::test (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\teardown_run_1 (0)',
                '4 2',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\teardown_run_0',
                '2 4',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\setup_run_1',
                "$path",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_run_0 (1)',
                "$path/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\test_function (1, 0)',
                '8 16 128',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\test_function (1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\TestClass::test (1, 0)',
                '8 16',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\TestClass::test (1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\teardown_run_0 (1)',
                '8 16',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_run_1 (1)',
                "$path/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\test_function (1, 1)',
                '16 8 128',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\test_function (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\TestClass::test (1, 1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_PASS,
                'subdir_params\\subdir\\TestClass::test (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\teardown_run_1 (1)',
                '16 8',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\teardown_run_1',
                '8 16',
            ),
        );
        $this->assert_log($expected);
    }


    public function test_runs_parameterized_target() {
        $this->path .= 'parameterized_target';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\setup_run_0',
                $path,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_run_0 (0)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_0 (0, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_0 (0, 0)',
                '1',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_1 (0, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_1 (0, 0)',
                '4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_run0 (0)',
                '1',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_run_1 (0)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_0 (0, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_0 (0, 1)',
                '3',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_1 (0, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_1 (0, 1)',
                '4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_run1 (0)',
                '3',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\teardown_run_0',
                '1',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\setup_run1',
                "$path",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_run_0 (1)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_0 (1, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_0 (1, 0)',
                '2',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_1 (1, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_1 (1, 0)',
                '4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_run0 (1)',
                '2',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_run_1 (1)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_0 (1, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_0 (1, 1)',
                '3',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_run_1 (1, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_run_1 (1, 1)',
                '4',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_run1 (1)',
                '3',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\teardown_run1',
                '2',
            ),
        );
        $this->assert_log($expected);
    }
}



function dir_setup()
{
    echo 'dir_setup';
}

function dir_teardown()
{
    echo 'dir_teardown';
}

function dir1_setup()
{
    echo 'dir1_setup';
}

function dir1_teardown()
{
    echo 'dir1_teardown';
}

function dir2_setup()
{
    echo 'dir2_setup';
}

function dir2_teardown()
{
    echo 'dir2_teardown';
}

function dir3_setup()
{
    echo 'dir3_setup';
}

function dir3_teardown()
{
    echo 'dir3_teardown';
}

function subdir_setup()
{
    echo 'subdir_setup';
}

function subdir_teardown()
{
    echo 'subdir_teardown';
}

function dir_test1() {}
function dir_test2() {}

function dir1_test1() {}
function dir1_test2() {}
function dir1_test3() {}
function dir1_test4() {}
function dir1_test5() {}
function dir1_test6() {}

function dir2_test1() {}
function dir2_test2() {}

function subdir_test1() {}
function subdir_test2() {}
function subdir_test3() {}
function subdir_test4() {}

function dir3_test1() {}
function dir3_test2() {}
