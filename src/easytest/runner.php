<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const ERROR_SETUP    = 0x01;
const ERROR_TEARDOWN = 0x02;


function discover_tests(Logger $logger, array $paths) {
    $state = new State();
    if (!$paths) {
        $paths[] = \getcwd();
    }
    foreach ($paths as $path) {
        $realpath = \realpath($path);
        if (!$realpath) {
            $logger->log_error($path, 'No such file or directory');
            continue;
        }

        if (\is_dir($path)) {
            $path .= \DIRECTORY_SEPARATOR;
        }
        $root = namespace\_determine_root($path);
        namespace\_discover_directory($state, $logger, $root, null, $path);
    }
}


function _determine_root($path) {
    // Determine a path's root test directory
    //
    // The root test directory is the highest directory above $path whose
    // case-insensitive name begins with 'test' or, if $path is a directory,
    // $path itself or, if $path is file, the dirname of $path. This is done to
    // ensure that directory fixtures are properly discovered when testing
    // individual subpaths within a test suite; discovery will begin at the
    // root directory and descend towards the specified path.
    if (\is_dir($path)) {
        $root = $parent = \rtrim($path, \DIRECTORY_SEPARATOR);
    }
    else {
        $root = $parent = \dirname($path);
    }

    while (0 === \substr_compare(\basename($parent), 'test', 0, 4, true)) {
        $root = $parent;
        $parent = \dirname($parent);
    }
    return $root . \DIRECTORY_SEPARATOR;
}


function _discover_directory(State $state, Logger $logger, $dir, $args, $target = null) {
    // Discover and run tests in a directory
    //
    // If $target is null, then all files and subdirectories within the
    // directory whose case-insensitive name begins with 'test' is discovered.
    // Otherwise, discovery is only done for the file or directory specified in
    // $target. Directory fixtures are discovered and run in either case.
    if ($target === $dir) {
        $target = null;
    }
    $processed = namespace\_process_directory($state, $logger, $dir, $target);
    if (!$processed) {
        return;
    }

    list($setup, $teardown, $tests) = $processed;
    if ($setup) {
        $logger = namespace\start_buffering($logger, $setup);
        list($succeeded, $args) = namespace\_run_setup($logger, $setup, $setup, $args);
        $logger = namespace\end_buffering($logger);
        if (!$succeeded) {
            return;
        }
    }

    foreach ($tests as $test => $run) {
        $run($state, $logger, $test, $args, $target);
    }

    if ($teardown) {
        $logger = namespace\start_buffering($logger, $teardown);
        namespace\_run_teardown($logger, $teardown, $teardown, $args);
        $logger = namespace\end_buffering($logger);
    }
}


