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
    private $reporter;
    private $runner;

    private $patterns = [
        'files' => '~/test[^/]*\\.php$~i',
        'dirs' => '~/test[^/]*/$~i',
        'fixtures' => [
            'setup' => '~/setup\\.php$~i',
            'teardown' => '~/teardown\\.php$~i',
        ],
    ];

    public function __construct(
        IReporter $reporter,
        IRunner $runner,
        IContext $context
    ) {
        $this->reporter = $reporter;
        $this->runner = $runner;
        $this->context = $context;
    }

    public function discover_tests(array $paths) {
        foreach ($paths as $path) {
            $realpath = realpath($path);
            if (!$realpath) {
                $this->reporter->report_error($path, 'No such file or directory');
                continue;
            }

            if (is_dir($path)) {
                $path .= '/';
            }
            $root = $this->determine_root($path);
            $this->discover_directory($root, $path);
        }
    }

    /*
     * Determine a path's root test directory.
     *
     * The root test directory is the highest directory that matches the test
     * directory regular expression, or the path itself.
     *
     * This is done to ensure that directory fixtures are properly loaded when
     * testing individual subpaths within a test suite; discovery will begin at
     * the root directory and descend towards the specified path.
     */
    private function determine_root($path) {
        if ('/' === substr($path, -1)) {
            $root = $parent = $path;
        }
        else {
            $root = $parent = dirname($path) . '/';
        }

        while (preg_match($this->patterns['dirs'], $parent)) {
            $root = $parent;
            $parent = dirname($parent) . '/';
        }
        return $root;
    }

    /*
     * Discover and run tests in a directory.
     *
     * If $target is null, then all files and subdirectories within the
     * directory that match the test regular expressions are discovered.
     * Otherwise, discovery is only done for the file or directory specified in
     * $target. Directory fixtures are discovered and run in either case.
     */
    private function discover_directory($dir, $target) {
        if ($target === $dir) {
            $target = null;
        }
        $paths = $this->process_directory($dir, $target);
        if (!$paths) {
            return;
        }

        if ($paths['setup']()) {
            foreach ($paths['files'] as $path) {
                $this->discover_file($path);
            }
            foreach ($paths['dirs'] as $path) {
                $this->discover_directory($path, $target);
            }
            $paths['teardown']();
        }
    }

    private function process_directory($path, $target) {
        $paths = glob("$path*", GLOB_MARK | GLOB_NOSORT);
        $processed = [];

        foreach ($this->patterns['fixtures'] as $fixture => $pattern) {
            $processed[$fixture] = $this->process_file($pattern, $paths);
            if (!$processed[$fixture]) {
                return false;
            }
        }

        if (!$target) {
            $processed['files'] = preg_grep($this->patterns['files'], $paths);
            $processed['dirs'] = preg_grep($this->patterns['dirs'], $paths);
            return $processed;
        }

        $i = strpos($target, '/', strlen($path));
        if (false === $i) {
            $processed['files'] = [$target];
            $processed['dirs'] = [];
        }
        else {
            $processed['files'] = [];
            $processed['dirs'] = [substr($target, 0, $i + 1)];
        }

        return $processed;
    }

    private function process_file($pattern, $paths) {
        $path = preg_grep($pattern, $paths);

        switch (count($path)) {
        case 0:
            return function() { return true; };

        case 1:
            $path = current($path);
            return function() use ($path) {
                return $this->include_file($path);
            };

        default:
            $this->reporter->report_error(
                dirname(current($path)),
                "Multiple files found:\n\t" . implode("\n\t", $path)
            );
            return false;
        }


    }

    /*
     * Discover and run tests in a file.
     */
    private function discover_file($file) {
        if (!$this->include_file($file)) {
            return;
        }

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
                $test = $this->instantiate_test($class);
                if ($test) {
                    $this->runner->run_test_case($test);
                }
            }
        }
    }

    private function include_file($file) {
        try {
            $this->context->include_file($file);
        }
        catch (\Exception $e) {
            $this->reporter->report_error($file, $e);
        }
        return !isset($e);
    }

    private function instantiate_test($class) {
        try {
            return new $class();
        }
        catch (\Exception $e) {
            $this->reporter->report_error($class, $e);
        }
        return false;
    }
}


final class Runner implements IRunner {
    private $reporter;

    private $patterns = [
        'tests' => '~^test~i',
        'fixtures' => [
            'setup_class' => '~^setup_?class$~i',
            'teardown_class' => '~^teardown_?class$~i',
            'setup' => '~^setup$~i',
            'teardown' => '~^teardown$~i',
        ],
    ];

    public function __construct(IReporter $reporter) {
        $this->reporter = $reporter;
    }

    public function run_test_case($object) {
        $methods = $this->process_methods($object);
        if (!$methods) {
            return;
        }

        if ($methods['setup_class']()) {
            foreach ($methods['tests'] as $method) {
                if ($methods['setup']()) {
                    $success = $this->run_test_method($object, $method);
                    if ($methods['teardown']() && $success) {
                        $this->reporter->report_success();
                    }
                }
            }
            $methods['teardown_class']();
        }
    }

    private function run_test_method($object, $method) {
        try {
            $object->$method();
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
        return !isset($e);
    }

    private function process_methods($object) {
        $methods = get_class_methods($object);
        $processed = [];

        foreach ($this->patterns['fixtures'] as $fixture => $pattern) {
            $processed[$fixture] = $this->process_method(
                $pattern,
                $methods,
                $object
            );
            if (!$processed[$fixture]) {
                return false;
            }
        }
        $processed['tests'] = preg_grep($this->patterns['tests'], $methods);

        return $processed;
    }

    private function process_method($pattern, $methods, $object) {
        $method = preg_grep($pattern, $methods);

        switch (count($method)) {
        case 0:
            return function() { return true; };

        case 1:
            $method = current($method);
            return function() use ($object, $method) {
                return $this->run_method($object, $method);
            };

        default:
            $this->reporter->report_error(
                get_class($object),
                "Multiple methods found:\n\t" . implode("\n\t", $method)
            );
            return false;
        }
    }

    private function run_method($object, $method) {
        try {
            $object->$method();
        }
        catch (\Exception $e) {
            $this->reporter->report_error(
                sprintf('%s::%s', get_class($object), $method),
                $e
            );
        }
        return !isset($e);
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
$runner = new Discoverer($reporter, new Runner($reporter), new Context());

$tests = array_slice($argv, 1);
if (!$tests) {
    $tests[] = getcwd();
}
$runner->discover_tests($tests);


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
