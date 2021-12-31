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
     * @return mixed
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

            if (!isset($this->state->results[$resolved]))
            {
                // The dependency hasn't been run
                // @todo Always resolve a prerequisite to a specific run?
                $dependees[] = array($resolved, null);
            }
            else
            {
                $runs = $this->run;
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
                    $dependees[] = array($resolved, $runs);
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
            if (!isset($this->state->postponed[$this->test->name]))
            {
                $this->state->postponed[$this->test->name]
                    = new FunctionDependency($this->test);
            }
            $dependency = $this->state->postponed[$this->test->name];

            $run_name = namespace\_get_run_name($this->state, $this->run);
            if (!isset($dependency->runs[$run_name]))
            {
                $dependency->runs[$run_name] = new RunDependency($this->run);
            }
            $dependency = $dependency->runs[$run_name];

            foreach ($dependees as $prerequisite)
            {
                list($name, $run) = $prerequisite;
                $dependency->prerequisites[$name] = $run;
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
 * @return void
 */
function run_tests(
    State $state, BufferingLogger $logger, PathTest $suite, PathTest $tests)
{
    $args = array();
    namespace\_run_path_test($state, $logger, $tests, array(0), $args);
    while ($state->postponed)
    {
        $dependencies = namespace\resolve_dependencies($state, $logger);
        if (!$dependencies)
        {
            break;
        }
        $state->postponed = array();
        $tests = namespace\build_test_from_dependencies($state, $suite, $dependencies);
        namespace\_run_path_test($state, $logger, $tests, array(0), $args);
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_path_test(
    State $state, BufferingLogger $logger,
    PathTest $path, array $run, array $args)
{
    $run_name = namespace\_get_run_name($state, $run);

    $setup = namespace\RESULT_PASS;
    if ($path->setup)
    {
        $name = $path->setup . $run_name;
        namespace\start_buffering($logger, $name);
        list($setup, $args) = namespace\_run_setup($logger, $name, $path->setup, $args);
        namespace\end_buffering($logger);
    }

    if (namespace\RESULT_PASS === $setup)
    {
        if (\is_iterable($args))
        {
            if (!\is_array($args))
            {
                $args = \iterator_to_array($args);
            }

            foreach ($path->runs as $tests)
            {
                namespace\_run_subrun($state, $logger, $tests, $run, $args);
            }
        }
        else
        {
            $message = "'{$path->setup}{$run_name}' returned a non-iterable argument set";
            $logger->log_error($path->name, $message);
        }

        if ($path->teardown)
        {
            $name = $path->teardown . $run_name;
            namespace\start_buffering($logger, $name);
            namespace\_run_teardown($logger, $name, $path->teardown, $args);
            namespace\end_buffering($logger);
        }
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_subrun(
    State $state, BufferingLogger $logger,
    TestRun $tests, array $run, array $args)
{
    if ($tests->run_info)
    {
        $run_name = namespace\_get_run_name($state, $run);
        $setup = $tests->run_info->setup;
        $name = $setup . $run_name;
        namespace\start_buffering($logger, $name);
        list($result, $args) = namespace\_run_setup($logger, $name, $setup, $args);
        namespace\end_buffering($logger);
        if (namespace\RESULT_PASS === $result)
        {
            if (\is_iterable($args))
            {
                if (!\is_array($args))
                {
                    $args = \iterator_to_array($args);
                }

                if ($args)
                {
                    $run[] = $tests->run_info->id;
                    namespace\_run_subrun_tests($state, $logger, $tests, $run, $args);
                }
                else
                {
                    $message = "'{$name}' did not return any arguments";
                    $logger->log_error($tests->name, $message);
                }
            }
            else
            {
                $message = "'{$name}' returned a non-iterable argument set";
                $logger->log_error($tests->name, $message);
            }

            if (isset($tests->run_info->teardown))
            {
                $teardown = $tests->run_info->teardown;
                $name = $teardown . $run_name;
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $teardown, $args);
                namespace\end_buffering($logger);
            }
        }
    }
    else
    {
        namespace\_run_subrun_tests($state, $logger, $tests, $run, $args);
    }
}

/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_subrun_tests(
    State $state, BufferingLogger $logger,
    TestRun $tests, array $run, array $args)
{
    foreach ($tests->tests as $test)
    {
        if ($test instanceof PathTest)
        {
            namespace\_run_path_test($state, $logger, $test, $run, $args);
        }
        elseif ($test instanceof ClassTest)
        {
            namespace\_run_class_test($state, $logger, $test, $run, $args);
        }
        else
        {
            \assert($test instanceof FunctionTest);
            namespace\_run_function_test($state, $logger, $test, $run, $args);
        }
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_class_test(
    State $state, BufferingLogger $logger,
    ClassTest $class, array $run, array $args)
{
    namespace\start_buffering($logger, $class->name);
    $object = namespace\_instantiate_test($logger, $class->name, $args);
    namespace\end_buffering($logger);

    if ($object)
    {
        $run_name = namespace\_get_run_name($state, $run);

        $setup = namespace\RESULT_PASS;
        if ($class->setup)
        {
            $name = $class->name . '::' . $class->setup . $run_name;
            $method = array($object, $class->setup);
            \assert(\is_callable($method));
            namespace\start_buffering($logger, $name);
            list($setup,) = namespace\_run_setup($logger, $name, $method);
            namespace\end_buffering($logger);
        }

        if (namespace\RESULT_PASS === $setup)
        {
            foreach ($class->tests as $test)
            {
                namespace\_run_method_test($state, $logger, $object, $test, $run);
            }

            if ($class->teardown)
            {
                $name = $class->name . '::' . $class->teardown . $run_name;
                $method = array($object, $class->teardown);
                \assert(\is_callable($method));
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $method);
                namespace\end_buffering($logger);
            }
        }
    }
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
 * @param object $object
 * @param int[] $run
 * @return void
 */
function _run_method_test(
    State $state, BufferingLogger $logger, $object, FunctionTest $test, array $run)
{
    $run_name = namespace\_get_run_name($state, $run);
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($test->setup_name)
    {
        $setup = array($object, $test->setup_name);
        \assert(\is_callable($setup));
        $name = $test->setup_name . ' for ' . $test_name;
        namespace\start_buffering($logger, $name);
        list($result, ) = namespace\_run_setup($logger, $name, $setup);
    }

    if (namespace\RESULT_PASS === $result)
    {
        $method = array($object, $test->function);
        \assert(\is_callable($method));
        $context = new _Context($state, $logger, $test, $run);
        namespace\start_buffering($logger, $test_name);
        $result = namespace\_run_test_function($logger, $test_name, $method, $context);

        while ($context->teardowns)
        {
            $teardown = \array_pop($context->teardowns);
            $result |= namespace\_run_teardown($logger, $test_name, $teardown);
        }

        if ($test->teardown_name)
        {
            $teardown = array($object, $test->teardown_name);
            \assert(\is_callable($teardown));
            $name = $test->teardown_name . ' for ' . $test_name;
            namespace\start_buffering($logger, $name);
            $result |= namespace\_run_teardown($logger, $name, $teardown);
        }
    }
    namespace\end_buffering($logger);
    namespace\_record_test_result($state, $logger, $test, $run, $test_name, $result);
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_function_test(
    State $state, BufferingLogger $logger,
    FunctionTest $test, array $run, array $args)
{
    $run_name = namespace\_get_run_name($state, $run);
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($test->setup)
    {
        $name = $test->setup_name . ' for ' . $test_name;
        namespace\start_buffering($logger, $name);
        list($result, $args) = namespace\_run_setup($logger, $name, $test->setup, $args);
    }

    if (namespace\RESULT_PASS === $result)
    {
        if (\is_iterable($args))
        {
            if (!\is_array($args))
            {
                $args = \iterator_to_array($args);
            }

            $context = new _Context($state, $logger, $test, $run);
            namespace\start_buffering($logger, $test_name);
            $result = namespace\_run_test_function(
                $logger, $test_name, $test->test, $context, $args
            );

            while ($context->teardowns)
            {
                $teardown = \array_pop($context->teardowns);
                $result |= namespace\_run_teardown($logger, $test_name, $teardown);
            }
        }
        else
        {
            $message = "'{$test->setup_name}{$run_name}' returned a non-iterable argument set";
            $logger->log_error($test->name, $message);
            $result = namespace\RESULT_FAIL;
        }

        if ($test->teardown)
        {
            $name = $test->teardown_name . ' for ' . $test_name;
            namespace\start_buffering($logger, $name);
            $result |= namespace\_run_teardown($logger, $name, $test->teardown, $args);
        }
    }
    namespace\end_buffering($logger);
    namespace\_record_test_result($state, $logger, $test, $run, $test_name, $result);
}


/**
 * @param int[] $run
 * @param string $name
 * @param int $result
 * @return void
 */
function _record_test_result(
    State $state, Logger $logger, FunctionTest $test, array $run, $name, $result)
{
    if (namespace\RESULT_POSTPONE !== $result)
    {
        if (!isset($state->results[$test->name]))
        {
            $state->results[$test->name] = array(
                'group' => $test->group,
                'runs' => array(),
            );
        }

        if (namespace\RESULT_PASS === $result)
        {
            $logger->log_pass($name);
            for ($i = 0, $c = \count($run); $i < $c; ++$i)
            {
                $id = \implode(',', \array_slice($run, 0, $i + 1));
                if (!isset($state->results[$test->name]['runs'][$id]))
                {
                    $state->results[$test->name]['runs'][$id] = true;
                }
            }
        }
        elseif (namespace\RESULT_FAIL & $result)
        {
            for ($i = 0, $c = \count($run); $i < $c; ++$i)
            {
                $id = \implode(',', \array_slice($run, 0, $i + 1));
                $state->results[$test->name]['runs'][$id] = false;
            }
            if (namespace\RESULT_POSTPONE & $result)
            {
                unset($state->postponed[$test->name]);
            }
        }
    }
}


/**
 * @param string $name
 * @param callable(mixed ...$args): mixed $callable
 * @param ?mixed[] $args
 * @return array{int, mixed}
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
        $result = isset($result) ? $result : array();
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
 * @param mixed $args
 * @return int
 */
function _run_teardown(Logger $logger, $name, $callable, $args = null)
{
    try
    {
        if ($args === null)
        {
            // @bc 5.3 Invoke (possible) object method using call_user_func()
            \call_user_func($callable);
        }
        elseif(\is_array($args))
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
