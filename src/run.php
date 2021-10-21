<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


const RESULT_PASS     = 0x0;
const RESULT_FAIL     = 0x1;
const RESULT_POSTPONE = 0x2;


/**
 * @api
 */
final class Context {
    /** @var State */
    private $state;
    /** @var Logger */
    private $logger;
    /** @var FunctionTest */
    private $test;
    /** @var int[] */
    private $run;
    /** @var int */
    private $result = namespace\RESULT_PASS;
    /** @var (callable(mixed ...): void)[] */
    private $teardowns = array();

    /**
     * @param int[] $run
     */
    public function __construct(State $state, Logger $logger,
        FunctionTest $test, array $run
    ) {
        $this->state = $state;
        $this->logger = $logger;
        $this->test = $test;
        $this->run = $run;
    }

    /**
     * @param callable(): void $callable
     * @return bool
     */
    public function subtest($callable) {
        try {
            $callable();
            return true;
        }
        catch (\AssertionError $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        // @bc 5.6 Catch Failure
        catch (Failure $e) {
            $this->logger->log_failure($this->test->name, $e);
        }
        $this->result = namespace\RESULT_FAIL;
        return false;
    }


    /**
     * @param callable(mixed ...): void $callable
     * @return void
     */
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
        // @bc 5.5 Use func_get_args instead of argument unpacking
        foreach (\func_get_args() as $nnames => $name) {
            $normalized = $this->normalize_name($name);

            $runs = $this->run;
            if (!isset($this->state->results[$normalized]))
            {
                // The dependency hasn't been run
                $dependees[] = array($normalized, \end($runs));
            }
            else
            {
                $results = $this->state->results[$normalized];
                if ($this->test->group !== $results['group'])
                {
                    $us = $this->state->groups[$this->test->group];
                    $them = $this->state->groups[$results['group']];
                    for ($i = 0, $c = \min(\count($us), \count($them)); $i < $c; ++$i)
                    {
                        if ($us[$i] !== $them[$i])
                        {
                            break;
                        }
                    }
                    \assert($i > 0);
                    $runs = \array_slice($runs, 0, $i);
                }

                $run = \end($runs);
                if (!isset($results['runs'][$run]))
                {
                    // The dependency hasn't been run
                    $dependees[] = array($normalized, $run);
                }
                elseif (!$results['runs'][$run])
                {
                    $run_name = namespace\_get_run_name($this->state, $runs);
                    throw new Skip("This test depends on '{$normalized}{$run_name}', which did not pass");
                }
                elseif (isset($this->state->fixture[$normalized][$run]))
                {
                    $result[$name] = $this->state->fixture[$normalized][$run];
                }
            }
        }

        if ($dependees) {
            if (!isset($this->state->depends[$this->test->name])) {
                $this->state->depends[$this->test->name] = new Dependency(
                    $this->test->file,
                    $this->test->class,
                    $this->test->function
                );
            }
            $dependency = $this->state->depends[$this->test->name];

            foreach ($dependees as $dependee) {
                list($name, $run) = $dependee;
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


    /**
     * @param mixed $value
     * @return void
     */
    public function set($value) {
        $run = \end($this->run);
        $this->state->fixture[$this->test->name][$run] = $value;
    }


    /**
     * @return int
     */
    public function result() {
        return $this->result;
    }


    /**
     * @return callable[]
     */
    public function teardowns() {
        return $this->teardowns;
    }


    /**
     * @param string $name
     * @return string
     */
    private function normalize_name($name) {
        if (
            !\preg_match(
                '~^(\\\\?(?:\\w+\\\\)*)?(\\w*::)?(\\w+)\\s*?$~',
                $name,
                $matches
            )
        ) {
            \trigger_error("Invalid test name: $name");
        }

        /** @var string[] $matches */
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
        return $name;
    }
}


/**
 * @param ?mixed[] $args
 * @param ?string $run
 * @return array{int, mixed}
 */
function _run_directory_setup(
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
 * @param int[] $run
 * @param ?mixed[] $args
 * @param ?Target[] $targets
 * @return void
 */
function run_directory_tests(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $run, array $args = null, array $targets = null
) {
    if ($targets) {
        list($error, $targets) = namespace\find_directory_targets($logger, $directory, $targets);
        if (!$targets && $error) {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    if ($directory->runs)
    {
        foreach ($directory->runs as $run_fixture)
        {
            $setup = $run_fixture->setup;
            $name = "{$setup}{$run_name}";
            namespace\start_buffering($logger, $name);
            list($result, $run_args) = namespace\_run_setup($logger, $name, $setup, $args);
            if (namespace\RESULT_PASS !== $result) {
                continue;
            }

            if (!\is_iterable($run_args)) {
                $message = "'{$name}' returned a non-iterable argument set";
                $logger->log_error($directory->name, $message);
            }
            else {
                if (!\is_array($run_args)) {
                    $run_args = \iterator_to_array($run_args);
                }

                $run[] = $run_fixture->id;
                namespace\_run_directory(
                    $state, $logger, $directory, $run, $run_args, $targets);
                \array_pop($run);
            }

            if (isset($run_fixture->teardown))
            {
                $teardown = $run_fixture->teardown;
                $name = "{$teardown}{$run_name}";
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $teardown, $run_args);
                namespace\end_buffering($logger);
            }
        }
    }
    else
    {
        namespace\_run_directory($state, $logger, $directory, $run, $args, $targets);
    }
}


/**
 * @param int[] $run
 * @param ?mixed[] $args
 * @param ?Target[] $targets
 * @return void
 */
function _run_directory(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $run, array $args = null, array $targets = null)
{
    $run_name = namespace\_get_run_name($state, $run);
    list($result, $args) = namespace\_run_directory_setup(
        $logger, $directory, $args, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    // @todo consider normalizing null $args to an empty array
    if (($args === null) || \is_iterable($args))
    {
        if ($args !== null && !\is_array($args))
        {
            $args = \iterator_to_array($args);
        }
        if ($targets) {
            foreach ($targets as $target) {
                namespace\_run_directory_test(
                    $state, $logger, $directory, $target->name(), $run, $args, $target->subtargets()
                );
            }
        }
        else {
            foreach ($directory->tests as $test => $_) {
                namespace\_run_directory_test(
                    $state, $logger, $directory, $test, $run, $args);
            }
        }
    }
    else
    {
        $message = "'{$directory->setup}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($directory->name, $message);
    }

    namespace\_run_directory_teardown($logger, $directory, $args, $run_name);
}


/**
 * @param string $test
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @param ?Target[] $targets
 * @return void
 */
function _run_directory_test(
    State $state, BufferingLogger $logger, DirectoryTest $directory, $test,
    array $run, $arglist = null, array $targets = null
) {
    $group = namespace\_get_current_group($state, $run);
    $type = $directory->tests[$test];
    switch ($type) {
    case namespace\TYPE_DIRECTORY:
        $test = namespace\discover_directory($state, $logger, $test, $group);
        if ($test) {
            namespace\run_directory_tests($state, $logger, $test, $run, $arglist, $targets);
        }
        break;

    case namespace\TYPE_FILE:
        $test = namespace\discover_file($state, $logger, $test, $group);
        if ($test) {
            namespace\_run_file_tests($state, $logger, $test, $run, $arglist, $targets);
        }
        break;

    default:
        throw new \Exception("Unkown directory test type: {$type}");
    }
}


/**
 * @param ?mixed $args
 * @param ?string $run
 * @return void
 */
function _run_directory_teardown(
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


/**
 * @param ?mixed[] $args
 * @param ?string $run
 * @return array{int, mixed}
 */
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


// @todo Consider combining directory tests and file tests
// The logic for the two types of tests is essentially identical
/**
 * @param int[] $run
 * @param ?mixed[] $args
 * @param ?Target[] $targets
 * @return void
 */
function _run_file_tests(
    State $state, BufferingLogger $logger, FileTest $file,
    array $run, array $args = null, array $targets = null
) {
    if ($targets) {
        list($error, $targets) = namespace\find_file_targets($logger, $file, $targets);
        if (!$targets && $error) {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    if ($file->runs)
    {
        foreach ($file->runs as $run_fixture)
        {
            $setup = $run_fixture->setup;
            $name = "{$setup}{$run_name}";
            namespace\start_buffering($logger, $name);
            list($result, $run_args) = namespace\_run_setup($logger, $name, $setup, $args);
            if (namespace\RESULT_PASS !== $result) {
                continue;
            }

            if (!\is_iterable($run_args)) {
                $message = "'{$name}' returned a non-iterable argument set";
                $logger->log_error($file->name, $message);
            }
            else {
                if (!\is_array($run_args)) {
                    $run_args = \iterator_to_array($run_args);
                }
                $run[] = $run_fixture->id;
                namespace\_run_file($state, $logger, $file, $run, $run_args, $targets);
                \array_pop($run);
            }

            if (isset($run_fixture->teardown))
            {
                $teardown = $run_fixture->teardown;
                $name = "{$teardown}{$run_name}";
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $teardown, $run_args);
                namespace\end_buffering($logger);
            }
        }
    }
    else
    {
        namespace\_run_file($state, $logger, $file, $run, $args, $targets);
    }
}


/**
 * @param int[] $run
 * @param ?mixed[] $args
 * @param ?Target[] $targets
 * @return void
 */
function _run_file(
    State $state, BufferingLogger $logger, FileTest $file,
    array $run, array $args = null, array $targets = null)
{
    $run_name = namespace\_get_run_name($state, $run);

    list($result, $args) = namespace\_run_file_setup($logger, $file, $args, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    if (($args === null) || \is_iterable($args))
    {
        if ($args !== null && !\is_array($args))
        {
            $args = \iterator_to_array($args);
        }
        if ($targets) {
            foreach ($targets as $target) {
                namespace\_run_file_test(
                    $state, $logger, $file, $target->name(), $run, $args, $target->subtargets()
                );
            }
        }
        else {
            foreach ($file->tests as $test => $_) {
                namespace\_run_file_test($state, $logger, $file, $test, $run, $args);
            }
        }
    }
    else
    {
        $message = "'{$file->setup}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($file->name, $message);
    }

    namespace\_run_file_teardown($logger, $file, $args, $run_name);
}


/**
 * @param string $test
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @param ?Target[] $targets
 * @return void
 */
function _run_file_test(
    State $state, BufferingLogger $logger, FileTest $file, $test,
    array $run, $arglist = null, array $targets = null
) {
    $group = namespace\_get_current_group($state, $run);
    $info = $file->tests[$test];
    switch ($info->type) {
    case namespace\TYPE_CLASS:
        $test = namespace\discover_class($state, $logger, $info, $group);
        if ($test) {
            namespace\_run_class_tests($state, $logger, $test, $run, $arglist, $targets);
        }
        break;

    case namespace\TYPE_FUNCTION:
        $test = new FunctionTest();
        $test->group = $group;
        $test->file = $info->filename;
        $test->namespace = $info->namespace;
        $test->function = $info->name;
        $test->name = $info->name;
        \assert(\is_callable($info->name));
        $test->test = $info->name;
        if ($file->setup_function) {
            $test->setup_name = $file->setup_function_name;
            $test->setup = $file->setup_function;
        }
        if ($file->teardown_function) {
            $test->teardown_name = $file->teardown_function_name;
            $test->teardown = $file->teardown_function;
        }
        \assert(!$targets);
        namespace\_run_function_test($state, $logger, $test, $run, $arglist);
        break;

    default:
        throw new \Exception("Unknown file test type {$info->type}");
    }

}


/**
 * @param ?mixed[] $args
 * @param ?string $run
 * @return void
 */
function _run_file_teardown(
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


/**
 * @param ?mixed[] $args
 * @param ?string $run
 * @return array{int, null}
 */
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
        \assert(\is_callable($method));
        namespace\start_buffering($logger, $name);
        list($result[0],) = namespace\_run_setup($logger, $name, $method);
        namespace\end_buffering($logger);
    }
    return $result;
}


/**
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @param ?Target[] $targets
 * @return void
 */
function _run_class_tests(
    State $state, BufferingLogger $logger, ClassTest $class,
    array $run, array $arglist = null, array $targets = null
) {
    if ($targets) {
        list($error, $targets) = namespace\find_class_targets($logger, $class, $targets);
        if (!$targets && $error) {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    list($result, ) = namespace\_run_class_setup($logger, $class, $arglist, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }

    if ($targets) {
        foreach ($targets as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run);
        }
    }
    else {
        foreach ($class->tests as $test) {
            namespace\_run_class_test($state, $logger, $class, $test, $run);
        }
    }

    namespace\_run_class_teardown($logger, $class, $run_name);
}


/**
 * @param string $method
 * @param int[] $run
 * @return void
 */
function _run_class_test(
    State $state, BufferingLogger $logger, ClassTest $class, $method, array $run)
{
    $test = new FunctionTest();
    $test->group = namespace\_get_current_group($state, $run);
    $test->file = $class->file;
    $test->namespace = $class->namespace;
    $test->class = $class->name;
    $test->function =  $method;
    $test->name = "{$class->name}::{$method}";
    $method = array($class->object, $method);
    \assert(\is_callable($method));
    $test->test = $method;
    if ($class->setup_function) {
        $method = array($class->object, $class->setup_function);
        \assert(\is_callable($method));
        $test->setup = $method;
        $test->setup_name = $class->setup_function;
    }
    if ($class->teardown_function) {
        $method = array($class->object, $class->teardown_function);
        \assert(\is_callable($method));
        $test->teardown = $method;
        $test->teardown_name = $class->teardown_function;
    }
    namespace\_run_function_test($state, $logger, $test, $run, null);
}


/**
 * @template T of object
 * @param class-string<T> $class
 * @param ?mixed[] $args
 * @return ?T
 */
function _instantiate_test(Logger $logger, $class, $args) {
    try {
        if ($args) {
            // @bc 5.5 Use proxy function for argument unpacking
            return namespace\unpack_construct($class, $args);
        }
        else {
            return new $class();
        }
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e) {
        $logger->log_error($class, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($class, $e);
    }
    return null;
}


/**
 * @param ?string $run
 * @return void
 */
function _run_class_teardown(BufferingLogger $logger, ClassTest $class, $run) {
    if ($class->teardown) {
        $name = "{$class->name}::{$class->teardown}{$run}";
        $method = array($class->object, $class->teardown);
        \assert(\is_callable($method));
        namespace\start_buffering($logger, $name);
        namespace\_run_teardown($logger, $name, $method);
        namespace\end_buffering($logger);
    }
    $class->object = null;
}


/**
 * @param ?mixed[] $args
 * @param ?string $run
 * @return array{int, mixed}
 */
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


/**
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @return void
 */
function _run_function_test(
    State $state, BufferingLogger $logger,
    FunctionTest $test, array $run, array $arglist = null)
{
    $run_name = namespace\_get_run_name($state, $run);

    list($result, $argset) = namespace\_run_function_setup($logger, $test, $arglist, $run_name);
    if (namespace\RESULT_PASS !== $result) {
        return;
    }
    if ($argset !== null && !\is_iterable($argset)) {
        $message = "'{$test->setup_name}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($test->name, $message);
    }
    else {
        if ($argset !== null && !\is_array($argset)) {
            $argset = \iterator_to_array($argset);
        }
        $test_name = "{$test->name}{$run_name}";
        namespace\start_buffering($logger, $test_name);
        $context = new Context($state, $logger, $test, $run);
        $test->result = namespace\_run_test_function(
            $logger, $test_name, $test->test, $context, $argset
        );

        foreach($context->teardowns() as $teardown) {
            $test->result |= namespace\_run_teardown($logger, $test_name, $teardown);
        }
    }

    namespace\_run_function_teardown($state, $logger, $test, $run, $argset);
}


/**
 * @param int[] $run
 * @param ?mixed[] $args
 * @return void
 */
function _run_function_teardown(
    State $state, BufferingLogger $logger,
    FunctionTest $test, array $run, array $args = null)
{
    $test_name = \sprintf('%s%s', $test->name, namespace\_get_run_name($state, $run));

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
        $state->results[$test->name] = array(
            'group' => $test->group,
            'runs' => array(),
        );
    }
    if (namespace\RESULT_PASS === $test->result) {
        $logger->log_pass($test_name);
        foreach ($run as $id)
        {
            if (!isset($state->results[$test->name]['runs'][$id]))
            {
                $state->results[$test->name]['runs'][$id] = true;
            }
        }
    }
    elseif (namespace\RESULT_FAIL & $test->result) {
        foreach ($run as $id)
        {
            $state->results[$test->name]['runs'][$id] = false;
        }
        if (namespace\RESULT_POSTPONE & $test->result) {
            unset($state->depends[$test->name]);
        }
    }
}


/**
 * @param string $name
 * @param callable(mixed ...$args): mixed[] $callable
 * @param ?mixed[] $args
 * @return array{int, ?mixed[]}
 */
function _run_setup(Logger $logger, $name, $callable, array $args = null) {
    try {
        if ($args) {
            // @bc 5.5 Use proxy function for argument unpacking
            $result = namespace\unpack_function($callable, $args);
        }
        else {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            $result = \call_user_func($callable);
        }
        return array(namespace\RESULT_PASS, $result);
    }
    catch (Skip $e) {
        $logger->log_skip($name, $e);
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    return array(namespace\RESULT_FAIL, null);
}


/**
 * @param string $name
 * @param callable(mixed ...$args): void $callable
 * @param ?mixed[] $args
 * @return int
 */
function _run_test_function(
    Logger $logger, $name, $callable, Context $context, array $args = null
) {
    try {
        if ($args) {
            $args[] = $context;
            // @bc 5.5 Use proxy function for argument unpacking
            namespace\unpack_function($callable, $args);
        }
        else {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $context);
        }
        $result = $context->result();
    }
    catch (\AssertionError $e) {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // @bc 5.6 Catch Failure
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
    // @bc 5.6 Catch Exception
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
 * @param string $name
 * @param callable(mixed ...$args): void $callable
 * @param ?mixed[] $args
 * @param ?bool $unpack
 * @return int
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
    // @bc 5.6 Catch Exception
    catch (\Exception $e) {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e) {
        $logger->log_error($name, $e);
    }
    return namespace\RESULT_FAIL;
}


/**
 * @param int[] $run
 * @return string
 */
function _get_run_name(State $state, array $run)
{
    $names = array();
    for ($i = 1, $c = \count($run); $i < $c; ++$i)
    {
        $id = $run[$i] - 1;
        $names[] = $state->runs[$id]->name;
    }
    if ($names)
    {
        $result = \sprintf(' (%s)', \implode(', ', $names));
    }
    else
    {
        $result = '';
    }
    return $result;
}


/**
 * @param int[] $run
 * @return int
 */
function _get_current_group(State $state, array $run)
{
    $id = \end($run);
    if ($id)
    {
        $group = $state->runs[$id - 1]->group;
    }
    else
    {
        $group = 0;
    }
    return $group;
}
