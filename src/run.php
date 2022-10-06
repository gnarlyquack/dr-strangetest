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


final class GroupID extends struct
{
    /** @var int */
    public $id;

    /** @var int[] */
    public $path;


    /**
     * @param int $id
     */
    public function __construct($id, GroupID $parent = null)
    {
        $this->id = $id;

        if ($parent)
        {
            $this->path = $parent->path;
        }

        $this->path[] = $id;
    }
}


final class RunInstance extends struct
{
    /** @var ?RunInstance */
    public $parent;

    /** @var GroupID */
    public $group;

    /** @var string[] */
    public $path;

    /** @var string */
    public $hash;

    /** @var string */
    public $qualifier;


    /**
     * @param int $group_id
     * @param string $name
     */
    public function __construct($group_id, $name, RunInstance $parent = null)
    {
        $this->parent = $parent;

        if ($parent)
        {
            $this->group = new GroupID($group_id, $parent->group);
            $this->path = $parent->path;
        }
        else
        {
            $this->group = new GroupID($group_id);
            $this->path = array();
        }

        if ('' !== $name)
        {
            $this->path[] = $name;
        }
        $this->hash = namespace\_get_run_hash($this->path);
        $this->qualifier = namespace\_format_run_qualifier($this->path);
    }
}


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

    /** @var RunInstance */
    private $run;

    /** @var int */
    public $result = namespace\RESULT_PASS;

    /** @var array<callable(): void> */
    public $teardowns = array();


    /**
     * @param FunctionTest|MethodTest $test
     */
    public function __construct(State $state, Logger $logger, $test, RunInstance $run)
    {
        $this->state = $state;
        $this->logger = $logger;
        $this->test = $test;
        $this->run = $run;
    }


    public function subtest($callback)
    {
        // @todo assign a more descriptive name to subtests?
        try
        {
            $callback();
            return true;
        }
        catch (\AssertionError $e)
        {
            $this->logger->log_failure($this->logger->failure_from_exception($this->test->name, $e));
        }
        // @bc 5.6 Catch Failure
        catch (Failure $e)
        {
            $this->logger->log_failure($this->logger->failure_from_exception($this->test->name, $e));
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
                $namespace = $this->test->test->class->namespace;
                $class = $this->test->test->class->name;
            }
            else
            {
                $namespace = $this->test->test->namespace;
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
                $runs = $this->run->path;
                $results = $this->state->results[$resolved];
                if ($this->run->group->id !== $results['group']->id)
                {
                    $us = $this->run->group->path;
                    $them = $results['group']->path;
                    for ($i = 0, $c = \min(\count($us), \count($them)); $i < $c; ++$i)
                    {
                        if ($us[$i] !== $them[$i])
                        {
                            break;
                        }
                    }

                    \assert($i > 0);
                    // @todo Don't implicitly assign an id to top-level run group?
                    // There is an implicit top-level group to which we assign
                    // the id 0. This means there is also an implicit,
                    // top-level run, but we don't want this being displayed.
                    // Therefore, the list of run names, which excludes this
                    // top-level run (and would just be the empty string
                    // anyway) has one less element than the list of groups
                    // (which includes the implicit top-level group).
                    // Therefore, when determining the slice of common names
                    // between two tests, we need to subtract 1 from the number
                    // of groups they have in common.
                    \assert(\count($runs) === (\count($us) - 1));
                    $runs = \array_slice($runs, 0, $i-1);
                }

                $run = namespace\_get_run_hash($runs);
                if (!isset($results['runs'][$run]))
                {
                    // The dependency hasn't been run
                    $dependees[] = array($resolved, $runs);
                }
                elseif (!$results['runs'][$run])
                {
                    $run_name = namespace\_format_run_qualifier($runs);
                    // @fixme Show resolved test name with correct casing
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
            $index = $this->test->hash;
            if (!isset($this->state->postponed[$index]))
            {
                $this->state->postponed[$index]
                    = new FunctionDependency($this->test);
            }
            $dependency = $this->state->postponed[$index];

            $run_name = $this->run->hash;
            if (!isset($dependency->runs[$run_name]))
            {
                $dependency->runs[$run_name] = new RunDependency($this->run->path);
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
        $run = $this->run->hash;
        $this->state->fixture[$this->test->hash][$run] = $value;
    }
}


/**
 * @template T of TestRunGroup|DirectoryTest
 * @param T $suite
 * @param T $tests
 * @return void
 */
function run_tests(State $state, $suite, $tests)
{
    $args = array();
    $run = new RunInstance(0, '');
    for (;;)
    {
        if ($tests instanceof TestRunGroup)
        {
            namespace\_run_test_run_group($state, $tests, $run, $args);
        }
        else
        {
            namespace\_run_directory($state, $tests, $run, $args);
        }

        if (!$state->postponed)
        {
            break;
        }

        $dependencies = namespace\resolve_dependencies($state);
        if (!$dependencies)
        {
            break;
        }

        $state->postponed = array();
        $tests = namespace\build_tests_from_dependencies($state, $suite, $dependencies);
    }
}


/**
 * @param mixed[] $args
 * @return void
 */
function _run_test_run_group(
    State $state,
    TestRunGroup $group, RunInstance $run, array $args)
{
    foreach ($group->runs as $test)
    {
        \assert(isset($test->setup));

        $run_name = $run->qualifier;

        $setup = $test->setup;
        $name = $setup->name . $run_name;
        $logger = $state->bufferer->start_buffering(
            $name,
            $setup->file,
            $setup->line);
        list($result, $run_args) = namespace\_run_setup(
            $logger,
            $name,
            $setup->file,
            $setup->line,
            $setup->name,
            $args);
        $state->bufferer->end_buffering($state->logger);

        if (namespace\RESULT_PASS === $result)
        {
            if (\is_array($run_args))
            {
                if ($run_args)
                {
                    $new_run = new RunInstance($group->id, $test->name, $run);
                    $tests = $test->tests;
                    if ($tests instanceof DirectoryTest)
                    {
                        namespace\_run_directory($state, $tests, $new_run, $run_args);
                    }
                    else
                    {
                        namespace\_run_file($state, $tests, $new_run, $run_args);
                    }
                }
                else
                {
                    $state->logger->log_error(
                        new ErrorEvent(
                            $test->tests->name,
                            \sprintf("'%s' did not return any arguments", $name),
                            $setup->file,
                            $setup->line));
                }
            }

            if (isset($test->teardown))
            {
                $teardown = $test->teardown;
                $name = $teardown->name . $run_name;
                $logger = $state->bufferer->start_buffering(
                    $name,
                    $teardown->file,
                    $teardown->line);
                namespace\_run_teardown(
                    $logger,
                    $name,
                    $teardown->name,
                    $run_args);
                $state->bufferer->end_buffering($state->logger);
            }
        }
    }
}


/**
 * @param mixed[] $args
 * @return void
 */
function _run_directory(
    State $state,
    DirectoryTest $directory, RunInstance $run, array $args)
{
    $run_name = $run->qualifier;

    $setup = namespace\RESULT_PASS;
    if ($directory->setup)
    {
        $name = $directory->setup->name . $run_name;
        $logger = $state->bufferer->start_buffering(
            $name,
            $directory->setup->file,
            $directory->setup->line);
        list($setup, $args) = namespace\_run_setup(
            $logger,
            $name,
            $directory->setup->file,
            $directory->setup->line,
            $directory->setup->name,
            $args);
        $state->bufferer->end_buffering($state->logger);
    }

    if (namespace\RESULT_PASS === $setup)
    {
        if (\is_array($args))
        {
            foreach ($directory->tests as $test)
            {
                if ($test instanceof TestRunGroup)
                {
                    namespace\_run_test_run_group($state, $test, $run, $args);
                }
                elseif ($test instanceof DirectoryTest)
                {
                    namespace\_run_directory($state, $test, $run, $args);
                }
                else
                {
                    namespace\_run_file($state, $test, $run, $args);
                }
            }
        }

        if ($directory->teardown)
        {
            $name = $directory->teardown->name . $run_name;
            $logger = $state->bufferer->start_buffering(
                $name,
                $directory->teardown->file,
                $directory->teardown->line);
            namespace\_run_teardown(
                $logger,
                $name,
                $directory->teardown->name,
                $args);
            $state->bufferer->end_buffering($state->logger);
        }
    }
}


/**
 * @param mixed[] $args
 * @return void
 */
function _run_file(
    State $state,
    FileTest $file, RunInstance $run, array $args)
{
    $run_name = $run->qualifier;

    $setup = namespace\RESULT_PASS;
    if ($file->setup_file)
    {
        $name = $file->setup_file->name . $run_name;
        $logger = $state->bufferer->start_buffering(
            $name,
            $file->setup_file->file,
            $file->setup_file->line);
        list($setup, $args) = namespace\_run_setup(
            $logger,
            $name,
            $file->setup_file->file,
            $file->setup_file->line,
            $file->setup_file->name,
            $args);
        $state->bufferer->end_buffering($state->logger);
    }

    if (namespace\RESULT_PASS === $setup)
    {
        if (\is_array($args))
        {
            foreach ($file->tests as $test)
            {
                if ($test instanceof ClassTest)
                {
                    namespace\_run_class($state, $test, $run, $args);
                }
                else
                {
                    \assert($test instanceof FunctionTest);
                    namespace\_run_function($state, $test, $file->setup_function, $file->teardown_function, $run, $args);
                }
            }
        }

        if ($file->teardown_file)
        {
            $name = $file->teardown_file->name . $run_name;
            $logger = $state->bufferer->start_buffering(
                $name,
                $file->teardown_file->file,
                $file->teardown_file->line);
            namespace\_run_teardown(
                $logger,
                $name,
                $file->teardown_file->name,
                $args);
            $state->bufferer->end_buffering($state->logger);
        }
    }
}


/**
 * @param mixed[] $args
 * @return void
 */
function _run_class(
    State $state,
    ClassTest $class, RunInstance $run, array $args)
{
    $logger = $state->bufferer->start_buffering(
        $class->test->name,
        $class->test->file,
        $class->test->line);
    $object = namespace\_instantiate_test($logger, $class->test, $args);
    $state->bufferer->end_buffering($state->logger);

    if ($object)
    {
        $run_name = $run->qualifier;

        $setup = namespace\RESULT_PASS;
        if ($class->setup_object)
        {
            $name = $class->test->name . '::' . $class->setup_object->name . $run_name;
            $method = array($object, $class->setup_object->name);
            \assert(\is_callable($method));
            $logger = $state->bufferer->start_buffering(
                $name,
                $class->setup_object->file,
                $class->setup_object->line);
            list($setup,) = namespace\_run_setup(
                $logger,
                $name,
                $class->setup_object->file,
                $class->setup_object->line,
                $method);
            $state->bufferer->end_buffering($state->logger);
        }

        if (namespace\RESULT_PASS === $setup)
        {
            foreach ($class->tests as $test)
            {
                namespace\_run_method($state, $object, $test, $class->setup_method, $class->teardown_method, $run);
            }

            if ($class->teardown_object)
            {
                $name = $class->test->name . '::' . $class->teardown_object->name . $run_name;
                $method = array($object, $class->teardown_object->name);
                \assert(\is_callable($method));
                $logger = $state->bufferer->start_buffering(
                    $name,
                    $class->teardown_object->file,
                    $class->teardown_object->line);
                namespace\_run_teardown($logger, $name, $method);
                $state->bufferer->end_buffering($state->logger);
            }
        }
    }
}


/**
 * @param ClassInfo $class
 * @param mixed[] $args
 * @return ?object
 */
function _instantiate_test(Logger $logger, ClassInfo $class, array $args)
{
    try
    {
        return namespace\unpack_construct($class->name, $args);
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($logger->error_from_exception($class->name, $e));
    }
    catch (\Throwable $e)
    {
        $logger->log_error($logger->error_from_exception($class->name, $e));
    }
    return null;
}


/**
 * @param object $object
 * @param ?MethodInfo $setup_method
 * @param ?MethodInfo $teardown_method
 * @return void
 */
function _run_method(
    State $state, $object, MethodTest $test, $setup_method, $teardown_method, RunInstance $run)
{
    $run_name = $run->qualifier;
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($setup_method)
    {
        $name = $setup_method->name . ' for ' . $test_name;
        $setup = array($object, $setup_method->name);
        \assert(\is_callable($setup));
        $logger = $state->bufferer->start_buffering(
            $name,
            $setup_method->file,
            $setup_method->line);
        list($result, ) = namespace\_run_setup(
            $logger,
            $name,
            $setup_method->file,
            $setup_method->line,
            $setup);
    }

    if (namespace\RESULT_PASS === $result)
    {
        $method = array($object, $test->test->name);
        \assert(\is_callable($method));
        $logger = $state->bufferer->start_buffering(
            $test_name,
            $test->test->file,
            $test->test->line);
        $context = new _Context($state, $logger, $test, $run);
        $result = namespace\_run_test($logger, $test_name, $method, $context);

        // @todo Buffer function-specific teardown functions separately from test function?
        while ($context->teardowns)
        {
            // @todo assign a more descriptive name to test-specific teardowns?
            $teardown = \array_pop($context->teardowns);
            $result |= namespace\_run_teardown($logger, $test_name, $teardown);
        }

        if ($teardown_method)
        {
            $name = $teardown_method->name . ' for ' . $test_name;
            $teardown = array($object, $teardown_method->name);
            \assert(\is_callable($teardown));
            $logger = $state->bufferer->start_buffering(
                $name,
                $teardown_method->file,
                $teardown_method->line);
            $result |= namespace\_run_teardown($logger, $name, $teardown);
        }
    }
    $state->bufferer->end_buffering($state->logger);
    namespace\_record_test_result($state, $test, $run, $test_name, $result);
}


/**
 * @param ?FunctionInfo $setup_function
 * @param ?FunctionInfo $teardown_function
 * @param mixed[] $args
 * @return void
 */
function _run_function(
    State $state,
    FunctionTest $test, $setup_function, $teardown_function, RunInstance $run, array $args)
{
    $run_name = $run->qualifier;
    $test_name = $test->name . $run_name;

    $result = namespace\RESULT_PASS;
    if ($setup_function)
    {
        $name = $setup_function->short_name . ' for ' . $test_name;
        $logger = $state->bufferer->start_buffering(
            $name,
            $setup_function->file,
            $setup_function->line);
        list($result, $args) = namespace\_run_setup(
            $logger,
            $name,
            $setup_function->file,
            $setup_function->line,
            $setup_function->name,
            $args);
    }

    if (namespace\RESULT_PASS === $result)
    {
        if (\is_array($args))
        {
            $logger = $state->bufferer->start_buffering(
                $test_name,
                $test->test->file,
                $test->test->line);
            $context = new _Context($state, $logger, $test, $run);
            $result = namespace\_run_test(
                $logger,
                $test_name,
                $test->test->name,
                $context,
                $args);

            // @todo Buffer test-specific teardown functions separately from the test?
            while ($context->teardowns)
            {
                // @todo assign a more descriptive name to test-specific teardowns?
                $teardown = \array_pop($context->teardowns);
                $result |= namespace\_run_teardown($logger, $test_name, $teardown);
            }
        }

        if ($teardown_function)
        {
            $name = $teardown_function->short_name . ' for ' . $test_name;
            $logger = $state->bufferer->start_buffering(
                $name,
                $teardown_function->file,
                $teardown_function->line);
            $result |= namespace\_run_teardown(
                $logger,
                $name,
                $teardown_function->name,
                $args);
        }
    }
    $state->bufferer->end_buffering($state->logger);
    namespace\_record_test_result($state, $test, $run, $test_name, $result);
}


/**
 * @param FunctionTest|MethodTest $test
 * @param string $name
 * @param int $result
 * @return void
 */
function _record_test_result(State $state, $test, RunInstance $run, $name, $result)
{
    $index = $test->hash;
    if (namespace\RESULT_POSTPONE !== $result)
    {
        if (!isset($state->results[$index]))
        {
            $state->results[$index] = array(
                'group' => $run->group,
                'runs' => array(),
            );
        }

        if (namespace\RESULT_PASS === $result)
        {
            $state->logger->log_pass(
                new PassEvent($name, $test->test->file, $test->test->line));
            for ( ; $run; $run = $run->parent)
            {
                $id = $run->hash;
                if (!isset($state->results[$index]['runs'][$id]))
                {
                    $state->results[$index]['runs'][$id] = true;
                }
            }
        }
        elseif (namespace\RESULT_FAIL & $result)
        {
            for ( ; $run; $run = $run->parent)
            {
                $id = $run->hash;
                $state->results[$index]['runs'][$id] = false;
            }
            if (namespace\RESULT_POSTPONE & $result)
            {
                unset($state->postponed[$index]);
            }
        }
    }
}


/**
 * @param string $name
 * @param string $file
 * @param int $line
 * @param callable(mixed ...$args): mixed $callable
 * @param ?mixed[] $args
 * @return array{int, mixed}
 */
function _run_setup(Logger $logger, $name, $file, $line, $callable, array $args = null)
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
                    $logger->log_error(new ErrorEvent($name, $message, $file, $line));
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
        $logger->log_skip($logger->skip_from_exception($name, $e));
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($logger->error_from_exception($name, $e));
    }
    catch (\Throwable $e)
    {
        $logger->log_error($logger->error_from_exception($name, $e));
    }

    return array($status, $result);
}


