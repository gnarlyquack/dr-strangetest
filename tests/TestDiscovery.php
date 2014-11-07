<?php

class TestDiscovery implements easytest\IRunner {
    private $context;
    private $discoverer;
    private $path;
    private $log;

    public function setup() {
        $this->context = new easytest\Context();
        $this->discoverer = new easytest\Discoverer($this, $this->context);
        $this->path = __DIR__ . '/discovery_files/';
        $this->log = [];
    }

    // implementation of runner interface

    public function run_test_case($object) {
        $this->log[] = get_class($object);
    }

    // tests

    public function test_discover_file() {
        $path = $this->path . 'MyTestFile.php';

        // suppress output from the test file
        ob_start();
        $this->discoverer->discover_tests([$path]);
        ob_end_clean();

        $expected = ['Test', 'test2', 'Test3'];
        $actual = $this->log;
        assert('$expected === $actual');
    }

    public function test_discover_directory() {
        $path = $this->path . 'discover_directory';

        $this->discoverer->discover_tests([$path]);

        $expected = [
            "$path/test.php",
            "$path/test_dir1/test1.php",
            "$path/test_dir1/test2.php",
            "$path/TEST_DIR2/TEST1.PHP",
            "$path/TEST_DIR2/TEST2.PHP",
        ];
        $actual = $this->context->log;
        assert('$expected === $actual');
    }
}
