<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const ERROR_SETUP             = 0x1;
const ERROR_TEARDOWN          = 0x2;
const ERROR_SETUP_FUNCTION    = 0x4;
const ERROR_TEARDOWN_FUNCTION = 0x8;

const DEBUG_DIRECTORY_ENTER    = 1;
const DEBUG_DIRECTORY_EXIT     = 2;
const DEBUG_DIRECTORY_SETUP    = 3;
const DEBUG_DIRECTORY_TEARDOWN = 4;

const TYPE_DIRECTORY = 1;
const TYPE_FILE      = 2;
const TYPE_CLASS     = 3;
const TYPE_FUNCTION  = 4;



final class State extends struct {
    public $seen = array();
    public $directories = array();
    public $files = array();
    public $classes = array();
}


final class DirectoryTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    public $tests = array();
}


final class FileTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    public $tests = array();

    public $setup_function;
    public $setup_function_name;
    public $teardown_function;
    public $teardown_function_name;
}


final class ClassTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    public $tests = array();

    public $setup_function;
    public $teardown_function;
}


final class FunctionTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    public $test;

    public $setup_name;
    public $teardown_name;
}


final class Context {
    public function __construct(Logger $logger, $name) {
        $this->logger = $logger;
        $this->name = $name;
    }


    public function __call($name, $args) {
        // This works, and will automatically pick up new assertions, but it'd
        // probably be best to implement actual class methods
        if ('assert' === \substr($name, 0, 6)
            && \function_exists("easytest\\$name"))
        {
            try {
                // #BC(5.5): Use proxy function for argument unpacking
                namespace\_unpack_function("easytest\\$name", $args);
                return true;
            }
            catch (\AssertionError $e) {
                $this->logger->log_failure($this->name, $e);
            }
            // #BC(5.6): Catch Failure
            catch (Failure $e) {
                $this->logger->log_failure($this->name, $e);
            }
            catch (\Throwable $e) {
                $this->logger->log_error($this->name, $e);
            }
            // #BC(5.6): Catch Exception
            catch (\Exception $e) {
                $this->logger->log_error($this->name, $e);
            }
            $this->success = false;
            return false;
        }

        throw new \Exception(
            \sprintf('Call to undefined method %s::%s()', __CLASS__, $name)
        );
    }


    public function subtest($callable) {
        try {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
            return true;
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->name, $e);
        }
        // #BC(5.6): Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->name, $e);
        }
        catch (\Throwable $e) {
            $this->logger->log_error($this->name, $e);
        }
        // #BC(5.6): Catch Exception
        catch (\Exception $e) {
            $this->logger->log_error($this->name, $e);
        }
        $this->success = false;
        return false;
    }


    public function teardown($callable) {
        $this->teardowns[] = $callable;
    }


    public function succeeded() {
        return $this->success;
    }

    public function teardowns() {
        return $this->teardowns;
    }


    private $name;
    private $logger;
    private $success = true;
    private $teardowns = array();
}


function discover_tests(Logger $logger, array $paths) {
    list($root, $paths) = namespace\_process_paths($logger, $paths);
    if (!$paths) {
        return;
    }

    $state = new State();
    $directory = namespace\_discover_directory($state, $logger, $root);
    if (!$directory) {
        return;
    }

    namespace\_run_tests($state, $logger, $directory, null, array(), $paths);
}


