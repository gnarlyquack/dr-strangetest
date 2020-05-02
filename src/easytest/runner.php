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

const TYPE_DIRECTORY = 1;
const TYPE_FILE      = 2;
const TYPE_CLASS     = 3;
const TYPE_FUNCTION  = 4;



final class DirectoryTest extends struct {
    public $path;
    public $target;
    public $setup;
    public $teardown;
    public $paths = array();
}


final class FileTest extends struct {
    public $filepath;
    public $setup_file;
    public $teardown_file;
    public $setup_function;
    public $teardown_function;
    public $identifiers = array();
}


final class ClassTest extends struct {
    public $name;
    public $setup_object;
    public $teardown_object;
    public $setup_method;
    public $teardown_method;
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


final class TestContext {
    private $source;
    private $logger;
    private $success = true;


    public function __construct(Logger $logger, $source) {
        $this->logger = $logger;
        $this->source = $source;
    }


    public function __call($name, $args) {
        // This works, and will automatically pick up new assertions, but it'd
        // probably be best to provide actual class methods
        if ('assert' === \substr($name, 0, 6)
            && \function_exists("easytest\\$name"))
        {
            try {
                // #BC(5.5): Use proxy function for argument unpacking
                namespace\_unpack_function("easytest\\$name", $args);
                return;
            }
            catch (\AssertionError $e) {
                $this->logger->log_failure($this->source, $e);
            }
            // #BC(5.6): Catch Failure
            catch (Failure $e) {
                $this->logger->log_failure($this->source, $e);
            }
            catch (\Throwable $e) {
                $this->logger->log_error($this->source, $e);
            }
            // #BC(5.6): Catch Exception
            catch (\Exception $e) {
                $this->logger->log_error($this->source, $e);
            }
            $this->success = false;
        }

        throw new \Exception(
            \sprintf('Call to undefined method %s::%s()', __CLASS__, $name)
        );
    }


    public function subtest($callable) {
        try {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
            return;
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->source, $e);
        }
        // #BC(5.6): Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->source, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($this->source, $e);
        }
        // #BC(5.6): Catch Exception
        catch (\Exception $e) {
            $this->logger->log_error($this->source, $e);
        }
        $this->success = false;
    }


    public function succeeded() {
        return $this->success;
    }
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

        $path = $realpath;
        if (\is_dir($path)) {
            $path .= \DIRECTORY_SEPARATOR;
        }
        $root = namespace\_determine_root($path);
        $directory = namespace\_discover_directory(
            $state, $logger, $root, $path);
        if (!$directory) {
            continue;
        }
        namespace\_run_directory_tests($state, $logger, $directory, null);
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


function _discover_directory(State $state, Logger $logger, $path, $target) {
    // If $target is null, then all files and subdirectories within $path whose
    // case-insensitive name begins with 'test' are discovered. Otherwise,
    // discovery is only done for the file or directory specified in $target.
    // Directory fixtures are discovered in either case.
    $error = false;
    $target_found = false;
    $setup = array();
    $tests = array();
    if ($target === $path) {
        $target = null;
    }

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
                    $tests[$pathname] = namespace\TYPE_FILE;
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
                    $tests[$pathname] = namespace\TYPE_DIRECTORY;
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

    return new DirectoryTest($path, $target, $setup, $teardown, $tests);
}


function _run_directory_tests(State $state, Logger $logger, DirectoryTest $test, $args) {
    $logger->log_debug($test->path, namespace\DEBUG_DIRECTORY_ENTER);

    if ($test->setup) {
        $logger = namespace\start_buffering($logger, $test->setup);
        list($success, $args) = namespace\_run_setup(
            $logger, $test->setup, $test->setup, $args);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            $logger->log_debug($test->path, namespace\DEBUG_DIRECTORY_EXIT);
            return;
        }
        $logger->log_debug($test->setup, namespace\DEBUG_DIRECTORY_SETUP);
    }

    if ($args instanceof ArgumentLists) {
        $arglists = $args->arglists;
    }
    else {
        $arglists = array($args);
    }

    foreach ($test->paths as $path => $type) {
        switch ($type) {
        case namespace\TYPE_DIRECTORY:
            $directory = namespace\_discover_directory($state, $logger, $path, $test->target);
            if (!$directory) {
                break;
            }
            foreach ($arglists as $arglist) {
                namespace\_run_directory_tests($state, $logger, $directory, $arglist);
            }
            break;

        case namespace\TYPE_FILE:
            $file = namespace\_discover_file($state, $logger, $path);
            if (!$file) {
                break;
            }
            foreach ($arglists as $arglist) {
                namespace\_run_file_tests($state, $logger, $file, $arglist);
            }
            break;

        default:
            throw new \Exception("Unknown test type: $type");
            break;
        }
    }

    if ($test->teardown) {
        $logger = namespace\start_buffering($logger, $test->teardown);
        if(namespace\_run_teardown($logger, $test->teardown, $test->teardown, $args)) {
            $logger->log_debug($test->teardown, namespace\DEBUG_DIRECTORY_TEARDOWN);
        }
        $logger = namespace\end_buffering($logger);
    }

    $logger->log_debug($test->path, namespace\DEBUG_DIRECTORY_EXIT);
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
    $setup = array();
    $teardown = array();
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
        $state->files[$file] = array(
            $setup ? $setup[0] : null,
            $teardown ? $teardown[0] : null
        );
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


