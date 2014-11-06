<?php

namespace easytest;

interface IReporter {
    public function report_success();

    public function report_error($source, $message);

    public function report_failure($source, $message);
}


final class ErrorHandler {
    private $assertion;

    public function enable() {
        error_reporting(-1);
        set_error_handler([$this, 'handle_error'], error_reporting());

        assert_options(ASSERT_ACTIVE, 1);
        assert_options(ASSERT_WARNING, 1);
        assert_options(ASSERT_BAIL, 0);
        assert_options(ASSERT_QUIET_EVAL, 0);
        assert_options(ASSERT_CALLBACK, [$this, 'handle_assertion']);
    }

    /*
     * Failed assertions are actually handled in the error handler, since it
     * has access to the error context (i.e., the variables that were in scope
     * when assert() was called). The assertion handler is used to save state
     * that is not available in the error handler, namely, the raw assertion
     * expression ($code) and the optional assertion message ($desc).
     */
    public function handle_assertion($file, $line, $code, $desc = null) {
        $this->assertion = [$code, $desc];
    }

    public function handle_error($errno, $errstr, $errfile, $errline, $errcontext) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        if (!$this->assertion) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        list($code, $message) = $this->assertion;
        $this->assertion = null;
        throw new Failure($this->format_message($code, $message, $errcontext));
    }

    private function format_message($code, $message, $context) {
        if (!$code) {
            return $message ?: 'Assertion failed';
        }

        if (!$message) {
            $message = "Assertion \"$code\" failed";
        }
        if (!$context) {
            return $message;
        }

        foreach (token_get_all("<?php $code") as $token) {
            if (is_array($token) && T_VARIABLE === $token[0]) {
                // Strip the leading '$' off the variable name.
                $variable = substr($token[1], 1);

                // The "pseudo-variable" '$this' (and possibly others?) will
                // parse as a variable but won't be in the context.
                if (array_key_exists($variable, $context)) {
                    $message .= sprintf(
                        "\n\n%s:\n%s",
                        $variable,
                        var_export($context[$variable], true)
                    );
                }
            }
        }
        return $message;
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


class TestRunner implements IReporter {
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
        $actual = $test->log;
        assert('[] === $actual');
        $this->runner->run_test_case($test);
        $actual = $test->log;
        assert('$expected === $actual');
    }

    private function assert_report($expected) {
        $expected = array_merge(
            ['Tests' => 0, 'Errors' => [], 'Failures' => []],
            $expected
        );
        $actual = $this->report;
        assert('$expected === $actual');
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


class TestReporter {
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
        $actual = $this->reporter->get_report();
        assert('$expected === $actual');
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


class TestAssert {
    public function test_failed_assertion() {
        try {
            assert(true == false);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (Failure $e) {}

        $actual = $e->getMessage();
        assert('"Assertion failed" === $actual');
    }

    public function test_failed_assertion_with_code() {
        try {
            assert('true == false');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (Failure $e) {}

        $expected = 'Assertion "true == false" failed';
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_failed_assertion_with_message() {
        if (version_compare(PHP_VERSION, '5.4.8') < 0) {
            // The assert() description parameter was added in PHP 5.4.8, so
            // skip this test if this is an earlier version of PHP.
            return;
        }

        $expected = 'My assertion failed. Or did it?';
        try {
            assert('true == false', $expected);
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (Failure $e) {}

        $actual = $e->getMessage();
        assert('$expected === $actual');
    }

    public function test_failed_assertion_with_variables() {
        $one = true;
        $two = false;
        try {
            assert('$one == $two');
            throw new \Exception("Failed assertion didn't trigger an exception");
        }
        catch (Failure $e) {}

        $expected = <<<'EXPECTED'
Assertion "$one == $two" failed

one:
true

two:
false
EXPECTED;
        $actual = $e->getMessage();
        assert('$expected === $actual');
    }
}


(new ErrorHandler())->enable();
$reporter = new Reporter();
$runner = new Runner($reporter);
$runner->run_test_case(new TestRunner());
$runner->run_test_case(new TestReporter());
$runner->run_test_case(new TestAssert());

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