function _process_paths(Logger $logger, array $paths) {
    if (!$paths) {
        $paths[] = \getcwd() . \DIRECTORY_SEPARATOR;
        $root = namespace\_determine_root($paths[0]);
        return array($root, $paths);
    }

    $root = null;
    $realpaths = array();
    foreach ($paths as $path) {
        $realpath = \realpath($path);
        if (!$realpath) {
            $logger->log_error($path, 'No such file or directory');
            continue;
        }

        if (\is_dir($realpath)) {
            $realpath .= \DIRECTORY_SEPARATOR;
        }

        if (!$root) {
            $root = namespace\_determine_root($realpath);
        }

        $realpaths[] = $realpath;
    }
    return array($root, $realpaths);
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
        // dirname() doesn't include a trailing directory separator, so we add
        // one before returning the determined root directory. However, if the
        // current path is determined to be the root, dirname() is never
        // called, so we want to avoid adding an extra directory separator
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


function _parse_file(Logger $logger, $filepath, array $checks, array &$seen) {
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
            if (!isset($checks[$token_type])) {
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

            if ($checks[$token_type]($name, $fullname)) {
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
    $success = namespace\_include_file($logger, $filepath);
    $logger = namespace\end_buffering($logger);
    if (!$success) {
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
        // file_get_contents() can return false if it fails. Presumably an
        // error would have been generated and already handled above, but the
        // documentation isn't explicit
        $logger->log_error($filename, "Failed to read file (no error was raised)");
    }
    return $source;
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
    //    In the second case, the identifier is optional
    //
    // 2) This is a use of the namespace operator, which takes the form:
    //      namespace\identifier
    //
    // Consequently, if the namespace separator '\' is the first non-whitespace
    // token found after the 'namespace' keyword, this isn't a namespace
    // declaration. Otherwise, everything until the terminating ';' or '{'
    // constitutes the identifier.
    $ns = array();
    while (++$i) {
        if ($tokens[$i] === ';' || $tokens[$i] === '{') {
            return array($ns ? (\implode('', $ns) . '\\') : '', $i);
        }

        if (!\is_array($tokens[$i])) {
            continue;
        }

        switch ($tokens[$i][0]) {
        case \T_NS_SEPARATOR:
            if (!$ns) {
                return array($current_ns, $i);
            }
            $ns[] = $tokens[$i][1];
            break;

        case \T_STRING:
            $ns[] = $tokens[$i][1];
            break;
        }
    }
}


function _discover_directory(State $state, Logger $logger, $path) {
    if (isset($state->directories[$path])) {
        return $state->directories[$path];
    }

    $directory = new DirectoryTest();
    $directory->name = $path;
    $error = false;
    foreach (new \DirectoryIterator($path) as $file) {
        $basename = $file->getBasename();
        $pathname = $file->getPathname();
        $type = $file->getType();

        if ('file' === $type) {
            if (0 === \strcasecmp($basename, 'setup.php')) {
                if ($directory->setup) {
                    // Note the error but continue iterating so we can identify
                    // all errors
                    $error = true;
                }
                $directory->setup[] = $pathname;
                continue;
            }
            if ($error) {
                continue;
            }
            if (0 === \substr_compare($basename, 'test', 0, 4, true)
                && 0 === \strcasecmp($file->getExtension(), 'php'))
            {
                $directory->tests[$pathname] = namespace\TYPE_FILE;
            }
            continue;
        }

        if ($error) {
            continue;
        }

        if ('dir' === $type) {
            if (0 === \substr_compare($basename, 'test', 0, 4, true)) {
                // Ensure directory names end with a directory separator to
                // ensure we can only match against full directory names
                $pathname .= \DIRECTORY_SEPARATOR;
                $directory->tests[$pathname] = namespace\TYPE_DIRECTORY;
            }
            continue;
        }
    }

    if ($error) {
        $logger->log_error(
            $path,
            \sprintf(
                "Multiple setup files found:\n\t%s",
                \implode("\n\t", $directory->setup)
            )
        );
        $directory = false;
    }
    elseif (!$directory->tests) {
        // Should this be logged/reported?
        $directory = false;
    }
    elseif ($directory->setup) {
        $setup = $directory->setup[0];
        $directory->setup = null;
        $directory = namespace\_discover_directory_setup(
            $logger, $directory, $setup, $state->seen
        );
    }

    $state->directories[$path] = $directory;
    return $directory;
}


function _discover_directory_setup(
    Logger $logger, DirectoryTest $directory, $filepath, array &$seen
) {
    $error = 0;
    $checks = array(
        \T_FUNCTION => function($function, $fullname) use ($directory, &$error) {
            return namespace\_is_directory_setup_function(
                $directory, $error, $function, $fullname
            );
        },
    );
    if (!namespace\_parse_file($logger, $filepath, $checks, $seen)) {
        return false;
    }

    if ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $directory->setup)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $directory->teardown)
                )
            );
        }
        return false;
    }

    $directory->setup = $directory->setup ? $directory->setup[0] : null;
    $directory->teardown = $directory->teardown ? $directory->teardown[0] : null;
    return $directory;
}


