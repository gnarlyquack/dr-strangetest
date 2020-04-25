<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

const PATTERN_FILE           = '~/test[^/]*\\.php$~i';
const PATTERN_DIR            = '~/test[^/]*/$~i';
const PATTERN_SETUP_DIR      = '~/setup\\.php$~i';
const PATTERN_DIR_FIXTURE    = '~^(setup|teardown)_?directory~i';
const PATTERN_TEST           = '~^test~i';
const PATTERN_SETUP_CLASS    = '~^setup_?class$~i';
const PATTERN_TEARDOWN_CLASS = '~^teardown_?class$~i';
const PATTERN_SETUP          = '~^setup$~i';
const PATTERN_TEARDOWN       = '~^teardown$~i';


function discover_tests(BufferingLogger $logger, array $paths) {
    $state = new State();
    foreach ($paths as $path) {
        $realpath = \realpath($path);
        if (!$realpath) {
            $logger->log_error($path, 'No such file or directory');
            continue;
        }

        if (\is_dir($path)) {
            $path .= '/';
        }
        $root = namespace\_determine_root($path);
        namespace\_discover_directory($state, $logger, $root, $path, null);
    }
}


// Determine a path's root test directory.
//
// The root test directory is the highest directory that matches the test
// directory regular expression, or the path itself.
//
// This is done to ensure that directory fixtures are properly loaded when
// testing individual subpaths within a test suite; discovery will begin at
// the root directory and descend towards the specified path.
function _determine_root($path) {
    if ('/' === \substr($path, -1)) {
        $root = $parent = $path;
    }
    else {
        $root = $parent = \dirname($path) . '/';
    }

    while (\preg_match(PATTERN_DIR, $parent)) {
        $root = $parent;
        $parent = \dirname($parent) . '/';
    }
    return $root;
}


// Discover and run tests in a directory.
//
// If $target is null, then all files and subdirectories within the directory
// that match the test regular expressions are discovered.  Otherwise,
// discovery is only done for the file or directory specified in $target.
// Directory fixtures are discovered and run in either case.
function _discover_directory(State $state, BufferingLogger $logger, $dir, $target, $args) {
    if ($target === $dir) {
        $target = null;
    }
    $processed = namespace\_process_directory($state, $logger, $dir, $target);
    if (!$processed) {
        return;
    }

    list($setup, $teardown, $files, $directories) = $processed;
    if ($setup) {
        $logger->start_buffering($setup);
        list($succeeded, $args) = namespace\_run_setup($logger, $setup, $setup, $args);
        $logger->end_buffering();
        if (!$succeeded) {
            return;
        }
    }

    foreach ($files as $file) {
        namespace\_discover_file($state, $logger, $file, $args);
    }
    foreach ($directories as $directory) {
        namespace\_discover_directory($state, $logger, $directory, $target, $args);
    }
    if ($teardown) {
        $logger->start_buffering($teardown);
        namespace\_run_teardown($logger, $teardown, $teardown, $args);
        $logger->end_buffering();
    }
}


