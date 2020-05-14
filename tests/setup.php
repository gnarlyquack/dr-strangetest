<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class ExpectedException extends \Exception {}
class UnexpectedException extends \Exception {}


function assert_log(array $log, easytest\BasicLogger $logger) {
    $expected = array(
        easytest\EVENT_PASS => 0,
        easytest\EVENT_FAIL => 0,
        easytest\EVENT_ERROR => 0,
        easytest\EVENT_SKIP => 0,
        easytest\EVENT_OUTPUT => 0,
        'events' => array(),
    );
    foreach ($log as $i => $entry) {
        $expected[$i] = $entry;
    }

    $actual = $logger->get_log();
    $actual = array(
        easytest\EVENT_PASS => $actual->pass_count(),
        easytest\EVENT_FAIL => $actual->failure_count(),
        easytest\EVENT_ERROR => $actual->error_count(),
        easytest\EVENT_SKIP => $actual->skip_count(),
        easytest\EVENT_OUTPUT => $actual->output_count(),
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
    easytest\assert_identical($expected, $actual);
}


function assert_report($expected, easytest\BasicLogger $logger) {
    $log = $logger->get_log();
    $log->seconds_elapsed = 1;
    $log->megabytes_used = 1;
    easytest\output_log($log);
    easytest\assert_identical($expected, ob_get_contents());
}
