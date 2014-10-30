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

        try {
            $test->$method();
            $this->reporter->report_success();
        }
        catch (\Exception $e) {
            $this->reporter->report_error();
        }

        if (is_callable([$test, 'teardown'])) {
            $test->teardown();
        }
    }
}


final class Reporter {
    private $report = [
        'Tests' => 0,
        'Errors' => 0,
    ];

    public function report_success() {
        ++$this->report['Tests'];
    }

    public function report_error() {
        ++$this->report['Errors'];
    }

    public function get_report() {
        return $this->report;
    }
}


class TestRunner {
    private $reporter;
    private $runner;

    public function setup() {
        $this->reporter = new Reporter();
        $this->runner = new Runner($this->reporter);
    }

    // helper assertions

    private function assert_run($test, $expected) {
        assert('[] === $test->log');
        $this->runner->run_test_case($test);
        assert('$expected === $test->log');
    }

    private function assert_report($expected) {
        assert('$expected === $this->reporter->get_report()');
    }

    // tests

    public function test_run_test_method() {
        $this->assert_run(new SimpleTestCase(), ['test']);
        $this->assert_report(['Tests' => 1, 'Errors' => 0]);
    }

    public function test_setup_and_teardown() {
        $this->assert_run(
            new FixtureTestCase(),
            [
                'setup', 'test1', 'teardown',
                'setup', 'test2', 'teardown',
            ]
        );
        $this->assert_report(['Tests' => 2, 'Errors' => 0]);
    }

    public function test_exception() {
        $this->assert_run(
            new ExceptionTestCase(),
            ['setup', 'test', 'teardown']
        );
        $this->assert_report(['Tests' => 0, 'Errors' => 1]);
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

class ExceptionTestCase {
    public $log = [];

    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }

    public function test() {
        $this->log[] = __FUNCTION__;
        throw new \Exception();
    }
}


$reporter = new Reporter();
$runner = new Runner($reporter);
$runner->run_test_case(new TestRunner());

$totals = [];
foreach ($reporter->get_report() as $type => $count) {
    if ($count) {
        $totals[] = "$type: $count";
    }
}
echo implode(', ', $totals), "\n";
