<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class Postpone extends \Exception
{
}


final class FunctionDependency extends struct
{
    /** @var FunctionTest|MethodTest */
    public $test;

    /** @var array<string, RunDependency> */
    public $runs = array();

    /**
     * @param FunctionTest|MethodTest $test
     */
    public function __construct($test)
    {
        $this->test = $test;
    }
}


final class RunDependency extends struct
{
    /** @var int[] */
    public $run_ids;

    /** @var array<string, ?int[]> */
    public $prerequisites = array();

    /**
     * @param int[] $run_ids
     */
    public function __construct(array $run_ids)
    {
        $this->run_ids = $run_ids;
    }
}


final class _DependencyGraph extends struct
{
    /** @var State */
    public $state;

    /** @var FunctionDependency[] */
    public $postorder = array();

    /** @var array<string, bool> */
    public $marked = array();

    /** @var array<string, bool> */
    public $stack = array();


    public function __construct(State $state)
    {
        $this->state = $state;
    }
}


/**
 * @return FunctionDependency[]
 */
function resolve_dependencies(State $state)
{
    $graph = new _DependencyGraph($state);
    foreach ($state->postponed as $postponed)
    {
        namespace\_resolve_dependency($graph, $postponed);
    }
    return $graph->postorder;
}


/**
 * @todo Ensure dependencies are resolved by run, not just by test
 * @return bool
 */
function _resolve_dependency(_DependencyGraph $graph, FunctionDependency $dependency)
{
    $name = $dependency->test->name;
    if (isset($graph->marked[$name]))
    {
        $valid = $graph->marked[$name];
    }
    else
    {
        $graph->stack[$name] = true;
        $valid = true;
        foreach ($dependency->runs as $run)
        {
            foreach ($run->prerequisites as $pre_name => $pre_run)
            {
                if (isset($graph->state->postponed[$pre_name]))
                {
                    if (isset($graph->stack[$pre_name]))
                    {
                        $cycle = array();
                        \end($graph->stack);
                        do
                        {
                            $key = \key($graph->stack);
                            $cycle[] = $key;
                            \prev($graph->stack);
                        } while ($key !== $pre_name);

                        $valid = false;

                        $file = $dependency->test->test->getFileName();
                        $line = $dependency->test->test->getStartLine();
                        // @todo Eliminate asserting if reflection function returns file info
                        \assert(\is_string($file));
                        \assert(\is_int($line));
                        $graph->state->logger->log_error(
                            new ErrorEvent(
                                $name,
                                \sprintf(
                                    "This test has a cyclical dependency with the following tests:\n\t%s",
                                    \implode("\n\t", \array_slice($cycle, 1))),
                                $file, $line));
                    }
                    else
                    {
                        $valid = namespace\_resolve_dependency(
                            $graph, $graph->state->postponed[$pre_name]);
                        if (!$valid)
                        {
                            $file = $dependency->test->test->getFileName();
                            $line = $dependency->test->test->getStartLine();
                            // @todo Eliminate asserting if reflection function returns file info
                            \assert(\is_string($file));
                            \assert(\is_int($line));
                            $graph->state->logger->log_skip(
                                new SkipEvent(
                                    $name,
                                    "This test depends on '{$pre_name}', which did not pass",
                                    $file, $line));
                        }
                    }
                }
                elseif (!isset($graph->state->results[$pre_name]))
                {
                    $valid = false;
                    $file = $dependency->test->test->getFileName();
                    $line = $dependency->test->test->getStartLine();
                    // @todo Eliminate asserting if reflection function returns file info
                    \assert(\is_string($file));
                    \assert(\is_int($line));
                    $graph->state->logger->log_error(
                        new ErrorEvent(
                            $name,
                            "This test depends on test '{$pre_name}', which was never run",
                            $file, $line));
                }
                else
                {
                    \assert((null === $pre_run) || (\count($pre_run) > 0));
                    $pre_run_id = isset($pre_run) ? \implode(',', $pre_run) : '';
                    \assert(
                        !isset($pre_run)
                        || isset($graph->state->results[$pre_name]['runs'][$pre_run_id]));
                }

                if (!$valid)
                {
                    break 2;
                }
            }
        }
        \array_pop($graph->stack);
        $graph->marked[$name] = $valid;

        if ($valid)
        {
            $graph->postorder[] = $dependency;
        }
    }
    return $valid;
}


/**
 * @param TestRunGroup|DirectoryTest $tests
 * @param FunctionDependency[] $dependencies
 * @return TestRunGroup|DirectoryTest
 */
function build_tests_from_dependencies(State $state, $tests, array $dependencies)
{
    if ($tests instanceof TestRunGroup)
    {
        $result = new TestRunGroup;
        $result->path = $tests->path;
        foreach ($dependencies as $dependency)
        {
            foreach ($dependency->runs as $run)
            {
                namespace\_add_run_from_dependency(
                    $state, $tests, $result, $dependency->test, $run->run_ids, 1);
            }
        }
    }
    else
    {
        $result = new DirectoryTest;
        $result->name = $tests->name;
        $result->run_group_id = $tests->run_group_id;
        $result->setup = $tests->setup;
        $result->teardown = $tests->teardown;
        $result->tests = array();

        foreach ($dependencies as $dependency)
        {
            foreach ($dependency->runs as $run)
            {
                namespace\_add_directory_test_from_dependency(
                    $state, $tests, $result, $dependency->test, $run->run_ids, 1);
            }
        }
    }

    return $result;
}


