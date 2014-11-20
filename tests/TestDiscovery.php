<?php

class TestDiscovery implements easytest\IRunner {
    private $reporter;
    private $context;
    private $discoverer;

    private $path;
    private $runner_log;

    public function setup() {
        $this->reporter = new StubReporter();
        $this->context = new easytest\Context();
        $this->discoverer = new easytest\Discoverer(
            $this->reporter,
            $this,
            $this->context
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

        // suppress output from the test file
        ob_start();
        $this->discoverer->discover_tests([$path]);
        ob_end_clean();

        $expected = ['Test', 'test2', 'Test3'];
        $actual = $this->runner_log;
        assert('$expected === $actual');
    }

    public function test_discover_directory() {
        $path = $this->path . 'discover_directory';

        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/test.php",

            "$path/test_dir1/setup.php",
            "$path/test_dir1/test1.php",
            "$path/test_dir1/test2.php",
            "$path/test_dir1/teardown.php",

            "$path/TEST_DIR2/SETUP.PHP",
            "$path/TEST_DIR2/TEST1.PHP",
            "$path/TEST_DIR2/TEST2.PHP",
            "$path/TEST_DIR2/TEARDOWN.PHP",
        ];
        $actual = $this->context->log;
        assert('$expected === $actual');
    }

    public function test_individual_paths() {
        $root = $this->path . 'test_individual_paths';
        $paths = array(
            "$root/test_dir1/test2.php",
            "$root/test_dir1/test3.php",
            "$root/test_dir2/test_subdir",
        );
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
        assert('$expected === $actual');
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
        assert('$expected === $actual');

        $expected = ['test_file_error_two'];
        $actual = $this->runner_log;
        assert('$expected === $actual');

        $this->reporter->assert_report([
            'Errors' => [["$path/test1.php", 'An error happened']]
        ]);
    }

    public function test_setup_error() {
        $path = $this->path . 'setup_error';
        $this->discoverer->discover_tests([$path]);

        $expected = ["$path/setup.php"];
        $actual = $this->context->log;
        assert('$expected === $actual');

        $expected = [];
        $actual = $this->runner_log;
        assert('$expected === $actual');

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
        assert('$expected === $actual');

        $expected = ['test_teardown_error'];
        $actual = $this->runner_log;
        assert('$expected === $actual');

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
        assert('$expected === $actual');

        $expected = ['test_instantiation_error_two'];
        $actual = $this->runner_log;
        assert('$expected === $actual');

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
        assert('$expected === $actual');

        $expected = [];
        $actual = $this->runner_log;
        assert('$expected === $actual');

        $this->reporter->assert_report([
            'Skips' => [["$path/test.php", 'Skip me']]
        ]);
    }

    public function test_skip_in_setup() {
        $path = $this->path . 'skip_in_setup';
        $this->discoverer->discover_tests([$path]);

        $expected = ["$path/setup.php"];
        $actual = $this->context->log;
        assert('$expected === $actual');

        $expected = [];
        $actual = $this->runner_log;
        assert('$expected === $actual');

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
        assert('$expected === $actual');

        $expected = ['test_skip_in_teardown'];
        $actual = $this->runner_log;
        assert('$expected === $actual');

        $this->reporter->assert_report([
            'Errors' => [["$path/teardown.php", 'Skip me']]
        ]);
    }
}
