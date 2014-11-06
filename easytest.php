<?php

namespace easytest;


/*
 * A Context object is used by the Discoverer to insulate itself from internal
 * state changes when including files, since an included file has private-level
 * access when included inside a class method.
 *
 * As a nice side effect, this same behavior also allows a Context object to
 * store common state that can be shared among test cases.
 */
interface IContext {
    public function include_file($file);
}

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


final class Context implements IContext {
    public function include_file($file) {
        include $file;
    }
}


final class Discoverer {
    private $context;
    private $runner;

    public function __construct(IRunner $runner, IContext $context) {
        $this->runner = $runner;
        $this->context = $context;
    }

    public function discover_tests($path) {
        if (is_dir($path)) {
            $this->discover_directory(rtrim($path, '/') . '/');
        }
        else {
            $this->discover_file($path);
        }
    }

    private function discover_directory($dir) {
        $files = glob("$dir*", GLOB_MARK | GLOB_NOSORT);

        foreach (preg_grep('~/test[^/]*\\.php$~i', $files) as $file) {
            $this->discover_file($file);
        }

        $files = preg_grep('~/test[^/]*/$~i', $files);
        while ($dir = array_shift($files)) {
            $this->discover_directory($dir);
        }
    }

    private function discover_file($file) {
        $this->context->include_file($file);

        $tokens = token_get_all(file_get_contents($file));
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




(new ErrorHandler())->enable();
$reporter = new Reporter();
$runner = new Discoverer(new Runner($reporter), new Context());
$runner->discover_tests(__DIR__);

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
