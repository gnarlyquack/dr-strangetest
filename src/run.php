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
interface Context {
    /**
     * @param callable(): void $callback
     * @return bool
     */
    public function subtest($callback);

    /**
     * @param callable(): void $callback
     * @return void
     */
    public function teardown($callback);

    /**
     * @param string... $name
     * @return ?mixed[]
     * @throws Postpone
     */
    public function requires($name);

    /**
     * @param mixed $value
     * @return void
     */
    public function set($value);

}


final class _Context implements Context {
    /** @var State */
    private $state;
    /** @var Logger */
    private $logger;
    /** @var FunctionTest */
    private $test;
    /** @var int[] */
    private $run;
    /** @var int */
    public $result = namespace\RESULT_PASS;
    /** @var array<callable(): void> */
    public $teardowns = array();


    /**
     * @param int[] $run
     */
    public function __construct(State $state, Logger $logger, FunctionTest $test, array $run)
    {
        $this->state = $state;
        $this->logger = $logger;
        $this->test = $test;
        $this->run = $run;
    }


    public function subtest($callback)
    {
        try
        {
            $callback();
            return true;
        }
        catch (\AssertionError $e)
        {
            $this->logger->log_failure($this->test->name, $e);
        }
        // @bc 5.6 Catch Failure
        catch (Failure $e)
        {
            $this->logger->log_failure($this->test->name, $e);
        }
        $this->result = namespace\RESULT_FAIL;
        return false;
    }


    public function teardown($callback)
    {
        $this->teardowns[] = $callback;
    }


    public function requires($name)
    {
        $dependees = array();
        $result = array();
        $nnames = 0;
        // @bc 5.5 Use func_get_args instead of argument unpacking
        foreach (\func_get_args() as $nnames => $name)
        {
            $resolved = namespace\resolve_test_name(
                $name, $this->test->namespace, (string)$this->test->class);
            if (!isset($resolved))
            {
                \trigger_error("Invalid test name: $name");
            }

            $runs = $this->run;
            if (!isset($this->state->results[$resolved]))
            {
                // The dependency hasn't been run
                $dependees[] = array($resolved, null);
            }
            else
            {
                $results = $this->state->results[$resolved];
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

                $run = \implode(',', $runs);
                if (!isset($results['runs'][$run]))
                {
                    // The dependency hasn't been run
                    $dependees[] = array($resolved, $run);
                }
                elseif (!$results['runs'][$run])
                {
                    $run_name = namespace\_get_run_name($this->state, $runs);
                    throw new Skip("This test depends on '{$resolved}{$run_name}', which did not pass");
                }
                elseif (isset($this->state->fixture[$resolved][$run]))
                {
                    $result[$name] = $this->state->fixture[$resolved][$run];
                }
            }
        }

        if ($dependees)
        {
            if (!isset($this->state->depends[$this->test->name]))
            {
                $this->state->depends[$this->test->name] = new Dependency(
                    $this->test->file,
                    $this->test->class,
                    $this->test->function
                );
            }
            $dependency = $this->state->depends[$this->test->name];

            foreach ($dependees as $dependee)
            {
                list($name, $run) = $dependee;
                $dependency->dependees[$name][] = $run;
            }

            throw new Postpone();
        }

        $nresults = \count($result);
        ++$nnames;
        if ($nresults)
        {
            if (1 === $nresults && 1 === $nnames)
            {
                return $result[$name];
            }
            return $result;
        }
        return null;
    }


