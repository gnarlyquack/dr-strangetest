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

interface IDiff {
    public function diff($from, $to, $from_name, $to_name);
}

interface IReporter {
    public function __construct($header);

    public function render_report();

    public function report_success();

    public function report_error($source, $message);

    public function report_failure($source, $message);

    public function report_skip($source, $message);
}

interface IRunner {
    public function run_test_case($object);
}

interface IVariableFormatter {
    public function format_var(&$var);
}


/*
 * Generate a unified diff between two strings.
 *
 * This is a basic implementation of the longest common subsequence algorithm.
 */
final class Diff implements IDiff {
    public function diff($from, $to, $from_name, $to_name) {
        $diff = $this->diff_array(explode("\n", $from), explode("\n", $to));
        $diff = implode("\n", $diff);
        return "- $from_name\n+ $to_name\n\n$diff";
    }

    private function diff_array($from, $to) {
        $flen = count($from);
        $tlen = count($to);
        $m = [];

        for ($i = 0; $i <= $flen; ++$i) {
            for ($j = 0; $j <= $tlen; ++$j) {
                if (0 === $i || 0 === $j) {
                    $m[$i][$j] = 0;
                }
                elseif ($from[$i-1] === $to[$j-1]) {
                    $m[$i][$j] = $m[$i-1][$j-1] + 1;
                }
                else {
                    $m[$i][$j] = max($m[$i][$j-1], $m[$i-1][$j]);
                }
            }
        }

        $i = $flen;
        $j = $tlen;
        $diff = [];
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $from[$i-1] === $to[$j-1]) {
                --$i;
                --$j;
                array_unshift($diff, '  ' . $to[$j]);
            }
            elseif ($j > 0 && (0 === $i || $m[$i][$j-1] >= $m[$i-1][$j])) {
                --$j;
                array_unshift($diff, '+ ' . $to[$j]);
            }
            elseif ($i > 0 && (0 === $j || $m[$i][$j-1] < $m[$i-1][$j])) {
                --$i;
                array_unshift($diff, '- ' . $from[$i]);
            }
            else {
                throw new \Exception('Reached unexpected branch');
            }
        }

        return $diff;
    }
}


/*
 * Format a variable for display.
 *
 * This provides more readable formatting of variables than PHP's built-in
 * variable-printing functions (print_r(), var_dump(), var_export()) and
 * also handles recursive references.
 */
final class VariableFormatter implements IVariableFormatter {
    private $sentinel;

    public function __construct() {
        $this->sentinel = ['byval' => new \stdClass(), 'byref' => null];
    }

    public function format_var(&$var) {
        $name = is_object($var) ? get_class($var) : gettype($var);
        $seen = ['byval' => [], 'byref' => []];
        return $this->format($var, $name, $seen);
    }

    private function format(&$var, $name, &$seen, $indent = 0) {
        $reference = $this->check_reference($var, $name, $seen);
        if ($reference) {
            return $reference;
        }
        if (is_scalar($var) || null === $var) {
            return $this->format_scalar($var);
        }
        if (is_resource($var)) {
            return $this->format_resource($var);
        }
        if (is_array($var)) {
            return $this->format_array($var, $name, $seen, $indent);
        }
        return $this->format_object($var, $name, $seen, $indent);
    }

    private function format_scalar(&$var) {
        return var_export($var, true);
    }

    private function format_resource(&$var) {
        return sprintf(
            '%s of type "%s"',
            print_r($var, true),
            get_resource_type($var)
        );
    }

    private function format_array(&$var, $name, &$seen, $indent) {
        $baseline = str_repeat(' ', $indent);
        $indent += 4;
        $padding = str_repeat(' ', $indent);
        $out = '';

        if ($var) {
            foreach ($var as $key => &$value) {
                $key = var_export($key, true);
                $out .= sprintf(
                    "\n%s%s => %s,",
                    $padding,
                    $key,
                    $this->format(
                        $value,
                        sprintf('%s[%s]', $name, $key),
                        $seen,
                        $indent
                    )
                );
            }
            $out .= "\n$baseline";
        }
        return "array($out)";
    }

