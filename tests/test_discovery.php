<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

final class DebugLogger implements easytest\Logger {
    public $log = [];

    public function log_pass($source) {
        $this->log[] = [easytest\LOG_EVENT_PASS, $source, null];
    }

    public function log_failure($source, $reason) {
        $this->log[] = [easytest\LOG_EVENT_FAIL, $source, $reason];
    }

    public function log_error($source, $reason) {
        $this->log[] = [easytest\LOG_EVENT_ERROR, $source, $reason];
    }

    public function log_skip($source, $reason) {
        $this->log[] = [easytest\LOG_EVENT_SKIP, $source, $reason];
    }

    public function log_output($source, $reason, $during_error) {
        $this->log[] = [easytest\LOG_EVENT_OUTPUT, $source, $reason];
    }

    public function log_debug($source, $reason) {
        $this->log[] = [easytest\LOG_EVENT_DEBUG, $source, $reason];
    }

}



class TestDiscovery {
    private $logger;
    private $path;


    public function setup() {
        $this->logger = new DebugLogger();
        $this->path = __DIR__ . '/discovery_files/';
    }


    // helper assertions

    private function assert_events($directories) {
        $root = null;
        $current = null;
        $directory = null;

        foreach ($this->logger->log as $event) {
            list($type, $source, $reason) = $event;
            // #BC(5.6): Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }

            switch ($type) {

            case easytest\LOG_EVENT_PASS:
            case easytest\LOG_EVENT_FAIL:
            case easytest\LOG_EVENT_ERROR:
            case easytest\LOG_EVENT_SKIP:
            case easytest\LOG_EVENT_OUTPUT:
                if (!$current) {
                    easytest\fail("Got event $type but we never entered a directory\nsource: $source");
                }

                if (isset($directory['tests'][$source])) {
                    $expected = $directory['tests'][$source];
                    if ($reason !== $expected[1]) {
                        $reason = \substr($reason, 0, \strlen($expected[1]));
                    }
                    easytest\assert_identical(
                        $expected,
                        [$type, $reason],
                        "Unexpected event\nsource: $source\ndirectory: $current"
                    );
                    unset($directory['tests'][$source]);
                }
                else {
                    $message = <<<MESSAGE
Unexpected event $type
source: $source
reason: $reason
current: $current
expected: %s
MESSAGE;
                    easytest\fail(
                        \sprintf($message, \print_r($directory['tests'], true))
                    );
                }
                break;

            case easytest\LOG_EVENT_DEBUG:
                switch ($reason) {
                case easytest\DEBUG_DIRECTORY_ENTER:
                    if ($current && !isset($directory['dirs'][$source])) {
                        $message = <<<MESSAGE
Wanted to descend into unexpected child directory
current: $current
child:   $source
MESSAGE;
                        easytest\fail($message);
                    }

                    if ($current) {
                        $directories[$current] = $directory;
                    }
                    $directory = $directories[$source];
                    $current = $source;
                    if (!$root) {
                        $root = $source;
                    }
                    if (!isset($directory['initialized'])) {
                        $directory['initialized'] = true;
                        if (isset($directory['fixtures'][0])) {
                            $directory['setup'] = $directory['fixtures'][0];
                            $directory[$directory['setup']] = 0;
                        }
                        if (isset($directory['fixtures'][1])) {
                            $directory['teardown'] = $directory['fixtures'][1];
                            $directory[$directory['teardown']] = 0;
                        }
                        if (!isset($directory['dirs'])) {
                            $directory['dirs'] = [];
                        }
                        else {
                            $dirs = $directory['dirs'];
                            $directory['dirs'] = [];
                            foreach ($dirs as $dir) {
                                $directory['dirs'][$dir] = true;
                            }
                        }
                        if (!isset($directory['tests'])) {
                            $directory['tests'] = [];
                        }
                    }
                    break;

                case easytest\DEBUG_DIRECTORY_EXIT:
                    if (!$current) {
                        $message = <<<MESSAGE
Wanted to ascend into a parent directory but we never entered a directory.
parent: $source
MESSAGE;
                        easytest\fail($message);
                    }
                    easytest\assert_identical(
                        $current, $source,
                        "We're exiting a directory we're not currently in?"
                    );
                    $directories[$current] = $directory;

                    if ($root === $current) {
                        $current = null;
                        break;
                    }

                    $parent = $directory['parent'];
                    if (!isset($directories[$parent])) {
                        $message = <<<MESSAGE
Wanted to ascend into an invalid parent directory.
current: $current
parent:  $parent
MESSAGE;
                        easytest\fail($message);
                    }

                    $directory = $directories[$parent];
                    if (!($directories[$current]['dirs'] || $directories[$current]['tests'])) {
                        unset($directory['dirs'][$current]);
                    }
                    $current = $parent;
                    break;

                case easytest\DEBUG_DIRECTORY_SETUP:
                    if (!isset($directory['setup'])) {
                        $message = <<<MESSAGE
Wanted to run invalid directory fixture "$source" in
$current
MESSAGE;
                        easytest\fail($message);
                    }
                    if ($directory['setup'] !== $source) {
                        $message = <<<MESSAGE
Expected fixture "{$directory['setup']}" but tried to run "$source" in
$current
MESSAGE;
                        easytest\fail($message);
                    }
                    ++$directory[$directory['setup']];
                    break;

                case easytest\DEBUG_DIRECTORY_TEARDOWN:
                    if (!isset($directory['teardown'])) {
                        $message = <<<MESSAGE
Wanted to run invalid directory fixture "$source" in
$current
MESSAGE;
                        easytest\fail($message);
                    }
                    if ($directory['teardown'] !== $source) {
                        $message = <<<MESSAGE
Expected fixture "{$directory['teardown']}" but tried to run "$source" in
$current
MESSAGE;
                        easytest\fail($message);
                    }
                    ++$directory[$directory['teardown']];
                    break;
                }
                break;
            }
        }

        foreach ($directories as $path => $directory) {
            if (isset($directory['setup'])) {
                if (!$directory[$directory['setup']]) {
                    easytest\fail("Never ran {$directory['setup']} for\n$path");
                }
            }
            if (isset($directory['teardown'])) {
                if (!$directory[$directory['teardown']]) {
                    easytest\fail("Never ran {$directory['teardown']} for\n$path\nevents: " . print_r($this->logger->log, true));
                }
            }
            if (isset($directory['setup'], $directory['teardown'])) {
                easytest\assert_identical(
                    $directory[$directory['setup']],
                    $directory[$directory['teardown']],
                    "fixtures were run an unequal amount of times in\n$path"
                );
            }
            easytest\assert_identical(
                [], $directory['dirs'],
                "not all tests in subdirectories were run in\n$path"
            );
            easytest\assert_identical(
                [], $directory['tests'],
                "not all tests were run in\n$path"
            );
        }
    }


