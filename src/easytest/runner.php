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

const TYPE_DIRECTORY = 1;
const TYPE_FILE      = 2;
const TYPE_CLASS     = 3;
const TYPE_FUNCTION  = 4;

const RESULT_PASS     = 0x0;
const RESULT_FAIL     = 0x1;
const RESULT_POSTPONE = 0x2;



final class Postpone extends \Exception {}


final class State extends struct {
    public $seen = array();
    public $directories = array();
    public $files = array();
    public $classes = array();
    public $results = array();
    public $depends = array();
    public $fixture = array();
}


final class TestInfo extends struct {
    public $type;
    public $filename;
    public $namespace;
    public $name;
}


final class DirectoryTest extends struct {
    public $name;
    public $setup;
    public $teardown;
    public $tests = array();


    public function setup(
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        return namespace\_run_directory_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        namespace\_run_directory_teardown($logger, $this, $args, $run);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\_run_directory_tests($state, $logger, $this, $args, $run, $targets);
    }
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


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\_run_file_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        $args = null,
        $run = null
    ) {
        namespace\_run_file_teardown($logger, $this, $args, $run);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\_run_file_tests($state, $logger, $this, $args, $run, $targets);
    }
}


final class ClassTest extends struct {
    public $file;
    public $namespace;

    public $name;
    public $object;
    public $setup;
    public $teardown;

    public $setup_function;
    public $teardown_function;
    public $tests = array();


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\_run_class_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        \assert(!$args);
        namespace\_run_class_teardown($logger, $this, $run);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\_run_class_tests($state, $logger, $this, $args, $run, $targets);
    }
}


final class FunctionTest extends struct {
    public $file;
    public $namespace;
    public $class;
    public $function;

    public $name;
    public $setup;
    public $teardown;
    public $test;
    public $result;

    public $setup_name;
    public $teardown_name;


    public function setup(
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        return namespace\_run_function_setup($logger, $this, $args, $run);
    }


    public function teardown(
        State $state,
        BufferingLogger $logger,
        array $args = null,
        $run = null
    ) {
        namespace\_run_function_teardown($state, $logger, $this, $args, $run);
    }


    public function run(
        State $state, BufferingLogger $logger,
        array $args = null, array $run = null, array $targets = null
    ) {
        namespace\_run_function_test($state, $logger, $this, $args, $run, $targets);
    }
}


final class Dependency extends struct {
    public $file;
    public $class;
    public $function;
    public $dependees;
}


final class DependencyGraph {
    private $state;
    private $logger;
    private $postorder = array();
    private $marked = array();
    private $stack = array();


    public function __construct(State $state, Logger $logger) {
        $this->state = $state;
        $this->logger = $logger;
    }


    public function sort() {
        foreach ($this->state->depends as $from => $_) {
            $this->postorder($from);
        }
        return $this->postorder;
    }


    private function postorder($from, array $runs = array()) {
        if (isset($this->marked[$from])) {
            if (!$this->marked[$from]) {
                return false;
            }

            if (!isset($this->state->results[$from])) {
                return true;
            }

            return $this->check_run_results($from, $runs);
        }

        $this->marked[$from] = true;
        $this->stack[$from] = true;

        if (isset($this->state->depends[$from])) {
            $dependency = $this->state->depends[$from];
            foreach ($dependency->dependees as $to => $runs) {
                if (isset($this->stack[$to])) {
                    $cycle = array();
                    \end($this->stack);
                    do {
                        \prev($this->stack);
                        $key = \key($this->stack);
                        $cycle[] = $key;
                    } while ($key !== $to);

                    $this->marked[$from] = false;
                    $this->logger->log_error(
                        $from,
                        \sprintf(
                            "This test has a cyclical dependency with the following tests:\n\t%s",
                            \implode("\n\t", $cycle)
                        )
                    );
                }
                else {
                    $this->marked[$from] = $this->postorder($to, $runs);
                    if (!$this->marked[$from]) {
                        $this->logger->log_skip($from, "This test depends on '$to', which did not pass");
                    }
                }
            }

            if ($this->marked[$from]) {
                $this->postorder[] = $dependency;
            }
        }
        else {
            if (!isset($this->state->results[$from])) {
                $this->marked[$from] = false;
                $this->logger->log_error(
                    $from,
                    'Other tests depend on this test, but this test was never run'
                );
            }
            else {
                $result = $this->check_run_results($from, $runs);
            }
        }

        \array_pop($this->stack);
        return isset($result) ? $result : $this->marked[$from];
    }


