<?php

namespace easytest;

final class Runner {
    public function run_test_method($test, $method) {
        $test->$method();
    }
}


class TestRunner {
    public function test_test_method() {
        $tester = new Runner();
        $test = new SimpleTestCase();
        assert('[] === $test->log');
        $tester->run_test_method($test, 'test');
        assert('["test"] === $test->log');
    }
}

class SimpleTestCase {
    public $log = [];

    public function test() {
        $this->log[] = __FUNCTION__;
    }
}


$runner = new Runner();
$runner->run_test_method(new TestRunner(), 'test_test_method');