function _process_directory(State $state, Logger $logger, $path, $target) {
    $error = false;
    $target_found = false;
    $setup = [];
    $tests = [];

    foreach (new \DirectoryIterator($path) as $file) {
        $basename = $file->getBasename();
        $pathname = $file->getPathname();
        $type = $file->getType();

        if ('file' === $type) {
            if (0 === \strcasecmp($basename, 'setup.php')) {
                if ($setup) {
                    // Note the error but continue iterating so we can identify
                    // all errors
                    $error = true;
                }
                $setup[] = $pathname;
                continue;
            }
            if ($error || $target_found) {
                continue;
            }
            if (0 === \substr_compare($basename, 'test', 0, 4, true)
                && 0 === \strcasecmp($file->getExtension(), 'php'))
            {
                if (!$target || $target === $pathname) {
                    $tests[$pathname] = 'easytest\\_discover_file';
                    if ($target) {
                        $target_found = true;
                    }
                }
            }
            continue;
        }

        if ($error || $target_found) {
            continue;
        }

        if ('dir' === $type) {
            if (0 === \substr_compare($basename, 'test', 0, 4, true)) {
                // Ensure directory names end with a directory separator to
                // ensure we can only match against full directory names
                $pathname .= \DIRECTORY_SEPARATOR;
                if (!$target
                    || 0 === \substr_compare($target, $pathname, 0, \strlen($pathname)))
                {
                    $tests[$pathname] = 'easytest\\_discover_directory';
                    if ($target) {
                        $target_found = true;
                    }
                }
            }
            continue;
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

    if ($setup) {
        $result = namespace\_parse_setup($state, $logger, $setup[0]);
        if (!$result) {
            return false;
        }
        list($setup, $teardown) = $result;
    }
    else {
        $setup = null;
        $teardown = null;
    }

    return [$setup, $teardown, $tests];
}


function _parse_setup(State $state, Logger $logger, $file) {
    if (isset($state->files[$file])) {
        return $state->files[$file];
    }

    $tokens = namespace\_tokenize_file($logger, $file);
    if (!$tokens) {
        return false;
    }

    $error = 0;
    $ns = '';
    $setup = [];
    $teardown = [];
    for ($i = 0, $c = \count($tokens); $i < $c; ++$i) {
        if (!\is_array($tokens[$i])) {
            continue;
        }
        switch ($tokens[$i][0]) {
        case \T_FUNCTION:
            list($function, $i) = namespace\_parse_identifier($tokens, $i);
            if (!isset($function)) {
                break;
            }

            if (\preg_match('~^(setup|teardown)_?directory~i', $function, $matches)) {
                $function = "$ns$function";
                if ('setup' === \strtolower($matches[1])) {
                    if ($setup) {
                        $error |= ERROR_SETUP;
                    }
                    $setup[] = $function;
                }
                else {
                    if ($teardown) {
                        $error |= ERROR_TEARDOWN;
                    }
                    $teardown[] = $function;
                }
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }

    if ($error) {
        if ($error & ERROR_SETUP) {
            $logger->log_error(
                $file,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $setup)
                )
            );
        }
        if ($error & ERROR_TEARDOWN) {
            $logger->log_error(
                $file,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $teardown)
                )
            );
        }
        $state->files[$file] = false;
    }
    else {
        $state->files[$file] = [
            $setup ? $setup[0] : null,
            $teardown ? $teardown[0] : null
        ];
    }

    return $state->files[$file];
}


/*
 * Discover and run tests in a file.
 */