    private function format_object(&$var, $name, &$seen, $indent) {
        $baseline = str_repeat(' ', $indent);
        $indent += 4;
        $padding = str_repeat(' ', $indent);
        $out = '';

        $class = get_class($var);
        $values = (array)$var;
        if ($values) {
            foreach ($values as $key => &$value) {
                /*
                 * Object properties are cast to array keys as follows:
                 *     public    $property -> "property"
                 *     protected $property -> "\0*\0property"
                 *     private   $property -> "\0class\0property"
                 *         where "class" is the name of the class where the
                 *         property is declared
                 */
                $key = explode("\0", $key);
                $property = '$' . array_pop($key);
                if ($key && $key[1] !== '*' && $key[1] !== $class) {
                    $property = "$key[1]::$property";
                }
                $out .= sprintf(
                    "\n%s%s = %s;",
                    $padding,
                    $property,
                    $this->format(
                        $value,
                        sprintf('%s->%s', $name, $property),
                        $seen,
                        $indent
                    )
                );
            }
            $out .= "\n$baseline";
        }
        return "$class {{$out}}";
    }

    /*
     * Check if $var is a reference to another value in $seen.
     *
     * If $var is normally pass-by-value, then it can only be an explicit
     * reference. If it's normally pass-by-reference, then it can either be an
     * object reference or an explicit reference. Explicit references are
     * marked with the reference operator, i.e., '&'.
     *
     * Since PHP has no built-in way to determine if a variable is a reference,
     * references are identified with a hack wherein $var is changed and $seen
     * is checked for an equivalent change.
     */
    private function check_reference(&$var, $name, &$seen) {
        if (is_scalar($var) || is_array($var) || null === $var) {
            $copy = $var;
            $var = $this->sentinel['byval'];
            $reference = array_search($var, $seen['byval'], true);
            if (false === $reference) {
                $seen['byval'][$name] = &$var;
            }
            else {
                $reference = "&$reference";
            }
            $var = $copy;
        }
        else {
            $reference = array_search($var, $seen['byref'], true);
            if (false === $reference) {
                $seen['byref'][$name] = &$var;
            }
            else {
                $copy = $var;
                $var = $this->sentinel['byref'];
                if ($var === $seen['byref'][$reference]) {
                    $reference = "&$reference";
                }
                $var = $copy;
            }
        }
        return $reference;
    }
}


final class ErrorHandler {
    private static $eh;

    private $formatter;
    private $diff;
    private $assertion;

    public static function enable(IVariableFormatter $formatter, IDiff $diff) {
        if (isset(self::$eh)) {
            throw new \Exception(get_class() . ' has already been enabled');
        }

        self::$eh = new ErrorHandler($formatter, $diff);

        error_reporting(-1);
        set_error_handler([self::$eh, 'handle_error'], error_reporting());

        assert_options(ASSERT_ACTIVE, 1);
        assert_options(ASSERT_WARNING, 1);
        assert_options(ASSERT_BAIL, 0);
        assert_options(ASSERT_QUIET_EVAL, 0);
        assert_options(ASSERT_CALLBACK, [self::$eh, 'handle_assertion']);
    }

    public static function assert_equal($expected, $actual, $message = null) {
        self::assert('$expected == $actual', $expected, $actual, $message);
    }

    public static function assert_identical($expected, $actual, $message = null) {
        self::assert('$expected === $actual', $expected, $actual, $message);
    }

    /*
     * The $desc parameter wasn't added to assert() until PHP 5.4.8, so
     * earlier versions need a nasty hack to inject the message.
     */
    private static function assert($code, $expected, $actual, $message) {
        if (version_compare(PHP_VERSION, '5.4.8', '>=')) {
            assert($code, $message);
        }
        elseif (!$message) {
            assert($code);
        }
        elseif (!eval("return $code;")) {
            self::$eh->handle_assertion(__FILE__, __LINE__, $code, $message);
            trigger_error();
        }
    }