function _is_directory_setup_function(
    DirectoryTest $directory, &$error, $function, $fullname
) {
    if (\preg_match('~^(setup|teardown)_?directory~i', $function, $matches)) {
        if ('setup' === \strtolower($matches[1])) {
            if ($directory->setup) {
                $error |= namespace\ERROR_SETUP;
            }
            $directory->setup[] = $fullname;
        }
        else {
            if ($directory->teardown) {
                $error |= namespace\ERROR_TEARDOWN;
            }
            $directory->teardown[] = $fullname;
        }
        return true;
    }
    return false;
}


function _discover_file(State $state, Logger $logger, $filepath) {
    if (isset($state->files[$filepath])) {
        return $state->files[$filepath];
    }

    $file = new FileTest();
    $file->name = $filepath;
    $error = 0;
    $checks = array(
        \T_CLASS => function($class, $fullname) use ($file) {
            return namespace\_is_test_class($file, $class, $fullname);
        },
        \T_FUNCTION => function($function, $fullname) use ($file, &$error) {
            return namespace\_is_test_function($file, $error, $function, $fullname);
        },
    );
    if (!namespace\_parse_file($logger, $filepath, $checks, $state->seen)) {
        $file = false;
    }
    elseif ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $directory->setup)
                )
            );
        }
        if ($error & namespace\ERROR_SETUP_FUNCTION) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $directory->setup_function)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $directory->teardown)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN_FUNCTION) {
            $logger->log_error(
                $filepath,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $directory->teardown_function)
                )
            );
        }
        $file = false;
    }
    elseif (!$file->tests) {
        // Should this be logged/reported?
        $file = false;
    }
    else {
        $file->setup = $file->setup ? $file->setup[0] : null;
        $file->teardown = $file->teardown ? $file->teardown[0] : null;
        $file->setup_function
            = $file->setup_function ? $file->setup_function[0] : null;
        $file->teardown_function
            = $file->teardown_function ? $file->teardown_function[0] : null;
    }

    $state->files[$filepath] = $file;
    return $file;
}


function _is_test_class(FileTest $file, $class, $fullname) {
    if (0 === \substr_compare($class, 'test', 0, 4, true)) {
        $file->tests["class $fullname"] = array($fullname, namespace\TYPE_CLASS);
        return true;
    }
    return false;
}


function _is_test_function(FileTest $file, &$error, $function, $fullname) {
    if (0 === \substr_compare($function, 'test', 0, 4, true)) {
        $file->tests["function $fullname"] = array($fullname, namespace\TYPE_FUNCTION);
        return true;
    }

    if (\preg_match('~^(setup|teardown)_?(file|function)~i', $function, $matches)) {
        if (0 === \strcasecmp('setup', $matches[1])) {
            if (0 === \strcasecmp('file', $matches[2])) {
                if ($file->setup) {
                    $error |= namespace\ERROR_SETUP;
                }
                $file->setup[] = $fullname;
            }
            else {
                if ($file->setup_function) {
                    $error |= namespace\ERROR_SETUP_FUNCTION;
                }
                $file->setup_function[] = $fullname;
                $file->setup_function_name = $function;
            }
        }
        else {
            if (0 === \strcasecmp('file', $matches[2])) {
                if ($file->teardown) {
                    $error |= namespace\ERROR_TEARDOWN;
                }
                $file->teardown[] = $fullname;
            }
            else {
                if ($file->teardown_function) {
                    $error |= namespace\ERROR_TEARDOWN_FUNCTION;
                }
                $file->teardown_function[] = $fullname;
                $file->teardown_function_name = $function;
            }
        }
        return true;
    }
    return false;
}


