<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class BasicLog implements Log {

    public function __construct($count, $events) {
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


    public function get_events() {
        // This is safe because PHP arrays are copy-on-write
        return $this->events;
    }

    private $count;
    private $events;
}



final class BasicLogger implements Logger {

    public function __construct($verbose) {
        $this->verbose = $verbose;
    }


    public function log_pass() {
        ++$this->count[namespace\LOG_EVENT_PASS];
    }


    public function log_failure($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_FAIL];
        $this->events[] = [namespace\LOG_EVENT_FAIL, $source, $reason];
    }


    public function log_error($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_ERROR];
        $this->events[] = [namespace\LOG_EVENT_ERROR, $source, $reason];
    }


    public function log_skip($source, $reason) {
        ++$this->count[namespace\LOG_EVENT_SKIP];
        if ($this->verbose) {
            $this->events[] = [namespace\LOG_EVENT_SKIP, $source, $reason];
        }
    }


    public function log_output($source, $reason, $during_error) {
        ++$this->count[namespace\LOG_EVENT_OUTPUT];
        if ($this->verbose || $during_error) {
            $this->events[] = [namespace\LOG_EVENT_OUTPUT, $source, $reason];
        }
    }


    public function get_log() {
        return new BasicLog($this->count, $this->events);
    }

    private $count = [
        namespace\LOG_EVENT_PASS   => 0,
        namespace\LOG_EVENT_ERROR  => 0,
        namespace\LOG_EVENT_FAIL   => 0,
        namespace\LOG_EVENT_SKIP   => 0,
        namespace\LOG_EVENT_OUTPUT => 0,
    ];
    private $events = [];
    private $verbose;
}



final class LiveUpdatingLogger implements Logger {

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }


    public function log_pass() {
        namespace\output_pass();
        $this->logger->log_pass();
    }


    public function log_failure($source, $reason) {
        namespace\output_failure();
        $this->logger->log_failure($source, $reason);
    }


    public function log_error($source, $reason) {
        namespace\output_error();
        $this->logger->log_error($source, $reason);
    }


    public function log_skip($source, $reason) {
        namespace\output_skip();
        $this->logger->log_skip($source, $reason);
    }


    public function log_output($source, $reason, $during_error) {
        namespace\output_output();
        $this->logger->log_output($source, $reason, $during_error);
    }


    public function get_log() {
        return $this->logger->get_log();
    }

    private $logger;
}


final class BufferingLogger implements Logger {

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }


    public function log_pass() {
        if ($this->buffering) {
            $this->queued[] = [$this->logger, 'log_pass'];
        }
        else
        {
            $this->logger->log_pass();
        }
    }


    public function log_failure($source, $reason) {
        if ($this->buffering) {
            // #BC(5.3): Explicitly pass $logger into anonymous function
            $logger = $this->logger;
            $this->queued[] = function() use ($logger, $source, $reason) {
                $logger->log_failure($source, $reason);
            };
        }
        else
        {
            $this->logger->log_failure($source, $reason);
        }
    }


    public function log_error($source, $reason) {
        if ($this->buffering) {
            // #BC(5.3): Explicitly pass $logger into anonymous function
            $logger = $this->logger;
            $this->queued[] = function() use ($logger, $source, $reason) {
                $logger->log_error($source, $reason);
            };
        }
        else
        {
            $this->logger->log_error($source, $reason);
        }
    }


    public function log_skip($source, $reason) {
        if ($this->buffering) {
            // #BC(5.3): Explicitly pass $logger into anonymous function
            $logger = $this->logger;
            $this->queued[] = function() use ($logger, $source, $reason) {
                $logger->log_skip($source, $reason);
            };
        }
        else
        {
            $this->logger->log_skip($source, $reason);
        }
    }


    public function log_output($source, $reason, $during_error) {
        if ($this->buffering) {
            // #BC(5.3): Explicitly pass $logger into anonymous function
            $logger = $this->logger;
            $this->queued[]
                = function() use ($logger, $source, $reason, $during_error) {
                    $logger->log_output($source, $reason, $during_error);
                };
        }
        else
        {
            $this->logger->log_output($source, $reason, $during_error);
        }
    }


    public function get_log() {
        return $this->logger->get_log();
    }


    public function buffer($source, $callable) {
        $this->buffering = true;

        $levels = \ob_get_level();
        \ob_start();

        try {
            $result = $callable();
        }
        catch (\Throwable $e) {}
        // #BC(5.6): Catch Exception, which implements Throwable
        catch (\Exception $e) {}

        $buffers = [];
        for ($level = \ob_get_level();
             $level > $levels;
             $level = \ob_get_level())
        {
            $buffer = \trim(\ob_get_clean());
            if (\strlen($buffer)) {
                $buffers[] = $buffer;
            }
        }

        $this->buffering = false;
        if ($this->queued) {
            foreach ($this->queued as $log) {
                $log();
            }
            $this->queued = [];
        }

        if ($buffers) {
            // Since output buffers stack, the first buffer read is the last
            // buffer that was written. To output them in chronological order,
            // we reverse the order of the buffers
            $output = \implode("\n\n\n", \array_reverse($buffers));
            $this->logger->log_output($source, $output, isset($e));
        }

        if (isset($e)) {
            throw $e;
        }
        return $result;
    }


    private $queued = [];
    private $buffering = false;
    private $logger;
}
