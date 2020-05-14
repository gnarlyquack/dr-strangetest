<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class BasicLog implements Log {
    public $megabytes_used;
    public $seconds_elapsed;
    private $count;
    private $events;


    public function __construct(array $count, array $events) {
        $this->count = $count;
        $this->events = $events;
    }


    public function pass_count() {
        return $this->count[namespace\LOG_EVENT_PASS];
    }


    public function failure_count() {
        return $this->count[namespace\LOG_EVENT_FAIL];
    }


    public function error_count() {
        return $this->count[namespace\LOG_EVENT_ERROR];
    }


    public function skip_count() {
        return $this->count[namespace\LOG_EVENT_SKIP];
    }


    public function output_count() {
        return $this->count[namespace\LOG_EVENT_OUTPUT];
    }


    public function seconds_elapsed() {
        return $this->seconds_elapsed;
    }


    public function memory_used() {
        return $this->megabytes_used;
    }


    public function get_events() {
        // This is safe because PHP arrays are copy-on-write
        return $this->events;
    }
}



final class BasicLogger implements Logger {
    private $count = array(
        namespace\LOG_EVENT_PASS   => 0,
        namespace\LOG_EVENT_ERROR  => 0,
        namespace\LOG_EVENT_FAIL   => 0,
        namespace\LOG_EVENT_SKIP   => 0,
        namespace\LOG_EVENT_OUTPUT => 0,
    );
    private $events = array();
    private $verbose;


    public function __construct($verbose) {
        $this->verbose = $verbose;
    }


    public function log_pass($source) {
        ++$this->count[namespace\LOG_EVENT_PASS];
        if ($this->verbose & namespace\LOG_PASS) {
            $this->events[] = array(namespace\LOG_EVENT_PASS, $source, null);
        }
    }


    public function log_failure($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_FAIL];
        $this->events[] = array(namespace\LOG_EVENT_FAIL, $source, $reason);
    }


    public function log_error($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_ERROR];
        $this->events[] = array(namespace\LOG_EVENT_ERROR, $source, $reason);
    }


    public function log_skip($source, $reason, $during_error = false) {
        ++$this->count[namespace\LOG_EVENT_SKIP];
        if ($during_error) {
            $reason = new Skip('Although this test was skipped, there was also an error', $reason);
            $this->events[] = array(namespace\LOG_EVENT_SKIP, $source, $reason);
        }
        elseif ($this->verbose & namespace\LOG_SKIP) {
            $this->events[] = array(namespace\LOG_EVENT_SKIP, $source, $reason);
        }
    }


    public function log_output($source, $reason, $during_error) {
        ++$this->count[namespace\LOG_EVENT_OUTPUT];
        if (($this->verbose & namespace\LOG_OUTPUT) || $during_error) {
            $this->events[] = array(namespace\LOG_EVENT_OUTPUT, $source, $reason);
        }
    }


    public function get_log() {
        return new BasicLog($this->count, $this->events);
    }
}