    private function check_run_results($from, array $runs) {
        $result = true;
        foreach ($runs as $run) {
            if (!isset($this->state->results[$from][$run])) {
                $this->logger->log_error(
                    "$from$run",
                    'Other tests depend on this test, but this test was never run'
                );
                $result = false;
            }
        }
        return $result;
    }
}


final class Context {
    private $state;
    private $logger;
    private $test;
    private $run;
    private $result = namespace\RESULT_PASS;
    private $teardowns = array();

    public function __construct(State $state, Logger $logger,
        FunctionTest $test, $run
    ) {
        $this->state = $state;
        $this->logger = $logger;
        $this->test = $test;
        $this->run = $run;
    }


    public function __call($name, $args) {
        // This works, and will automatically pick up new assertions, but it'd
        // probably be best to implement actual class methods
        if ('assert' === \substr($name, 0, 6)
            && \function_exists("easytest\\$name"))
        {
            try {
                // #BC(5.5): Use proxy function for argument unpacking
                $value = namespace\_unpack_function("easytest\\$name", $args);
                return array(namespace\RESULT_PASS, $value);
            }
            catch (\AssertionError $e) {
                $this->logger->log_failure($this->test->name, $e);
            }
            // #BC(5.6): Catch Failure
            catch (Failure $e) {
                $this->logger->log_failure($this->test->name, $e);
            }
            $this->result = namespace\RESULT_FAIL;
            return array(namespace\RESULT_FAIL, null);
        }

        throw new \Exception(
            \sprintf('Call to undefined method %s::%s()', __CLASS__, $name)
        );
    }


