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


final class _FormatExceptionResult extends struct
{
    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var ?string */
    public $trace;
}


/**
 * @param \Throwable $exception
 * @param string $root
 * @return _FormatExceptionResult
 */
function _format_exception($exception, $root)
{
    $result = new _FormatExceptionResult;
    $offset = \strlen($root);

    if (0 !== \strpos($exception->getFile(), __DIR__))
    {
        $file = $exception->getFile();
        $result->file = \substr($file, $offset);
        $result->line = $line = $exception->getLine();
    }

    // Create a backtrace excluding calls made within Dr. Strangetest
    $backtrace = array();
    foreach ($exception->getTrace() as $frame)
    {
        // There seem to be occasions (which may vary by versions of PHP) in
        // which a frame may not have a file, such as if an exception is thrown
        // from a function invoked from call_user_func(), or if an exception is
        // thrown from an error handler, and possibly others. It seems safe to
        // just ignore these.
        if (!isset($frame['file']))
        {
            continue;
        }
        if (0 === \strpos($frame['file'], __DIR__))
        {
            continue;
        }
        if ('strangetest\\main' === $frame['function'])
        {
            // strangetest\main is invoked from the entry point script, i.e.,
            // bin/strangetest. So when we've reached this function, we're at
            // the bottom of the call stack.
            break;
        }

        \assert(isset($frame['line']));
        if (!isset($file, $line))
        {
            $file = $frame['file'];
            $result->file = \substr($file, $offset);
            $result->line = $line = $frame['line'];
        }

        if (($line === $frame['line']) && ($file === $frame['file']))
        {
            continue;
        }

        $callee = $frame['function'];
        if (isset($frame['class']))
        {
            \assert(isset($frame['type']));
            $callee = $frame['class'] . $frame['type'] . $callee;
        }

        $backtrace[] = \sprintf(
            '%s(%d): %s()',
            \substr($frame['file'], $offset), $frame['line'], $callee);
    }

    if ($backtrace)
    {
        $result->trace = "Called from:\n" .  \implode("\n", $backtrace);
    }
    elseif (!isset($file, $line))
    {
        $result->file = \substr($exception->getFile(), $offset);
        $result->line = $exception->getLine();
    }

    return $result;
}


final class Log extends struct
{
    /** @var float Memory used during the test run */
    public $megabytes_used;

    /** @var float Duration of the test run */
    public $seconds_elapsed;

    /** @var int */
    public $pass_count;

    /** @var int */
    public $failure_count;

    /** @var int */
    public $error_count;

    /** @var int */
    public $skip_count;

    /** @var int */
    public $output_count;

    /** @var Event[] */
    public $events;
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

    /** @var string */
    private $root;

    /** @var int */
    private $verbose;

    /** @var LogOutputter */
    private $outputter;


    /**
     * @param string $root
     * @param int $verbose
     */
    public function __construct($root, $verbose, LogOutputter $outputter)
    {
        $this->root = $root;
        $this->verbose = $verbose;
        $this->outputter = $outputter;
    }


    /**
     * @return void
     */
    public function log_pass(PassEvent $event)
    {
        ++$this->count[namespace\EVENT_PASS];
        if ($this->verbose & namespace\LOG_PASS)
        {
            $this->events[] = $event;
        }
        $this->outputter->output_pass();
    }


    /**
     * @return void
     */
    public function log_failure(FailEvent $event)
    {
        ++$this->count[namespace\EVENT_FAIL];
        $this->events[] = $event;
        $this->outputter->output_failure();
    }


    /**
     * @return void
     */
    public function log_error(ErrorEvent $event)
    {
        ++$this->count[namespace\EVENT_ERROR];
        $this->events[] = $event;
        $this->outputter->output_error();
    }


    /**
     * @param ?bool $during_error
     * @return void
     */
    public function log_skip(SkipEvent $event, $during_error = false)
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


    /**
     * @param ?bool $during_error
     * @return void
     */
    public function log_output(OutputEvent $event, $during_error = false)
    {
        ++$this->count[namespace\EVENT_OUTPUT];
        if (($this->verbose & namespace\LOG_OUTPUT) || $during_error)
        {
            $this->events[] = $event;
        }
        $this->outputter->output_output();
    }


    /**
     * @param string $source
     * @param \AssertionError|Failure $failure
     * @return FailEvent
     */
    public function failure_from_exception($source, $failure)
    {
        $formatted = namespace\_format_exception($failure, $this->root);
        return new FailEvent($source, $failure->getMessage(), $formatted->file, $formatted->line, $formatted->trace);
    }


    /**
     * @param string $source
     * @param \Throwable $error
     * @return ErrorEvent
     */
    public function error_from_exception($source, $error)
    {
        $formatted = namespace\_format_exception($error, $this->root);
        return new ErrorEvent($source, $error->getMessage(), $formatted->file, $formatted->line, $formatted->trace);
    }


    /**
     * @param string $source
     * @return SkipEvent
     */
    public function skip_from_exception($source, Skip $skip)
    {
        $formatted = namespace\_format_exception($skip, $this->root);
        return new SkipEvent($source, $skip->getMessage(), $formatted->file, $formatted->line, $formatted->trace);
    }