/**
 * @param string $name
 * @param callable(mixed ...$args): void $callable
 * @param ?mixed[] $args
 * @return int
 */
function _run_test(Logger $logger, $name, $callable, _Context $context, array $args = null)
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
        $logger->log_failure($logger->failure_from_exception($name, $e));
        $result = namespace\RESULT_FAIL;
    }
    // @bc 5.6 Catch Failure
    catch (Failure $e)
    {
        $logger->log_failure($logger->failure_from_exception($name, $e));
        $result = namespace\RESULT_FAIL;
    }
    catch (Skip $e)
    {
        $logger->log_skip($logger->skip_from_exception($name, $e));
        $result = namespace\RESULT_FAIL;
    }
    catch (Postpone $_)
    {
        $result = namespace\RESULT_POSTPONE;
    }
    // @bc 5.6 Catch Exception
    catch (\Exception $e)
    {
        $logger->log_error($logger->error_from_exception($name, $e));
        $result = namespace\RESULT_FAIL;
    }
    catch (\Throwable $e)
    {
        $logger->log_error($logger->error_from_exception($name, $e));
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
        $logger->log_error($logger->error_from_exception($name, $e));
    }
    catch (\Throwable $e)
    {
        $logger->log_error($logger->error_from_exception($name, $e));
    }
    return namespace\RESULT_FAIL;
}


/**
 * @param string[] $names
 * @return string
 */
function _get_run_hash(array $names)
{
    return \implode(',', $names);
}


/**
 * @param string[] $names
 * @return string
 */
function _format_run_qualifier(array $names)
{
    if ($names)
    {
        $result = ' ('. \implode(', ', $names) . ')';
    }
    else
    {
        $result = '';
    }

    return $result;
}
