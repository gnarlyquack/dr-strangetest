<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


const RESULT_PASS     = 0x0;
const RESULT_FAIL     = 0x1;
const RESULT_POSTPONE = 0x2;


final class Context {
    /** @var State */
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


    public function assert_different($expected, $actual, $description = null) {
        return $this->do_assert(
            function() use ($expected, $actual, $description) {
                namespace\assert_different($expected, $actual, $description);
            }
        );
    }


    public function assert_equal($expected, $actual, $description = null) {
        return $this->do_assert(
            function() use ($expected, $actual, $description) {
                namespace\assert_equal($expected, $actual, $description);
            }
        );
    }


    function assert_false($actual, $description = null) {
        return $this->do_assert(
            function() use ($actual, $description) {
                namespace\assert_false($actual, $description);
            }
        );
    }


    function assert_falsy($actual, $description = null) {
        return $this->do_assert(
            function() use ($actual, $description) {
                namespace\assert_falsy($actual, $description);
            }
        );
    }


    function assert_greater($actual, $min, $description = null) {
        return $this->do_assert(
            function() use ($actual, $min, $description) {
                namespace\assert_greater($actual, $min, $description);
            }
        );
    }


    function assert_greater_or_equal($actual, $min, $description = null) {
        return $this->do_assert(
            function() use ($actual, $min, $description) {
                namespace\assert_greater_or_equal($actual, $min, $description);
            }
        );
    }


    function assert_identical($expected, $actual, $description = null) {
        return $this->do_assert(
            function() use ($expected, $actual, $description) {
                namespace\assert_identical($expected, $actual, $description);
            }
        );
    }


    function assert_less($actual, $max, $description = null) {
        return $this->do_assert(
            function() use ($actual, $max, $description) {
                namespace\assert_less($actual, $max, $description);
            }
        );
    }


    function assert_less_or_equal($actual, $max, $description = null) {
        return $this->do_assert(
            function() use ($actual, $max, $description) {
                namespace\assert_less_or_equal($actual, $max, $description);
            }
        );
    }


    function assert_throws($expected, $callback, $description = null, &$result = null) {
        return $this->do_assert(
            function() use ($expected, $callback, $description, &$result) {
                $result = namespace\assert_throws($expected, $callback, $description);
            }
        );
    }


    function assert_true($actual, $description = null) {
        return $this->do_assert(
            function() use ($actual, $description) {
                namespace\assert_true($actual, $description);
            }
        );
    }


    function assert_truthy($actual, $description = null) {
        return $this->do_assert(
            function() use ($actual, $description) {
                namespace\assert_truthy($actual, $description);
            }
        );
    }


    function assert_unequal($expected, $actual, $description = null) {
        return $this->do_assert(
            function() use ($expected, $actual, $description) {
                namespace\assert_unequal($expected, $actual, $description);
            }
        );
    }


    function fail($reason) {
        return $this->do_assert(
            function() use ($reason) {
                namespace\fail($reason);
            }
        );
    }


