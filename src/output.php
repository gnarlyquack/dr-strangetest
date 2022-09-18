<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;


final class CommandLineOutputter extends struct implements LogOutputter
{
    public function output_pass()
    {
        echo '.';
    }


    public function output_failure()
    {
        echo 'F';
    }


    public function output_error()
    {
        echo 'E';
    }


    public function output_skip()
    {
        echo 'S';
    }


    public function output_output()
    {
        echo 'O';
    }
}



/**
 * @param string $text
 * @return void
 */
function output($text)
{
    echo "$text\n";
}


/**
 * @param string $text
 * @return void
 */
function output_header($text)
{
    echo "$text\n\n";
}


/**
 * @return string
 */
function _format_message_from_event(Event $event)
{
    $message = '';

    if ($event instanceof PassEvent)
    {
        $message = \sprintf("PASS: %s\nin %s:%d\n",
            $event->source, $event->file, $event->line);
    }
    elseif ($event instanceof FailEvent)
    {
        $message = \sprintf("FAILED: %s\n%s\nin %s:%d\n",
            $event->source, $event->reason, $event->file, $event->line);
        if (isset($event->additional))
        {
            $message .= "\n" . $event->additional . "\n";
        }
    }
    elseif ($event instanceof ErrorEvent)
    {
        $message = \sprintf("ERROR: %s\n%s\n", $event->source, $event->reason);

        if (isset($event->file, $event->line))
        {
            $message .= \sprintf("in %s:%d\n", $event->file, $event->line);
        }

        if (isset($event->additional))
        {
            $message .= "\n" . $event->additional . "\n";
        }
    }
    elseif ($event instanceof SkipEvent)
    {
        $message = \sprintf("SKIPPED: %s\n%s\nin %s:%d\n",
            $event->source, $event->reason, $event->file, $event->line);
        if (isset($event->additional))
        {
            $message .= "\n" . $event->additional . "\n";
        }
    }
    else
    {
        \assert($event instanceof OutputEvent);
        $message = \sprintf("OUTPUT: %s\n%s\n", $event->source, $event->output);

        if (isset($event->file, $event->line))
        {
            $message .= \sprintf("in %s:%d\n", $event->file, $event->line);
        }
    }

    return $message;
}


/**
 * @return void
 */
function output_log(Log $log)
{
    $output_count = 0;
    $skip_count = 0;
    foreach ($log->events as $event)
    {
        // @fixme Figure out how/where to format messagse from events
        $message = namespace\_format_message_from_event($event);

        if ($event instanceof OutputEvent)
        {
            ++$output_count;
        }
        elseif ($event instanceof SkipEvent)
        {
            ++$skip_count;
        }

        echo "\n\n\n", $message;
    }

    $omitted = array();
    if ($output_count !== $log->output_count)
    {
        $omitted[] = 'output';
    }
    if ($skip_count !== $log->skip_count)
    {
        $omitted[] = 'skipped tests';
    }
    if ($omitted)
    {
        \printf(
            "\n\n\nThis report omitted %s.\nTo view, rerun Dr. Strangetest with the --verbose option.",
            \implode(' and ', $omitted)
        );
    }

    $summary = array();
    if ($log->pass_count)
    {
        $summary[] = \sprintf('Passed: %d', $log->pass_count);
    }
    if ($log->failure_count)
    {
        $summary[] = \sprintf('Failed: %d', $log->failure_count);
    }
    if ($log->error_count)
    {
        $summary[] = \sprintf('Errors: %d', $log->error_count);
    }
    if ($log->skip_count)
    {
        $summary[] = \sprintf('Skipped: %d', $log->skip_count);
    }
    if ($log->output_count)
    {
        $summary[] = \sprintf('Output: %d', $log->output_count);
    }

    if ($summary)
    {
        echo
            ($omitted ? "\n\n" : "\n\n\n"),
            "Seconds elapsed: ", $log->seconds_elapsed,
            "\nMemory used: ", $log->megabytes_used, " MB\n",
            \implode(', ', $summary), "\n";
    }
    else
    {
        echo "No tests found!\n";
    }
}
