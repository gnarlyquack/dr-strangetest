<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class Log extends struct
{
    /** @var float */
    public $megabytes_used;

    /** @var float */
    public $seconds_elapsed;

    /** @var int[] $count */
    private $count;

    /** @var array{int, string, string|\Throwable|null}[] */
    private $events;


    /**
     * @param int[] $count
     * @param array{int, string, string|\Throwable|null}[] $events
     */
    public function __construct(array $count, array $events)
    {
        $this->count = $count;
        $this->events = $events;
    }


    /**
     * @return int
     */
    public function pass_count()
    {
        return $this->count[namespace\EVENT_PASS];
    }


    /**
     * @return int
     */
    public function failure_count()
    {
        return $this->count[namespace\EVENT_FAIL];
    }


    /**
     * @return int
     */
    public function error_count()
    {
        return $this->count[namespace\EVENT_ERROR];
    }

    /**
     * @return int
     */
    public function skip_count()
    {
        return $this->count[namespace\EVENT_SKIP];
    }


    /**
     * @return int
     */
    public function output_count()
    {
        return $this->count[namespace\EVENT_OUTPUT];
    }


    /**
     * @return float
     */
    public function seconds_elapsed()
    {
        return $this->seconds_elapsed;
    }


    /**
     * @return float
     */
    public function memory_used()
    {
        return $this->megabytes_used;
    }


    /**
     * @return array{int, string, string|\Throwable|null}[]
     */
    public function get_events()
    {
        // This is safe because PHP arrays are copy-on-write
        return $this->events;
    }
}



final class Logger extends struct
{
    /** @var int[] */
    private $count = array(
        namespace\EVENT_PASS   => 0,
        namespace\EVENT_ERROR  => 0,
        namespace\EVENT_FAIL   => 0,
        namespace\EVENT_SKIP   => 0,
        namespace\EVENT_OUTPUT => 0,
    );

    /** @var array{int, string, string|\Throwable|null}[] */
    private $events = array();

    /** @var int */
    private $verbose;

    /** @var LogOutputter */
    private $outputter;

    /** @var ?string */
    public $buffer;

    /** @var bool */
    public $error = false;

    /** @var int */
    public $ob_level_current;

    /** @var int */
    public $ob_level_start;

    /** @var array{int, string, string|\Throwable|null}[] */
    public $queued = array();


    /**
     * @param int $verbose
     */
    public function __construct($verbose, LogOutputter $outputter)
    {
        $this->verbose = $verbose;
        $this->outputter = $outputter;
    }


    /**
     * @param string $source
     * @return void
     */
    public function log_pass($source)
    {
        if ($this->buffer)
        {
            $this->queued[] = array(namespace\EVENT_PASS, $source, null);
        }
        else
        {
            ++$this->count[namespace\EVENT_PASS];
            if ($this->verbose & namespace\LOG_PASS)
            {
                $this->events[] = array(namespace\EVENT_PASS, $source, null);
            }
            $this->outputter->output_pass();
        }
    }


    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @return void
     */
    public function log_failure($source, $reason)
    {
        if ($this->buffer)
        {
            $this->queued[] = array(namespace\EVENT_FAIL, $source, $reason);
            $this->error = true;
        }
        else
        {
            ++$this->count[namespace\EVENT_FAIL];
            $this->events[] = array(namespace\EVENT_FAIL, $source, $reason);
            $this->outputter->output_failure();
        }
    }


    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @return void
     */
    public function log_error($source, $reason)
    {
        if ($this->buffer)
        {
            $this->queued[] = array(namespace\EVENT_ERROR, $source, $reason);
            $this->error = true;
        }
        else
        {
            ++$this->count[namespace\EVENT_ERROR];
            $this->events[] = array(namespace\EVENT_ERROR, $source, $reason);
            $this->outputter->output_error();
        }
    }


    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @param ?bool $during_error
     * @return void
     */
    public function log_skip($source, $reason, $during_error = false)
    {
        if ($this->buffer)
        {
            $this->queued[] = array(namespace\EVENT_SKIP, $source, $reason);
        }
        else
        {
            ++$this->count[namespace\EVENT_SKIP];
            if ($during_error)
            {
                \assert($reason instanceof \Throwable);
                $reason = new Skip('Although this test was skipped, there was also an error', $reason);
                $this->events[] = array(namespace\EVENT_SKIP, $source, $reason);
            }
            elseif ($this->verbose & namespace\LOG_SKIP)
            {
                $this->events[] = array(namespace\EVENT_SKIP, $source, $reason);
            }
            $this->outputter->output_skip();
        }
    }


