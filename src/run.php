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


final class _Context extends struct implements Context
{
    /** @var State */
    private $state;
    /** @var Logger */
    private $logger;
    /** @var FunctionTest|MethodTest */
    private $test;
    /** @var int[] */
    private $run;
    /** @var int */
    public $result = namespace\RESULT_PASS;
    /** @var array<callable(): void> */
    public $teardowns = array();


    /**
     * @param FunctionTest|MethodTest $test
     * @param int[] $run
     */
    public function __construct(State $state, Logger $logger, $test, array $run)
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
            if ($this->test instanceof MethodTest)
            {
                $namespace = $this->test->test->getDeclaringClass()->getNamespaceName() . '\\';
                $class = $this->test->test->class;
            }
            else
            {
                $namespace = $this->test->test->getNamespaceName() . '\\';
                $class = '';
            }

            $resolved = namespace\resolve_test_name($name, $namespace, $class);
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
                if ($this->test->run_group_id !== $results['group'])
                {
                    $us = $this->state->groups[$this->test->run_group_id];
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
 * @param TestRunGroup|DirectoryTest $suite
 * @param TestRunGroup|DirectoryTest $tests
 * @return void
 */
function run_tests(State $state, BasicLogger $logger, $suite, $tests)
{
    $args = array();
    $run = array(0);
    for (;;)
    {
        if ($tests instanceof TestRunGroup)
        {
            namespace\_run_test_run_group($state, $logger, $tests, $run, $args);
        }
        else
        {
            namespace\_run_directory($state, $logger, $tests, $run, $args);
        }

        if (!$state->postponed)
        {
            break;
        }

        $dependencies = namespace\resolve_dependencies($state, $logger);
        if (!$dependencies)
        {
            break;
        }

        $state->postponed = array();
        $tests = namespace\build_tests_from_dependencies($state, $suite, $dependencies);
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_test_run_group(
    State $state, BasicLogger $logger,
    TestRunGroup $group, array $run, array $args)
{
    foreach ($group->runs as $test)
    {
        \assert(isset($test->setup));

        $run_name = namespace\_get_run_name($state, $run);
        $setup = $test->setup;
        $name = $setup->name . $run_name;
        $callable = namespace\_get_callable_function($setup);
        namespace\start_buffering($logger, $name);
        list($result, $run_args) = namespace\_run_setup($logger, $name, $callable, $args);
        namespace\end_buffering($logger);

        if (namespace\RESULT_PASS === $result)
        {
            if (\is_array($run_args))
            {
                if ($run_args)
                {
                    $new_run = $run;
                    $new_run[] = $test->id;
                    $tests = $test->tests;
                    if ($tests instanceof DirectoryTest)
                    {
                        namespace\_run_directory($state, $logger, $tests, $new_run, $run_args);
                    }
                    else
                    {
                        namespace\_run_file($state, $logger, $tests, $new_run, $run_args);
                    }
                }
                else
                {
                    $message = "'{$name}' did not return any arguments";
                    $logger->log_error($test->tests->name, $message);
                }
            }

            if (isset($test->teardown))
            {
                $teardown = $test->teardown;
                $name = $teardown->name . $run_name;
                $callable = namespace\_get_callable_function($teardown);
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $callable, $run_args);
                namespace\end_buffering($logger);
            }
        }
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_directory(
    State $state, BasicLogger $logger,
    DirectoryTest $directory, array $run, array $args)
{
    $run_name = namespace\_get_run_name($state, $run);

    $setup = namespace\RESULT_PASS;
    if ($directory->setup)
    {
        $name = $directory->setup->name . $run_name;
        $callable = namespace\_get_callable_function($directory->setup);
        namespace\start_buffering($logger, $name);
        list($setup, $args) = namespace\_run_setup($logger, $name, $callable, $args);
        namespace\end_buffering($logger);
    }

    if (namespace\RESULT_PASS === $setup)
    {
        if (\is_array($args))
        {
            foreach ($directory->tests as $test)
            {
                if ($test instanceof TestRunGroup)
                {
                    namespace\_run_test_run_group($state, $logger, $test, $run, $args);
                }
                elseif ($test instanceof DirectoryTest)
                {
                    namespace\_run_directory($state, $logger, $test, $run, $args);
                }
                else
                {
                    namespace\_run_file($state, $logger, $test, $run, $args);
                }
            }
        }

        if ($directory->teardown)
        {
            $name = $directory->teardown->name . $run_name;
            $callable = namespace\_get_callable_function($directory->teardown);
            namespace\start_buffering($logger, $name);
            namespace\_run_teardown($logger, $name, $callable, $args);
            namespace\end_buffering($logger);
        }
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_file(
    State $state, BasicLogger $logger,
    FileTest $file, array $run, array $args)
{
    $run_name = namespace\_get_run_name($state, $run);

    $setup = namespace\RESULT_PASS;
    if ($file->setup_file)
    {
        $name = $file->setup_file->name . $run_name;
        $callable = namespace\_get_callable_function($file->setup_file);
        namespace\start_buffering($logger, $name);
        list($setup, $args) = namespace\_run_setup($logger, $name, $callable, $args);
        namespace\end_buffering($logger);
    }

    if (namespace\RESULT_PASS === $setup)
    {
        if (\is_array($args))
        {
            foreach ($file->tests as $test)
            {
                if ($test instanceof ClassTest)
                {
                    namespace\_run_class($state, $logger, $test, $run, $args);
                }
                else
                {
                    \assert($test instanceof FunctionTest);
                    namespace\_run_function($state, $logger, $test, $file->setup_function, $file->teardown_function, $run, $args);
                }
            }
        }

        if ($file->teardown_file)
        {
            $name = $file->teardown_file->name . $run_name;
            $callable = namespace\_get_callable_function($file->teardown_file);
            namespace\start_buffering($logger, $name);
            namespace\_run_teardown($logger, $name, $callable, $args);
            namespace\end_buffering($logger);
        }
    }
}


/**
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_class(
    State $state, BasicLogger $logger,
    ClassTest $class, array $run, array $args)
{
    namespace\start_buffering($logger, $class->test->name);
    $object = namespace\_instantiate_test($logger, $class->test, $args);
    namespace\end_buffering($logger);

    if ($object)
    {
        $run_name = namespace\_get_run_name($state, $run);

        $setup = namespace\RESULT_PASS;
        if ($class->setup_object)
        {
            $name = $class->test->name . '::' . $class->setup_object->name . $run_name;
            $method = namespace\_get_callable_method($class->setup_object, $object);
            namespace\start_buffering($logger, $name);
            list($setup,) = namespace\_run_setup($logger, $name, $method);
            namespace\end_buffering($logger);
        }

        if (namespace\RESULT_PASS === $setup)
        {
            foreach ($class->tests as $test)
            {
                namespace\_run_method($state, $logger, $object, $test, $class->setup_method, $class->teardown_method, $run);
            }

            if ($class->teardown_object)
            {
                $name = $class->test->name . '::' . $class->teardown_object->name . $run_name;
                $method = namespace\_get_callable_method($class->teardown_object, $object);
                namespace\start_buffering($logger, $name);
                namespace\_run_teardown($logger, $name, $method);
                namespace\end_buffering($logger);
            }
        }
    }
}


/**
 * @template T of object
 * @param \ReflectionClass<T> $class
 * @param mixed[] $args
 * @return ?T
 */
function _instantiate_test(Logger $logger, \ReflectionClass $class, array $args)
{
    try
    {
        return $class->newInstanceArgs($args);
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($class->name, $e);
    }
    catch (\Throwable $e)
    {
        $logger->log_error($class->name, $e);
    }
    return null;
}


/**
 * @param object $object
 * @param ?\ReflectionMethod $setup_method
 * @param ?\ReflectionMethod $teardown_method
 * @param int[] $run
 * @return void
 */
function _run_method(
    State $state, BasicLogger $logger, $object, MethodTest $test, $setup_method, $teardown_method, array $run)
{
    $run_name = namespace\_get_run_name($state, $run);
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($setup_method)
    {
        $name = $setup_method->name . ' for ' . $test_name;
        $setup = namespace\_get_callable_method($setup_method, $object);
        namespace\start_buffering($logger, $name);
        list($result, ) = namespace\_run_setup($logger, $name, $setup);
    }

    if (namespace\RESULT_PASS === $result)
    {
        $method = namespace\_get_callable_method($test->test, $object);
        $context = new _Context($state, $logger, $test, $run);
        namespace\start_buffering($logger, $test_name);
        $result = namespace\_run_test($logger, $test_name, $method, $context);

        while ($context->teardowns)
        {
            $teardown = \array_pop($context->teardowns);
            $result |= namespace\_run_teardown($logger, $test_name, $teardown);
        }

        if ($teardown_method)
        {
            $name = $teardown_method->name . ' for ' . $test_name;
            $teardown = namespace\_get_callable_method($teardown_method, $object);
            namespace\start_buffering($logger, $name);
            $result |= namespace\_run_teardown($logger, $name, $teardown);
        }
    }
    namespace\end_buffering($logger);
    namespace\_record_test_result($state, $logger, $test, $run, $test_name, $result);
}


/**
 * @param ?\ReflectionFunction $setup_function
 * @param ?\ReflectionFunction $teardown_function
 * @param int[] $run
 * @param mixed[] $args
 * @return void
 */
function _run_function(
    State $state, BasicLogger $logger,
    FunctionTest $test, $setup_function, $teardown_function, array $run, array $args)
{
    $run_name = namespace\_get_run_name($state, $run);
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($setup_function)
    {
        $name = $setup_function->getShortName() . ' for ' . $test_name;
        $callable = namespace\_get_callable_function($setup_function);
        namespace\start_buffering($logger, $name);
        list($result, $args) = namespace\_run_setup($logger, $name, $callable, $args);
    }

    if (namespace\RESULT_PASS === $result)
    {
        if (\is_array($args))
        {
            $context = new _Context($state, $logger, $test, $run);
            $callable = namespace\_get_callable_function($test->test);
            namespace\start_buffering($logger, $test_name);
            $result = namespace\_run_test($logger, $test_name, $callable, $context, $args);

            while ($context->teardowns)
            {
                $teardown = \array_pop($context->teardowns);
                $result |= namespace\_run_teardown($logger, $test_name, $teardown);
            }
        }

        if ($teardown_function)
        {
            $name = $teardown_function->getShortName() . ' for ' . $test_name;
            $callable = namespace\_get_callable_function($teardown_function);
            namespace\start_buffering($logger, $name);
            $result |= namespace\_run_teardown($logger, $name, $callable, $args);
        }
    }
    namespace\end_buffering($logger);
    namespace\_record_test_result($state, $logger, $test, $run, $test_name, $result);
}


/**
 * @param FunctionTest|MethodTest $test
 * @param int[] $run
 * @param string $name
 * @param int $result
 * @return void
 */
function _record_test_result(
    State $state, Logger $logger, $test, array $run, $name, $result)
{
    if (namespace\RESULT_POSTPONE !== $result)
    {
        if (!isset($state->results[$test->name]))
        {
            $state->results[$test->name] = array(
                'group' => $test->run_group_id,
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
    $status = namespace\RESULT_FAIL;
    $result = null;
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

        $status = namespace\RESULT_PASS;
        if (isset($args))
        {
            if (isset($result))
            {
                if (\is_iterable($result))
                {
                    if (!\is_array($result))
                    {
                        $result = \iterator_to_array($result);
                    }
                }
                else
                {
                    $message = "Invalid return value: setup fixtures should return an iterable (or 'null')";
                    $logger->log_error($name, $message);
                }
            }
            else
            {
                $result = array();
            }
        }
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

    return array($status, $result);
}


/**
 * @param string $name
 * @param callable(mixed ...$args): void $callable
 * @param ?mixed[] $args
 * @return int
 */
function _run_test(
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


/**
 * @return callable
 */
function _get_callable_function(\ReflectionFunction $function)
{
    // @bc 5.3 Ensure ReflectionFunction has getClosure method
    if (\method_exists($function, 'getClosure'))
    {
        // @todo Handle failure of ReflectionFunction::getClosure()?
        // We only reflect functions that we have already discovered and know
        // exist, so it doesn't seem like this should ever fail. However this
        // method is also undocumented, so it's unclear what could cause a
        // failure here
        $result = $function->getClosure();
    }
    else
    {
        $result = $function->getName();
    }
    \assert(\is_callable($result));
    return $result;
}


/**
 * @param object $object
 * @return callable
 */
function _get_callable_method(\ReflectionMethod $method, $object)
{
    // @bc 5.3 Ensure ReflectionMethod has getClosure method
    if (\method_exists($method, 'getClosure'))
    {
        // @todo Handle failure of ReflectionMethod::getClosure()?
        // We only reflect methods that we have already discovered and know
        // exist, so it doesn't seem like this should ever fail. However this
        // method is also undocumented, so it's unclear what could cause a
        // failure here
        $result = $method->getClosure($object);
    }
    else
    {
        $result = array($object, $method->getName());
    }

    \assert(\is_callable($result));
    return $result;
}
