<?php

namespace easytest;

interface IReporter {
    public function report_success();

    public function report_error($source, $message);

    public function report_failure($source, $message);
}

interface IRunner {
    public function run_test_case($object);
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
            throw new Error($errstr, $errno, $errfile, $errline);
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

final class Error extends \ErrorException {
    public function __construct($message, $severity, $file, $line) {
        parent::__construct($message, 0, $severity, $file, $line);
    }

    public function __toString() {
        return sprintf(
            "%s\nin %s on line %s\nStack trace:\n%s",
            $this->message,
            $this->file,
            $this->line,
            $this->getTraceAsString()
        );
    }
}

final class Failure extends \Exception {
    public function __toString() {
        return $this->message;
    }
}


final class Discoverer {
    private $runner;

    public function __construct(IRunner $runner) {
        $this->runner = $runner;
    }

    public function discover_tests($path) {
        require $path;

        $tokens = token_get_all(file_get_contents($path));
        // Assume token 0 = '<?php' and token 1 = whitespace
        for ($i = 2, $c = count($tokens); $i < $c; ++$i) {
            if (!is_array($tokens[$i]) || T_CLASS !== $tokens[$i][0]) {
                continue;
            }
            // $i = 'class' and $i+1 = whitespace
            $i += 2;
            while (!is_array($tokens[$i]) || T_STRING !== $tokens[$i][0]) {
                ++$i;
            }
            $class = $tokens[$i][1];
            if (0 === stripos($class, 'test')) {
                $this->runner->run_test_case(new $class());
            }
        }
    }
}


final class Runner implements IRunner {
    private $reporter;

    public function __construct(IReporter $reporter) {
        $this->reporter = $reporter;
    }

    public function run_test_case($object) {
        foreach (preg_grep('~^test~i', get_class_methods($object)) as $method) {
            $this->run_test_method($object, $method);
        }
    }

    private function run_test_method($object, $method) {
        if (is_callable([$object, 'setup'])) {
            $object->setup();
        }

        try {
            $object->$method();
            $this->reporter->report_success();
        }
        catch (\Exception $e) {
            $source = sprintf('%s::%s', get_class($object), $method);
            switch (get_class($e)) {
            case 'easytest\\Failure':
                $this->reporter->report_failure($source, $e);
                break;
            default:
                $this->reporter->report_error($source, $e);
                break;
            }
        }

        if (is_callable([$object, 'teardown'])) {
            $object->teardown();
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


class TestExceptions {
    public function test_error_format() {
        $file = __FILE__;
        $line = __LINE__;
        $message = 'An error happened';
        $e = new Error($message, E_USER_ERROR, $file, $line);

        $expected = sprintf(
            "%s\nin %s on line %s\nStack trace:\n%s",
            $message,
            $file,
            $line,
            $e->getTraceAsString()
        );
        $actual = (string)$e;
        assert('$expected === $actual');
    }

    public function test_failure_format() {
        $expected = 'Assertion failed';
        $f = new Failure($expected);
        $actual = (string)$f;
        assert('$expected === $actual');
    }
}


class TestDiscovery implements IRunner {
    private $discoverer;
    private $path;
    private $log;

    public function setup() {
        $this->discoverer = new Discoverer($this);
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
        $this->discoverer->discover_tests($path);
        ob_end_clean();

        $expected = ['Test', 'test2', 'Test3'];
        $actual = $this->log;
        assert('$expected === $actual');
    }
}


(new ErrorHandler())->enable();
$reporter = new Reporter();
$runner = new Runner($reporter);
$runner->run_test_case(new TestRunner());
$runner->run_test_case(new TestReporter());
$runner->run_test_case(new TestAssert());
$runner->run_test_case(new TestExceptions());
$runner->run_test_case(new TestDiscovery());

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