function _discover_class(State $state, Logger $logger, $classname) {
    if (isset($state->classes[$classname])) {
        return $state->classes[$classname];
    }

    $class = new ClassTest();
    $class->name = $classname;
    $error = 0;
    foreach (\get_class_methods($classname) as $method) {
        if (0 === \substr_compare($method, 'test', 0, 4, true)) {
            $class->tests[] = $method;
            continue;
        }

        if(\preg_match('~^(setup|teardown)(?:_?object)?$~i', $method, $matches)) {
            if (0 === \strcasecmp('setup', $matches[1])) {
                if ($matches[0] === $matches[1]) {
                    $class->setup_function = $method;
                }
                else {
                    if ($class->setup) {
                        $error |= namespace\ERROR_SETUP;
                    }
                    $class->setup[] = $method;
                }
            }
            else {
                if ($matches[0] === $matches[1]) {
                    $class->teardown_function = $method;
                }
                else {
                    if ($class->teardown) {
                        $error |= namespace\ERROR_TEARDOWN;
                    }
                    $class->teardown[] = $method;
                }
            }
            continue;
        }
    }

    if ($error) {
        if ($error & namespace\ERROR_SETUP) {
            $logger->log_error(
                $classname,
                \sprintf(
                    "Multiple setup fixtures found:\n\t%s",
                    \implode("\n\t", $class->setup)
                )
            );
        }
        if ($error & namespace\ERROR_TEARDOWN) {
            $logger->log_error(
                $classname,
                \sprintf(
                    "Multiple teardown fixtures found:\n\t%s",
                    \implode("\n\t", $class->teardown)
                )
            );
        }
        $class = false;
    }
    elseif (!$class->tests) {
        // Should this be logged/reported?
        $class = false;
    }
    else {
        $class->setup = $class->setup ? $class->setup[0] : null;
        $class->teardown = $class->teardown ? $class->teardown[0] : null;
    }

    $state->classes[$classname] = $class;
    return $class;
}


function _run_tests(
    State $state, Logger $logger, $test,
    $params = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        $targets = namespace\_find_targets($logger, $test, $targets);
    }

    if ($params instanceof ArgumentLists) {
        $arglists = $params->arglists();
    }
    else {
        $arglists = array($params);
    }

    foreach ($arglists as $i => $arglist) {
        if ($arglist
            // #BC(7.0): don't use is_iterable to check if iterable
            && !(\is_array($arglist) || $arglist instanceof \Traversable)
        ) {
            // $arglists can only be invalid if $params is an instance of
            // ArgumentLists
            $type = \is_object($arglist)
                ? \sprintf('instance of %s', \get_class($arglist) )
                : \sprintf(
                    '%s %s',
                    \gettype($arglist), \var_export($arglist, true));
            $logger->log_error(
                $params->source,
                "Each argument list must be iterable, instead got $type"
            );
            continue;
        }

        $this_run_id = $run_id;
        if (\count($arglists) > 1) {
            $this_run_id[] = $i;
        }

        $type = \get_class($test);
        switch ($type) {
        case 'easytest\\DirectoryTest':
            namespace\_run_directory_tests(
                $state, $logger, $test, $arglist, $this_run_id, $targets
            );
            break;

        case 'easytest\\FileTest':
            namespace\_run_file_tests(
                $state, $logger, $test, $arglist, $this_run_id, $targets
            );
            break;

        case 'easytest\\ClassTest':
            namespace\_run_class_tests(
                $logger, $test, $arglist, $this_run_id, $targets
            );
            break;

        case 'easytest\\FunctionTest':
            namespace\_run_function_test(
                $logger, $test, $arglist, $this_run_id, $targets
            );
            break;

        default:
            // #BC(5.4): Omit description from assert
            \assert(false); // "Unknown test type $type"
            break;
        }
    }
}


function _find_targets(Logger $logger, $test, array $targets) {
    $current = null;
    $parents = array();
    $children = array();
    foreach ($targets as $target) {
        if ($target === $test->name) {
            // The entire path is a target, so test the entire path. Any
            // other targets are just duplicates, which we can skip
            return null;
        }

        if (isset($test->tests[$target])) {
            $parent = $target;
        }
        elseif (0 === \substr_compare($target, $test->name, 0, \strlen($test->name))) {
            // $target is in a subdirectory of the current directory.
            // $i = the location of the directory separator, which we want
            // to include in the subdirectory name
            $i = \strpos($target, \DIRECTORY_SEPARATOR, \strlen($test->name));
            $parent = \substr($target, 0, $i+1);
        }
        else {
            $logger->log_error($test->name, "Invalid test requested: $target");
            continue;
        }

        if ($parent === $current) {
            $children[] = $target;
        }
        else {
            if ($current) {
                $parents[] = array($current, $children);
            }
            $current = $parent;
            $children = array($target);
        }
    }
    if ($current) {
        $parents[] = array($current, $children);
    }
    return $parents;
}


