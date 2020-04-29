<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const ERROR_SETUP    = 0x01;
const ERROR_TEARDOWN = 0x02;

const DEBUG_DIRECTORY_ENTER    = 1;
const DEBUG_DIRECTORY_EXIT     = 2;
const DEBUG_DIRECTORY_SETUP    = 3;
const DEBUG_DIRECTORY_TEARDOWN = 4;

const TYPE_CLASS    = 1;
const TYPE_FUNCTION = 2;



final class ClassTest extends struct {
    public $name;
    public $setup_object;
    public $teardown_object;
    public $setup;
    public $teardown;
    public $methods;
}


final class FunctionTest extends struct {
    public $name;
    public $function;
    public $setup_name;
    public $setup;
    public $teardown_name;
    public $teardown;
}


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
    $logger->log_debug($dir, namespace\DEBUG_DIRECTORY_ENTER);

    list($setup, $teardown, $tests) = $processed;
    if ($setup) {
        $logger = namespace\start_buffering($logger, $setup);
        list($succeeded, $args) = namespace\_run_setup($logger, $setup, $setup, $args);
        $logger = namespace\end_buffering($logger);
        if (!$succeeded) {
            $logger->log_debug($dir, namespace\DEBUG_DIRECTORY_EXIT);
            return;
        }
        $logger->log_debug($setup, namespace\DEBUG_DIRECTORY_SETUP);
    }

    foreach ($tests as $test => $run) {
        $run($state, $logger, $test, $args, $target);
    }

    if ($teardown) {
        $logger = namespace\start_buffering($logger, $teardown);
        if(namespace\_run_teardown($logger, $teardown, $teardown, $args)) {
            $logger->log_debug($teardown, namespace\DEBUG_DIRECTORY_TEARDOWN);
        }
        $logger = namespace\end_buffering($logger);
    }

    $logger->log_debug($dir, namespace\DEBUG_DIRECTORY_EXIT);
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


function _discover_file(State $state, Logger $logger, $filepath, $args) {
    if (isset($state->files[$filepath])) {
        $logger->log_skip($filepath, 'File has already been tested!');
        return;
    }
    $state->files[$filepath] = true;

    $identifiers = namespace\_parse_test_file($logger, $filepath, $state->seen);
    if (!$identifiers) {
        return;
    }

    $test = new FunctionTest();
    foreach ($identifiers as $identifier) {
        list($name, $type) = $identifier;
        switch ($type) {
        case namespace\TYPE_CLASS:
            $logger = namespace\start_buffering($logger, $name);
            $object = namespace\_instantiate_test($logger, $name, $args);
            $logger = namespace\end_buffering($logger);
            if ($object) {
                namespace\_run_class_test($logger, $name, $object);
            }
            break;

        case namespace\TYPE_FUNCTION:
            $test->name = $name;
            $test->function = $name;
            namespace\_run_test($logger, $test);
            break;

        default:
            throw new \Exception("Unknown test type: $type");
            break;
        }
    }
}


