<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestDiscovery implements easytest\IRunner {
    private $reporter;
    private $context;
    private $discoverer;

    private $path;
    private $runner_log;

    public function setup() {
        $this->reporter = new StubReporter();
        $this->context = new easytest\Context();
        $this->context->log = [];
        $this->discoverer = new easytest\Discoverer(
            $this->reporter,
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

    // tests

    public function test_discover_file() {
        $path = $this->path . 'MyTestFile.php';

        $this->discoverer->discover_tests([$path]);

        $this->reporter->assert_report([
            'Output' => [
                [$path, "class TestTextBefore {}\n\n\nclass TestTestAfter {}"],
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
            "$path/test.php",

            "$path/TEST_DIR1/SETUP.PHP",
            "$path/TEST_DIR1/TEST1.PHP",
            "$path/TEST_DIR1/TEST2.PHP",
            "$path/TEST_DIR1/TEARDOWN.PHP",

            "$path/test_dir2/setup.php",
            "$path/test_dir2/test1.php",
            "$path/test_dir2/test2.php",
            "$path/test_dir2/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);
    }

    public function test_individual_paths() {
        $root = $this->path . 'test_individual_paths';
        $paths = [
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        ];
        $this->discoverer->discover_tests($paths);

        $expected = [
            "$root/setup.php",
            "$root/test_dir1/setup.php",
            "$root/test_dir1/test2.php",
            "$root/test_dir1/teardown.php",
            "$root/teardown.php",

            "$root/setup.php",
            "$root/test_dir1/setup.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir1/teardown.php",
            "$root/teardown.php",

            "$root/setup.php",
            "$root/test_dir2/setup.php",
            "$root/test_dir2/test_subdir/setup.php",
            "$root/test_dir2/test_subdir/test1.php",
            "$root/test_dir2/test_subdir/test2.php",
            "$root/test_dir2/test_subdir/teardown.php",
            "$root/test_dir2/teardown.php",
            "$root/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);
    }

    public function test_nonexistent_path() {
        $path = $this->path . 'foobar.php';
        $this->discoverer->discover_tests([$path]);
        $this->reporter->assert_report([
            'Errors' => [[$path, 'No such file or directory']],
        ]);
    }

    public function test_file_error() {
        $path = $this->path . 'file_error';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test1.php",
            "$path/test2.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_file_error_two'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [["$path/test1.php", 'An error happened']]
        ]);
    }

    public function test_setup_error() {
        $path = $this->path . 'setup_error';
        $this->discoverer->discover_tests([$path]);

        $expected = ["$path/setup.php"];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [["$path/setup.php", 'An error happened']]
        ]);
    }

    public function test_teardown_error() {
        $path = $this->path . 'teardown_error';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_teardown_error'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [["$path/teardown.php", 'An error happened']]
        ]);
    }

    public function test_instantiation_error() {
        $path = $this->path . 'instantiation_error';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_instantiation_error_two'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [['test_instantiation_error_one', 'An error happened']]
        ]);
    }

    public function test_skip() {
        $path = $this->path . 'skip';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Skips' => [["$path/test.php", 'Skip me']]
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

        $this->reporter->assert_report([
            'Skips' => [["$path/setup.php", 'Skip me']]
        ]);
    }

    public function test_skip_in_teardown() {
        $path = $this->path . 'skip_in_teardown';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['test_skip_in_teardown'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [["$path/teardown.php", 'Skip me']]
        ]);
    }

    public function test_custom_loader() {
        $path = $this->path . 'custom_loader';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/setup.php loading TestLoaderOne",

            "$path/test_subdir1/setup.php",
            "$path/test_subdir1/test.php",
            "$path/test_subdir1/setup.php loading TestLoaderTwo",

            "$path/test_subdir2/test.php",
            "$path/setup.php loading TestLoaderThree",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = ['TestLoaderOne', 'TestLoaderTwo', 'TestLoaderThree'];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([]);
    }

    public function test_error_if_loader_does_not_return_an_object() {
        $path = $this->path . 'bad_loader';
        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/setup.php",
            "$path/test.php",
            "$path/teardown.php",
        ];
        $actual = $this->context->log;
        easytest\assert_identical($expected, $actual);

        $expected = [];
        $actual = $this->runner_log;
        easytest\assert_identical($expected, $actual);

        $this->reporter->assert_report([
            'Errors' => [
                ['TestBadLoader', 'Test loader did not return an object instance'],
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

        $this->reporter->assert_report([]);
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

        $this->reporter->assert_report([
            'Output' => [
                ["$path/setup.php", "$path/setup.php"],
                ["$path/test.php", "$path/test.php"],
                ['test_output_buffering', '__construct'],
                ["$path/teardown.php", "$path/teardown.php"],
            ],
        ]);
    }
}