    private function do_assert($assert) {
        try {
            $assert();
            return true;
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        // #BC(5.6): Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        $this->result = namespace\RESULT_FAIL;
        return false;
    }


    public function teardown($callable) {
        $this->teardowns[] = $callable;
    }


    /**
     * @param string... $name
     * @return ?mixed[]
     * @throws Postpone
     */
    public function depend_on($name) {
        $dependees = array();
        $result = array();
        $nnames = 0;
        // #BC(5.5): Use func_get_args instead of argument unpacking
        foreach (\func_get_args() as $nnames => $name) {
            list($normalized, $run) = $this->normalize_name($name);

            if (!isset($this->state->results[$normalized][$run])) {
                $dependees[] = array($normalized, $run);
            }
            elseif (!$this->state->results[$normalized][$run]) {
                throw new Skip("This test depends on '{$normalized}{$run}', which did not pass");
            }
            elseif (
                \array_key_exists($normalized, $this->state->fixture)
                && \array_key_exists($run, $this->state->fixture[$normalized])
            ) {
                $result[$name] = $this->state->fixture[$normalized][$run];
            }
        }

        if ($dependees) {
            if (!isset($this->state->depends[$this->test->name])) {
                $dependency = new Dependency(
                    $this->test->file,
                    $this->test->class,
                    $this->test->function
                );
                $this->state->depends[$this->test->name] = $dependency;
            }
            else {
                $dependency = $this->state->depends[$this->test->name];
            }

            foreach ($dependees as $dependee) {
                list($name, $run) = $dependee;
                if (!isset($dependency->dependees[$name])) {
                    $dependency->dependees[$name] = array();
                }
                $dependency->dependees[$name][] = $run;
            }

            throw new Postpone();
        }

        $nresults = \count($result);
        ++$nnames;
        if ($nresults) {
            if (1 === $nresults && 1 === $nnames) {
                return $result[$name];
            }
            return $result;
        }
        return null;
    }


    public function set($value) {
        $this->state->fixture[$this->test->name][$this->run] = $value;
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


function run_test(
    State $state, BufferingLogger $logger, $test,
    $args = null, array $run_id = null, array $targets = null
) {
    if ($targets) {
        list($error, $targets) = $test->find_targets($logger, $targets);
        if (!$targets && $error) {
            return;
        }
    }

    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';
    list($result, $args) = $test->setup_runs($logger, $update_run, $run_name, $args);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    if (!\is_iterable($args)) {
        $message = "'{$test->setup_runs}' returned a non-iterable argument set";
        $logger->log_error($test->name, $message);
    }
    else {
        foreach ($args as $i => $argset) {
            if (isset($argset)) {
                if (\is_iterable($argset)) {
                    if (!\is_array($argset)) {
                        $argset = \iterator_to_array($argset);
                    }
                }
                else {
                    $message = "'{$test->setup_runs}' returned a non-iterable argument set";
                    if ($update_run) {
                        $message .= "\nfor argument set '{$i}'";
                    }
                    $logger->log_error($test->name, $message);
                    continue;
                }
            }

            $this_run_id = $run_id;
            $this_run_name = $run_name;
            if ($update_run) {
                $this_run_id[] = $i;
                $this_run_name = \sprintf(' (%s)', \implode(', ', $this_run_id));
            }

            list($result, $argset) = $test->setup($logger, $argset, $this_run_name);
            if (namespace\RESULT_PASS !== $result) {
                continue;
            }
            if ($argset !== null && !\is_iterable($argset)) {
                $message = "'{$test->setup}' returned a non-iterable argument set";
                if ($update_run) {
                    $message .= "\nfor argument set '{$i}'";
                }
                $logger->log_error($test->name, $message);
            }
            else {
                if ($argset !== null && !\is_array($argset)) {
                    $argset = \iterator_to_array($argset);
                }
                $test->run($state, $logger, $argset, $this_run_id, $targets);
            }
            $test->teardown($state, $logger, $argset, $this_run_name);
        }
    }

    $test->teardown_runs($logger, $args, $run_name);
}


function run_directory_setup(
    BufferingLogger $logger,
    DirectoryTest $directory,
    array $args = null,
    $run = null
) {
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


/**
 * @param BufferingLogger $logger
 * @param DirectoryTest $directory
 * @param ?mixed[] $args
 * @param string $run
 * @param ?bool $update_run
 * @return array{int, array<?mixed[]>}
 */
function run_directory_setup_runs(
    BufferingLogger $logger,
    DirectoryTest $directory,
    &$update_run,
    $run = null,
    array $args = null
) {
    if ($directory->setup_runs) {
        $update_run = true;
        $name = "{$directory->setup_runs}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $directory->setup_runs, $args);
        namespace\end_buffering($logger);
    }
    else {
        $update_run = false;
        $result = array(namespace\RESULT_PASS, array($args));
    }
    return $result;
}


function run_directory_tests(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($targets) {
        foreach ($targets as $target) {
            namespace\_run_directory_test(
                $state, $logger, $directory, $target->name(), $arglist, $run_id, $target->subtargets()
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
        $test = namespace\discover_directory($state, $logger, $test);
        break;

    case namespace\TYPE_FILE:
        $test = namespace\discover_file($state, $logger, $test);
        break;

    default:
        throw new \Exception("Unkown directory test type: {$type}");
    }

    if ($test) {
        namespace\run_test($state, $logger, $test, $arglist, $run_id, $targets);
    }
}


/**
 * @param BufferingLogger $logger
 * @param DirectoryTest $directory
 * @param mixed $args
 * @param ?string $run
 * @return void
 */
function run_directory_teardown_runs(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    if ($directory->teardown_runs) {
        $name = "{$directory->teardown_runs}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $directory->teardown_runs, $args, false);
        namespace\end_buffering($logger);
    }
}


function run_directory_teardown(
    BufferingLogger $logger,
    DirectoryTest $directory,
    $args = null,
    $run = null
) {
    if ($directory->teardown) {
        $name = "{$directory->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $directory->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function run_file_setup(
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


/**
 * @param BufferingLogger $logger
 * @param FileTest $file
 * @param ?mixed[] $args
 * @param ?string $run
 * @param ?bool $update_run
 * @return array{int, array<?mixed[]>}
 */
function run_file_setup_runs(
    BufferingLogger $logger,
    FileTest $file,
    &$update_run,
    $run = null,
    array $args = null
) {
    if ($file->setup_runs) {
        $update_run = true;
        $name = "{$file->setup_runs}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $file->setup_runs, $args);
        namespace\end_buffering($logger);
    }
    else {
        $update_run = false;
        $result = array(namespace\RESULT_PASS, array($args));
    }
    return $result;
}


function run_file_tests(
    State $state, BufferingLogger $logger, FileTest $file,
    array $arglist = null, array $run_id = null, array $targets = null
) {
    $run_name = $run_id ? \sprintf(' (%s)', \implode(', ', $run_id)) : '';

    if ($targets) {
        foreach ($targets as $target) {
            namespace\_run_file_test(
                $state, $logger, $file, $target->name(), $arglist, $run_id, $target->subtargets()
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
        $test = namespace\discover_class($state, $logger, $info);
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
        throw new \Exception("Unknown file test type {$info->type}");
    }

    if ($test) {
        namespace\run_test(
            $state, $logger, $test, $arglist, $run_id, $targets
        );
    }
}


/**
 * @param BufferingLogger $logger
 * @param FileTest $file
 * @param mixed $args
 * @param ?string $run
 * @return void
 */
function run_file_teardown_runs(
    BufferingLogger $logger,
    FileTest $file,
    $args = null,
    $run = null
) {
    if ($file->teardown_runs) {
        $name = "{$file->teardown_runs}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown_runs, $args, false);
        namespace\end_buffering($logger);
    }
}


function run_file_teardown(
    BufferingLogger $logger,
    FileTest $file,
    $args = null,
    $run = null
) {
    if ($file->teardown) {
        $name = "{$file->teardown}{$run}";
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $file->teardown, $args);
        namespace\end_buffering($logger);
    }
}


function run_class_setup(
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


function run_class_tests(
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
    namespace\run_test($state, $logger, $test, null, $run_id);
}


function _instantiate_test(Logger $logger, $class, $args) {
    try {
        if ($args) {
            // #BC(5.5): Use proxy function for argument unpacking
            return namespace\unpack_construct($class, $args);
        }
        else {
            return new $class();
        }
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($class, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($class, $e);
    }
    return false;
}


function run_class_teardown(BufferingLogger $logger, ClassTest $class, $run) {
    if ($class->teardown) {
        $name = "{$class->name}::{$class->teardown}{$run}";
        $method = array($class->object, $class->teardown);
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $method);
        namespace\end_buffering($logger);
    }
    $class->object = null;
}


function run_function_setup(
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


function run_function_test(
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


function run_function_teardown(
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
            $result = namespace\unpack_function($callable, $args);
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
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e) {
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
            namespace\unpack_function($callable, $args);
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
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    return $result;
}


/**
 * @param ?bool $unpack
 */
function _run_teardown(Logger $logger, $name, $callable, $args = null, $unpack = true) {
    try {
        if ($args === null) {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif($unpack && \is_array($args)) {
            // @bc 5.5 Use proxy function for argument unpacking
            namespace\unpack_function($callable, $args);
        }
        else {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $args);
        }
        return namespace\RESULT_PASS;
    }
    // #BC(5.6): Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    return namespace\RESULT_FAIL;
}