    private function __construct(IVariableFormatter $formatter, IDiff $diff) {
        $this->formatter = $formatter;
        $this->diff = $diff;
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

        $sort = true;
        $variables = [];
        foreach (token_get_all("<?php $code") as $token) {
            if (!is_array($token)) {
                continue;
            }
            switch ($token[0]) {
            case T_VARIABLE:
                // Strip the leading '$' off the variable name.
                $variable = substr($token[1], 1);

                // The "pseudo-variable" '$this' (and possibly others?) will
                // parse as a variable but won't be in the context.
                if (array_key_exists($variable, $context)) {
                    $variables[$variable] = $context[$variable];
                }
                break;

            case T_IS_IDENTICAL:
                // Key order is significant, so don't sort arrays.
                $sort = false;
                break;
            }
        }

        if (!$variables) {
            return $message;
        }
        if (2 === count($variables)) {
            list($key1, $key2) = array_keys($variables);
            if ($sort) {
                $this->sort_array($variables[$key1]);
                $this->sort_array($variables[$key2]);
            }
            $message .= "\n\n" . $this->diff->diff(
                $this->formatter->format_var($variables[$key1]),
                $this->formatter->format_var($variables[$key2]),
                $key1,
                $key2
            );
            return $message;
        }
        foreach ($variables as $key => $value) {
            $message .= sprintf(
                "\n\n%s:\n%s",
                $key,
                $this->formatter->format_var($value)
            );
        }
        return $message;
    }

