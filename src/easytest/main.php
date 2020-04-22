<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

const VERSION = '0.2.2';

const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;

const LOG_EVENT_PASS   = 1;
const LOG_EVENT_FAIL   = 2;
const LOG_EVENT_ERROR  = 3;
const LOG_EVENT_SKIP   = 4;
const LOG_EVENT_OUTPUT = 5;

const PATTERN_TEST           = '~^test~i';
const PATTERN_SETUP_CLASS    = '~^setup_?class$~i';
const PATTERN_TEARDOWN_CLASS = '~^teardown_?class$~i';
const PATTERN_SETUP          = '~^setup$~i';
const PATTERN_TEARDOWN       = '~^teardown$~i';


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


interface Log {
    public function pass_count();

    public function failure_count();

    public function error_count();

    public function skip_count();

    public function output_count();

    public function get_events();
}

interface Logger {
    public function log_pass();

    public function log_failure($source, $reason);

    public function log_error($source, $reason);

    public function log_skip($source, $reason);

    public function log_output($source, $reason, $during_error);

    public function get_log();
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
        $diff = $this->diff_array(\explode("\n", $from), \explode("\n", $to));
        $diff = \implode("\n", $diff);
        return "- $from_name\n+ $to_name\n\n$diff";
    }

    private function diff_array($from, $to) {
        $flen = \count($from);
        $tlen = \count($to);
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
                    $m[$i][$j] = \max($m[$i][$j-1], $m[$i-1][$j]);
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
                \array_unshift($diff, '  ' . $to[$j]);
            }
            elseif ($j > 0 && (0 === $i || $m[$i][$j-1] >= $m[$i-1][$j])) {
                --$j;
                \array_unshift($diff, '+ ' . $to[$j]);
            }
            elseif ($i > 0 && (0 === $j || $m[$i][$j-1] < $m[$i-1][$j])) {
                --$i;
                \array_unshift($diff, '- ' . $from[$i]);
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
        $name = \is_object($var) ? \get_class($var) : \gettype($var);
        $seen = ['byval' => [], 'byref' => []];
        return $this->format($var, $name, $seen);
    }

    private function format(&$var, $name, &$seen, $indent = 0) {
        $reference = $this->check_reference($var, $name, $seen);
        if ($reference) {
            return $reference;
        }
        if (\is_scalar($var) || null === $var) {
            return $this->format_scalar($var);
        }
        if (\is_resource($var)) {
            return $this->format_resource($var);
        }
        if (\is_array($var)) {
            return $this->format_array($var, $name, $seen, $indent);
        }
        return $this->format_object($var, $name, $seen, $indent);
    }

    private function format_scalar(&$var) {
        return \var_export($var, true);
    }

    private function format_resource(&$var) {
        return \sprintf(
            '%s of type "%s"',
            \print_r($var, true),
            \get_resource_type($var)
        );
    }

    private function format_array(&$var, $name, &$seen, $indent) {
        $baseline = \str_repeat(' ', $indent);
        $indent += 4;
        $padding = \str_repeat(' ', $indent);
        $out = '';

        if ($var) {
            foreach ($var as $key => &$value) {
                $key = \var_export($key, true);
                $out .= \sprintf(
                    "\n%s%s => %s,",
                    $padding,
                    $key,
                    $this->format(
                        $value,
                        \sprintf('%s[%s]', $name, $key),
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
        $baseline = \str_repeat(' ', $indent);
        $indent += 4;
        $padding = \str_repeat(' ', $indent);
        $out = '';

        $class = \get_class($var);
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
                $key = \explode("\0", $key);
                $property = '$' . \array_pop($key);
                if ($key && $key[1] !== '*' && $key[1] !== $class) {
                    $property = "$key[1]::$property";
                }
                $out .= \sprintf(
                    "\n%s%s = %s;",
                    $padding,
                    $property,
                    $this->format(
                        $value,
                        \sprintf('%s->%s', $name, $property),
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
        if (\is_scalar($var) || \is_array($var) || null === $var) {
            $copy = $var;
            $var = $this->sentinel['byval'];
            $reference = \array_search($var, $seen['byval'], true);
            if (false === $reference) {
                $seen['byval'][$name] = &$var;
            }
            else {
                $reference = "&$reference";
            }
            $var = $copy;
        }
        else {
            $reference = \array_search($var, $seen['byref'], true);
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
            throw new \Exception(\get_class() . ' has already been enabled');
        }

        self::$eh = new ErrorHandler($formatter, $diff);

        // #BC(5.3): Include E_STRICT in error_reporting()
        \error_reporting(\E_ALL | \E_STRICT);
        \set_error_handler([self::$eh, 'handle_error'], \error_reporting());

        // #BC(5.6): Check if PHP 7 assertion options are supported
        if (\version_compare(\PHP_VERSION, '7.0', '>=')) {
            if (-1 === \ini_get('zend.assertions')) {
                \fwrite(\STDERR, "EasyTest should not be run in a production environment.\n");
                exit(EasyTest::FAILURE);
            }
            \ini_set('zend.assertions', 1);

            // #BC(7.1): Check whether or not to enable assert.exception
            // With PHP 7.2 deprecating calling assert() with a string
            // assertion, there doesn't seem to be  any reason to keep assert's
            // legacy behavior enabled
            if (\version_compare(\PHP_VERSION, '7.2', '>=')) {
                \ini_set('assert.exception', 1);
            }
        }
        // Although the documentation discourages using these configuration
        // directives for PHP 7-only code, we want to ensure that assert() is
        // in a known configured state regardless of the environment
        \assert_options(\ASSERT_ACTIVE, 1);
        \assert_options(\ASSERT_WARNING, 1);
        \assert_options(\ASSERT_BAIL, 0);
        \assert_options(\ASSERT_QUIET_EVAL, 0);
        \assert_options(\ASSERT_CALLBACK, [self::$eh, 'handle_assertion']);
    }


    public static function assert_equal($expected, $actual, $message = null) {
        \assert(self::$eh);
        return self::$eh->do_assert_equal($expected, $actual, $message);
    }


    public static function assert_identical($expected, $actual, $message = null) {
        \assert(self::$eh);
        return self::$eh->do_assert_identical($expected, $actual, $message);
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
        if (!\ini_get('assert.exception')) {
            $this->assertion = [$code, $desc];
        }
    }

    public function handle_error($errno, $errstr, $errfile, $errline) {
        if (!(\error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        if (!$this->assertion) {
            throw new Error($errstr, $errno, $errfile, $errline);
        }

        list($code, $message) = $this->assertion;
        $this->assertion = null;

        if (!$message) {
            $message = $code ? "assert($code) failed" : $errstr;
        }
        throw new Failure($message);
    }


    private function do_assert_equal($expected, $actual, $message = null) {
        if ($expected == $actual) {
            return;
        }

        if (\is_array($expected) && \is_array($actual)) {
            $this->sort_array($expected);
            $this->sort_array($actual);
        }
        if (!isset($message)) {
            $message = 'Assertion "$expected == $actual" failed';
        }
        throw new Failure(
            \sprintf(
                "%s\n\n%s",
                $message,
                $this->diff->diff(
                    $this->formatter->format_var($expected),
                    $this->formatter->format_var($actual),
                    'expected', 'actual'
                )
            )
        );
    }


    private function do_assert_identical($expected, $actual, $message = null) {
        if ($expected === $actual) {
            return;
        }

        if (!isset($message)) {
            $message = 'Assertion "$expected === $actual" failed';
        }
        throw new Failure(
            \sprintf(
                "%s\n\n%s",
                $message,
                $this->diff->diff(
                    $this->formatter->format_var($expected),
                    $this->formatter->format_var($actual),
                    'expected', 'actual'
                )
            )
        );
    }

    private function sort_array(&$array, &$seen = []) {
        if (!\is_array($array)) {
            return;
        }

        /* Prevent infinite recursion for arrays with recursive references. */
        $temp = $array;
        $array = null;
        $sorted = \in_array($array, $seen, true);
        $array = $temp;
        unset($temp);

        if (false !== $sorted) {
            return;
        }
        $seen[] = &$array;
        \ksort($array);
        foreach ($array as &$value) {
            $this->sort_array($value, $seen);
        }
    }
}



final class Context implements IContext {
    public function include_file($file) {
        return include $file;
    }
}




final class Discoverer {
    private $context;
    private $logger;
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
        BufferingLogger $logger,
        IRunner $runner,
        IContext $context,
        $sort_files = false
    ) {
        $this->logger = $logger;
        $this->runner = $runner;
        $this->context = $context;
        $this->glob_sort = $sort_files ? 0 : \GLOB_NOSORT;
        $this->loader = function($classname) { return new $classname(); };
    }

    public function discover_tests(array $paths) {
        foreach ($paths as $path) {
            $realpath = \realpath($path);
            if (!$realpath) {
                $this->logger->log_error($path, 'No such file or directory');
                continue;
            }

            if (\is_dir($path)) {
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
        if ('/' === \substr($path, -1)) {
            $root = $parent = $path;
        }
        else {
            $root = $parent = \dirname($path) . '/';
        }

        while (\preg_match($this->patterns['dirs'], $parent)) {
            $root = $parent;
            $parent = \dirname($parent) . '/';
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
        $paths = \glob("$path*", \GLOB_MARK | $this->glob_sort);
        $processed = [];

        $processed['setup'] = $this->process_setup($loader, $paths);
        $processed['teardown'] = $this->process_teardown($paths);
        if (!$processed['setup'] || !$processed['teardown']) {
            return false;
        }

        if (!$target) {
            $processed['files'] = \preg_grep($this->patterns['files'], $paths);
            $processed['dirs'] = \preg_grep($this->patterns['dirs'], $paths);
            return $processed;
        }

        $i = \strpos($target, '/', \strlen($path));
        if (false === $i) {
            $processed['files'] = [$target];
            $processed['dirs'] = [];
        }
        else {
            $processed['files'] = [];
            $processed['dirs'] = [\substr($target, 0, $i + 1)];
        }

        return $processed;
    }

    private function process_setup($loader, $paths) {
        $path = \preg_grep($this->patterns['setup'], $paths);

        switch (\count($path)) {
        case 0:
            return function() use ($loader) { return $loader; };

        case 1:
            $path = \current($path);
            return function() use ($path, $loader) {
                $this->logger->start_buffering($path);
                $succeeded = $this->include_setup($loader, $path);
                $this->logger->end_buffering();
                return $succeeded;
            };

        default:
            $this->logger->log_error(
                \dirname(\current($path)),
                "Multiple files found:\n\t" . \implode("\n\t", $path)
            );
            return false;
        }
    }

    private function process_teardown($paths) {
        $path = \preg_grep($this->patterns['teardown'], $paths);

        switch (\count($path)) {
        case 0:
            return function() { return true; };

        case 1:
            $path = \current($path);
            return function() use ($path) {
                $this->logger->start_buffering($path);
                $succeeded = $this->include_teardown($path);
                $this->logger->end_buffering();
                return $succeeded;
            };

        default:
            $this->logger->log_error(
                \dirname(\current($path)),
                "Multiple files found:\n\t" . \implode("\n\t", $path)
            );
            return false;
        }
    }

    /*
     * Discover and run tests in a file.
     */
    private function discover_file($loader, $file) {
        $this->logger->start_buffering($file);
        $succeeded = $this->include_file($file);
        $this->logger->end_buffering();
        if (!$succeeded) {
            return;
        }

        $ns = '';
        $tokens = \token_get_all(\file_get_contents($file));
        /* Assume token 0 = '<?php' and token 1 = whitespace */
        for ($i = 2, $c = \count($tokens); $i < $c; ++$i) {
            if (!\is_array($tokens[$i])) {
                continue;
            }
            switch ($tokens[$i][0]) {
            case \T_CLASS:
                list($class, $i) = $this->parse_class($tokens, $i);
                if ($class) {
                    $classname = "$ns$class";
                    $this->logger->start_buffering($classname);
                    $test = $this->instantiate_test($loader, $classname);
                    $this->logger->end_buffering();
                    if ($test) {
                        $this->runner->run_test_case($test);
                    }
                }
                break;

            case \T_NAMESPACE:
                list($ns, $i) = $this->parse_namespace($tokens, $i, $ns);
                break;
            }
        }
    }

    private function parse_class($tokens, $i) {
        /* $i = 'class' and $i+1 = whitespace */
        $i += 2;
        while (!\is_array($tokens[$i]) || \T_STRING !== $tokens[$i][0]) {
            ++$i;
        }
        $class = $tokens[$i][1];

        // advance token index to the end of the class definition
        while ('{' !== $tokens[++$i]);
        $scope = 1;
        while ($scope) {
            $token = $tokens[++$i];
            if ('{' === $token) {
                ++$scope;
            }
            elseif ('}' === $token) {
                --$scope;
            }
        }

        if (0 === \substr_compare($class, 'test', 0, 4, true)) {
            return [$class, $i];
        }
        return [false, $i];
    }

    /*
     * There are two options:
     *
     * 1) This is a namespace declaration, which takes two forms:
     *      namespace identifier;
     *      namespace identifier { ... }
     *  In the second case, the identifier is optional
     *
     * 2) This is a use of the namespace operator, which takes the form:
     *      namespace\identifier
     *
     * Consequently, if the namespace separator '\' is the first non-whitespace
     * token found after the 'namespace' keyword, this isn't a namespace
     * declaration. Otherwise, everything until the terminating ';' or '{'
     * constitutes the identifier.
     */
    private function parse_namespace($tokens, $i, $current_ns) {
        $ns = '';
        while (++$i) {
            if ($tokens[$i] === ';' || $tokens[$i] === '{') {
                return [$ns ? "$ns\\" : '', $i];
            }

            if (!\is_array($tokens[$i])) {
                continue;
            }

            switch ($tokens[$i][0]) {
            case \T_NS_SEPARATOR:
                if (!$ns) {
                    return [$current_ns, $i];
                }
                $ns .= $tokens[$i][1];
                break;

            case \T_STRING:
                $ns .= $tokens[$i][1];
                break;
            }
        }
    }

    private function include_file($file) {
        try {
            $this->context->include_file($file);
            return true;
        }
        catch (Skip $e) {
            $this->logger->log_skip($file, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($file, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($file, $e);
        }
        return false;
    }

    private function include_setup($loader, $file) {
        try {
            $result = $this->context->include_file($file);
            return \is_callable($result) ? $result : $loader;
        }
        catch (Skip $e) {
            $this->logger->log_skip($file, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($file, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($file, $e);
        }
        return false;
    }

    private function include_teardown($file) {
        try {
            $this->context->include_file($file);
            return true;
        }
        catch (\Throwable $e) {
            $this->logger->log_error($file, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($file, $e);
        }
        return false;
    }

    private function instantiate_test($loader, $class) {
        try {
            $result = $loader($class);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($class, $e);
            return false;
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($class, $e);
            return false;
        }

        if (\is_object($result)) {
            return $result;
        }

        $this->logger->log_error(
            $class,
            'Test loader did not return an object instance'
        );
        return false;
    }
}


final class Runner implements IRunner {
    private $logger;


    public function __construct(BufferingLogger $logger) {
        $this->logger = $logger;
    }

    public function run_test_case($object) {
        $class = \get_class($object);
        $methods = $this->process_methods($class, $object);
        if (!$methods) {
            return;
        }

        list($setup_class, $teardown_class, $setup, $teardown, $tests)
            = $methods;

        if ($setup_class) {
            $source = "$class::$setup_class";
            $this->logger->start_buffering($source);
            $succeeded = $this->run_setup($source, $object, $setup_class);
            $this->logger->end_buffering();
            if (!$succeeded) {
                return;
            }
        }

        foreach ($tests as $test) {
            $this->run_test($class, $object, $test, $setup, $teardown);
        }

        if ($teardown_class) {
            $source = "$class::$teardown_class";
            $this->logger->start_buffering($source);
            $this->run_teardown($source, $object, $teardown_class);
            $this->logger->end_buffering();
        }
    }

    private function process_methods($class, $object) {
        $methods = \get_class_methods($object);

        $setup_class =  namespace\_find_fixture(
            $this->logger, $class, $methods, namespace\PATTERN_SETUP_CLASS);
        if (false === $setup_class) {
            return false;
        }

        $teardown_class = namespace\_find_fixture(
            $this->logger, $class, $methods, namespace\PATTERN_TEARDOWN_CLASS);
        if (false === $teardown_class) {
            return false;
        }

        $setup = namespace\_find_fixture(
            $this->logger, $class, $methods, namespace\PATTERN_SETUP);
        if (false === $setup) {
            return false;
        }

        $teardown = namespace\_find_fixture(
            $this->logger, $class, $methods, namespace\PATTERN_TEARDOWN);
        if (false === $teardown) {
            return false;
        }

        $tests = \preg_grep(namespace\PATTERN_TEST, $methods);
        if (!$tests) {
            return false;
        }

        return [$setup_class, $teardown_class, $setup, $teardown, $tests];
    }


    private function run_test($class, $object, $test, $setup, $teardown) {
        $passed = true;

        if ($setup) {
            $source = "$setup for $class::$test";
            $this->logger->start_buffering($source);
            $passed = $this->run_setup($source, $object, $setup);
        }

        if ($passed) {
            $source = "$class::$test";
            $this->logger->start_buffering($source);
            $passed = $this->run_test_method($source, $object, $test);

            if ($teardown) {
                $source = "$teardown for $class::$test";
                $this->logger->start_buffering($source);
                $passed = $this->run_teardown($source, $object, $teardown)
                        && $passed;
            }
        }

        $this->logger->end_buffering();
        if ($passed) {
            $this->logger->log_pass();
        }
    }


    private function run_setup($source, $object, $method) {
        try {
            $object->$method();
            return true;
        }
        catch (Skip $e) {
            $this->logger->log_skip($source, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($source, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($source, $e);
        }
        return false;
    }

    private function run_test_method($source, $object, $method) {
        try {
            $object->$method();
            return true;
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($source, $e);
        }
        // #BC(5.6): Catch Failure, which extends from AssertionError
        catch (Failure $e) {
            $this->logger->log_failure($source, $e);
        }
        catch (Skip $e) {
            $this->logger->log_skip($source, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($source, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($source, $e);
        }
        return false;
    }

    private function run_teardown($source, $object, $method) {
        try {
            $object->$method();
            return true;
        }
        catch (\Throwable $e) {
            $this->logger->log_error($source, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($source, $e);
        }
        return false;
    }
}


function _find_fixture($logger, $class, $methods, $pattern) {
    $found = \preg_grep($pattern, $methods);
    $count = \count($found);

    if (0 === $count) {
        return null;
    }

    if (1 === $count) {
        return \current($found);
    }

    $logger->log_error(
        $class,
        \sprintf(
            "Multiple fixtures found:\n\t%s",
            \implode("\n\t", $found)
        )
    );
    return false;
}




function main($argc, $argv) {
    namespace\_try_loading_composer();
    namespace\_load_easytest();

    list($options, $tests) = namespace\_parse_arguments($argc, $argv);
    if (!$tests) {
        $tests[] = \getcwd();
    }

    ErrorHandler::enable(new VariableFormatter(), new Diff());

    $logger = new BufferingLogger(
        new LiveUpdatingLogger(
            new BasicLogger($options['verbose'])
        )
    );
    $app = new Discoverer($logger, new Runner($logger), new Context());

    namespace\output_header('EasyTest ' . namespace\VERSION);
    $app->discover_tests($tests);

    $log = $logger->get_log();
    namespace\output_log($log);
    exit(
        $log->failure_count() || $log->error_count()
        ? namespace\EXIT_FAILURE
        : namespace\EXIT_SUCCESS
    );
}


function _try_loading_composer() {
    $files = ['/../../../../autoload.php', '/../../vendor/autoload.php'];
    foreach ($files as $file) {
        $file = __DIR__ . $file;
        if (\file_exists($file)) {
            require $file;
            return;
        }
    }
}


function _load_easytest() {
    $files = ['assertions', 'exceptions', 'log', 'output'];
    foreach ($files as $file) {
        require __DIR__ . "/{$file}.php";
    }
}


function _parse_arguments($argc, $argv) {
    $opts = ['verbose' => false];
    $args = \array_slice($argv, 1);

    while ($args) {
        $arg = $args[0];

        if ('--' === \substr($arg, 0, 2)) {
            list($opts, $args) = namespace\_parse_long_option($args, $opts);
        }
        elseif ('-' === \substr($arg, 0, 1)) {
            list($opts, $args) = namespace\_parse_short_option($args, $opts);
        }
        else {
            break;
        }
    }

    return [$opts, $args];
}


function _parse_long_option($args, $opts) {
    $opt = \array_shift($args);
    $opt = \substr($opt, 2);
    return namespace\_parse_option($opt, $args, $opts);
}


function _parse_short_option($args, $opts) {
    $opt = \array_shift($args);
    $opt = \substr($opt, 1);
    return namespace\_parse_option($opt, $args, $opts);
}


function _parse_option($opt, $args, $opts) {
    switch ($opt) {
        case 'q':
        case 'quiet':
            $opts['verbose'] = false;
            break;

        case 'v':
        case 'verbose':
            $opts['verbose'] = true;
            break;
    }

    return [$opts, $args];
}
