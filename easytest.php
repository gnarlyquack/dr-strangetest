<?php

namespace easytest;

final class Runner {
    public function run_test_method($test, $method) {
        if (is_callable([$test, 'setup'])) {
            $test->setup();
        }
        $test->$method();
        if (is_callable([$test, 'teardown'])) {
            $test->teardown();
        }
    }
}


class TestRunner {
    private $runner;

    public function setup() {
        $this->runner = new Runner();
    }

    public function test_test_method() {
        $test = new SimpleTestCase();
        assert('[] === $test->log');
        $this->runner->run_test_method($test, 'test');
        assert('["test"] === $test->log');
    }

    public function test_setup_and_teardown() {
        $test = new FixtureTestCase();
        assert('[] === $test->log');
        $this->runner->run_test_method($test, 'test');
        assert('["setup", "test", "teardown"] === $test->log');
    }
}

class SimpleTestCase {
    public $log = [];

    public function test() {
        $this->log[] = __FUNCTION__;
    }
}

class FixtureTestCase extends SimpleTestCase {
    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }
}


$runner = new Runner();
$runner->run_test_method(new TestRunner(), 'test_test_method');
$runner->run_test_method(new TestRunner(), 'test_setup_and_teardown');