function _run_directory_tests(
    State $state, Logger $logger, DirectoryTest $directory,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $logger->log_debug($directory->name, namespace\DEBUG_DIRECTORY_ENTER);

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($directory->setup) {
        $name = "{$directory->setup}{$run_name}";
        $logger = namespace\start_buffering($logger, $name);
        list($success, $arglist) = namespace\_run_setup(
            $logger, $name, $directory->setup, $arglist);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            $logger->log_debug($directory->name, namespace\DEBUG_DIRECTORY_EXIT);
            return;
        }
        $arglist = namespace\_normalize_arglists($arglist, $name);
        $logger->log_debug($name, namespace\DEBUG_DIRECTORY_SETUP);
    }

    if ($targets) {
        foreach ($targets as $target) {
            list($test, $targets) = $target;
            namespace\_run_directory_test(
                $state, $logger, $directory, $test, $arglist, $run_id, $targets
            );
        }
    }
    else {
        foreach ($directory->tests as $test => $_) {
            namespace\_run_directory_test(
                $state, $logger, $directory, $test, $arglist, $run_id
            );
        }
    }

    if ($directory->teardown) {
        $name = "{$directory->teardown}{$run_name}";
        $logger = namespace\start_buffering($logger, $name);
        if(namespace\_run_teardown($logger, $name, $directory->teardown, $arglist)) {
            $logger->log_debug($name, namespace\DEBUG_DIRECTORY_TEARDOWN);
        }
        $logger = namespace\end_buffering($logger);
    }

    $logger->log_debug($directory->name, namespace\DEBUG_DIRECTORY_EXIT);
}


function _run_directory_test(
    State $state, Logger $logger, DirectoryTest $directory, $test,
    $arglist = null, array $run_id = null, array $targets = null
) {
    $type = $directory->tests[$test];
    switch ($type) {
    case namespace\TYPE_DIRECTORY:
        $test = namespace\_discover_directory($state, $logger, $test);
        break;

    case namespace\TYPE_FILE:
        $test = namespace\_discover_file($state, $logger, $test);
        break;

    default:
        // #BC(5.4): Omit description from assert
        \assert(false); // "Unknown directory test type: $type"
        break;
    }

    if ($test) {
        namespace\_run_tests($state, $logger, $test, $arglist, $run_id, $targets);
    }
}


function _run_file_tests(
    State $state, Logger $logger, FileTest $file,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    // #BC(5.4): Omit description from assert
    \assert(!$targets); // file test targets aren't supported

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($file->setup) {
        $name = "{$file->setup}{$run_name}";
        $logger = namespace\start_buffering($logger, $name);
        list($success, $arglist) = namespace\_run_setup(
            $logger, $name, $file->setup, $arglist);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            return;
        }
        $arglist = namespace\_normalize_arglists($arglist, $name);
    }

    $function = new FunctionTest();
    if ($file->setup_function) {
        $function->setup_name = $file->setup_function_name;
        $function->setup = $file->setup_function;
    }
    if ($file->teardown_function) {
        $function->teardown_name = $file->teardown_function_name;
        $function->teardown = $file->teardown_function;
    }

    foreach ($file->tests as $test) {
        list($name, $type) = $test;
        switch ($type) {
        case namespace\TYPE_CLASS:
            $class = namespace\_discover_class($state, $logger, $name);
            if ($class) {
                namespace\_run_tests(
                    $state, $logger, $class, $arglist, $run_id, $targets
                );
            }
            break;

        case namespace\TYPE_FUNCTION:
            $function->name = $name;
            $function->test = $name;
            namespace\_run_tests(
                $state, $logger, $function, $arglist, $run_id, $targets
            );
            break;

        default:
            // #BC(5.4): Omit description from assert
            \assert(false); // "Unknown file test type: $type"
            break;
        }
    }

    if ($file->teardown) {
        $name = "{$file->teardown}{$run_name}";
        $logger = namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown, $arglist);
        $logger = namespace\end_buffering($logger);
    }
}


