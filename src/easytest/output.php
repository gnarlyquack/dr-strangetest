<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


function output($text) {
    echo "$text\n";
}


function output_header($text) {
    echo "$text\n\n";
}


function output_pass() {
    echo '.';
}


function output_error() {
    echo 'E';
}


function output_failure() {
    echo 'F';
}


function output_skip() {
    echo 'S';
}


function output_output() {
    echo 'O';
}


function output_log(Log $log, $secs_elapsed) {
    $event_types = [
        namespace\LOG_EVENT_FAIL => 'FAILED',
        namespace\LOG_EVENT_ERROR => 'ERROR',
        namespace\LOG_EVENT_SKIP => 'SKIPPED',
        namespace\LOG_EVENT_OUTPUT => 'OUTPUT',
    ];

    $output_count = 0;
    $skip_count = 0;
    foreach ($log->get_events() as $entry) {
        list($type, $source, $message) = $entry;
        switch ($type) {
            case namespace\LOG_EVENT_OUTPUT:
                ++$output_count;
                break;

            case namespace\LOG_EVENT_SKIP:
                ++$skip_count;
                break;
        }

        \printf(
            "\n\n\n%s: %s\n%s\n",
            $event_types[$type],
            $source,
            \trim($message)
        );
    }

    $passed = $log->pass_count();
    $failed = $log->failure_count();
    $errors = $log->error_count();
    $skipped = $log->skip_count();
    $output = $log->output_count();
    $omitted = [];
    if ($output_count !== $output) {
        $omitted[] = 'output';
    }
    if ($skip_count !== $skipped) {
        $omitted[] = 'skipped tests';
    }
    if ($omitted) {
        \printf(
            "\n\n\nThis report omitted %s.\nTo view, rerun easytest with the --verbose option.",
            \implode(' and ', $omitted)
        );
    }

    $summary = [];
    if ($passed) {
        $summary[] = \sprintf('Passed: %d', $passed);
    }
    if ($failed) {
        $summary[] = \sprintf('Failed: %d', $failed);
    }
    if ($errors) {
        $summary[] = \sprintf('Errors: %d', $errors);
    }
    if ($skipped) {
        $summary[] = \sprintf('Skipped: %d', $skipped);
    }
    if ($output) {
        $summary[] = \sprintf('Output: %d', $output);
    }

    if ($summary) {
        echo
            ($omitted ? "\n\n" : "\n\n\n"),
            "Seconds elapsed: $secs_elapsed\n",
            \implode(', ', $summary), "\n";
    }
    else {
        echo "No tests found!\n";
    }
}
