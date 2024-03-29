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
    /** @var string[] */
    public $run_names;

    /** @var array<string, ?string[]> */
    public $prerequisites = array();

    /**
     * @param string[] $run_names
     */
    public function __construct(array $run_names)
    {
        $this->run_names = $run_names;
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
    $index = $dependency->test->hash;
    if (isset($graph->marked[$index]))
    {
        $valid = $graph->marked[$index];
    }
    else
    {
        $graph->stack[$index] = true;
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
                        $graph->state->logger->log_error(
                            new ErrorEvent(
                                $dependency->test->name,
                                \sprintf(
                                    "This test has a cyclical dependency with the following tests:\n\t%s",
                                    \implode("\n\t", \array_slice($cycle, 1))),
                                $dependency->test->test->file,
                                $dependency->test->test->line));
                    }
                    else
                    {
                        $valid = namespace\_resolve_dependency(
                            $graph, $graph->state->postponed[$pre_name]);
                        if (!$valid)
                        {
                            $graph->state->logger->log_skip(
                                new SkipEvent(
                                    $dependency->test->name,
                                    \sprintf(
                                        "This test depends on '%s', which did not pass",
                                        $pre_name),
                                    $dependency->test->test->file,
                                    $dependency->test->test->line));
                        }
                    }
                }
                elseif (!isset($graph->state->results[$pre_name]))
                {
                    $valid = false;
                    $graph->state->logger->log_error(
                        new ErrorEvent(
                            $dependency->test->name,
                            \sprintf(
                                "This test depends on test '%s', which was never run",
                                $pre_name),
                            $dependency->test->test->file,
                            $dependency->test->test->line));
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
        $graph->marked[$index] = $valid;

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
        $result->id = $tests->id;
        $result->path = $tests->path;
        foreach ($dependencies as $dependency)
        {
            foreach ($dependency->runs as $run)
            {
                namespace\_add_run_from_dependency(
                    $state, $tests, $result, $dependency->test, $run->run_names, 0);
            }
        }
    }
    else
    {
        $result = new DirectoryTest;
        $result->name = $tests->name;
        $result->setup = $tests->setup;
        $result->teardown = $tests->teardown;
        $result->tests = array();

        foreach ($dependencies as $dependency)
        {
            foreach ($dependency->runs as $run)
            {
                namespace\_add_directory_test_from_dependency(
                    $state, $tests, $result, $dependency->test, $run->run_names, 0);
            }
        }
    }

    return $result;
}


/**
 * @param FunctionTest|MethodTest $dependency
 * @param string[] $run_names
 * @param int $run_index
 * @return void
 */
function _add_run_from_dependency(
    State $state, TestRunGroup $reference,
    TestRunGroup $test, $dependency, array $run_names, $run_index)
{
    $run_name = $run_names[$run_index++];
    $run = $reference->runs[$run_name];
    $source = $run->tests;

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
            $child->setup = $source->setup;
            $child->teardown = $source->teardown;
            $child->tests = array();
        }
        else
        {
            $child = new FileTest;
            $child->name = $source->name;
            $child->setup_file = $source->setup_file;
            $child->teardown_file = $source->teardown_file;
            $child->setup_function = $source->setup_function;
            $child->teardown_function = $source->teardown_function;
        }

        $test_run = new TestRun;
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
            $state, $source, $child, $dependency, $run_names, $run_index);
    }
    else
    {
        \assert($source instanceof FileTest);
        namespace\_add_file_test_from_dependency($state, $source, $child, $dependency);
    }
}


/**
 * @param FunctionTest|MethodTest $dependency
 * @param string[] $run_names
 * @param int $run_index
 * @return void
 */
function _add_directory_test_from_dependency(
    State $state, DirectoryTest $reference,
    DirectoryTest $test, $dependency, array $run_names, $run_index)
{
    \assert($reference->name === $test->name);

    $file = $dependency->test->file;
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
            $child->id = $source->id;
            $child->path = $source->path;
            $test->tests[] = $child;
        }
        namespace\_add_run_from_dependency(
            $state, $source, $child, $dependency, $run_names, $run_index);
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
            $child->setup = $source->setup;
            $child->teardown = $source->teardown;
            $child->tests = array();
            $test->tests[] = $child;
        }
        namespace\_add_directory_test_from_dependency(
            $state, $source, $child, $dependency, $run_names, $run_index);
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
    \assert($test->name === $dependency->test->file);

    if ($dependency instanceof MethodTest)
    {
        // @todo Save hash for ClassTest
        $name = 'class ' . namespace\normalize_identifier($dependency->test->class->name);
        $class = $reference->tests[$name];
        \assert($class instanceof ClassTest);
        $last = \end($test->tests);
        if ($last
            && ($last instanceof ClassTest)
            && ($last->test->name === $dependency->test->class->name))
        {
            $child = $last;
        }
        else
        {
            $child = new ClassTest;
            $child->test = $class->test;
            $child->setup_object = $class->setup_object;
            $child->teardown_object = $class->teardown_object;
            $child->setup_method = $class->setup_method;
            $child->teardown_method = $class->teardown_method;
            $test->tests[] = $child;
        }
        // @todo Save short hash for method name (without class)?
        $child->tests[] = $class->tests[namespace\normalize_identifier($dependency->test->name)];
    }
    else
    {
        $name = 'function ' . $dependency->hash;
        $test->tests[] = $reference->tests[$name];
    }
}