    public function set($value)
    {
        $run = \implode(',', $this->run);
        $this->state->fixture[$this->test->name][$run] = $value;
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
    $run = null)
{
    if ($directory->setup)
    {
        $name = "{$directory->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $directory->setup, $args);
        namespace\end_buffering($logger);
    }
    else
    {
        $result = array(namespace\RESULT_PASS, $args);
    }
    return $result;
}


/**
 * @param ?Target[] $targets
 * @return void
 */
function run_tests(
    State $state, BufferingLogger $logger, DirectoryTest $tests, array $targets = null)
{
    namespace\_run_directory_tests($state, $logger, $tests, array(0), null, $targets);
    while ($state->depends)
    {
        $dependencies = namespace\resolve_dependencies($state, $logger);
        if (!$dependencies)
        {
            break;
        }
        $targets = namespace\build_targets_from_dependencies($dependencies);
        $state->depends = array();
        namespace\_run_directory_tests($state, $logger, $tests, array(0), null, $targets);
    }
}


/**
 * @param int[] $run
 * @param ?mixed[] $args
 * @param ?Target[] $targets
 * @return void
 */
function _run_directory_tests(
    State $state, BufferingLogger $logger, DirectoryTest $directory,
    array $run, array $args = null, array $targets = null)
{
    if ($targets)
    {
        list($error, $targets) = namespace\find_directory_targets($logger, $directory, $targets);
        if (!$targets && $error)
        {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    list($result, $args) = namespace\_run_directory_setup(
        $logger, $directory, $args, $run_name);
    if (namespace\RESULT_PASS !== $result)
    {
        return;
    }

    // @todo consider normalizing null $args to an empty array
    if (($args === null) || \is_iterable($args))
    {
        if ($args !== null && !\is_array($args))
        {
            $args = \iterator_to_array($args);
        }
        if ($directory->runs)
        {
            foreach ($directory->runs as $run_fixture)
            {
                $setup = $run_fixture->setup;
                $name = "{$setup}{$run_name}";
                namespace\start_buffering($logger, $name);
                list($result, $run_args) = namespace\_run_setup($logger, $name, $setup, $args);
                namespace\end_buffering($logger);
                if (namespace\RESULT_PASS !== $result)
                {
                    continue;
                }

                if (!\is_iterable($run_args))
                {
                    $message = "'{$name}' returned a non-iterable argument set";
                    $logger->log_error($directory->name, $message);
                }
                else
                {
                    if (!\is_array($run_args))
                    {
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
    else
    {
        $message = "'{$directory->setup}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($directory->name, $message);
    }

    namespace\_run_directory_teardown($logger, $directory, $args, $run_name);

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
    if ($targets)
    {
        foreach ($targets as $target)
        {
            namespace\_run_directory_test(
                $state, $logger, $directory, $target->name(), $run, $args, $target->subtargets()
            );
        }
    }
    else
    {
        foreach ($directory->tests as $test => $_)
        {
            namespace\_run_directory_test(
                $state, $logger, $directory, $test, $run, $args);
        }
    }
}


/**
 * @param string $name
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @param ?Target[] $targets
 * @return void
 */
function _run_directory_test(
    State $state, BufferingLogger $logger, DirectoryTest $directory, $name,
    array $run, $arglist = null, array $targets = null)
{
    $test = $directory->tests[$name];
    if ($test instanceof DirectoryTest)
    {
        namespace\_run_directory_tests($state, $logger, $test, $run, $arglist, $targets);
    }
    elseif ($test instanceof FileTest)
    {
        namespace\_run_file_tests($state, $logger, $test, $run, $arglist, $targets);
    }
    else
    {
        throw new \Exception("Unknown directory test {$test}");
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
    $run = null)
{
    if ($directory->teardown)
    {
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
    $run = null)
{
    if ($file->setup)
    {
        $name = "{$file->setup}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $file->setup, $args);
        namespace\end_buffering($logger);
    }
    else
    {
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
    array $run, array $args = null, array $targets = null)
{
    if ($targets)
    {
        list($error, $targets) = namespace\find_file_targets($logger, $file, $targets);
        if (!$targets && $error)
        {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    list($result, $args) = namespace\_run_file_setup($logger, $file, $args, $run_name);
    if (namespace\RESULT_PASS !== $result)
    {
        return;
    }

    if (($args === null) || \is_iterable($args))
    {
        if ($args !== null && !\is_array($args))
        {
            $args = \iterator_to_array($args);
        }
        if ($file->runs)
        {
            foreach ($file->runs as $run_fixture)
            {
                $setup = $run_fixture->setup;
                $name = "{$setup}{$run_name}";
                namespace\start_buffering($logger, $name);
                list($result, $run_args) = namespace\_run_setup($logger, $name, $setup, $args);
                namespace\end_buffering($logger);
                if (namespace\RESULT_PASS !== $result)
                {
                    continue;
                }

                if (!\is_iterable($run_args))
                {
                    $message = "'{$name}' returned a non-iterable argument set";
                    $logger->log_error($file->name, $message);
                }
                else
                {
                    if (!\is_array($run_args))
                    {
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
    else
    {
        $message = "'{$file->setup}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($file->name, $message);
    }

    namespace\_run_file_teardown($logger, $file, $args, $run_name);
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

    if ($targets)
    {
        foreach ($targets as $target)
        {
            namespace\_run_file_test(
                $state, $logger, $file, $target->name(), $run, $args, $target->subtargets()
            );
        }
    }
    else
    {
        foreach ($file->tests as $test => $_)
        {
            namespace\_run_file_test($state, $logger, $file, $test, $run, $args);
        }
    }
}


/**
 * @param string $name
 * @param int[] $run
 * @param ?mixed[] $arglist
 * @param ?Target[] $targets
 * @return void
 */
function _run_file_test(
    State $state, BufferingLogger $logger, FileTest $file, $name,
    array $run, $arglist = null, array $targets = null)
{
    $test = $file->tests[$name];
    if ($test instanceof ClassTest)
    {
        namespace\_run_class_tests($state, $logger, $test, $run, $arglist, $targets);
    }
    elseif ($test instanceof FunctionTest)
    {
        namespace\_run_function_test($state, $logger, $test, $run, $arglist);
    }
    else
    {
        throw new \Exception("Unknown file test {$test}");
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
    $run = null)
{
    if ($file->teardown)
    {
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
    $run = null)
{
    namespace\start_buffering($logger, $class->name);
    $class->object = namespace\_instantiate_test($logger, $class->name, $args);
    namespace\end_buffering($logger);
    if (!$class->object)
    {
        return array(namespace\RESULT_FAIL, null);
    }

    $result = array(namespace\RESULT_PASS, null);
    if ($class->setup)
    {
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
    array $run, array $arglist = null, array $targets = null)
{
    if ($targets)
    {
        list($error, $targets) = namespace\find_class_targets($logger, $class, $targets);
        if (!$targets && $error)
        {
            return;
        }
    }

    $run_name = namespace\_get_run_name($state, $run);
    list($result, ) = namespace\_run_class_setup($logger, $class, $arglist, $run_name);
    if (namespace\RESULT_PASS !== $result)
    {
        return;
    }
    \assert(\is_object($class->object));

    if ($targets)
    {
        foreach ($targets as $name)
        {
            $test = $class->tests[$name];
            namespace\_run_class_test($state, $logger, $class->object, $test, $run);
        }
    }
    else
    {
        foreach ($class->tests as $test)
        {
            namespace\_run_class_test($state, $logger, $class->object, $test, $run);
        }
    }

    namespace\_run_class_teardown($logger, $class, $run_name);
}


/**
 * @param object $object
 * @param int[] $run
 * @return void
 */
function _run_class_test(
    State $state, BufferingLogger $logger, $object, FunctionTest $test, array $run)
{
    $method = array($object, $test->function);
    \assert(\is_callable($method));
    $test->test = $method;
    if ($test->setup_name)
    {
        $method = array($object, $test->setup_name);
        \assert(\is_callable($method));
        $test->setup = $method;
    }
    if ($test->teardown_name)
    {
        $method = array($object, $test->teardown_name);
        \assert(\is_callable($method));
        $test->teardown = $method;
    }
    namespace\_run_function_test($state, $logger, $test, $run, null);

    // Release reference to $object
    $test->test = $test->setup = $test->teardown = null;
}


/**
 * @template T of object
 * @param class-string<T> $class
 * @param ?mixed[] $args
 * @return ?T
 */
function _instantiate_test(Logger $logger, $class, $args)
{
    try
    {
        if ($args)
        {
            // @bc 5.5 Use proxy function for argument unpacking
            return namespace\unpack_construct($class, $args);
        }
        else
        {
            return new $class();
        }
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($class, $e);
    }
    catch (\Throwable $e)
    {
        $logger->log_error($class, $e);
    }
    return null;
}


/**
 * @param ?string $run
 * @return void
 */
function _run_class_teardown(BufferingLogger $logger, ClassTest $class, $run)
{
    if ($class->teardown)
    {
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
    $run = null)
{
    if ($test->setup)
    {
        $name = "{$test->setup_name} for {$test->name}{$run}";
        namespace\start_buffering($logger, $name);
        $result = namespace\_run_setup($logger, $name, $test->setup, $args);
        if (namespace\RESULT_PASS !== $result[0])
        {
            namespace\end_buffering($logger);
        }
    }
    else
    {
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

    // @todo Ensure function setup works properly for methods
    // Setup methods shouldn't return arguments, and it looks like we just
    // assume this is the case here. If they do return something, then we
    // should ignore it (or potentially raise an error). Perhaps we should just
    // handle methods and functions separately?
    list($result, $argset) = namespace\_run_function_setup($logger, $test, $arglist, $run_name);
    if (namespace\RESULT_PASS !== $result)
    {
        return;
    }
    if ($argset !== null && !\is_iterable($argset))
    {
        $message = "'{$test->setup_name}{$run_name}' returned a non-iterable argument set";
        $logger->log_error($test->name, $message);
    }
    else
    {
        if ($argset !== null && !\is_array($argset))
        {
            $argset = \iterator_to_array($argset);
        }
        $test_name = "{$test->name}{$run_name}";
        namespace\start_buffering($logger, $test_name);
        $context = new _Context($state, $logger, $test, $run);
        $test->result = namespace\_run_test_function(
            $logger, $test_name, $test->test, $context, $argset
        );

        foreach($context->teardowns as $teardown)
        {
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

    if ($test->teardown)
    {
        $name = "{$test->teardown_name} for {$test_name}";
        namespace\start_buffering($logger, $name);
        $test->result |= namespace\_run_teardown($logger, $name, $test->teardown, $args);
    }
    namespace\end_buffering($logger);

    if (namespace\RESULT_POSTPONE === $test->result)
    {
        return;
    }
    if (!isset($state->results[$test->name]))
    {
        $state->results[$test->name] = array(
            'group' => $test->group,
            'runs' => array(),
        );
    }
    if (namespace\RESULT_PASS === $test->result)
    {
        $logger->log_pass($test_name);
        for ($i = 0, $c = \count($run); $i < $c; ++$i)
        {
            $id = \implode(',', \array_slice($run, 0, $i + 1));
            if (!isset($state->results[$test->name]['runs'][$id]))
            {
                $state->results[$test->name]['runs'][$id] = true;
            }
        }
    }
    elseif (namespace\RESULT_FAIL & $test->result)
    {
        for ($i = 0, $c = \count($run); $i < $c; ++$i)
        {
            $id = \implode(',', \array_slice($run, 0, $i + 1));
            $state->results[$test->name]['runs'][$id] = false;
        }
        if (namespace\RESULT_POSTPONE & $test->result)
        {
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
function _run_setup(Logger $logger, $name, $callable, array $args = null)
{
    try
    {
        if ($args)
        {
            // @bc 5.5 Use proxy function for argument unpacking
            $result = namespace\unpack_function($callable, $args);
        }
        else
        {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            $result = \call_user_func($callable);
        }
        return array(namespace\RESULT_PASS, $result);
    }
    catch (Skip $e)
    {
        $logger->log_skip($name, $e);
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e)
    {
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
    Logger $logger, $name, $callable, _Context $context, array $args = null)
{
    try
    {
        if ($args)
        {
            $args[] = $context;
            // @bc 5.5 Use proxy function for argument unpacking
            namespace\unpack_function($callable, $args);
        }
        else
        {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $context);
        }
        $result = $context->result;
    }
    catch (\AssertionError $e)
    {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    // @bc 5.6 Catch Failure
    catch (Failure $e)
    {
        $logger->log_failure($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Skip $e)
    {
        $logger->log_skip($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (Postpone $_)
    {
        $result = namespace\RESULT_POSTPONE;
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($name, $e);
        $result = namespace\RESULT_FAIL;
    }
    catch (\Throwable $e)
    {
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
function _run_teardown(Logger $logger, $name, $callable, $args = null, $unpack = true)
{
    try
    {
        if ($args === null)
        {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif($unpack && \is_array($args))
        {
            // @bc 5.5 Use proxy function for argument unpacking
            namespace\unpack_function($callable, $args);
        }
        else
        {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable, $args);
        }
        return namespace\RESULT_PASS;
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($name, $e);
    }
    catch (\Throwable $e)
    {
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
