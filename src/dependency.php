<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class Postpone extends \Exception {}


final class Dependency extends struct {
    /** @var string */
    public $file;

    /** @var ?string */
    public $class;

    /** @var string */
    public $function;

    /** @var array<string, string[]> */
    public $dependees = array();


    /**
     * @param string $file
     * @param ?string $class
     * @param string $function
     */
    public function __construct($file, $class, $function)
    {
        $this->file = $file;
        $this->class = $class;
        $this->function = $function;
    }
}


final class _DependencyGraph {
    /** @var State */
    private $state;

    /** @var Logger */
    private $logger;

    /** @var Dependency[] */
    private $postorder = array();

    /** @var array<string, bool> */
    private $marked = array();

    /** @var array<string, bool> */
    private $stack = array();


    public function __construct(State $state, Logger $logger)
    {
        $this->state = $state;
        $this->logger = $logger;
    }


    /**
     * @return Dependency[]
     */
    public function sort()
    {
        foreach ($this->state->depends as $from => $_)
        {
            $this->postorder($from);
        }
        return $this->postorder;
    }


    /**
     * @param string $from
     * @param string[] $runs
     * @return bool
     */
    private function postorder($from, array $runs = array())
    {
        if (isset($this->marked[$from]))
        {
            if (!$this->marked[$from])
            {
                return false;
            }

            if (!isset($this->state->results[$from]))
            {
                return true;
            }

            return $this->check_run_results($from, $runs);
        }

        $this->marked[$from] = true;
        $this->stack[$from] = true;

        if (isset($this->state->depends[$from]))
        {
            $dependency = $this->state->depends[$from];
            foreach ($dependency->dependees as $to => $runs)
            {
                if (isset($this->stack[$to]))
                {
                    $cycle = array();
                    \end($this->stack);
                    do
                    {
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
                else
                {
                    $this->marked[$from] = $this->postorder($to, $runs);
                    if (!$this->marked[$from])
                    {
                        $this->logger->log_skip($from, "This test depends on '$to', which did not pass");
                    }
                }
            }

            if ($this->marked[$from])
            {
                $this->postorder[] = $dependency;
            }
        }
        else
        {
            if (!isset($this->state->results[$from]))
            {
                $this->marked[$from] = false;
                $this->logger->log_error(
                    $from,
                    'Other tests depend on this test, but this test was never run'
                );
            }
            else
            {
                $result = $this->check_run_results($from, $runs);
            }
        }

        \array_pop($this->stack);
        return isset($result) ? $result : $this->marked[$from];
    }


    /**
     * @param string $from
     * @param string[] $runs
     * @return bool
     */
    private function check_run_results($from, array $runs)
    {
        $result = true;
        foreach ($runs as $run)
        {
            if (!isset($this->state->results[$from]['runs'][$run]))
            {
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


/**
 * @return Dependency[]
 */
function resolve_dependencies(State $state, Logger $logger)
{
    $graph = new _DependencyGraph($state, $logger);
    return $graph->sort();
}