    /**
     * @param string $source
     * @param string|\Throwable|null $output
     * @param ?bool $during_error
     * @return void
     */
    public function log_output($source, $output, $during_error = false)
    {
        if ($this->buffer)
        {
            $this->queued[] = array(namespace\EVENT_OUTPUT, $source, $output);
        }
        else
        {
            ++$this->count[namespace\EVENT_OUTPUT];
            if (($this->verbose & namespace\LOG_OUTPUT) || $during_error)
            {
                $this->events[] = array(namespace\EVENT_OUTPUT, $source, $output);
            }
            $this->outputter->output_output();
        }
    }


    /**
     * @return Log
     */
    public function get_log()
    {
        return new Log($this->count, $this->events);
    }
}


/**
 * @param string $source
 * @return void
 */
function start_buffering(Logger $logger, $source)
{
    if ($logger->buffer)
    {
        namespace\_reset_buffer($logger);
        $logger->buffer = $source;
    }
    else
    {
        \ob_start();
        $logger->buffer = $source;
        $logger->ob_level_start = $logger->ob_level_current = \ob_get_level();
    }
}


/**
 * @return void
 */
function end_buffering(Logger $logger)
{
    $source = $logger->buffer;
    \assert(\is_string($source));

    // While clearing out the buffers, we still want to keep the logger
    // buffered in case this process results in more events being logged. This
    // way, the new events are properly added to the end of the existing
    // buffered event queue.
    $buffers = array();
    for ($level = \ob_get_level();
         $level > $logger->ob_level_start;
         --$level)
    {
        $logger->log_error(
            $source,
            \sprintf(
                "An output buffer was started but never deleted.\nBuffer contents were: %s",
                namespace\_format_buffer((string)\ob_get_clean())
            )
        );
    }

    if ($level < $logger->ob_level_start)
    {
        $logger->log_error(
            $source,
            "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
    }
    else
    {
        $output = (string)\ob_get_clean();
        if (\strlen($output))
        {
            $logger->log_output(
                $source,
                namespace\_format_buffer($output),
                $logger->error
            );
        }
    }

    // Now unbuffer the logger and log any buffered events as normal
    $logger->buffer = null;
    foreach ($logger->queued as $event)
    {
        list($type, $source, $reason) = $event;
        switch ($type)
        {
            case namespace\EVENT_PASS:
                $logger->log_pass($source);
                break;

            case namespace\EVENT_FAIL:
                $logger->log_failure($source, $reason);
                break;

            case namespace\EVENT_ERROR:
                $logger->log_error($source, $reason);
                break;

            case namespace\EVENT_SKIP:
                $logger->log_skip($source, $reason, $logger->error);
                break;

            case namespace\EVENT_OUTPUT:
                $logger->log_output($source, $reason, $logger->error);
                break;
        }
    }

    // Now clear out any state accumulated while the logger was buffered
    $logger->error = false;
    $logger->queued = array();
}


/**
 * @return void
 */
function _reset_buffer(Logger $logger)
{
    \assert(\is_string($logger->buffer));
    $level = \ob_get_level();

    if ($level < $logger->ob_level_start)
    {
        $logger->log_error(
            $logger->buffer,
            "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
        \ob_start();
        $logger->ob_level_start = $logger->ob_level_current = \ob_get_level();
        return;
    }

    $buffers = array();
    if ($level > $logger->ob_level_start)
    {
        // Somebody else is doing their own output buffering, so we don't
        // want to mess it with. If their buffering started before we last
        // reset our own buffer then we don't need to do anything. But if
        // their buffering started after we last reset our own buffer, then
        // we want to pop off the other buffer(s), handle our own, and then
        // put the other buffer(s) back.
        if ($logger->ob_level_current > $logger->ob_level_start)
        {
            return;
        }

        $logger->ob_level_current = $level;
        while ($level-- > $logger->ob_level_start)
        {
            $buffers[] = \ob_get_clean();
        }
    }

    $output = (string)\ob_get_contents();
    \ob_clean();
    if (\strlen($output))
    {
        $logger->log_output(
            $logger->buffer,
            namespace\_format_buffer($output),
            $logger->error
        );
    }

    while ($buffers)
    {
        // Output buffers stack, so the first buffer we read was the last
        // buffer that was written. To restore them in the correct order,
        // we output them in reverse order from which we read them
        $buffer = \array_pop($buffers);
        \ob_start();
        echo $buffer;
    }
    \assert($logger->ob_level_current === \ob_get_level());
}


/**
 * @param string $buffer
 * @return string
 */
function _format_buffer($buffer)
{
    if ('' === $buffer)
    {
        return '[the output buffer was empty]';
    }
    if ('' === \trim($buffer))
    {
        return \sprintf(
            "[the output buffer contained only whitespace]\n%s",
            \var_export($buffer, true)
        );
    }
    return $buffer;
}
