<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestDiscovery {
    private $logger;
    private $path;

    private $blank_log = [
        easytest\LOG_EVENT_PASS => 0,
        easytest\LOG_EVENT_FAIL => 0,
        easytest\LOG_EVENT_ERROR => 0,
        easytest\LOG_EVENT_SKIP => 0,
        easytest\LOG_EVENT_OUTPUT => 0,
        'events' => [],
    ];

    public function setup() {
        $this->logger = new easytest\BufferingLogger(
            new easytest\BasicLogger(true)
        );
        $this->path = __DIR__ . '/discovery_files/';
    }


    // helper assertions

    private function assert_counts(array $counts, easytest\Log $log) {
        $expected = $this->blank_log;
        unset($expected['events']);
        foreach ($counts as $i => $count) {
            $expected[$i] = $count;
        }

        $actual = [
            easytest\LOG_EVENT_PASS => $log->pass_count(),
            easytest\LOG_EVENT_FAIL => $log->failure_count(),
            easytest\LOG_EVENT_ERROR => $log->error_count(),
            easytest\LOG_EVENT_SKIP => $log->skip_count(),
            easytest\LOG_EVENT_OUTPUT => $log->output_count(),
        ];
        easytest\assert_identical(
            $expected, $actual,
            sprintf("Unexpected events:\n%s", print_r($log->get_events(), true))
        );
    }

    private function assert_run($directories, $events, $root = null) {
        $current = null;
        $directory = null;
        if ($root) {
            $directory = &$directories[$root];
            $current = $root;
        }

        foreach ($events as $event) {
            list($type, $source, $reason) = $event;
            if (preg_match('~^setup_?directory~i', $source)) {
                $new = trim($reason, "\\'");
                if ($current && !isset($directory['dirs'][$new])) {
                    $message = <<<MESSAGE
Wanted to descend into a child directory that either doesn't exist or has no tests.
current: $current
child:   $new
MESSAGE;
                    easytest\fail($message);
                }
                $directory = &$directories[$new];
                $current = $new;
                if (!$root) {
                    $root = $new;
                }
                ++$directory['setup'];
            }

            elseif (preg_match('~^teardown_?directory~i', $source)) {
                $new = $directory['parent'];
                if ($current !== $root && !isset($directories[$new])) {
                    $message = <<<MESSAGE
Wanted to ascend into an invalid parent directory.
current: $current
parent:  $new
MESSAGE;
                    easytest\fail($message);
                }
                ++$directory['teardown'];
                $directory = &$directories[$new];
                if (!($directories[$current]['dirs'] || $directories[$current]['tests'])) {
                    unset($directory['dirs'][$current]);
                }
                $current = $new;
            }

            else {
                if (isset($directory['tests'][$source])) {
                    unset($directory['tests'][$source]);
                }
                else {
                    $message = <<<MESSAGE
Wanted to run a non-existent test
current directory: $current
test:  $new
available tests:
    %s
MESSAGE;
                    $message = sprintf(
                        $message,
                        implode("\n    ", array_keys($directory['tests']))
                    );
                    easytest\fail($message);
                }
            }
        }

        foreach ($directories as $path => $directory) {
            easytest\assert_identical(
                $directory['setup'], $directory['teardown'],
                "fixtures were run an unequal amount of times in\n$path"
            );
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

    private function assert_report($report) {
        $expected = $this->blank_log;
        foreach ($report as $i => $entry) {
            $expected[$i] = $entry;
        }

        $log = $this->logger->get_log();
        $actual = [
            easytest\LOG_EVENT_PASS => $log->pass_count(),
            easytest\LOG_EVENT_FAIL => $log->failure_count(),
            easytest\LOG_EVENT_ERROR => $log->error_count(),
            easytest\LOG_EVENT_SKIP => $log->skip_count(),
            easytest\LOG_EVENT_OUTPUT => $log->output_count(),
            'events' => $log->get_events(),
        ];
        for ($i = 0, $c = count($actual['events']); $i < $c; ++$i) {
            list($type, $source, $reason) = $actual['events'][$i];
            switch ($type) {
                case easytest\LOG_EVENT_ERROR:
                    // #BC(5.6) Check if $reason is instanceof Exception
                    if ($reason instanceof Throwable
                        || $reason instanceof Exception)
                    {
                        $reason = $reason->getMessage();
                        $actual['events'][$i][2] = $reason;
                    }
                    break;

                case easytest\LOG_EVENT_FAIL:
                    // #BC(5.6) Check if $reason is instanceof Failure
                    if ($reason instanceof AssertionError
                        || $reason instanceof easytest\Failure)
                    {
                        $reason = $reason->getMessage();
                        $actual['events'][$i][2] = $reason;
                    }
                    break;

                case easytest\LOG_EVENT_SKIP:
                    $actual['events'][$i][2] = $reason->getMessage();
                    break;
            }
        }

        easytest\assert_identical($expected, $actual);
    }


    // tests

    public function test_discover_file() {
        $path = $this->path . 'TestMyFile.php';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_PASS => 5,
            easytest\LOG_EVENT_OUTPUT => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    $path,
                    "'class TestTextBefore {}\n\n\nclass TestTestAfter {}\n'"
                ],
            ],
        ]);
    }

    public function test_discover_directory() {
        $path = $this->path . 'discover_directory';

        easytest\discover_tests($this->logger,[$path]);

        $log = $this->logger->get_log();
        $this->assert_counts([easytest\LOG_EVENT_OUTPUT => 9], $log);

        $expected = [
            $path => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => null,
                'dirs' => [
                    "$path/TEST_DIR1" => true,
                    "$path/test_dir2" => true,
                ],
                'tests' => ["$path/test.php" => true],
            ],

            "$path/TEST_DIR1" => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => $path,
                'dirs' => [],
                'tests' => [
                    "$path/TEST_DIR1/TEST1.PHP" => true,
                    "$path/TEST_DIR1/TEST2.PHP" => true,
                ],
            ],

            "$path/test_dir2" => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => $path,
                'dirs' => [],
                'tests' => [
                    "$path/test_dir2/test1.php" => true,
                    "$path/test_dir2/test2.php" => true,
                ],
            ],
        ];
        $this->assert_run($expected, $log->get_events(), $path);
    }

    public function test_individual_paths() {
        $root = $this->path . 'test_individual_paths';
        $paths = [
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        ];
        easytest\discover_tests($this->logger,$paths);

        $log = $this->logger->get_log();
        // Because EasyTest (currently) ascends back up $root after finishing
        // each individual path, we'll have more output than if EasyTest
        // traversed directly to the next individual path
        $this->assert_counts([easytest\LOG_EVENT_OUTPUT => 18], $log);

        $expected = [
            $root => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => null,
                'dirs' => [
                    "$root/test_dir1" => true,
                    "$root/test_dir2" => true,
                ],
                'tests' => [],
            ],

            "$root/test_dir1" => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => $root,
                'dirs' => [],
                'tests' => [
                    "$root/test_dir1/test2.php" => true,
                    "$root/test_dir1/test3.php" => true,
                ],
            ],

            "$root/test_dir2" => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => $root,
                'dirs' => [
                    "$root/test_dir2/test_subdir" => true,
                ],
                'tests' => [],
            ],

            "$root/test_dir2/test_subdir" => [
                'setup' => 0,
                'teardown' => 0,
                'parent' => "$root/test_dir2",
                'dirs' => [],
                'tests' => [
                    "$root/test_dir2/test_subdir/test1.php" => true,
                    "$root/test_dir2/test_subdir/test2.php" => true,
                ],
            ],
        ];
        $this->assert_run($expected, $log->get_events());
    }

    public function test_nonexistent_path() {
        $path = $this->path . 'foobar.php';
        easytest\discover_tests($this->logger,[$path]);
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    $path,
                    'No such file or directory'
                ]
            ],
        ]);
    }


    public function test_handles_error_in_directory_setup_file() {
        $path = $this->path . 'directory_setup_error';
        easytest\discover_tests($this->logger,[$path]);

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    "$path/setup.php",
                    'Skip me'
                ]
            ]
        ]);
    }


    public function test_handles_error_in_test_file() {
        $path = $this->path . 'test_file_error';
        easytest\discover_tests($this->logger,[$path]);

        // Note that any exception thrown while including a file, including a
        // skip, is reported as an error
        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 2,
            easytest\LOG_EVENT_OUTPUT => 2,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_test_file_error',
                    "'setup_directory_test_file_error'",
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    "$path/test1.php",
                    'An error happened'
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    "$path/test3.php",
                    'Skip me',
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_test_file_error',
                    "'teardown_directory_test_file_error'",
                ],
            ]
        ]);
    }

    public function test_setup_error() {
        $path = $this->path . 'setup_error';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'setup_directory_setup_error',
                    'An error happened'
                ]
            ]
        ]);
    }

    public function test_teardown_error() {
        $path = $this->path . 'teardown_error';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_OUTPUT => 2,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_teardown_error',
                    "'setup_directory_teardown_error'",
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    'teardown_directory_teardown_error',
                    'An error happened'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_teardown_error',
                    "'teardown_directory_teardown_error'",
                ],
            ]
        ]);
    }


    public function test_skip_in_teardown() {
        $path = $this->path . 'skip_in_teardown';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_ERROR => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_ERROR,
                    'teardown_directory_skip_in_teardown',
                    'Skip me'
                ]
            ]
        ]);
    }


    public function test_passes_arguments_to_tests_and_subdirectories() {
        $path = $this->path . 'argument_passing';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_PASS => 3,
        ]);
    }


    public function test_errors_on_insufficient_arguments() {
        $path = $this->path . 'insufficient_arguments';
        easytest\discover_tests($this->logger,[$path]);

        $expected = $this->blank_log;
        $expected[easytest\LOG_EVENT_ERROR] = 1;
        $expected[easytest\LOG_EVENT_OUTPUT] = 1;
        $expected['events'] = [
            [
                easytest\LOG_EVENT_ERROR,
                'TestInsufficientArguments',
                // #BC(7.0): Check format of expected error message
                version_compare(PHP_VERSION, '7.1', '<')
                    ? 'Missing argument 2 for TestInsufficientArguments::__construct()'
                    : 'Too few arguments to function TestInsufficientArguments::__construct()',
            ],
            [
                easytest\LOG_EVENT_OUTPUT,
                'teardown_directory_insufficient_arguments',
                "'teardown_directory_insufficient_arguments'",
            ],
        ];

        $log = $this->logger->get_log();
        $actual = [
            easytest\LOG_EVENT_PASS => $log->pass_count(),
            easytest\LOG_EVENT_FAIL => $log->failure_count(),
            easytest\LOG_EVENT_ERROR => $log->error_count(),
            easytest\LOG_EVENT_SKIP => $log->skip_count(),
            easytest\LOG_EVENT_OUTPUT => $log->output_count(),
            'events' => $log->get_events(),
        ];
        for ($i = 0, $c = count($actual['events']); $i < $c; ++$i) {
            list($type, $source, $reason) = $actual['events'][$i];
            // #BC(5.6) Check if $reason is instanceof Exception
            if ($reason instanceof Throwable
                || $reason instanceof Exception) {
                $actual['events'][$i][2] = substr(
                    $reason->getMessage(), 0,
                    // #BC(7.0): Check format of expected error message
                    version_compare(PHP_VERSION, '7.1', '<') ? 63 : 70
                );
            }
        }

        easytest\assert_identical($expected, $actual);
    }

    public function test_namespaces() {
        $path = $this->path . 'namespaces';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([easytest\LOG_EVENT_PASS => 5]);
    }

    public function test_output_buffering() {
        $path = $this->path . 'output_buffering';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_PASS => 1,
            easytest\LOG_EVENT_OUTPUT => 4,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_output_buffering',
                    "'setup_directory_output_buffering'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test.php",
                    "'$path/test.php'"
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'test_output_buffering',
                    '\'__construct\''
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_output_buffering',
                    "'teardown_directory_output_buffering'",
                ],
            ],
        ]);
    }


    public function test_does_not_discover_anonymous_classes() {
        // #BC(5.6): Check if anonymous classes are supported
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced anonymous classes');
        }

        $path = $this->path . 'TestAnonymousClass.php';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([easytest\LOG_EVENT_PASS => 1]);

    }


    public function test_does_not_find_conditionally_nondeclared_tests() {
        $path = $this->path . 'conditional_declaration';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([easytest\LOG_EVENT_PASS => 2]);
    }


    public function test_reports_error_for_multiple_directory_fixtures() {
        $path = $this->path . 'multiple_fixtures';
        easytest\discover_tests($this->logger,[$path]);

        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 2,
            'events' => [
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
            ]
        ]);
    }
}
