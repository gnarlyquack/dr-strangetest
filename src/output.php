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

    if (isset($event->reason))
    {
        $message .= $event->reason;
    }

    if (isset($event->file))
    {
        if (\strlen($message))
        {
            $message .= "\n";
        }

        $message .= 'in ' . $event->file;

        if (isset($event->line))
        {
            $message .= ':' . $event->line;
        }
    }

    if ($event->additional)
    {
        if (\strlen($message))
        {
            $message .= "\n\n";
        }
        $message .= $event->additional;
    }

    return $message;
}


/**
 * @return void
 */
function output_log(Log $log)
{
    $event_types = array(
        namespace\EVENT_FAIL => 'FAILED',
        namespace\EVENT_ERROR => 'ERROR',
        namespace\EVENT_SKIP => 'SKIPPED',
        namespace\EVENT_OUTPUT => 'OUTPUT',
    );

    $output_count = 0;
    $skip_count = 0;
    foreach ($log->get_events() as $event)
    {
        if ($event instanceof Event)
        {
            $type = $event->type;
            $source = $event->source;
            // @fixme Fixure out how/where to format messagse from events
            $message = namespace\_format_message_from_event($event);
        }
        else
        {
            list($type, $source, $message) = $event;
        }
        switch ($type)
        {
            case namespace\EVENT_OUTPUT:
                ++$output_count;
                break;

            case namespace\EVENT_SKIP:
                ++$skip_count;
                break;
        }

        \printf("\n\n\n%s: %s\n%s\n", $event_types[$type], $source, $message);
    }

    $passed = $log->pass_count();
    $failed = $log->failure_count();
    $errors = $log->error_count();
    $skipped = $log->skip_count();
    $output = $log->output_count();
    $omitted = array();
    if ($output_count !== $output)
    {
        $omitted[] = 'output';
    }
    if ($skip_count !== $skipped)
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
    if ($passed)
    {
        $summary[] = \sprintf('Passed: %d', $passed);
    }
    if ($failed)
    {
        $summary[] = \sprintf('Failed: %d', $failed);
    }
    if ($errors)
    {
        $summary[] = \sprintf('Errors: %d', $errors);
    }
    if ($skipped)
    {
        $summary[] = \sprintf('Skipped: %d', $skipped);
    }
    if ($output)
    {
        $summary[] = \sprintf('Output: %d', $output);
    }

    if ($summary)
    {
        echo
            ($omitted ? "\n\n" : "\n\n\n"),
            "Seconds elapsed: ", $log->seconds_elapsed(),
            "\nMemory used: ", $log->memory_used(), " MB\n",
            \implode(', ', $summary), "\n";
    }
    else
    {
        echo "No tests found!\n";
    }
}
