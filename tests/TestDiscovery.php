<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestDiscovery implements easytest\IRunner {
    private $logger;
    private $context;
    private $discoverer;

    private $path;
    private $runner_log;

    private $blank_report = [
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
        $this->context = new easytest\Context();
        $this->context->log = [];
        $this->discoverer = new easytest\Discoverer(
            $this->logger,
            $this,
            $this->context,
            true
        );
        $this->path = __DIR__ . '/discovery_files/';
        $this->runner_log = [];
    }

    // implementation of runner interface

    public function run_test_case($object) {
        $this->runner_log[] = get_class($object);
    }


    // helper assertions

    private function assert_report($report) {
        $expected = $this->blank_report;
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
                    if ($reason instanceof Throwable
                        || $reason instanceof Exception)
                    {
                        $reason = $reason->getMessage();
                        $actual['events'][$i][2] = $reason;
                    }
                    break;

                case easytest\LOG_EVENT_FAIL:
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
        $path = $this->path . 'MyTestFile.php';

        $this->discoverer->discover_tests([$path]);

        $this->assert_report([
            easytest\LOG_EVENT_OUTPUT => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    $path,
                    "'class TestTextBefore {}\n\n\nclass TestTestAfter {}\n'"
                ],
            ],
        ]);

        $expected = ['Test', 'test2', 'Test3'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);
    }

    public function test_discover_directory() {
        $path = $this->path . 'discover_directory';

        $this->discoverer->discover_tests([$path]);

        $expected = [
            [easytest\LOG_EVENT_OUTPUT, "$path/test.php", "'$path/test.php'"],

            [easytest\LOG_EVENT_OUTPUT, "SetupDirectoryTEST1", "'SetupDirectoryTEST1'"],
            [easytest\LOG_EVENT_OUTPUT, "$path/TEST_DIR1/TEST1.PHP", "'$path/TEST_DIR1/TEST1.PHP'"],
            [easytest\LOG_EVENT_OUTPUT, "$path/TEST_DIR1/TEST2.PHP", "'$path/TEST_DIR1/TEST2.PHP'"],
            [easytest\LOG_EVENT_OUTPUT, "TearDownDirectoryTEST1", "'TearDownDirectoryTEST1'"],

            [easytest\LOG_EVENT_OUTPUT, "setup_directory_test2", "'setup_directory_test2'"],
            [easytest\LOG_EVENT_OUTPUT, "$path/test_dir2/test1.php", "'$path/test_dir2/test1.php'"],
            [easytest\LOG_EVENT_OUTPUT, "$path/test_dir2/test2.php", "'$path/test_dir2/test2.php'"],
            [easytest\LOG_EVENT_OUTPUT, "teardown_directory_test2", "'teardown_directory_test2'"],
        ];
        $actual = $this->logger->get_log();
        easytest\assert_identical($expected, $actual->get_events());
    }

    public function test_individual_paths() {
        $root = $this->path . 'test_individual_paths';
        $paths = [
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        ];
        $this->discoverer->discover_tests($paths);

        $this->assert_report([
            easytest\LOG_EVENT_OUTPUT => 18,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths',
                    "'setup_directory_individual_paths'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths_dir1',
                    "'setup_directory_individual_paths_dir1'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$root/test_dir1/test2.php",
                    "'$root/test_dir1/test2.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths_dir1',
                    "'teardown_directory_individual_paths_dir1'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths',
                    "'teardown_directory_individual_paths'",
                ],

                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths',
                    "'setup_directory_individual_paths'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths_dir1',
                    "'setup_directory_individual_paths_dir1'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$root/test_dir1/test3.php",
                    "'$root/test_dir1/test3.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths_dir1',
                    "'teardown_directory_individual_paths_dir1'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths',
                    "'teardown_directory_individual_paths'",
                ],

                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths',
                    "'setup_directory_individual_paths'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths_dir2',
                    "'setup_directory_individual_paths_dir2'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_individual_paths_dir2_subdir',
                    "'setup_directory_individual_paths_dir2_subdir'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$root/test_dir2/test_subdir/test1.php",
                    "'$root/test_dir2/test_subdir/test1.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$root/test_dir2/test_subdir/test2.php",
                    "'$root/test_dir2/test_subdir/test2.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths_dir2_subdir',
                    "'teardown_directory_individual_paths_dir2_subdir'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths_dir2',
                    "'teardown_directory_individual_paths_dir2'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_individual_paths',
                    "'teardown_directory_individual_paths'",
                ],
            ],
        ]);
    }

    public function test_nonexistent_path() {
        $path = $this->path . 'foobar.php';
        $this->discoverer->discover_tests([$path]);
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

    public function test_file_error() {
        $path = $this->path . 'file_error';
        $this->discoverer->discover_tests([$path]);

        $expected = ['test_file_error_two'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_OUTPUT => 2,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_file_error',
                    "'setup_directory_file_error'",
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    "$path/test1.php",
                    'An error happened'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_file_error',
                    "'teardown_directory_file_error'",
                ],
            ]
        ]);
    }

    public function test_setup_error() {
        $path = $this->path . 'setup_error';
        $this->discoverer->discover_tests([$path]);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

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
        $this->discoverer->discover_tests([$path]);

        $expected = ['test_teardown_error'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
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

    public function test_instantiation_error() {
        $path = $this->path . 'instantiation_error';
        $this->discoverer->discover_tests([$path]);

        $expected = ['test_instantiation_error_two'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_OUTPUT => 2,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_instantiation_error',
                    "'setup_directory_instantiation_error'",
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    'test_instantiation_error_one',
                    'An error happened'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_instantiation_error',
                    "'teardown_directory_instantiation_error'",
                ],
            ]
        ]);
    }

    public function test_skip() {
        $path = $this->path . 'skip';
        $this->discoverer->discover_tests([$path]);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
            easytest\LOG_EVENT_SKIP => 1,
            easytest\LOG_EVENT_OUTPUT => 3,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_skip',
                    "'setup_directory_skip'",
                ],
                [
                    easytest\LOG_EVENT_SKIP,
                    "$path/test.php",
                    'Skip me'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test.php",
                    "'$path/test.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_skip',
                    "'teardown_directory_skip'",
                ],
            ]
        ]);
    }

    public function test_skip_in_setup() {
        $path = $this->path . 'skip_in_setup';
        $this->discoverer->discover_tests([$path]);

        $expected = ["$path/setup.php"];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
            easytest\LOG_EVENT_SKIP => 1,
            'events' => [
                [
                    easytest\LOG_EVENT_SKIP,
                    "$path/setup.php",
                    'Skip me'
                ]
            ]
        ]);
    }

    public function test_skip_in_teardown() {
        $path = $this->path . 'skip_in_teardown';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_skip_in_teardown'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
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

    public function test_custom_loader() {
        $path = $this->path . 'custom_loader';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            easytest\LOG_EVENT_OUTPUT => 8,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/setup.php",
                    "'$path/setup.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test.php",
                    "'$path/test.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'TestLoaderOne',
                    "'$path/setup.php loading TestLoaderOne'",
                ],

                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test_subdir1/setup.php",
                    "'$path/test_subdir1/setup.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test_subdir1/test.php",
                    "'$path/test_subdir1/test.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'TestLoaderTwo',
                    "'$path/test_subdir1/setup.php loading TestLoaderTwo'",
                ],

                [
                    easytest\LOG_EVENT_OUTPUT,
                    "$path/test_subdir2/test.php",
                    "'$path/test_subdir2/test.php'",
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'TestLoaderThree',
                    "'$path/setup.php loading TestLoaderThree'",
                ],
            ]
        ];
        $this->assert_report($expected);

        $expected = ['TestLoaderOne', 'TestLoaderTwo', 'TestLoaderThree'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

    }

    public function test_error_if_loader_does_not_return_an_object() {
        $path = $this->path . 'bad_loader';
        $this->discoverer->discover_tests([$path]);

        $this->assert_report([
            easytest\LOG_EVENT_ERROR => 1,
            easytest\LOG_EVENT_OUTPUT => 2,
            'events' => [
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'setup_directory_bad_loader',
                    "'setup_directory_bad_loader'",
                ],
                [
                    easytest\LOG_EVENT_ERROR,
                    'TestBadLoader',
                    'Test loader did not return an object instance'
                ],
                [
                    easytest\LOG_EVENT_OUTPUT,
                    'teardown_directory_bad_loader',
                    "'teardown_directory_bad_loader'",
                ],
            ],
        ]);
    }

    public function test_namespaces() {
        $path = $this->path . 'namespaces';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/test_bracketed_namespaces.php",
            "$path/test_simple_namespaces.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = [
            /* Namespaced tests using bracketed syntax */
            'ns1\\ns1\\TestNamespace',
            'ns1\\ns2\\TestNamespace',
            'TestNamespace',

            /* Namespaced tests using "simple" syntax */
            'ns2\\TestNamespace',
            'ns3\\TestNamespace'
        ];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([]);
    }

    public function test_output_buffering() {
        $path = $this->path . 'output_buffering';
        $this->discoverer->discover_tests([$path]);

        $expected = [];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_output_buffering'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->assert_report([
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

        $path = $this->path . 'AnonymousClass.php';

        $this->discoverer->discover_tests([$path]);

        $this->assert_report([]);

        $expected = ['TestAnonymousClass'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);
    }


    public function test_does_not_find_conditionally_nondeclared_tests() {
        $path = $this->path . 'conditional_declaration';
        $this->discoverer->discover_tests([$path]);

        $this->assert_report([]);

        $expected = [
            'conditional\\TestA' => true,
            'conditional\\TestB' => true,
        ];
        foreach ($this->runner_log as $test) {
            assert(isset($expected[$test]), "Loaded unexpected test: $test");
            unset($expected[$test]);
        }
        easytest\assert_identical([], $expected);
    }
}
