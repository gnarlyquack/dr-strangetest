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




final class ErrorHandler {
    public function handle_assertion($file, $line, $code, $desc = null) {
        if (!\ini_get('assert.exception')) {
            if (!$desc) {
                $desc = $code ? "assert($code) failed" : 'assert() failed';
            }
            throw new Failure($desc);
        }
    }

    public function handle_error($errno, $errstr, $errfile, $errline) {
        if (!(\error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new Error($errstr, $errno, $errfile, $errline);
    }
}



final class Context implements IContext {
    public function include_file($file) {
        return include $file;
    }
}


final class State {
    public $seen = [];
    public $files = [];
}


final class ArgList {
    private $args;

    public function __construct($arg) {
        $this->args = \func_get_args();
    }

    public function args() {
        return $this->args;
    }
}




final class Discoverer {
    private $context;
    private $logger;
    private $runner;
    private $loader;
    private $glob_sort;
    private $state;

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
        $this->state = new State();
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
            $this->discover_directory(null, $root, $path);
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
    private function discover_directory($args, $dir, $target) {
        if ($target === $dir) {
            $target = null;
        }
        $processed = $this->process_directory($dir, $target);
        if (!$processed) {
            return;
        }

        list($setup, $teardown, $files, $directories) = $processed;
        if ($setup) {
            $this->logger->start_buffering($setup);
            list($succeeded, $args) = $this->run_setup($setup, $args);
            $this->logger->end_buffering();
            if (!$succeeded) {
                return;
            }
        }

        foreach ($files as $file) {
            $this->discover_file($args, $file);
        }
        foreach ($directories as $directory) {
            $this->discover_directory($args, $directory, $target);
        }
        if ($teardown) {
            $this->logger->start_buffering($teardown);
            $this->run_teardown($teardown, $args);
            $this->logger->end_buffering();
        }
    }

    private function process_directory($path, $target) {
        $error = false;
        $setup = [];
        $files = [];
        $directories = [];

        foreach (\scandir($path) as $basename) {
            $filepath = "$path$basename";
            if (0 === \strcasecmp($basename, 'setup.php')) {
                if ($setup) {
                    $error = true;
                }
                $setup[] = $filepath;
                continue;
            }
            if ($error || $target) {
                continue;
            }
            if (0 === \substr_compare($basename, 'test', 0, 4, true)) {
                if (\is_dir($filepath)) {
                    $directories[] = "$filepath/";
                }
                elseif (0 === \substr_compare($basename, '.php', -4, 4, true)) {
                    $files[] = $filepath;
                }
            }
        }

        if ($error) {
            $this->logger->log_error(
                $path,
                \sprintf(
                    "Multiple setup files found:\n\t%s",
                    \implode("\n\t", $setup)
                )
            );
            return false;
        }

        $teardown = null;
        if ($setup) {
            $result = $this->parse_setup($setup[0]);
            if (!$result) {
                return false;
            }
            list($setup, $teardown) = $result;
        }

        if ($target) {
            $i = \strpos($target, '/', \strlen($path));
            if (false === $i) {
                $files[] = $target;
            }
            else {
                $directories[] = \substr($target, 0, $i + 1);
            }
        }

        return [$setup, $teardown, $files, $directories];
    }

    private function parse_setup($file) {
        if (isset($this->state->files[$file])) {
            return $this->state->files[$file];
        }

        $this->logger->start_buffering($file);
        $succeeded = $this->include_file($file);
        $this->logger->end_buffering();
        if (!$succeeded) {
            return false;
        }

        $ns = '';
        $functions = [
            'setup' => [],
            'teardown' => [],
        ];
        $tokens = \token_get_all(\file_get_contents($file));
        /* Assume token 0 = '<?php' and token 1 = whitespace */
        for ($i = 2, $c = \count($tokens); $i < $c; ++$i) {
            if (!\is_array($tokens[$i])) {
                continue;
            }
            switch ($tokens[$i][0]) {
            case \T_FUNCTION:
                $function = null;
                /* $i = 'function' and $i+1 = whitespace */
                $i += 2;
                while (true) {
                    if ('{' === $tokens[$i]) {
                        break;
                    }
                    if (\is_array($tokens[$i])
                        && \T_STRING === $tokens[$i][0])
                    {
                        $function = $tokens[$i][1];
                        break;
                    }
                    ++$i;
                }

                // advance token index to the end of the function definition
                while ('{' !== $tokens[$i++]);
                $scope = 1;
                while ($scope) {
                    $token = $tokens[$i++];
                    if ('{' === $token) {
                        ++$scope;
                    }
                    elseif ('}' === $token) {
                        --$scope;
                    }
                }

                if ($function
                    && \preg_match('~^(teardown|setup)_?directory~i', $function, $matches)) {
                    $function = "$ns$function";
                    $functions[\strtolower($matches[1])][] = $function;
                }
                break;

            case \T_NAMESPACE:
                list($ns, $i) = $this->parse_namespace($tokens, $i, $ns);
                break;
            }
        }

        $error = false;
        if (\count($functions['setup']) > 1) {
            $this->logger->log_error(
                $file,
                \sprintf(
                    "Multiple fictures found:\n\t%s",
                    \implode("\n\t", $functions['setup'])
                )
            );
            $error = true;
        }
        if (\count($functions['teardown']) > 1) {
            $this->logger->log_error(
                $file,
                \sprintf(
                    "Multiple fictures found:\n\t%s",
                    \implode("\n\t", $functions['teardown'])
                )
            );
            $error = true;
        }

        if ($error) {
            $this->state->files[$file] = false;
        }
        else {
            $this->state->files[$file] = [
                \current($functions['setup']),
                \current($functions['teardown'])
            ];
        }
        return $this->state->files[$file];
    }


    /*
     * Discover and run tests in a file.
     */
    private function discover_file($args, $file) {
        if (isset($this->state->files[$file])) {
            $this->logger->log_skip($file, 'File has already been tested!');
            return;
        }
        $this->state->files[$file] = true;

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
                    if (!isset($this->state->seen[$classname])
                        && \class_exists($classname))
                    {
                        $this->state->seen[$classname] = true;
                        $this->logger->start_buffering($classname);
                        $test = $this->instantiate_test($args, $classname);
                        $this->logger->end_buffering();
                        if ($test) {
                            $this->runner->run_test_case($test);
                        }
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

    private function run_setup($callable, $args) {
        try {
            if ($args) {
                // #BC(5.5): Use proxy function for argument unpacking
                $result = namespace\_unpack_function($callable, $args->args());
            }
            else {
                $result = $callable();
            }
            return [true, $result ? $result : $args];
        }
        catch (Skip $e) {
            $this->logger->log_skip($callable, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($callable, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($callable, $e);
        }
        return [false, null];
    }

    private function run_teardown($callable, $args) {
        try {
            if ($args) {
                // #BC(5.5): Use proxy function for argument unpacking
                namespace\_unpack_function($callable, $args->args());
            }
            else {
                $callable();
            }
            return true;
        }
        catch (\Throwable $e) {
            $this->logger->log_error($callable, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($callable, $e);
        }
        return false;
    }

    private function instantiate_test($args, $class) {
        try {
            if ($args) {
                // #BC(5.5): Use proxy function for argument unpacking
                return namespace\_unpack_construct($class, $args->args());
            }
            else {
                return new $class();
            }
        }
        catch (\Throwable $e) {
            $this->logger->log_error($class, $e);
        }
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {
            $this->logger->log_error($class, $e);
        }
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
    namespace\_enable_error_handling();
    namespace\_try_loading_composer();
    namespace\_load_easytest();

    list($options, $tests) = namespace\_parse_arguments($argc, $argv);
    if (!$tests) {
        $tests[] = \getcwd();
    }

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
    $files = ['assertions', 'exceptions', 'log', 'output', 'util'];
    // #BC(5.5): Implement proxy functions for argument unpacking
    // PHP 5.6's argument unpacking syntax causes a syntax error in earlier PHP
    // versions, so we need to include version-dependent proxy functions to do
    // the unpacking for us. When support for PHP < 5.6 is dropped, this can
    // all be eliminated and we can just use the argument unpacking syntax
    // directly at the call site.
    if (\version_compare(\PHP_VERSION, '5.6', '<')) {
        $files[] = 'unpack5.5';
    }
    else {
        $files[] = 'unpack';
    }
    foreach ($files as $file) {
        require __DIR__ . "/{$file}.php";
    }
}


function _enable_error_handling() {
    $eh = new ErrorHandler();

    // #BC(5.3): Include E_STRICT in error_reporting()
    \error_reporting(\E_ALL | \E_STRICT);
    \set_error_handler([$eh, 'handle_error'], \error_reporting());

    // #BC(5.6): Check if PHP 7 assertion options are supported
    if (\version_compare(\PHP_VERSION, '7.0', '>=')) {
        if (-1 === \ini_get('zend.assertions')) {
            \fwrite(\STDERR, "EasyTest should not be run in a production environment.\n");
            exit(namespace\EXIT_FAILURE);
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
    \assert_options(\ASSERT_WARNING, 0);
    \assert_options(\ASSERT_BAIL, 0);
    \assert_options(\ASSERT_QUIET_EVAL, 0);
    \assert_options(\ASSERT_CALLBACK, [$eh, 'handle_assertion']);
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