function _discover_file(State $state, Logger $logger, $filepath) {
    if (isset($state->files[$filepath])) {
        return $state->files[$filepath];
    }

    $file = new FileTest();

    $parsers = array(
        \T_CLASS => function($class, $fullname) use ($file) {
            return namespace\_is_test_class($file, $class, $fullname);
        },
        \T_FUNCTION => function($function, $fullname) use ($file) {
            return namespace\_is_test_function($file, $function, $fullname);
        },
    );
    if (!namespace\_parse_file($logger, $filepath, $parsers, $state->seen)) {
        $state->files[$filepath] = false;
    }
    else {
        $file->filepath = $filepath;
        $state->files[$filepath] = $file;
    }
    return $state->files[$filepath];
}


function _is_test_class(FileTest $file, $class, $fullname) {
    if (0 === \substr_compare($class, 'test', 0, 4, true)) {
        $file->identifiers[] = array($fullname, namespace\TYPE_CLASS);
        return true;
    }
    return false;
}


function _is_test_function(FileTest $file, $function, $fullname) {
    if (0 === \substr_compare($function, 'test', 0, 4, true)) {
        $file->identifiers[] = array($fullname, namespace\TYPE_FUNCTION);
        return true;
    }

    if (\preg_match('~^(setup|teardown)_?(file|function)~i', $function, $matches)) {
        if (0 === \strcasecmp('setup', $matches[1])) {
            if (0 === \strcasecmp('file', $matches[2])) {
                $file->setup_file = $fullname;
            }
            else {
                $file->setup_function = $fullname;
            }
        }
        else {
            if (0 === \strcasecmp('file', $matches[2])) {
                $file->teardown_file = $fullname;
            }
            else {
                $file->teardown_function = $fullname;
            }
        }
        return true;
    }
    return false;
}