function _run_class_tests(
    Logger $logger, ClassTest $class,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    // #BC(5.4): Omit description from assert
    \assert(!$targets); // class test targets aren't supported

    $logger = namespace\start_buffering($logger, $class->name);
    $object = namespace\_instantiate_test($logger, $class->name, $arglist);
    $logger = namespace\end_buffering($logger);
    if (!$object) {
        return;
    }

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($class->setup) {
        $name = "{$class->name}::{$class->setup}{$run_name}";
        $method = array($object, $class->setup);
        $logger = namespace\start_buffering($logger, $name);
        list($success,) = namespace\_run_setup($logger, $name, $method);
        $logger = namespace\end_buffering($logger);
        if (!$success) {
            return;
        }
    }

    $test = new FunctionTest();
    if ($class->setup_function) {
        $test->setup = array($object, $class->setup_function);
        $test->setup_name = $class->setup_function;
    }
    if ($class->teardown_function) {
        $test->teardown = array($object, $class->teardown_function);
        $test->teardown_name = $class->teardown_function;
    }
    foreach ($class->tests as $method) {
        $test->name = "{$class->name}::{$method}";
        $test->test = array($object, $method);
        namespace\_run_function_test($logger, $test, null, $run_id);
    }

    if ($class->teardown) {
        $name = "{$class->name}::{$class->teardown}{$run_name}";
        $method = array($object, $class->teardown);
        $logger = namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $method);
        $logger = namespace\end_buffering($logger);
    }
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


function _run_function_test(
    Logger $logger, FunctionTest $test,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    // #BC(5.4): Omit description from assert
    \assert(!$targets); // function tests can't have targets

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    $test_name = "{$test->name}{$run_name}";
    $success = true;

    if ($test->setup) {
        $name = "{$test->setup_name} for {$test_name}";
        $logger = namespace\start_buffering($logger, $name);
        list($success, $arglist) = namespace\_run_setup(
            $logger, $name, $test->setup, $arglist
        );
        if (!$success) {
            $logger = namespace\end_buffering($logger);
            return;
        }
    }

    $logger = namespace\start_buffering($logger, $test_name);
    $context = new Context($logger, $test_name);
    $success = namespace\_run_test_function(
        $logger, $test_name, $test->test, $context, $arglist
    );

    foreach($context->teardowns() as $teardown) {
        $success = namespace\_run_teardown($logger, $test_name, $teardown)
                && $success;
    }
    if ($test->teardown) {
        $name = "{$test->teardown_name} for {$test_name}";
        $logger = namespace\start_buffering($logger, $name);
        if(!namespace\_run_teardown($logger, $name, $test->teardown, $arglist)) {
            $logger = namespace\end_buffering($logger);
            return;
        }
    }

    $logger = namespace\end_buffering($logger);
    if ($success) {
        $logger->log_pass($test_name);
    }
}


function _run_setup(Logger $logger, $name, $callable, array $args = null) {
    try {
        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            $result = namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            $result = \call_user_func($callable);
        }
        return array(true, $result);
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return array(false, null);
}


function _run_test_function(
    Logger $logger, $name, $callable, Context $context, array $args = null
) {
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
        $logger->log_failure($name, $e);
    }
    // #BC(5.6): Catch Failure
    catch (Failure $e) {
        $logger->log_failure($name, $e);
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return false;
}


function _run_teardown(Logger $logger, $name, $callable, $args = null) {
    try {
        if ($args instanceof ArgumentLists) {
            $args = $args->arglists();
            if (\count($args) > 1) {
                // #BC(5.3): Invoke (possible) object method using
                // call_user_func()
                \call_user_func($callable, $args);
                return true;
            }
            $args = $args[0];
        }

        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
        }
        else {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        return true;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return false;
}