function _parse_test_file(Logger $logger, $filepath, array &$seen) {
    $tests = [];
    $source = namespace\_read_file($logger, $filepath);
    if (!$source) {
        return $tests;
    }

    $ns = '';
    $tokens = \token_get_all($source);
    for ($i = 0, $c = \count($tokens); $i < $c; ++$i) {
        if (!\is_array($tokens[$i])) {
            continue;
        }
        switch ($tokens[$i][0]) {
        case \T_CLASS:
            list($class, $i) = namespace\_parse_identifier($tokens, $i);
            if (!$class) {
                break;
            }

            if (0 !== \substr_compare($class, 'test', 0, 4, true)) {
                break;
            }

            $fullname = "$ns$class";
            $seenname = "class $fullname";
            if (!isset($seen[$seenname]) && \class_exists($fullname)) {
                $seen[$seenname] = true;
                $tests[] = [$fullname, namespace\TYPE_CLASS];
            }
            break;

        case \T_FUNCTION:
            list($function, $i) = namespace\_parse_identifier($tokens, $i);
            if (!$function) {
                break;
            }

            if (0 !== \substr_compare($function, 'test', 0, 4, true)) {
                break;
            }

            $fullname = "$ns$function";
            $seenname = "function $fullname";
            if (!isset($seen[$seenname]) && \function_exists($fullname)) {
                $seen[$seenname] = true;
                $tests[] = [$fullname, namespace\TYPE_FUNCTION];
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }
    return $tests;
}


function _read_file(Logger $logger, $filepath) {
    // First include the file to ensure it parses correctly
    $logger = namespace\start_buffering($logger, $filepath);
    $succeeded = namespace\_include_file($logger, $filepath);
    $logger = namespace\end_buffering($logger);
    if (!$succeeded) {
        return false;
    }

    try {
        $source = \file_get_contents($filepath);
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

    if (false === $source) {
        $logger->log_error($filename, "Failed to read file (no error was raised)");
    }
    return $source;
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


function _guard_include($file) {
    // Isolate included files to prevent them from meddling with local state
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


function _run_class_test(Logger $logger, $class, $object) {
    $class = namespace\_discover_class($logger, $class);
    if (!$class) {
        return;
    }

    if ($class->setup_object) {
        $source = "{$class->name}::{$class->setup_object}";
        $callable = [$object, $class->setup_object];
        $logger = namespace\start_buffering($logger, $source);
        list($success,) = namespace\_run_setup($logger, $source, $callable);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            return;
        }
    }

    $test = new FunctionTest();
    if ($class->setup) {
        $test->setup_name = $class->setup;
        $test->setup = [$object, $class->setup];
    }
    if ($class->teardown) {
        $test->teardown_name = $class->teardown;
        $test->teardown = [$object, $class->teardown];
    }
    foreach ($class->methods as $method) {
        $test->name = "{$class->name}::$method";
        $test->function = [$object, $method];
        namespace\_run_test($logger, $test);
    }

    if ($class->teardown_object) {
        $source = "{$class->name}::{$class->teardown_object}";
        $callable = [$object, $class->teardown_object];
        $logger = namespace\start_buffering($logger, $source);
        namespace\_run_teardown($logger, $source, $callable);
        $logger = namespace\end_buffering($logger);
    }
}


function _discover_class(Logger $logger, $class) {
    $error = 0;
    $setup_object = [];
    $teardown_object = [];
    $setup = null;
    $teardown = null;
    $methods = [];

    foreach (\get_class_methods($class) as $method) {
        if (0 === \substr_compare($method, 'test', 0, 4, true)) {
            $methods[] = $method;
            continue;
        }

        if(\preg_match('~^(setup|teardown)(?:_?object)?$~i', $method, $matches)) {
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
    if (!$methods) {
        return false;
    }

    return new ClassTest(
        $class,
        $setup_object ? $setup_object[0] : null,
        $teardown_object ? $teardown_object[0] : null,
        $setup,
        $teardown,
        $methods
    );
}


function _run_test(Logger $logger, FunctionTest $test) {
    $success = true;

    if ($test->setup) {
        $source = "{$test->setup_name} for {$test->name}";
        $logger = namespace\start_buffering($logger, $source);
        list($success,) = namespace\_run_setup($logger, $source, $test->setup);
    }

    if ($success) {
        $logger = namespace\start_buffering($logger, $test->name);
        $success = namespace\_run_test_function($logger, $test->name, $test->function);

        if ($test->teardown) {
            $source = "{$test->teardown_name} for {$test->name}";
            $logger = namespace\start_buffering($logger, $source);
            $success = namespace\_run_teardown($logger, $source, $test->teardown)
                    && $success;
        }
    }

    $logger = namespace\end_buffering($logger);
    if ($success) {
        $logger->log_pass($test->name);
    }
}


function _run_test_function(Logger $logger, $source, $callable) {
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