function _discover_file(State $state, Logger $logger, $file, $args) {
    if (isset($state->files[$file])) {
        $logger->log_skip($file, 'File has already been tested!');
        return;
    }
    $state->files[$file] = true;

    $tokens = namespace\_tokenize_file($logger, $file);
    if (!$tokens) {
        return;
    }

    $ns = '';
    for ($i = 0, $c = \count($tokens); $i < $c; ++$i) {
        if (!\is_array($tokens[$i])) {
            continue;
        }
        switch ($tokens[$i][0]) {
        case \T_CLASS:
            list($class, $i) = namespace\_parse_identifier($tokens, $i);
            if (!isset($class)) {
                break;
            }

            if (0 !== \substr_compare($class, 'test', 0, 4, true)) {
                break;
            }

            $class = "$ns$class";
            if (!isset($state->seen[$class]) && \class_exists($class)) {
                $state->seen[$class] = true;
                $logger = namespace\start_buffering($logger, $class);
                $object = namespace\_instantiate_test($logger, $class, $args);
                $logger = namespace\end_buffering($logger);
                if ($object) {
                    namespace\_run_test_case($logger, $class, $object);
                }
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }
}


function _tokenize_file(Logger $logger, $filename) {
    $logger = namespace\start_buffering($logger, $filename);
    $succeeded = namespace\_include_file($logger, $filename);
    $logger = namespace\end_buffering($logger);
    if (!$succeeded) {
        return;
    }

    try {
        $code = \file_get_contents($filename);
    }
    catch (\Throwable $e) {
        $logger->log_error($filename, $e);
        return false;
    }
    // #(BC 5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($filename, $e);
        return false;
    }
    if (false === $code) {
        $logger->log_error($filename, "Failed to read file (no error was thrown)");
        return false;
    }

    return \token_get_all($code);
}


function _parse_identifier($tokens, $i) {
        $identifier = null;
        // $i = keyword identifying the type of identifer ('class', 'function',
        // etc.) and $i+1 = whitespace
        $i += 2;
        while (true) {
            $token = $tokens[$i];
            if ('{' === $token) {
                break;
            }
            ++$i;
            if (\is_array($token) && \T_STRING === $token[0]) {
                $identifier = $token[1];
                break;
            }
        }

        // advance token index to the end of the definition
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

        return [$identifier, $i];
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


function _include_file(Logger $logger, $file) {
    try {
        namespace\_guard_include($file);
        return true;
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


function _run_setup(Logger $logger, $source, $callable, $args=null) {
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


function _run_teardown(Logger $logger, $source, $callable, $args = null) {
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


function _instantiate_test(Logger $logger, $class, $args) {
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


function _run_test_case(Logger $logger, $class, $object) {
    $methods = namespace\_process_methods($logger, $class, $object);
    if (!$methods) {
        return;
    }

    list($setup_object, $teardown_object, $setup, $teardown, $tests)
        = $methods;

    if ($setup_object) {
        $source = "$class::$setup_object";
        $callable = [$object, $setup_object];
        $logger = namespace\start_buffering($logger, $source);
        list($succeeded,) = namespace\_run_setup($logger, $source, $callable);
        $logger = namespace\end_buffering($logger);
        if (!$succeeded) {
            return;
        }
    }

    foreach ($tests as $test) {
        namespace\_run_test($logger, $class, $object, $test, $setup, $teardown);
    }

    if ($teardown_object) {
        $source = "$class::$teardown_object";
        $callable = [$object, $teardown_object];
        $logger = namespace\start_buffering($logger, $source);
        namespace\_run_teardown($logger, $source, $callable);
        $logger = namespace\end_buffering($logger);
    }
}


function _process_methods(Logger $logger, $class, $object) {
    $error = 0;
    $setup_object = [];
    $teardown_object = [];
    $setup = null;
    $teardown = null;
    $tests = [];

    foreach (\get_class_methods($object) as $method) {
        if (0 === \substr_compare($method, 'test', 0, 4, true)) {
            $tests[] = $method;
            continue;
        }

        if(\preg_match('~^(setup|teardown)(?:$|_?object)~i', $method, $matches)) {
            if (0 === \strcasecmp('setup', $matches[1])) {
                if ($matches[0] === $matches[1]) {
                    $setup = $method;
                }
                else {
                    if ($setup_object) {
                        $error |= namespace\ERROR_SETUP;
                    }
                    $setup_object[] = $method;
                }
            }
            else {
                if ($matches[0] === $matches[1]) {
                    $teardown = $method;
                }
                else {
                    if ($teardown_object) {
                        $error |= namespace\ERROR_TEARDOWN;
                    }
                    $teardown_object[] = $method;
                }
            }
            continue;
        }
    }

    if ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $class,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $setup_object)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $class,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $teardown_object)
                )
            );
        }
        return false;
    }
    if (!$tests) {
        return false;
    }

    return [
        $setup_object ? $setup_object[0] : null,
        $teardown_object ? $teardown_object[0] : null,
        $setup,
        $teardown,
        $tests,
    ];
}


function _run_test(Logger $logger, $class, $object, $test, $setup, $teardown) {
    $passed = true;

    if ($setup) {
        $source = "$setup for $class::$test";
        $callable = [$object, $setup];
        $logger = namespace\start_buffering($logger, $source);
        list($passed,) = namespace\_run_setup($logger, $source, $callable);
    }

    if ($passed) {
        $source = "$class::$test";
        $callable = [$object, $test];
        $logger = namespace\start_buffering($logger, $source);
        $passed = namespace\_run_test_method($logger, $source, $callable);

        if ($teardown) {
            $source = "$teardown for $class::$test";
            $callable = [$object, $teardown];
            $logger = namespace\start_buffering($logger, $source);
            $passed = namespace\_run_teardown($logger, $source, $callable)
                    && $passed;
        }
    }

    $logger = namespace\end_buffering($logger);
    if ($passed) {
        $logger->log_pass();
    }
}


function _run_test_method(Logger $logger, $source, $callable) {
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
