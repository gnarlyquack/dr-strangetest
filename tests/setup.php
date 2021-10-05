<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class ExpectedException extends \Exception {}
class UnexpectedException extends \Exception {}


function assert_log(array $log, strangetest\BasicLogger $logger) {
    $expected = array(
        strangetest\EVENT_PASS => 0,
        strangetest\EVENT_FAIL => 0,
        strangetest\EVENT_ERROR => 0,
        strangetest\EVENT_SKIP => 0,
        strangetest\EVENT_OUTPUT => 0,
        'events' => array(),
    );
    foreach ($log as $i => $entry) {
        $expected[$i] = $entry;
    }

    $actual = $logger->get_log();
    $actual = array(
        strangetest\EVENT_PASS => $actual->pass_count(),
        strangetest\EVENT_FAIL => $actual->failure_count(),
        strangetest\EVENT_ERROR => $actual->error_count(),
        strangetest\EVENT_SKIP => $actual->skip_count(),
        strangetest\EVENT_OUTPUT => $actual->output_count(),
        'events' => $actual->get_events(),
    );
    for ($i = 0, $c = count($actual['events']); $i < $c; ++$i) {
        list($type, $source, $reason) = $actual['events'][$i];
        if ($reason instanceof \Throwable
            // #BC(5.6): Check if $reason is instance of Exception
            || $reason instanceof \Exception)
        {
            $actual['events'][$i][2] = $reason->getMessage();
        }
    }
    strangetest\assert_identical($expected, $actual);
}


function assert_events($expected, strangetest\BasicLogger $logger) {
    $actual = $logger->get_log()->get_events();
    foreach ($actual as $i => $event) {
        list($type, $source, $reason) = $event;
        // #BC(5.6): Check if reason is instance of Exception
        if ($reason instanceof \Throwable
            || $reason instanceof \Exception)
        {
            $reason = $reason->getMessage();
        }

        $actual[$i] = array($type, $source, $reason);
    }
    strangetest\assert_identical($expected, $actual);
}


function assert_report($expected, strangetest\BasicLogger $logger) {
    $log = $logger->get_log();
    $log->seconds_elapsed = 1;
    $log->megabytes_used = 1;
    strangetest\output_log($log);
    strangetest\assert_identical($expected, ob_get_contents());
}