    /**
     * @return Log
     */
    public function get_log()
    {
        $result = new Log;
        $result->pass_count = $this->count[namespace\EVENT_PASS];
        $result->error_count = $this->count[namespace\EVENT_ERROR];
        $result->failure_count = $this->count[namespace\EVENT_FAIL];
        $result->skip_count = $this->count[namespace\EVENT_SKIP];
        $result->output_count = $this->count[namespace\EVENT_OUTPUT];
        $result->events = $this->events;

        return $result;
    }


    /**
     * @return void
     */
    public function clear()
    {
        foreach ($this->count as $i => $_)
        {
            $this->count[$i] = 0;
        }
        $this->events = array();
    }
}


final class LogBufferer extends struct implements LogOutputter
{
    /** @var Logger */
    private $logger;

    /** @var ?string */
    public $buffer_source;

    /** @var ?string */
    public $buffer_file;

    /** @var ?int */
    public $buffer_line;

    /** @var bool */
    public $error = false;

    /** @var int */
    public $ob_level_start;


    /**
     * @param string $root
     */
    public function __construct($root)
    {
        $this->logger = new Logger($root, namespace\LOG_ALL, $this);
    }


    /**
     * @param string $source
     * @param ?string $file
     * @param ?int $line
     * @return Logger
     */
    public function start_buffering($source, $file = null, $line = null)
    {
        if ($this->buffer_source)
        {
            $this->reset_buffer();
        }
        else
        {
            \ob_start();
            $this->ob_level_start = \ob_get_level();
        }

        $this->buffer_source = $source;
        $this->buffer_file = $file;
        $this->buffer_line = $line;

        return $this->logger;
    }


    /**
     * @return void
     */
    public function end_buffering(Logger $logger)
    {
        \assert(\is_string($this->buffer_source));

        // While clearing out the buffers, we still want to keep the logger
        // buffered in case this process results in more events being logged. This
        // way, the new events are properly added to the end of the existing
        // buffered event queue.
        $buffers = array();
        for ($level = \ob_get_level();
             $level > $this->ob_level_start;
             --$level)
        {
            $this->logger->log_error(
                new ErrorEvent(
                    $this->buffer_source,
                    \sprintf(
                        "An output buffer was started but never deleted.\nBuffer contents were: %s",
                        namespace\_format_buffer((string)\ob_get_clean())),
                    $this->buffer_file, $this->buffer_line));
        }

        if ($level < $this->ob_level_start)
        {
            $this->logger->log_error(
                new ErrorEvent(
                    $this->buffer_source,
                    "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.",
                    $this->buffer_file, $this->buffer_line));
        }
        else
        {
            $output = (string)\ob_get_clean();
            if (\strlen($output))
            {
                $this->logger->log_output(
                    new OutputEvent(
                        $this->buffer_source,
                        namespace\_format_buffer($output),
                        $this->buffer_file, $this->buffer_line));
            }
        }

        // Now unbuffer the logger and log any buffered events as normal
        foreach ($this->logger->get_log()->events as $event)
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
                $logger->log_skip($event, $this->error);
            }
            else
            {
                \assert($event instanceof OutputEvent);
                $logger->log_output($event, $this->error);
            }
        }

        $this->buffer_source = $this->buffer_file = $this->buffer_line = null;
        $this->error = false;
        $this->logger->clear();
    }


    /**
     * @return void
     */
    public function reset_buffer()
    {
        \assert(\is_string($this->buffer_source));

        $level = \ob_get_level();

        if ($level < $this->ob_level_start)
        {
            $this->logger->log_error(
                new ErrorEvent(
                    $this->buffer_source,
                    "Dr. Strangetest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions.",
                    $this->buffer_file, $this->buffer_line));
            \ob_start();
            $this->ob_level_start = \ob_get_level();
            return;
        }

        $buffers = array();
        for ( ; $level > $this->ob_level_start; --$level)
        {
            // Somebody else is doing their own output buffering, so we want to pop
            // off their buffer(s), check our own, and then put their buffer(s)
            // back.
            $buffers[] = \ob_get_clean();
        }

        $output = (string)\ob_get_contents();
        if (\strlen($output))
        {
            \ob_clean();
            $this->logger->log_output(
                new OutputEvent(
                    $this->buffer_source,
                    namespace\_format_buffer($output),
                    $this->buffer_file, $this->buffer_line));
        }

        while ($buffers)
        {
            // Output buffers stack, so the first buffer we read was the last
            // buffer that was written. To restore them in the correct order,
            // we output them in reverse order from which we read them
            \ob_start();
            echo \array_pop($buffers);
        }
    }


    //
    // Implementation of LogOutputter interface
    //

    /** @return void */
    public function output_pass()
    {
    }

    /** @return void */
    public function output_failure()
    {
        $this->error = true;
    }

    /** @return void */
    public function output_error()
    {
        $this->error = true;
    }

    /** @return void */
    public function output_skip()
    {
    }

    /** @return void */
    public function output_output()
    {
    }
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
