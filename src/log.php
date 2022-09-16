<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


interface Event {}


final class PassEvent extends struct implements Event
{
    /** @var string */
    public $source;

    /** @var string */
    public $file;

    /** @var int */
    public $line;


    /**
     * @param string $source
     * @param string $file
     * @param int $line
     */
    public function __construct($source, $file, $line)
    {
        $this->source = $source;
        $this->file = $file;
        $this->line = $line;
    }
}


final class FailEvent extends struct implements Event
{
    /** @var string */
    public $source;

    /** @var string */
    public $reason;

    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var ?string */
    public $additional;


    /**
     * @param string $source
     * @param string $reason
     * @param string $file
     * @param int $line
     * @param ?string $additional
     */
    public function __construct($source, $reason, $file, $line, $additional = null)
    {
        $this->source = $source;
        $this->reason = $reason;
        $this->file = $file;
        $this->line = $line;
        $this->additional = $additional;
    }
}


final class ErrorEvent extends struct implements Event
{
    /** @var string */
    public $source;

    /** @var string */
    public $reason;

    /** @var ?string */
    public $file;

    /** @var ?int */
    public $line;

    /** @var ?string */
    public $additional;


    /**
     * @param string $source
     * @param string $reason
     * @param ?string $file
     * @param ?int $line
     * @param ?string $additional
     */
    public function __construct($source, $reason, $file = null, $line = null, $additional = null)
    {
        $this->source = $source;
        $this->reason = $reason;
        $this->file = $file;
        $this->line = $line;
        $this->additional = $additional;
    }
}


final class SkipEvent extends struct implements Event
{
    /** @var string */
    public $source;

    /** @var string */
    public $reason;

    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var ?string */
    public $additional;


    /**
     * @param string $source
     * @param string $reason
     * @param string $file
     * @param int $line
     * @param ?string $additional
     */
    public function __construct($source, $reason, $file, $line, $additional = null)
    {
        $this->source = $source;
        $this->reason = $reason;
        $this->file = $file;
        $this->line = $line;
        $this->additional = $additional;
    }
}


final class OutputEvent extends struct implements Event
{
    /** @var string */
    public $source;

    /** @var string */
    public $output;

    /** @var ?string */
    public $file;

    /** @var ?int */
    public $line;


    /**
     * @param string $source
     * @param string $output
     * @param ?string $file
     * @param ?int $line
     */
    public function __construct($source, $output, $file = null, $line = null)
    {
        $this->source = $source;
        $this->output = $output;
        $this->file = $file;
        $this->line = $line;
    }
}



final class Log extends struct
{
    /** @var float */
    public $megabytes_used;

    /** @var float */
    public $seconds_elapsed;

    /** @var int[] $count */
    private $count;

    /** @var Event[] */
    private $events;


    /**
     * @param int[] $count
     * @param Event[] $events
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
     * @return Event[]
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

    /** @var Event[] */
    private $events = array();

    /** @var int */
    private $verbose;

    /** @var LogOutputter */
    private $outputter;

    /** @var ?string */
    public $buffer;

    /** @var ?string */
    public $buffer_file;

    /** @var ?int */
    public $buffer_line;

    /** @var bool */
    public $error = false;

    /** @var int */
    public $ob_level_current;

    /** @var int */
    public $ob_level_start;

    /** @var Event[] */
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
     * @return void
     */
    public function log_pass(PassEvent $event)
    {
        if ($this->buffer)
        {
            $this->queued[] = $event;
        }
        else
        {
            ++$this->count[namespace\EVENT_PASS];
            if ($this->verbose & namespace\LOG_PASS)
            {
                $this->events[] = $event;
            }
            $this->outputter->output_pass();
        }
    }


    /**
     * @return void
     */
    public function log_failure(FailEvent $event)
    {
        if ($this->buffer)
        {
            $this->queued[] = $event;
            $this->error = true;
        }
        else
        {
            ++$this->count[namespace\EVENT_FAIL];
            $this->events[] = $event;
            $this->outputter->output_failure();
        }
    }


    /**
     * @return void
     */
    public function log_error(ErrorEvent $event)
    {
        if ($this->buffer)
        {
            $this->queued[] = $event;
            $this->error = true;
        }
        else
        {
            ++$this->count[namespace\EVENT_ERROR];
            $this->events[] = $event;
            $this->outputter->output_error();
        }
    }


    /**
     * @param ?bool $during_error
     * @return void
     */
    public function log_skip(SkipEvent $event, $during_error = false)
    {
        if ($this->buffer)
        {
            $this->queued[] = $event;
        }
        else
        {
            ++$this->count[namespace\EVENT_SKIP];
            if ($during_error)
            {
                // An error could happen during a skipped test if the skip is
                // thrown from a test function and then an error happens during
                // teardown
                $reason = "This test was skipped, but there was also an error\nThe reason the test was skipped is:\n\n" . $event->reason;
                $event->reason = $reason;
                $this->events[] = $event;
            }
            elseif ($this->verbose & namespace\LOG_SKIP)
            {
                $this->events[] = $event;
            }
            $this->outputter->output_skip();
        }
    }


    /**
     * @param ?bool $during_error
     * @return void
     */
    public function log_output(OutputEvent $event, $during_error = false)
    {
        if ($this->buffer)
        {
            $this->queued[] = $event;
        }
        else
        {
            ++$this->count[namespace\EVENT_OUTPUT];
            if (($this->verbose & namespace\LOG_OUTPUT) || $during_error)
            {
                $this->events[] = $event;
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
 * @param ?string $file
 * @param ?int $line
 * @return void
 */
function start_buffering(Logger $logger, $source, $file = null, $line = null)
{
    if ($logger->buffer)
    {
        namespace\_reset_buffer($logger);
        $logger->buffer = $source;
        $logger->buffer_file = $file;
        $logger->buffer_line = $line;
    }
    else
    {
        \ob_start();
        $logger->buffer = $source;
        $logger->buffer_file = $file;
        $logger->buffer_line = $line;
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
            new ErrorEvent(
                $source,
                \sprintf(
                    "An output buffer was started but never deleted.\nBuffer contents were: %s",
                    namespace\_format_buffer((string)\ob_get_clean())),
                $logger->buffer_file, $logger->buffer_line));
    }

    if ($level < $logger->ob_level_start)
    {
        $logger->log_error(
            new ErrorEvent(
                $source,
                "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.",
                $logger->buffer_file, $logger->buffer_line));
    }
    else
    {
        $output = (string)\ob_get_clean();
        if (\strlen($output))
        {
            $logger->log_output(
                new OutputEvent(
                    $source,
                    namespace\_format_buffer($output),
                    $logger->buffer_file, $logger->buffer_line));
        }
    }

    // Now unbuffer the logger and log any buffered events as normal
    $logger->buffer = $logger->buffer_file = $logger->buffer_line = null;
    foreach ($logger->queued as $event)
    {
        if ($event instanceof PassEvent)
        {
            $logger->log_pass($event);
        }
        elseif ($event instanceof FailEvent)
        {
            $logger->log_failure($event);
        }
        elseif ($event instanceof ErrorEvent)
        {
            $logger->log_error($event);
        }
        elseif ($event instanceof SkipEvent)
        {
            $logger->log_skip($event, $logger->error);
        }
        else
        {
            \assert($event instanceof OutputEvent);
            $logger->log_output($event, $logger->error);
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
            new ErrorEvent(
                $logger->buffer,
                "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.",
                $logger->buffer_file, $logger->buffer_line));
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
            new OutputEvent(
                $logger->buffer,
                namespace\_format_buffer($output),
                $logger->buffer_file, $logger->buffer_line));
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
