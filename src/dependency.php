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
    /** @var FunctionTest */
    public $test;

    /** @var array<string, RunDependency> */
    public $runs = array();

    /**
     * @param FunctionTest $test
     */
    public function __construct($test)
    {
        $this->test = $test;
    }
}


final class RunDependency extends struct
{
    /** @var int[] */
    public $run;

    /** @var array<string, ?int[]> */
    public $prerequisites = array();

    /**
     * @param int[] $run
     */
    public function __construct(array $run)
    {
        $this->run = $run;
    }
}


final class _DependencyGraph extends struct
{
    /** @var State */
    public $state;

    /** @var Logger */
    public $logger;

    /** @var FunctionDependency[] */
    public $postorder = array();

    /** @var array<string, bool> */
    public $marked = array();

    /** @var array<string, bool> */
    public $stack = array();


    public function __construct(State $state, Logger $logger)
    {
        $this->state = $state;
        $this->logger = $logger;
    }
}


/**
 * @return FunctionDependency[]
 */
function resolve_dependencies(State $state, Logger $logger)
{
    $graph = new _DependencyGraph($state, $logger);
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
                        $graph->logger->log_error(
                            $name,
                            \sprintf(
                                "This test has a cyclical dependency with the following tests:\n\t%s",
                                \implode("\n\t", \array_slice($cycle, 1))
                            )
                        );
                    }
                    else
                    {
                        $valid = namespace\_resolve_dependency(
                            $graph, $graph->state->postponed[$pre_name]);
                        if (!$valid)
                        {
                            $graph->logger->log_skip(
                                $name,
                                "This test depends on '{$pre_name}', which did not pass");
                        }
                    }
                }
                elseif (!isset($graph->state->results[$pre_name]))
                {
                    $valid = false;
                    $graph->logger->log_error(
                        $name,
                        "This test depends on test '{$pre_name}', which was never run");
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