/**
 * @param FunctionTest|MethodTest $dependency
 * @param int[] $run_ids
 * @param int $run_index
 * @return void
 */
function _add_run_from_dependency(
    State $state, TestRunGroup $reference,
    TestRunGroup $test, $dependency, array $run_ids, $run_index)
{
    $run_id = $run_ids[$run_index++] - 1;
    $run = $state->runs[$run_id];
    $run_name = $run->name;
    $source = $reference->runs[$run_name]->tests;

    if (isset($test->runs[$run_name]))
    {
        $child = $test->runs[$run_name]->tests;
    }
    else
    {
        if ($source instanceof DirectoryTest)
        {
            $child = new DirectoryTest;
            $child->name = $source->name;
            $child->run_group_id = $source->run_group_id;
            $child->setup = $source->setup;
            $child->teardown = $source->teardown;
            $child->tests = array();
        }
        else
        {
            $child = new FileTest;
            $child->name = $source->name;
            $child->run_group_id = $source->run_group_id;
            $child->setup_file = $source->setup_file;
            $child->teardown_file = $source->teardown_file;
            $child->setup_function = $source->setup_function;
            $child->teardown_function = $source->teardown_function;
        }

        $test_run = new TestRun;
        $test_run->id = $run->id;
        $test_run->name = $run->name;
        $test_run->setup = $run->setup;
        $test_run->teardown = $run->teardown;
        $test_run->tests = $child;

        $test->runs[$run_name] = $test_run;
    }

    if ($child instanceof DirectoryTest)
    {
        \assert($source instanceof DirectoryTest);
        namespace\_add_directory_test_from_dependency(
            $state, $source, $child, $dependency, $run_ids, $run_index);
    }
    else
    {
        \assert($source instanceof FileTest);
        namespace\_add_file_test_from_dependency($state, $source, $child, $dependency);
    }
}


/**
 * @param FunctionTest|MethodTest $dependency
 * @param int[] $run_ids
 * @param int $run_index
 * @return void
 */
function _add_directory_test_from_dependency(
    State $state, DirectoryTest $reference,
    DirectoryTest $test, $dependency, array $run_ids, $run_index)
{
    \assert($reference->name === $test->name);

    // ReflectionFunctionAbstract should always return an actual filename here
    // (instead of false) because we only ever reflect functions and methods
    // that have been discovered in a test file
    $file = $dependency->test->getFileName();
    \assert($file !== false);
    \assert(0 === \substr_compare($file, $test->name, 0, \strlen($test->name)));

    $pos = \strpos($file, \DIRECTORY_SEPARATOR, \strlen($test->name));
    if (false === $pos)
    {
        $path = $file;
    }
    else
    {
        $path = \substr($file, 0, $pos + 1);
    }

    $source = $reference->tests[$path];
    $last = \end($test->tests);
    if ($source instanceof TestRunGroup)
    {
        if (($last instanceof TestRunGroup) && ($last->path === $source->path))
        {
            $child = $last;
        }
        else
        {
            $child = new TestRunGroup;
            $child->path = $source->path;
            $test->tests[] = $child;
        }
        namespace\_add_run_from_dependency(
            $state, $source, $child, $dependency, $run_ids, $run_index);
    }
    elseif ($source instanceof DirectoryTest)
    {
        if (($last instanceof DirectoryTest) && ($last->name === $source->name))
        {
            $child = $last;
        }
        else
        {
            $child = new DirectoryTest;
            $child->name = $source->name;
            $child->run_group_id = $source->run_group_id;
            $child->setup = $source->setup;
            $child->teardown = $source->teardown;
            $child->tests = array();
            $test->tests[] = $child;
        }
        namespace\_add_directory_test_from_dependency(
            $state, $source, $child, $dependency, $run_ids, $run_index);
    }
    else
    {
        if (($last instanceof FileTest) && ($last->name === $source->name))
        {
            $child = $last;
        }
        else
        {
            $child = new FileTest;
            $child->name = $source->name;
            $child->run_group_id = $source->run_group_id;
            $child->setup_file = $source->setup_file;
            $child->teardown_file = $source->teardown_file;
            $child->setup_function = $source->setup_function;
            $child->teardown_function = $source->teardown_function;
            $test->tests[] = $child;
        }
        namespace\_add_file_test_from_dependency($state, $source, $child, $dependency);
    }
}


/**
 * @param FunctionTest|MethodTest $dependency
 * @return void
 */
function _add_file_test_from_dependency(
    State $state, FileTest $reference,
    FileTest $test, $dependency)
{
    \assert($reference->name === $test->name);
    \assert($test->name === $dependency->test->getFileName());

    if ($dependency instanceof MethodTest)
    {
        $name = 'class ' . $dependency->test->class;
        $class = $reference->tests[$name];
        \assert($class instanceof ClassTest);
        $last = \end($test->tests);
        if ($last
            && ($last instanceof ClassTest)
            && ($last->test->name === $dependency->test->class))
        {
            $child = $last;
        }
        else
        {
            $child = new ClassTest;
            $child->run_group_id = $class->run_group_id;
            $child->test = $class->test;
            $child->setup_object = $class->setup_object;
            $child->teardown_object = $class->teardown_object;
            $child->setup_method = $class->setup_method;
            $child->teardown_method = $class->teardown_method;
            $test->tests[] = $child;
        }
        $child->tests[] = $class->tests[$dependency->test->name];
    }
    else
    {
        $name = 'function ' . $dependency->name;
        $test->tests[] = $reference->tests[$name];
    }
}
