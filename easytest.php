<?php

namespace easytest;

final class Runner {
    public function run_test_case($test) {
        foreach (preg_grep('~^test~i', get_class_methods($test)) as $method) {
            $this->run_test_method($test, $method);
        }
    }

    private function run_test_method($test, $method) {
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
        $this->runner->run_test_case($test);
        assert('["test"] === $test->log');
    }

    public function test_setup_and_teardown() {
        $test = new FixtureTestCase();
        assert('[] === $test->log');
        $this->runner->run_test_case($test);

        $expected = [
            'setup', 'test1', 'teardown',
            'setup', 'test2', 'teardown',
        ];
        assert('$expected === $test->log');
    }
}

class SimpleTestCase {
    public $log = [];

    public function test() {
        $this->log[] = __FUNCTION__;
    }
}

class FixtureTestCase {
    public $log = [];

    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }

    public function test1() {
        $this->log[] = __FUNCTION__;
    }

    public function test2() {
        $this->log[] = __FUNCTION__;
    }
}


$runner = new Runner();
$runner->run_test_case(new TestRunner());