    private function sort_array(&$array, &$seen = []) {
        if (!is_array($array)) {
            return;
        }

        /* Prevent infinite recursion for arrays with recursive references. */
        $temp = $array;
        $array = null;
        $sorted = in_array($array, $seen, true);
        $array = $temp;
        unset($temp);

        if (false !== $sorted) {
            return;
        }
        $seen[] = &$array;
        ksort($array);
        foreach ($array as &$value) {
            $this->sort_array($value, $seen);
        }
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

final class Skip extends \Exception {
    public function __toString() {
        return $this->message;
    }
}


final class Context implements IContext {
    public function include_file($file) {
        return include $file;
    }
}


final class Discoverer {
    private $context;
    private $reporter;
    private $runner;
    private $loader;
    private $glob_sort;

    private $patterns = [
        'files' => '~/test[^/]*\\.php$~i',
        'dirs' => '~/test[^/]*/$~i',
        'setup' => '~/setup\\.php$~i',
        'teardown' => '~/teardown\\.php$~i',
    ];

    public function __construct(
        IReporter $reporter,
        IRunner $runner,
        IContext $context,
        $sort_files = false
    ) {
        $this->reporter = $reporter;
        $this->runner = $runner;
        $this->context = $context;
        $this->glob_sort = $sort_files ? 0 : GLOB_NOSORT;
        $this->loader = function($classname) { return new $classname(); };
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
            $this->discover_directory($this->loader, $root, $path);
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
    private function discover_directory($loader, $dir, $target) {
        if ($target === $dir) {
            $target = null;
        }
        $paths = $this->process_directory($loader, $dir, $target);
        if (!$paths) {
            return;
        }

        $loader = $paths['setup']();
        if ($loader) {
            foreach ($paths['files'] as $path) {
                $this->discover_file($loader, $path);
            }
            foreach ($paths['dirs'] as $path) {
                $this->discover_directory($loader, $path, $target);
            }
            $paths['teardown']();
        }
    }

    private function process_directory($loader, $path, $target) {
        $paths = glob("$path*", GLOB_MARK | $this->glob_sort);
        $processed = [];

        $processed['setup'] = $this->process_setup($loader, $paths);
        $processed['teardown'] = $this->process_teardown($paths);
        if (!$processed['setup'] || !$processed['teardown']) {
            return false;
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

    private function process_setup($loader, $paths) {
        $path = preg_grep($this->patterns['setup'], $paths);

        switch (count($path)) {
        case 0:
            return function() use ($loader) { return $loader; };

        case 1:
            $path = current($path);
            return function() use ($path, $loader) {
                return $this->include_setup($loader, $path);
            };

        default:
            $this->reporter->report_error(
                dirname(current($path)),
                "Multiple files found:\n\t" . implode("\n\t", $path)
            );
            return false;
        }
    }

    private function process_teardown($paths) {
        $path = preg_grep($this->patterns['teardown'], $paths);

        switch (count($path)) {
        case 0:
            return function() { return true; };

        case 1:
            $path = current($path);
            return function() use ($path) {
                return $this->include_teardown($path);
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
    private function discover_file($loader, $file) {
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
                $test = $this->instantiate_test($loader, $class);
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
        catch (Skip $e) {
            $this->reporter->report_skip($file, $e);
        }
        catch (\Exception $e) {
            $this->reporter->report_error($file, $e);
        }
        return !isset($e);
    }

    private function include_setup($loader, $file) {
        try {
            $result = $this->context->include_file($file);
            return is_callable($result) ? $result : $loader;
        }
        catch (Skip $e) {
            $this->reporter->report_skip($file, $e);
        }
        catch (\Exception $e) {
            $this->reporter->report_error($file, $e);
        }
        return false;
    }

    private function include_teardown($file) {
        try {
            $this->context->include_file($file);
        }
        catch (\Exception $e) {
            $this->reporter->report_error($file, $e);
        }
        return !isset($e);
    }

    private function instantiate_test($loader, $class) {
        try {
            return $loader($class);
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
            'setup_class' => [
                'regex' => '~^setup_?class$~i',
                'action' => 'run_setup',
            ],
            'teardown_class' => [
                'regex' => '~^teardown_?class$~i',
                'action' => 'run_teardown',
            ],
            'setup' => [
                'regex' => '~^setup$~i',
                'action' => 'run_setup',
            ],
            'teardown' => [
                'regex' => '~^teardown$~i',
                'action' => 'run_teardown',
            ],
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
            case 'easytest\\Skip':
                $this->reporter->report_skip($source, $e);
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
                $pattern['regex'],
                $pattern['action'],
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

    private function process_method($pattern, $action, $methods, $object) {
        $method = preg_grep($pattern, $methods);

        switch (count($method)) {
        case 0:
            return function() { return true; };

        case 1:
            $method = current($method);
            return function() use ($action, $object, $method) {
                return $this->$action($object, $method);
            };

        default:
            $this->reporter->report_error(
                get_class($object),
                "Multiple methods found:\n\t" . implode("\n\t", $method)
            );
            return false;
        }
    }

    private function run_setup($object, $method) {
        try {
            $object->$method();
        }
        catch (Skip $e) {
            $this->reporter->report_skip(
                sprintf('%s::%s', get_class($object), $method),
                $e
            );
        }
        catch (\Exception $e) {
            $this->reporter->report_error(
                sprintf('%s::%s', get_class($object), $method),
                $e
            );
        }
        return !isset($e);
    }

    private function run_teardown($object, $method) {
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
    private $count = 0;
    private $results = [
        'Errors' => [],
        'Failures' => [],
        'Skips' => [],
    ];

    public function __construct($header) {
        echo "$header\n\n";
    }

    public function report_success() {
        ++$this->count;
        echo '.';
    }

    public function report_error($source, $message) {
        $this->results['Errors'][] = [$source, $message];
        echo 'E';
    }

    public function report_failure($source, $message) {
        ++$this->count;
        $this->results['Failures'][] = [$source, $message];
        echo 'F';
    }

    public function report_skip($source, $message) {
        $this->results['Skips'][] = [$source, $message];
        echo 'S';
    }

    public function render_report() {
        if ($this->count || array_filter($this->results)) {
            echo "\n\n";
        }

        $totals = sprintf('Tests: %d', $this->count);
        foreach ($this->results as $type => $results) {
            if (!$results) {
                continue;
            }

            $totals .= sprintf(', %s: %d', $type, count($results));
            echo str_pad("     $type     ", 75, '=', STR_PAD_BOTH), "\n\n";
            foreach ($results as $i => $result) {
                printf("%d) %s\n%s\n\n\n", $i + 1, $result[0], $result[1]);
            }
        }
        echo "$totals\n";
    }
}




function assert_exception($expected, $callback, $message = null) {
    try {
        $callback();
    }
    catch (\Exception $e) {
        if ($e instanceof $expected) {
            return $e;
        }
        throw $e;
    }

    $message = $message ?: 'No exception was thrown although one was expected';
    throw new Failure($message);
}

function skip($reason) {
    throw new Skip($reason);
}

/*
 * assert_equal() and assert_identical() are simply proxies for static methods
 * on the ErrorHandler. This is done to support the $message parameter in
 * versions of PHP < 5.4.8.
 */

function assert_equal($expected, $actual, $message = null) {
    ErrorHandler::assert_equal($expected, $actual, $message);
}

function assert_identical($expected, $actual, $message = null) {
    ErrorHandler::assert_identical($expected, $actual, $message);
}




ErrorHandler::enable(new VariableFormatter(), new Diff());

$reporter = new Reporter('EasyTest');
$runner = new Discoverer($reporter, new Runner($reporter), new Context());

$tests = array_slice($argv, 1);
if (!$tests) {
    $tests[] = getcwd();
}
$runner->discover_tests($tests);

$reporter->render_report();