    public function subtest($callable) {
        try {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            $value = \call_user_func($callable);
            return array(namespace\RESULT_PASS, $value);
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        // #BC(5.6): Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        $this->result = namespace\RESULT_FAIL;
        return array(namespace\RESULT_FAIL, null);
    }


    public function teardown($callable) {
        $this->teardowns[] = $callable;
    }


    public function depends($name) {
        $dependees = array();
        // #BC(5.5): Use func_get_args instead of argument unpacking
        foreach (\func_get_args() as $name) {
            list($name, $run) = $this->normalize_name($name);

            if (!isset($this->state->results[$name][$run])) {
                $dependees[] = $name;
            }
            elseif (!$this->state->results[$name][$run]) {
                throw new Skip("This test depends on '{$name}{$run}', which did not pass");
            }
        }

        if ($dependees) {
            if (!isset($this->state->depends[$this->test->name])) {
                $dependency = new Dependency();
                $dependency->file = $this->test->file;
                $dependency->class = $this->test->class;
                $dependency->function = $this->test->function;
                $this->state->depends[$this->test->name] = $dependency;
            }
            else {
                $dependency = $this->state->depends[$this->test->name];
            }

            foreach ($dependees as $dependee) {
                if (!isset($dependency->dependees[$dependee])) {
                    $dependency->dependees[$dependee] = array();
                }
                $dependency->dependees[$dependee][] = $run;
            }

            throw new Postpone();
        }
    }


    public function set($value) {
        $this->state->fixture[$this->test->name][$this->run] = $value;
    }


    public function get($name) {
        list($name, $run) = $this->normalize_name($name);
        return $this->state->fixture[$name][$run];
    }


    public function result() {
        return $this->result;
    }


    public function teardowns() {
        return $this->teardowns;
    }


    private function normalize_name($name) {
        if (
            !\preg_match(
                '~^(\\\\?(?:\\w+\\\\)*)?(\\w*::)?(\\w+)\\s*(\\((.*)\\))?$~',
                $name,
                $matches
            )
        ) {
            \trigger_error("Invalid test name: $name");
        }

        list(, $namespace, $class, $function) = $matches;

        if (!$namespace) {
            if (!$class && $this->test->class) {
                // the namespace is already included in the class name
                $class = $this->test->class;
            }
            else {
                $namespace = $this->test->namespace;
                if ($class) {
                    $class = \rtrim($class, ':');
                }
            }
        }
        else {
            $namespace = \ltrim($namespace, '\\');
        }

        if ($class) {
            $class .= '::';
        }

        $name = $namespace . $class . $function;

        if (isset($matches[4])) {
            $run = ('' === $matches[5]) ? $matches[5] : " {$matches[4]}";
        }
        else {
            $run = $this->run;
        }

        return array($name, $run);
    }
}


function discover_tests(BufferingLogger $logger, array $paths) {
    list($root, $paths) = namespace\_process_paths($logger, $paths);
    if (!$paths) {
        return;
    }

    $state = new State();
    $directory = namespace\_discover_directory($state, $logger, $root);
    if (!$directory) {
        return;
    }

    namespace\_run_test($state, $logger, $directory, null, null, $paths);
    while ($state->depends) {
        $targets = namespace\_resolve_dependencies($state, $logger);
        if (!$targets) {
            break;
        }
        $state->depends = array();
        namespace\_run_test($state, $logger, $directory, null, null, $targets);
    }
}


function _process_paths(Logger $logger, array $paths) {
    if (!$paths) {
        $path = \getcwd();
        $root = namespace\_determine_root($path);

        $target = new Target();
        $target->name = $path . \DIRECTORY_SEPARATOR;
        $paths[] = $target;
        return array($root, $paths);
    }

    $root = null;
    $targets = array();
    foreach ($paths as $path) {
        $realpath = \realpath($path->name);
        if (!$realpath) {
            $logger->log_error($path->name, 'No such file or directory');
            continue;
        }

        if (!$root) {
            $root = namespace\_determine_root($realpath);
        }

        if (\is_dir($realpath)) {
            $realpath .= \DIRECTORY_SEPARATOR;
        }

        $path->name = $realpath;
        $targets[] = $path;
    }
    return array($root, $targets);
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
        $root = $parent = $path;
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


function _parse_file(BufferingLogger $logger, $filepath, array $checks, array &$seen) {
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

            if ($checks[$token_type]($ns, $name, $fullname)) {
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


function _read_file(BufferingLogger $logger, $filepath) {
    // First include the file to ensure it parses correctly
    namespace\start_buffering($logger, $filepath);
    $success = namespace\_include_file($logger, $filepath);
    namespace\end_buffering($logger);
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


function _discover_directory(State $state, BufferingLogger $logger, $path) {
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
    BufferingLogger $logger, DirectoryTest $directory, $filepath, array &$seen
) {
    $error = 0;
    $checks = array(
        \T_FUNCTION => function($namespace, $function, $fullname) use ($directory, &$error) {
            return namespace\_is_directory_setup_function(
                $directory, $error, $namespace, $function, $fullname
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
    DirectoryTest $directory, &$error, $namespace, $function, $fullname
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


function _discover_file(State $state, BufferingLogger $logger, $filepath) {
    if (isset($state->files[$filepath])) {
        return $state->files[$filepath];
    }

    $file = new FileTest();
    $file->name = $filepath;
    $error = 0;
    $checks = array(
        \T_CLASS => function($namespace, $class, $fullname) use ($file) {
            return namespace\_is_test_class($file, $namespace, $class, $fullname);
        },
        \T_FUNCTION => function($namespace, $function, $fullname) use ($file, &$error) {
            return namespace\_is_test_function($file, $error, $namespace, $function, $fullname);
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


function _is_test_class(FileTest $file, $namespace, $class, $fullname) {
    if (0 === \substr_compare($class, 'test', 0, 4, true)) {
        $info = new TestInfo();
        $info->type = namespace\TYPE_CLASS;
        $info->filename = $file->name;
        $info->namespace = $namespace;
        $info->name = $fullname;
        $file->tests["class $fullname"] = $info;
        return true;
    }
    return false;
}


function _is_test_function(FileTest $file, &$error, $namespace, $function, $fullname) {
    if (0 === \substr_compare($function, 'test', 0, 4, true)) {
        $info = new TestInfo();
        $info->type = namespace\TYPE_FUNCTION;
        $info->filename = $file->name;
        $info->namespace = $namespace;
        $info->name = $fullname;
        $file->tests["function $fullname"] = $info;
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


function _discover_class(State $state, Logger $logger, TestInfo $info) {
    $classname = $info->name;
    if (isset($state->classes[$classname])) {
        return $state->classes[$classname];
    }

    $class = new ClassTest();
    $class->file = $info->filename;
    $class->namespace = $info->namespace;
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


function _run_test(
    State $state, BufferingLogger $logger, $test,
    $args = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        $type = \get_class($test);
        switch ($type) {
        case 'easytest\\DirectoryTest':
            $targets = namespace\_find_directory_targets($logger, $test, $targets);
            break;

        case 'easytest\\FileTest':
            $targets = namespace\_find_file_targets($logger, $test, $targets);
            break;

        case 'easytest\\ClassTest':
            $targets = namespace\_find_class_targets($logger, $test, $targets);
            break;

        default:
            // #BC(5.4): Omit description from assert
            \assert(false); // "Test type '$type' can't have targets"
            break;
        }
    }

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    list($result, $args) = $test->setup($logger, $args, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    $update_run = false;
    if ($args instanceof ArgumentLists) {
        $arglists = $args->arglists();
        $update_run = true;
    }
    else {
        $arglists = array($args);
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
        if ($update_run) {
            $this_run_id[] = $i;
        }

        $test->run($state, $logger, $arglist, $this_run_id, $targets);
    }

    $test->teardown($state, $logger, $args, $run_name);
}


function _find_directory_targets(Logger $logger, DirectoryTest $test, array $targets) {
    $current = null;
    $parents = array();
    $children = array();
    foreach ($targets as $target) {
        $name = $target->name;
        if ($name === $test->name) {
            // The entire directory is a target, so test the entire path. Any
            // other targets are just duplicates, which we can skip
            return null;
        }

        if (isset($test->tests[$name])) {
            $parent = $name;
            $target = $target->targets;
        }
        elseif (0 === \substr_compare($name, $test->name, 0, \strlen($test->name))) {
            // $target is in a subdirectory of the current directory.
            // $i = the location of the directory separator, which we want
            // to include in the subdirectory name
            $i = \strpos($name, \DIRECTORY_SEPARATOR, \strlen($test->name));
            $parent = \substr($name, 0, $i+1);
            $target = array($target);
        }
        else {
            $logger->log_error($test->name, "Invalid test requested: $name");
            continue;
        }

        if ($parent === $current) {
            $children = \array_merge($children, $target);
        }
        else {
            if ($current) {
                $parents[] = array($current, $children);
            }
            $current = $parent;
            $children = $target;
        }
    }
    if ($current) {
        $parents[] = array($current, $children);
    }
    return $parents;
}


function _find_file_targets(Logger $logger, FileTest $test, array $targets) {
    $tests = array();
    foreach ($targets as $target) {
        if (!isset($test->tests[$target->name])) {
            $logger->log_error($test->name, "Invalid test requested: $target->name");
            continue;
        }
        $tests[] = $target;
    }
    return $tests;
}


function _find_class_targets(Logger $logger, ClassTest $test, array $targets) {
    $methods = array();
    foreach ($targets as $target) {
        \assert(!$target->targets);
        $method = $target->name;
        if (!\method_exists($test->name, $method)) {
            $logger->log_error($test->name, "Invalid test requested: $method");
            continue;
        }
        $methods[] = $method;
    }
    return $methods;
}


function _run_directory_setup(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($directory->setup) {
        $name = "{$directory->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $directory->setup, $args);
        namespace\end_buffering($logger);
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    return $result;
}


function _run_directory_tests(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

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
}


function _run_directory_test(
    State $state, BufferingLogger $logger, DirectoryTest $directory, $test,
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
        namespace\_run_test($state, $logger, $test, $arglist, $run_id, $targets);
    }
}


function _run_directory_teardown(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($directory->teardown) {
        $name = "{$directory->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $directory->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function _run_file_setup(
    BufferingLogger $logger,
    FileTest $file,
    array $args = null,
    $run = null
) {
    if ($file->setup) {
        $name = "{$file->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $file->setup, $args);
        namespace\end_buffering($logger);
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    return $result;
}


function _run_file_tests(
    State $state, BufferingLogger $logger, FileTest $file,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($targets) {
        foreach ($targets as $target) {
            namespace\_run_file_test(
                $state, $logger, $file, $target->name, $arglist, $run_id, $target->targets
            );
        }
    }
    else {
        foreach ($file->tests as $test => $_) {
            namespace\_run_file_test(
                $state, $logger, $file, $test, $arglist, $run_id
            );
        }
    }
}


function _run_file_test(
    State $state, BufferingLogger $logger, FileTest $file, $test,
    $arglist = null, array $run_id = null, array $targets = null
) {
    $info = $file->tests[$test];
    switch ($info->type) {
    case namespace\TYPE_CLASS:
        $test = namespace\_discover_class($state, $logger, $info);
        break;

    case namespace\TYPE_FUNCTION:
        $test = new FunctionTest();
        $test->file = $info->filename;
        $test->namespace = $info->namespace;
        $test->function = $info->name;
        $test->name = $info->name;
        $test->test = $info->name;
        if ($file->setup_function) {
            $test->setup_name = $file->setup_function_name;
            $test->setup = $file->setup_function;
        }
        if ($file->teardown_function) {
            $test->teardown_name = $file->teardown_function_name;
            $test->teardown = $file->teardown_function;
        }
        break;

    default:
        // #BC(5.4): Omit description from assert
        \assert(false); // "Unknown file test type: $type"
        break;
    }

    if ($test) {
        namespace\_run_test(
            $state, $logger, $test, $arglist, $run_id, $targets
        );
    }
}


function _run_file_teardown(
    BufferingLogger $logger,
    FileTest $file,
    $args = null,
    $run = null
) {
    \assert(
        null === $args
        || \is_array($args)
        || ($args instanceof ArgumentLists)
    );

    if ($file->teardown) {
        $name = "{$file->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function _run_class_setup(
    BufferingLogger $logger,
    ClassTest $class,
    array $args = null,
    $run = null
) {
    namespace\start_buffering($logger, $class->name);
    $class->object = namespace\_instantiate_test($logger, $class->name, $args);
    namespace\end_buffering($logger);
    if (!$class->object) {
        return array(namespace\RESULT_FAIL, null);
    }

    $result = array(namespace\RESULT_PASS, null);
    if ($class->setup) {
        $name = "{$class->name}::{$class->setup}{$run}";
        $method = array($class->object, $class->setup);
        namespace\start_buffering($logger, $name);
        list($result[0],) = namespace\_run_setup($logger, $name, $method);
        namespace\end_buffering($logger);
    }
    return $result;
}


function _run_class_tests(
    State $state, BufferingLogger $logger, ClassTest $class,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        foreach ($targets as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run_id);
        }
    }
    else {
        foreach ($class->tests as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run_id);
        }
    }
}


function _run_class_test(
    State $state, BufferingLogger $logger, ClassTest $class, $method,
    array $run_id = null, array $targets = null
) {
    $test = new FunctionTest();
    $test->file = $class->file;
    $test->namespace = $class->namespace;
    $test->class = $class->name;
    $test->function =  $method;
    $test->name = "{$class->name}::{$method}";
    $test->test = array($class->object, $method);
    if ($class->setup_function) {
        $test->setup = array($class->object, $class->setup_function);
        $test->setup_name = $class->setup_function;
    }
    if ($class->teardown_function) {
        $test->teardown = array($class->object, $class->teardown_function);
        $test->teardown_name = $class->teardown_function;
    }
    namespace\_run_test($state, $logger, $test, null, $run_id);
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


function _run_class_teardown(BufferingLogger $logger, ClassTest $class, $run) {
    if ($class->teardown) {
        $name = "{$class->name}::{$class->teardown}{$run}";
        $method = array($class->object, $class->teardown);
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $method);
        namespace\end_buffering($logger);
    }
}


function _run_function_setup(
    BufferingLogger $logger,
    FunctionTest $test,
    array $args = null,
    $run = null
) {
    if ($test->setup) {
        $name = "{$test->setup_name} for {$test->name}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $test->setup, $args);
        if (namespace\RESULT_PASS !== $result[0]) {
            namespace\end_buffering($logger);
        }
    }
    else {
        $result = array(namespace\RESULT_PASS, $args);
    }
    $test->result = $result[0];
    return $result;
}


function _run_function_test(
    State $state, BufferingLogger $logger, FunctionTest $test,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    // #BC(5.4): Omit description from assert
    \assert(!$targets); // function tests can't have targets
    \assert($test->result === namespace\RESULT_PASS);

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    $test_name = "{$test->name}{$run_name}";

    namespace\start_buffering($logger, $test_name);
    $context = new Context($state, $logger, $test, $run_name);
    $test->result = namespace\_run_test_function(
        $logger, $test_name, $test->test, $context, $arglist
    );

    foreach($context->teardowns() as $teardown) {
        $test->result |= namespace\_run_teardown($logger, $test_name, $teardown);
    }
}


function _run_function_teardown(
    State $state,
    BufferingLogger $logger,
    FunctionTest $test,
    array $args = null,
    $run = null
) {
    $test_name = "{$test->name}{$run}";

    if ($test->teardown) {
        $name = "{$test->teardown_name} for {$test_name}";
        namespace\start_buffering($logger, $name);
        $test->result |= namespace\_run_teardown($logger, $name, $test->teardown, $args);
    }
    namespace\end_buffering($logger);

    if (namespace\RESULT_POSTPONE === $test->result) {
        return;
    }
    if (!isset($state->results[$test->name])) {
        $state->results[$test->name] = array('' => true);
    }
    if (namespace\RESULT_PASS === $test->result) {
        $logger->log_pass($test_name);
        $state->results[$test->name][$run] = true;
        $state->results[$test->name][''] = $state->results[$test->name][''];
    }
    elseif (namespace\RESULT_FAIL & $test->result) {
        $state->results[$test->name][$run] = false;
        $state->results[$test->name][''] = false;
        if (namespace\RESULT_POSTPONE & $test->result) {
            unset($state->depends[$test->name]);
        }
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
        return array(namespace\RESULT_PASS, $result);
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
    return array(namespace\RESULT_FAIL, null);
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
        $result = $context->result();
    }
    catch (\AssertionError $e) {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // #BC(5.6): Catch Failure
    catch (Failure $e) {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Postpone $_) {
        $result = namespace\RESULT_POSTPONE;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    return $result;
}


function _run_teardown(Logger $logger, $name, $callable, $args = null) {
    try {
        if (!$args) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif ($args instanceof ArgumentLists) {
            // #BC(5.3): Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $args->arglists());
        }
        else {
            // #BC(5.5): Use proxy function for argument unpacking
            namespace\_unpack_function($callable, $args);
        }
        return namespace\RESULT_PASS;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    return namespace\RESULT_FAIL;
}


function _resolve_dependencies(State $state, Logger $logger) {
    $graph = new DependencyGraph($state, $logger);
    $dependencies = $graph->sort();
    if (!$dependencies) {
        return null;
    }

    $targets = array();
    $current = new Target();
    foreach ($dependencies as $dependency) {
        if ($current->name !== $dependency->file) {
            if ($current->name) {
                $targets[] = $current;
            }
            $current = new Target();
            $current->name = $dependency->file;
        }
        if ($dependency->class) {
            $name = "class {$dependency->class}";
            $target = \end($current->targets);
            if ($target && $target->name === $name) {
                $target->targets[] = new Target($dependency->function);
                continue;
            }
            $target = new Target();
            $target->name = $name;
            $target->targets[] = new Target($dependency->function);
            $current->targets[] = $target;
        }
        else {
            $target = new Target("function {$dependency->function}");
            $current->targets[] = $target;
        }
    }
    $targets[] = $current;
    return $targets;
}