    public function assert_log($expected) {
        $actual = $this->logger->log;
        foreach ($actual as $i => $event) {
            list($type, $source, $reason) = $event;
            // #BC(5.6): Check if reason is instance of Exception
            if ($reason instanceof \Throwable
                || $reason instanceof \Exception)
            {
                $reason = $reason->getMessage();
            }

            $actual[$i] = [$type, $source, $reason];
        }
        easytest\assert_identical($expected, $actual);
    }


    // tests

    public function test_discover_file() {
        $path = $this->path . 'TestMyFile.php';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            $this->path => [
                'tests' => [
                    $path => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'class TestTextBefore {}\n\n\nclass TestTestAfter {}\n'"
                    ],
                    'Test::test_me' => [easytest\LOG_EVENT_PASS, null],
                    'test2::testOne' => [easytest\LOG_EVENT_PASS, null],
                    'test2::testTwo' => [easytest\LOG_EVENT_PASS, null],
                    'test2::testThree' => [easytest\LOG_EVENT_PASS, null],
                    'TestThree::test2' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_discover_directory() {
        $path = $this->path . 'discover_directory';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'dirs' => ["$path/TEST_DIR1/", "$path/test_dir2/"],
                'tests' => [
                    "$path/test.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/test.php'"
                    ],
                ],
            ],

            "$path/TEST_DIR1/" => [
                'fixtures' => [
                    'SetupDirectoryTEST1',
                    'TearDownDirectoryTEST1',
                ],
                'parent' => "$path/",
                'tests' => [
                    "$path/TEST_DIR1/TEST1.PHP" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/TEST_DIR1/TEST1.PHP'",
                    ],
                    "$path/TEST_DIR1/TEST2.PHP" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/TEST_DIR1/TEST2.PHP'",
                    ],
                ],
            ],

            "$path/test_dir2/" => [
                'fixtures' => [
                    'setup_directory_test2',
                    'teardown_directory_test2',
                ],
                'parent' => "$path/",
                'tests' => [
                    "$path/test_dir2/test1.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/test_dir2/test1.php'",
                    ],
                    "$path/test_dir2/test2.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/test_dir2/test2.php'",
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_individual_paths() {
        $root = $this->path . 'test_individual_paths';
        $paths = [
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        ];
        easytest\discover_tests($this->logger, $paths);

        $expected = [
            "$root/" => [
                'fixtures' => [
                    'setup_directory_individual_paths',
                    'teardown_directory_individual_paths',
                ],
                'dirs' => ["$root/test_dir1/", "$root/test_dir2/"],
            ],

            "$root/test_dir1/" => [
                'fixtures' => [
                    'setup_directory_individual_paths_dir1',
                    'teardown_directory_individual_paths_dir1',
                ],
                'parent' => "$root/",
                'tests' => [
                    "$root/test_dir1/test2.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$root/test_dir1/test2.php'",
                    ],
                    "$root/test_dir1/test3.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$root/test_dir1/test3.php'",
                    ],
                ],
            ],

            "$root/test_dir2/" => [
                'fixtures' => [
                    'setup_directory_individual_paths_dir2',
                    'teardown_directory_individual_paths_dir2',
                ],
                'parent' => "$root/",
                'dirs' => ["$root/test_dir2/test_subdir/"],
            ],

            "$root/test_dir2/test_subdir/" => [
                'fixtures' => [
                    'setup_directory_individual_paths_dir2_subdir',
                    'teardown_directory_individual_paths_dir2_subdir',
                ],
                'parent' => "$root/test_dir2/",
                'tests' => [
                    "$root/test_dir2/test_subdir/test1.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$root/test_dir2/test_subdir/test1.php'",
                    ],
                    "$root/test_dir2/test_subdir/test2.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$root/test_dir2/test_subdir/test2.php'",
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_nonexistent_path() {
        $path = $this->path . 'foobar.php';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            [
                easytest\LOG_EVENT_ERROR,
                $path,
                'No such file or directory'
            ],
        ];
        easytest\assert_identical($expected, $this->logger->log);
    }


    public function test_handles_error_in_directory_setup_file() {
        $path = $this->path . 'directory_setup_error';
        easytest\discover_tests($this->logger,[$path]);

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $expected = [
            [
                easytest\LOG_EVENT_ERROR,
                "$path/setup.php",
                'Skip me',
            ],
        ];
        $expected = [
            "$path/" => [
                'tests' => [
                    "$path/setup.php" => [easytest\LOG_EVENT_ERROR, 'Skip me'],
                ],
            ],
        ];
        //$this->assert_events($expected);
        $expected = [
            [
                easytest\LOG_EVENT_ERROR,
                "$path/setup.php",
                'Skip me',
            ],
        ];
        $this->assert_log($expected);
    }


    public function test_handles_error_in_test_file() {
        $path = $this->path . 'test_file_error';
        easytest\discover_tests($this->logger,[$path]);

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $expected = [
            "$path/" => [
                'fixtures' => [
                    'setup_directory_test_file_error',
                    'teardown_directory_test_file_error',
                ],
                'tests' => [
                    "$path/test1.php" => [easytest\LOG_EVENT_ERROR, 'An error happened'],
                    "test_file_error_two::test" => [easytest\LOG_EVENT_PASS, null],
                    "$path/test3.php" => [easytest\LOG_EVENT_ERROR, 'Skip me'],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_setup_error() {
        $path = $this->path . 'setup_error';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'tests' => [
                    'setup_directory_setup_error' => [
                        easytest\LOG_EVENT_ERROR,
                        'An error happened',
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_teardown_error() {
        $path = $this->path . 'teardown_error';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'fixtures' =>  ['setup_directory_teardown_error'],
                'tests' => [
                    'test_teardown_error::test' => [easytest\LOG_EVENT_PASS, null],
                    'teardown_directory_teardown_error' => [
                        easytest\LOG_EVENT_ERROR,
                        'An error happened',
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_skip_in_teardown() {
        $path = $this->path . 'skip_in_teardown';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'tests' => [
                    'test_skip_in_teardown::test' => [easytest\LOG_EVENT_PASS, null],
                    'teardown_directory_skip_in_teardown' => [
                        easytest\LOG_EVENT_ERROR,
                        'Skip me',
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_passes_arguments_to_tests_and_subdirectories() {
        $path = $this->path . 'argument_passing';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'fixtures' => [
                    'setup_directory_arguments',
                    'teardown_directory_arguments',
                ],
                'dirs' => ["$path/test_subdir1/", "$path/test_subdir2/"],
                'tests' => [
                    'TestArgumentsOne::test' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],

            "$path/test_subdir1/" => [
                'fixtures' => ['setup_directory_arguments_subdir1'],
                'parent' => "$path/",
                'tests' => [
                    'TestArgumentsTwo::test' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],

            "$path/test_subdir2/" => [
                'parent' => "$path/",
                'tests' => [
                    'TestArgumentsThree::test' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_errors_on_insufficient_arguments() {
        $path = $this->path . 'insufficient_arguments';
        easytest\discover_tests($this->logger,[$path]);


        $expected = [
            "$path/" => [
                'fixtures' => [
                    'setup_directory_insufficient_arguments',
                    'teardown_directory_insufficient_arguments',
                ],
                'tests' => [
                    'TestInsufficientArguments' => [
                        easytest\LOG_EVENT_ERROR,
                        // #BC(7.0): Check format of expected error message
                        version_compare(PHP_VERSION, '7.1', '<')
                            ? 'Missing argument 2 for TestInsufficientArguments::__construct()'
                            : 'Too few arguments to function TestInsufficientArguments::__construct()',
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_namespaces() {
        $path = $this->path . 'namespaces';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'tests' => [
                    'ns1\\ns1\\TestNamespace::test' => [easytest\LOG_EVENT_PASS, null],
                    'ns1\\ns2\\TestNamespace::test' => [easytest\LOG_EVENT_PASS, null],
                    'TestNamespace::test' => [easytest\LOG_EVENT_PASS, null],
                    'ns2\\TestNamespace::test' => [easytest\LOG_EVENT_PASS, null],
                    'ns3\\TestNamespace::test' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],
        ];
        $this->assert_events($expected);
    }

    public function test_output_buffering() {
        $path = $this->path . 'output_buffering';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'fixtures' => [
                    'setup_directory_output_buffering',
                    'teardown_directory_output_buffering',
                ],
                'tests' => [
                    'setup_directory_output_buffering' => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'setup_directory_output_buffering'",
                    ],
                    "$path/test.php" => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'$path/test.php'"
                    ],
                    'test_output_buffering' => [
                        easytest\LOG_EVENT_OUTPUT,
                        '\'__construct\''
                    ],
                    'test_output_buffering::test' => [
                        easytest\LOG_EVENT_PASS,
                        null
                    ],
                    'teardown_directory_output_buffering' => [
                        easytest\LOG_EVENT_OUTPUT,
                        "'teardown_directory_output_buffering'",
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_does_not_discover_anonymous_classes() {
        // #BC(5.6): Check if anonymous classes are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced anonymous classes');
        }

        $path = $this->path . 'TestAnonymousClass.php';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "{$this->path}" => [
                'tests' => [
                    'TestAnonymousClass::test' => [
                        easytest\LOG_EVENT_PASS,
                        null
                    ],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_does_not_find_conditionally_nondeclared_tests() {
        $path = $this->path . 'conditional_declaration';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            "$path/" => [
                'tests' => [
                    'conditional\\TestA::test' => [easytest\LOG_EVENT_PASS, null],
                    'conditional\\TestB::test' => [easytest\LOG_EVENT_PASS, null],
                ],
            ],
        ];
        $this->assert_events($expected);
    }


    public function test_reports_error_for_multiple_directory_fixtures() {
        $path = $this->path . 'multiple_fixtures';
        easytest\discover_tests($this->logger,[$path]);

        $expected = [
            [
                easytest\LOG_EVENT_ERROR,
                "$path/setup.php",
                "Multiple setup fixtures found:\n\tsetup_directory_multiple_fixtures\n\tSetupDirectoryMultipleFixtures",
            ],
            [
                easytest\LOG_EVENT_ERROR,
                "$path/setup.php",
                "Multiple teardown fixtures found:\n\tteardown_directory_multiple_fixtures\n\tTeardownDirectoryMultipleFixtures",
            ],
        ];
        $this->assert_log($expected);
    }
}