function _process_directory(State $state, BufferingLogger $logger, $path, $target) {
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
        $logger->log_error(
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
        $result = namespace\_parse_setup($state, $logger, $setup[0]);
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


function _parse_setup(State $state, BufferingLogger $logger, $file) {
    if (isset($state->files[$file])) {
        return $state->files[$file];
    }

    $logger->start_buffering($file);
    $succeeded = namespace\_include_file($logger, $file);
    $logger->end_buffering();
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
                && \preg_match(PATTERN_DIR_FIXTURE, $function, $matches)) {
                $function = "$ns$function";
                $functions[\strtolower($matches[1])][] = $function;
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }

    $error = false;
    if (\count($functions['setup']) > 1) {
        $logger->log_error(
            $file,
            \sprintf(
                "Multiple fictures found:\n\t%s",
                \implode("\n\t", $functions['setup'])
            )
        );
        $error = true;
    }
    if (\count($functions['teardown']) > 1) {
        $logger->log_error(
            $file,
            \sprintf(
                "Multiple fictures found:\n\t%s",
                \implode("\n\t", $functions['teardown'])
            )
        );
        $error = true;
    }

    if ($error) {
        $state->files[$file] = false;
    }
    else {
        $state->files[$file] = [
            \current($functions['setup']),
            \current($functions['teardown'])
        ];
    }
    return $state->files[$file];
}


/*
 * Discover and run tests in a file.
 */
function _discover_file(State $state, BufferingLogger $logger, $file, $args) {
    if (isset($state->files[$file])) {
        $logger->log_skip($file, 'File has already been tested!');
        return;
    }
    $state->files[$file] = true;

    $logger->start_buffering($file);
    $succeeded = namespace\_include_file($logger, $file);
    $logger->end_buffering();
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
            list($class, $i) = namespace\_parse_class($tokens, $i);
            if ($class) {
                $classname = "$ns$class";
                if (!isset($state->seen[$classname])
                    && \class_exists($classname))
                {
                    $state->seen[$classname] = true;
                    $logger->start_buffering($classname);
                    $test = namespace\_instantiate_test($logger, $classname, $args);
                    $logger->end_buffering();
                    if ($test) {
                        namespace\_run_test_case($logger, $test);
                    }
                }
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }
}


function _parse_class($tokens, $i) {
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


function _parse_namespace($tokens, $i, $current_ns) {
    // There are two options:
    //
    // 1) This is a namespace declaration, which takes two forms:
    //      namespace identifier;
    //      namespace identifier { ... }
    //  In the second case, the identifier is optional
    //
    // 2) This is a use of the namespace operator, which takes the form:
    //      namespace\identifier
    //
    // Consequently, if the namespace separator '\' is the first non-whitespace
    // token found after the 'namespace' keyword, this isn't a namespace
    // declaration. Otherwise, everything until the terminating ';' or '{'
    // constitutes the identifier.
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


function _include_file(BufferingLogger $logger, $file) {
    try {
        namespace\_guard_include($file);
        return true;
    }
    catch (Skip $e) {
        $logger->log_skip($file, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($file, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($file, $e);
    }
    return false;
}


// Isolate included files to prevent them from meddling with any local state
function _guard_include($file) {
    include $file;
}


function _run_setup(BufferingLogger $logger, $source, $callable, $args=null) {
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
        $logger->log_skip($source, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($source, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($source, $e);
    }
    return [false, null];
}


function _run_teardown(BufferingLogger $logger, $source, $callable, $args = null) {
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
        $logger->log_error($source, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($source, $e);
    }
    return false;
}


function _instantiate_test(BufferingLogger $logger, $class, $args) {
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
        $logger->log_error($class, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($class, $e);
    }
    return false;
}


function _run_test_case(BufferingLogger $logger, $object) {
    $class = \get_class($object);
    $methods = namespace\_process_methods($logger, $class, $object);
    if (!$methods) {
        return;
    }

    list($setup_class, $teardown_class, $setup, $teardown, $tests)
        = $methods;

    if ($setup_class) {
        $source = "$class::$setup_class";
        $callable = [$object, $setup_class];
        $logger->start_buffering($source);
        list($succeeded,) = namespace\_run_setup($logger, $source, $callable);
        $logger->end_buffering();
        if (!$succeeded) {
            return;
        }
    }

    foreach ($tests as $test) {
        namespace\_run_test($logger, $class, $object, $test, $setup, $teardown);
    }

    if ($teardown_class) {
        $source = "$class::$teardown_class";
        $callable = [$object, $teardown_class];
        $logger->start_buffering($source);
        namespace\_run_teardown($logger, $source, $callable);
        $logger->end_buffering();
    }
}


function _process_methods(BufferingLogger $logger, $class, $object) {
    $methods = \get_class_methods($object);

    $setup_class =  namespace\_find_fixture(
        $logger, $class, $methods, namespace\PATTERN_SETUP_CLASS);
    if (false === $setup_class) {
        return false;
    }

    $teardown_class = namespace\_find_fixture(
        $logger, $class, $methods, namespace\PATTERN_TEARDOWN_CLASS);
    if (false === $teardown_class) {
        return false;
    }

    $setup = namespace\_find_fixture(
        $logger, $class, $methods, namespace\PATTERN_SETUP);
    if (false === $setup) {
        return false;
    }

    $teardown = namespace\_find_fixture(
        $logger, $class, $methods, namespace\PATTERN_TEARDOWN);
    if (false === $teardown) {
        return false;
    }

    $tests = \preg_grep(namespace\PATTERN_TEST, $methods);
    if (!$tests) {
        return false;
    }

    return [$setup_class, $teardown_class, $setup, $teardown, $tests];
}


function _run_test(BufferingLogger $logger, $class, $object, $test, $setup, $teardown) {
    $passed = true;

    if ($setup) {
        $source = "$setup for $class::$test";
        $callable = [$object, $setup];
        $logger->start_buffering($source);
        list($passed,) = namespace\_run_setup($logger, $source, $callable);
    }

    if ($passed) {
        $source = "$class::$test";
        $callable = [$object, $test];
        $logger->start_buffering($source);
        $passed = namespace\_run_test_method($logger, $source, $callable);

        if ($teardown) {
            $source = "$teardown for $class::$test";
            $callable = [$object, $teardown];
            $logger->start_buffering($source);
            $passed = namespace\_run_teardown($logger, $source, $callable)
                    && $passed;
        }
    }

    $logger->end_buffering();
    if ($passed) {
        $logger->log_pass();
    }
}


function _run_test_method(BufferingLogger $logger, $source, $callable) {
    try {
        $callable();
        return true;
    }
    catch (\AssertionError $e) {
        $logger->log_failure($source, $e);
    }
    // #BC(5.6): Catch Failure
    catch (Failure $e) {
        $logger->log_failure($source, $e);
    }
    catch (Skip $e) {
        $logger->log_skip($source, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($source, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($source, $e);
    }
    return false;
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
