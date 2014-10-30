<?php

namespace easytest;


final class Runner {
    private $reporter;

    public function __construct($reporter) {
        $this->reporter = $reporter;
    }

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
        $this->reporter->report_success();
        if (is_callable([$test, 'teardown'])) {
            $test->teardown();
        }
    }
}


final class Reporter {
    private $tests = 0;

    public function report_success() {
        ++$this->tests;
    }

    public function get_report() {
        return $this->tests;
    }
}


class TestRunner {
    private $reporter;
    private $runner;

    public function setup() {
        $this->reporter = new Reporter();
        $this->runner = new Runner($this->reporter);
    }

    public function test_test_method() {
        $test = new SimpleTestCase();
        assert('[] === $test->log');
        $this->runner->run_test_case($test);
        assert('["test"] === $test->log');
        assert('1 === $this->reporter->get_report()');
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
        assert('2 === $this->reporter->get_report()');
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


$reporter = new Reporter();
$runner = new Runner($reporter);
$runner->run_test_case(new TestRunner());
echo 'Tests: ', $reporter->get_report(), "\n";
