<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


class TestDirectories {
    private $logger;
    private $path;


    public function setup() {
        $this->logger = new strangetest\BasicLogger(strangetest\LOG_ALL);
        $this->path = __DIR__ . '/sample_files/directories/';
    }


    // helper assertions

    private function assert_events($directories, strangetest\Context $context) {
        list($root, $targets) = strangetest\process_user_targets((array)$this->path, $errors);
        strangetest\assert_falsy($errors);

        strangetest\discover_tests(
            new strangetest\BufferingLogger($this->logger),
            $root,
            $targets
        );

        $root = null;
        $current = null;
        $directory = null;

        foreach ($this->logger->get_log()->get_events() as $event) {
            list($type, $source, $reason) = $event;
            // #BC(5.6): Check if reason is instance of Exception
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
            $context->assert_falsy($directory['setup'], "setup for $path");
            $context->assert_falsy($directory['teardown'], "teardown for $path");
            $context->assert_falsy($directory['dirs'], "dirs for $path");
            $context->assert_falsy($directory['events'], "events for $path");
        }
    }


    private function assert_log($expected) {
        list($root, $targets) = strangetest\process_user_targets((array)$this->path, $errors);
        strangetest\assert_falsy($errors);

        strangetest\discover_tests(
            new strangetest\BufferingLogger($this->logger),
            $root, $targets
        );

        $actual = $this->logger->get_log()->get_events();
        foreach ($actual as $i => $event) {
            list($type, $source, $reason) = $event;
            // #BC(5.6): Check if reason is instance of Exception
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

    public function test_discover_directory(strangetest\Context $context) {
        $this->path .= 'discover_directory';
        $path = $this->path;
        $this->assert_events(
            array(
                $path => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'dirs' => array("$path/TEST_DIR1", "$path/test_dir2"),
                    'events' => array(
                        "$path/test.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$path/test.php"
                        ),
                    ),
                ),

                "$path/TEST_DIR1" => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'parent' => $path,
                    'events' => array(
                        "$path/TEST_DIR1/TEST1.PHP" => array(
                            strangetest\EVENT_OUTPUT,
                            "$path/TEST_DIR1/TEST1.PHP",
                        ),
                        "$path/TEST_DIR1/TEST2.PHP" => array(
                            strangetest\EVENT_OUTPUT,
                            "$path/TEST_DIR1/TEST2.PHP",
                        ),
                    ),
                ),

                "$path/test_dir2" => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'parent' => $path,
                    'events' => array(
                        "$path/test_dir2/test1.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$path/test_dir2/test1.php",
                        ),
                        "$path/test_dir2/test2.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$path/test_dir2/test2.php",
                        ),
                    ),
                ),
            ),
            $context
        );
    }


    public function test_does_not_find_conditionally_nondeclared_tests(strangetest\Context $context) {
        $this->path .= 'conditional';

        $this->assert_events(
            array(
                $this->path => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'events' => array(
                        'condition\\TestA::test' => array(strangetest\EVENT_PASS , null),
                        'condition\\TestB::test' => array(strangetest\EVENT_PASS , null),
                    ),
                ),
            ),
            $context
        );
    }


    public function test_individual_paths(strangetest\Context $context) {
        $root = $this->path . 'test_individual_paths';
        $this->path = array(
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        );

        $this->assert_events(
            array(
                $root => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'dirs' => array("$root/test_dir1", "$root/test_dir2"),
                ),

                "$root/test_dir1" => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'parent' => $root,
                    'events' => array(
                        "$root/test_dir1/test2.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$root/test_dir1/test2.php",
                        ),
                        "$root/test_dir1/test3.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$root/test_dir1/test3.php",
                        ),
                    ),
                ),

                "$root/test_dir2" => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'parent' => $root,
                    'dirs' => array("$root/test_dir2/test_subdir"),
                ),

                "$root/test_dir2/test_subdir" => array(
                    'setup' => 1,
                    'teardown' => 1,
                    'parent' => "$root/test_dir2",
                    'events' => array(
                        "$root/test_dir2/test_subdir/test1.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$root/test_dir2/test_subdir/test1.php",
                        ),
                        "$root/test_dir2/test_subdir/test2.php" => array(
                            strangetest\EVENT_OUTPUT,
                            "$root/test_dir2/test_subdir/test2.php",
                        ),
                    ),
                ),
            ),
            $context
        );
    }


    public function test_handles_error_in_setup_file() {
        $this->path .= 'setup_error';

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $expected = array(
            array(
                strangetest\EVENT_ERROR,
                "{$this->path}/setup.php",
                'Skip me',
            ),
        );
        $this->assert_log($expected);
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
                        // #BC(7.0): Check format of expected error message
                        version_compare(PHP_VERSION, '7.1', '<')
                            ? 'Missing argument 2 for TestInsufficientArguments::__construct()'
                            : 'Too few arguments to function TestInsufficientArguments::__construct()',
                    ),
                ),
            ),
        );
        $this->assert_events($expected, $context);
    }


    public function test_reports_error_for_multiple_directory_fixtures() {
        $this->path .= 'multiple_fixtures';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_ERROR,
                "$path/setup.php",
                "Multiple conflicting fixture functions found:\n    1) setup_directory_multiple_fixtures\n    2) SetupDirectoryMultipleFixtures",
            ),
            array(
                strangetest\EVENT_ERROR,
                "$path/setup.php",
                "Multiple conflicting fixture functions found:\n    1) teardown_directory_multiple_fixtures\n    2) TeardownDirectoryMultipleFixtures",
            ),
        );
        $this->assert_log($expected);
    }


    public function test_runs_directory_tests_once_per_arg_list() {
        $this->path .= 'dir_params';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_runs_for_directory',
                $path,
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_directory (0)',
                '2 4',
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
                'dir_params\\teardown_runs_for_file (0)',
                '2 4 4 2',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_directory (0)',
                '2 4',
            ),



            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\setup_directory (1)',
                '8 16',
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
                'dir_params\\teardown_runs_for_file (1)',
                '8 16 16 8',
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_directory (1)',
                '8 16',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'dir_params\\teardown_runs_for_directory',
                $path,
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
                'subdir_params\\setup_runs_for_directory',
                $path,
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_runs_for_directory (0)',
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
                'subdir_params\\subdir\\teardown_runs_for_directory (0)',
                '2 4 4 2',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\subdir\\setup_runs_for_directory (1)',
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
                'subdir_params\\subdir\\teardown_runs_for_directory (1)',
                '8 16 16 8',
            ),

            array(
                strangetest\EVENT_OUTPUT,
                'subdir_params\\teardown_runs_for_directory',
                $path,
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
                'param_target\\setup_runs_for_directory',
                $path,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_runs_for_directory (0)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_runs_for_directory (0, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_runs_for_directory (0, 0)',
                "$path/test_dir/test_subdir",
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_runs_for_directory (0, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (0, 1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_runs_for_directory (0, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_runs_for_directory (0)',
                "$path/test_dir",
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\setup_runs_for_directory (1)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_runs_for_directory (1, 0)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_runs_for_directory (1, 0)',
                "$path/test_dir/test_subdir",
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\setup_runs_for_directory (1, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'param_target\\dir\\subdir\\test (1, 1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\subdir\\teardown_runs_for_directory (1, 1)',
                "$path/test_dir/test_subdir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\dir\\teardown_runs_for_directory (1)',
                "$path/test_dir",
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'param_target\\teardown_runs_for_directory',
                $path,
            ),
        );
        $this->assert_log($expected);
    }


    function test_generators() {
        // BC(5.4): Check if generators are supported
        if (\version_compare(\PHP_VERSION, '5.5', '<')) {
            strangetest\skip('PHP 5.5 introduced generators');
        }


        $this->path .= 'generator';
        $path = $this->path;

        $expected = array(
            array(
                strangetest\EVENT_PASS,
                'generator\\test_one (0, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'generator\\test_two (0, 0)',
                null,
            ),

            array(
                strangetest\EVENT_PASS,
                'generator\\test_one (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'generator\\test_two (0, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'generator\\teardown_runs_for_file (0)',
                'generator\\teardown_runs_for_file',
            ),


            array(
                strangetest\EVENT_PASS,
                'generator\\test_one (1, 0)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'generator\\test_two (1, 0)',
                null,
            ),

            array(
                strangetest\EVENT_PASS,
                'generator\\test_one (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_PASS,
                'generator\\test_two (1, 1)',
                null,
            ),
            array(
                strangetest\EVENT_OUTPUT,
                'generator\\teardown_runs_for_file (1)',
                'generator\\teardown_runs_for_file',
            ),


            array(
                strangetest\EVENT_OUTPUT,
                'generator\\teardown_runs_for_directory',
                'generator\\teardown_runs_for_directory',
            ),
        );
        $this->assert_log($expected);
    }
}
