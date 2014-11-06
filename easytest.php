<?php

namespace easytest;

interface IReporter {
    public function report_success();

    public function report_error($source, $message);

    public function report_failure($source, $message);
}


final class ErrorHandler {
    public function enable() {
        error_reporting(-1);
        set_error_handler([$this, 'handle_error'], error_reporting());

        assert_options(ASSERT_ACTIVE, 1);
        assert_options(ASSERT_WARNING, 0);
        assert_options(ASSERT_BAIL, 0);
        assert_options(ASSERT_QUIET_EVAL, 0);
        assert_options(ASSERT_CALLBACK, [$this, 'handle_assertion']);
    }

    public function handle_assertion($file, $line, $code, $desc = null) {
        throw new Failure('Assertion failed');
    }

    public function handle_error($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}

final class Failure extends \Exception {}


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
            $source = sprintf('%s::%s', get_class($test), $method);
            switch (get_class($e)) {
            case 'easytest\\Failure':
                $this->reporter->report_failure($source, $e);
                break;
            default:
                $this->reporter->report_error($source, $e);
                break;
            }
        }

        if (is_callable([$test, 'teardown'])) {
            $test->teardown();
        }
    }
}


final class Reporter implements IReporter {
    private $report = [
        'Tests' => 0,
        'Errors' => [],
        'Failures' => [],
    ];

    public function report_success() {
        ++$this->report['Tests'];
    }

    public function report_error($source, $message) {
        $this->report['Errors'][] = [$source, $message];
    }

    public function report_failure($source, $message) {
        ++$this->report['Tests'];
        $this->report['Failures'][] = [$source, $message];
    }

    public function get_report() {
        return $this->report;
    }
}


abstract class TestCase {
    protected final function assert_identical($expected, $actual) {
        if ($expected !== $actual) {
            throw new \Exception(
                sprintf(
                    "\n\nexpected: %s\n\nactual: %s\n\n",
                    var_export($expected, true),
                    var_export($actual, true)
                )
            );
        }
    }
}

class TestRunner extends TestCase implements IReporter {
    private $runner;
    private $report;

    public function setup() {
        $this->runner = new Runner($this);
        $this->report = [
            'Tests' => 0,
            'Errors' => [],
            'Failures' => [],
        ];
    }

    // implementation of reporter interface

    public function report_success() {
        ++$this->report['Tests'];
    }

    public function report_error($source, $message) {
        $this->report['Errors'][] = [$source, $message->getMessage()];
    }

    public function report_failure($source, $message) {
        $this->report['Failures'][] = [$source, $message->getMessage()];
    }

    // helper assertions

    private function assert_run($test, $expected) {
        $this->assert_identical([], $test->log);
        $this->runner->run_test_case($test);
        $this->assert_identical($expected, $test->log);
    }

    private function assert_report($expected) {
        $expected = array_merge(
            ['Tests' => 0, 'Errors' => [], 'Failures' => []],
            $expected
        );
        $this->assert_identical($expected, $this->report);
    }

    // tests

    public function test_run_test_method() {
        $this->assert_run(new SimpleTestCase(), ['test']);
        $this->assert_report(['Tests' => 1]);
    }

    public function test_setup_and_teardown() {
        $this->assert_run(
            new FixtureTestCase(),
            [
                'setup', 'test1', 'teardown',
                'setup', 'test2', 'teardown',
            ]
        );
        $this->assert_report(['Tests' => 2]);
    }

    public function test_exception() {
        $this->assert_run(
            new ExceptionTestCase(),
            ['setup', 'test', 'teardown']
        );
        $this->assert_report([
            'Errors' => [
                ['easytest\\ExceptionTestCase::test', 'How exceptional!'],
            ],
        ]);
    }

    public function test_error() {
        $this->assert_run(
            new ErrorTestCase(),
            ['setup', 'test', 'teardown']
        );
        $this->assert_report([
            'Errors' => [
                ['easytest\\ErrorTestCase::test', 'Did I err?'],
            ],
        ]);
    }

    public function test_suppressed_error() {
        $this->assert_run(
            new SuppressedErrorTestCase(),
            ['setup', 'test', 'teardown']
        );
        $this->assert_report(['Tests' => 1]);
    }

    public function test_failure() {
        $this->assert_run(
            new FailedTestCase(),
            ['setup', 'test', 'teardown']
        );
        $this->assert_report([
            'Failures' => [
                ['easytest\\FailedTestCase::test', 'Assertion failed'],
            ],
        ]);
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

abstract class BaseTestCase {
    public $log = [];

    public function setup() {
        $this->log[] = __FUNCTION__;
    }

    public function teardown() {
        $this->log[] = __FUNCTION__;
    }

    public abstract function test();
}

class ExceptionTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        throw new \Exception('How exceptional!');
    }
}

class ErrorTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        trigger_error('Did I err?');
    }
}

class SuppressedErrorTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        @$foo['bar'];
    }
}

class FailedTestCase extends BaseTestCase {
    public function test() {
        $this->log[] = __FUNCTION__;
        assert(true == false);
    }
}


class TestReporter extends TestCase {
    private $reporter;

    public function setup() {
        $this->reporter = new Reporter();
    }

    // helper assertions

    private function assert_report($expected) {
        $expected = array_merge(
            ['Tests' => 0, 'Errors' => [], 'Failures' => []],
            $expected
        );
        $this->assert_identical($expected, $this->reporter->get_report());
    }

    // tests

    public function test_blank_report() {
        $this->assert_report([]);
    }

    public function test_report_success() {
        $this->reporter->report_success();
        $this->assert_report(['Tests' => 1]);
    }

    public function test_report_error() {
        $this->reporter->report_error('source', 'message');
        $this->assert_report(['Errors' => [['source', 'message']]]);
    }

    public function test_report_failure() {
        $this->reporter->report_failure('source', 'message');
        $this->assert_report([
            'Tests' => 1,
            'Failures' => [['source', 'message']]
        ]);
    }
}


(new ErrorHandler())->enable();
$reporter = new Reporter();
$runner = new Runner($reporter);
$runner->run_test_case(new TestRunner());
$runner->run_test_case(new TestReporter());

$totals = [];
foreach ($reporter->get_report() as $type => $results) {
    if (!$results) {
        continue;
    }
    if (is_array($results)) {
        $totals[] = sprintf('%s: %d', $type, count($results));
        echo str_pad("     $type     ", 70, '*', STR_PAD_BOTH), "\n\n\n";
        foreach ($results as $i => $result) {
            printf("%d) %s\n%s\n\n\n", $i + 1, $result[0], $result[1]);
        }
    }
    else {
        $totals[] = "$type: $results";
    }
}
echo implode(', ', $totals), "\n";