function _parse_file(Logger $logger, $filepath, array $parsers, array &$seen) {
    $source = namespace\_read_file($logger, $filepath);
    if (!$source) {
        return false;
    }

    $ns = '';
    $tokens = \token_get_all($source);
    // Start with $i = 2 since all PHP code starts with '<?php' followed by
    // whitespace
    for ($i = 2, $c = \count($tokens); $i < $c; ++$i) {
        if (!\is_array($tokens[$i])) {
            continue;
        }

        $token = $tokens[$i];
        list($token_type, $token_name, ) = $tokens[$i];
        switch ($token_type) {
        case \T_CLASS:
        case \T_FUNCTION:
            // Always parse the identifier so we only match top-level
            // identifiers and not class methods, anonymous objects, etc.
            list($name, $i) = namespace\_parse_identifier($tokens, $i);
            if (!$name) {
                // anonymous object, although this test might be unnecessary?
                break;
            }
            if (!isset($parsers[$token_type])) {
                // whatever we're parsing doesn't care about this identifier
                break;
            }

            $fullname = "$ns$name";
            // functions and classes with identical names can coexist!
            $seenname = "$token_name $fullname";
            $exists = "{$token_name}_exists";
            // Handle conditionally-defined identifiers, i.e., we found the
            // identifier in the file, but it wasn't actually defined due to
            // some failed conditional. The identifier could have still been
            // defined previously -- e.g., perhaps the file has multiple
            // configuration-based implementations -- so we also need to
            // ensure we don't "rediscover" already-defined identifiers. Our
            // implementation here isn't necessarily foolproof, because we
            // only keep track of identifiers we've seen in files we've parsed,
            // however it seems like somebody would have to be doing something
            // very bizarre for us to have a false positive here
            if (isset($seen[$seenname]) || !$exists($fullname)) {
                break;
            }

            if ($parsers[$token_type]($name, $fullname)) {
                // whatever we're parsing cared about this identifier, so add
                // it to the "seen" list
                $seen[$seenname] = true;
            }
            break;

        case \T_NAMESPACE:
            list($ns, $i) = namespace\_parse_namespace($tokens, $i, $ns);
            break;
        }
    }
    return true;
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

    return array($identifier, $i);
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
            return array($ns ? "$ns\\" : '', $i);
        }

        if (!\is_array($tokens[$i])) {
            continue;
        }

        switch ($tokens[$i][0]) {
        case \T_NS_SEPARATOR:
            if (!$ns) {
                return array($current_ns, $i);
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


function _run_file_tests(State $state, Logger $logger, FileTest $file, $args) {
    if ($file->setup_file) {
        $logger = namespace\start_buffering($logger, $file->setup_file);
        list($success, $args) = namespace\_run_setup(
            $logger, $file->setup_file, $file->setup_file, $args);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            return;
        }
    }

    $test = new FunctionTest();
    if ($file->setup_function) {
        $test->setup = $test->setup_name = $file->setup_function;
    }
    if ($file->teardown_function) {
        $test->teardown = $test->teardown_name = $file->teardown_function;
    }

    if ($args instanceof ArgumentLists) {
        $arglists = $args->arglists;
    }
    else {
        $arglists = array($args);
    }

    foreach ($arglists as $file_args) {
        foreach ($file->identifiers as $identifier) {
            list($name, $type) = $identifier;
            switch ($type) {
            case namespace\TYPE_CLASS:
                $logger = namespace\start_buffering($logger, $name);
                $object = namespace\_instantiate_test($logger, $name, $file_args);
                $logger = namespace\end_buffering($logger);
                if ($object) {
                    namespace\_run_class_test($logger, $name, $object);
                }
                break;

            case namespace\TYPE_FUNCTION:
                $test->name = $name;
                $test->function = $name;
                namespace\_run_test($logger, $test, $file_args);
                break;

            default:
                throw new \Exception("Unknown test type: $type");
                break;
            }
        }
    }

    if ($file->teardown_file) {
        $logger = namespace\start_buffering($logger, $file->teardown_file);
        namespace\_run_teardown(
            $logger, $file->teardown_file, $file->teardown_file, $args);
        $logger = namespace\end_buffering($logger);
    }

}


function _run_setup(Logger $logger, $source, $callable, $args=null) {
    try {
        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            $result = namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            $result = \call_user_func($callable);
        }
        return array(true, $result ? $result : $args);
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
    return array(false, null);
}


function _run_teardown(Logger $logger, $source, $callable, $args) {
    try {
        if (!$args) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif ($args instanceof ArgumentLists) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $args->arglists);
        }
        else {
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
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
            return namespace\_unpack_construct($class, $args);
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
        $callable = array($object, $class->setup_object);
        $logger = namespace\start_buffering($logger, $source);
        list($success,) = namespace\_run_setup($logger, $source, $callable);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            return;
        }
    }

    $test = new FunctionTest();
    if ($class->setup_method) {
        $test->setup_name = $class->setup_method;
        $test->setup = array($object, $class->setup_method);
    }
    if ($class->teardown_method) {
        $test->teardown_name = $class->teardown_method;
        $test->teardown = array($object, $class->teardown_method);
    }
    foreach ($class->methods as $method) {
        $test->name = "{$class->name}::$method";
        $test->function = array($object, $method);
        namespace\_run_test($logger, $test, null);
    }

    if ($class->teardown_object) {
        $source = "{$class->name}::{$class->teardown_object}";
        $callable = array($object, $class->teardown_object);
        $logger = namespace\start_buffering($logger, $source);
        namespace\_run_teardown($logger, $source, $callable, null);
        $logger = namespace\end_buffering($logger);
    }
}


function _discover_class(Logger $logger, $class) {
    $error = 0;
    $setup_object = array();
    $teardown_object = array();
    $setup = null;
    $teardown = null;
    $methods = array();

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


function _run_test(Logger $logger, FunctionTest $test, $args) {
    $success = true;

    if ($test->setup) {
        $source = "{$test->setup_name} for {$test->name}";
        $logger = namespace\start_buffering($logger, $source);
        list($success, $args) = namespace\_run_setup($logger, $source, $test->setup, $args);
    }

    if ($success) {
        $context = new TestContext($logger, $test->name);
        $logger = namespace\start_buffering($logger, $test->name);
        $success = namespace\_run_test_function($logger, $test->name, $test->function, $args, $context);

        if ($test->teardown) {
            $source = "{$test->teardown_name} for {$test->name}";
            $logger = namespace\start_buffering($logger, $source);
            $success = namespace\_run_teardown($logger, $source, $test->teardown, $args)
                    && $success;
        }
    }

    $logger = namespace\end_buffering($logger);
    if ($success) {
        $logger->log_pass($test->name);
    }
}


function _run_test_function(Logger $logger, $source, $callable, $args, TestContext $context) {
    try {
        if ($args) {
            $args[] = $context;
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $context);
        }
        return $context->succeeded();
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
