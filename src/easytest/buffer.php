<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


final class BufferingLogger implements Logger {

    public function __construct(Logger $logger, $source, $ob_level) {
        $this->logger = $logger;
        $this->source = $source;
        $this->ob_level_start = $this->ob_level_current = $ob_level;
    }

    public function log_pass($source) {
        $this->queued[] = array(namespace\LOG_EVENT_PASS, $source);
    }


    public function log_failure($source, $reason) {
        $this->queued[] = array(namespace\LOG_EVENT_FAIL, array($source, $reason));
        $this->error = true;
    }


    public function log_error($source, $reason) {
        $this->queued[] = array(namespace\LOG_EVENT_ERROR, array($source, $reason));
        $this->error = true;
    }


    public function log_skip($source, $reason) {
        $this->queued[] = array(namespace\LOG_EVENT_SKIP, array($source, $reason));
    }


    public function log_output($source, $output, $during_error) {
        $this->queued[] = array(namespace\LOG_EVENT_OUTPUT, array($source, $output));
    }


    public function log_debug($source, $output) {
        $this->queued[] = array(namespace\LOG_EVENT_DEBUG, array($source, $output));
    }


    public $source;
    public $queued = array();
    public $ob_level_current;
    public $ob_level_start;
    public $error = false;
    public $logger;
}



function start_buffering(Logger $logger, $source) {
    // This may not be a win, since we're recreating a new logger every time we
    // need one (and deleting it when we're done), but it avoids an explicit
    // dependency on BufferingLogger and moves most of the logic out of the
    // BufferingLogger class.
    if ($logger instanceof BufferingLogger) {
        namespace\_reset_buffer($logger);
        $logger->source = $source;
        return $logger;
    }

    \ob_start();
    return new BufferingLogger($logger, $source, \ob_get_level());
}


function end_buffering(BufferingLogger $logger) {
    $buffers = array();
    for ($level = \ob_get_level();
         $level > $logger->ob_level_start;
         --$level)
    {
        $logger->log_error(
            $logger->source,
            \sprintf(
                "An output buffer was started but never deleted.\nBuffer contents were: %s",
                \var_export(\ob_get_clean(), true)
            )
        );
    }

    if ($level < $logger->ob_level_start) {
        $logger->log_error(
            $logger->source,
            "EasyTest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
    }
    else {
        $output = \ob_get_clean();
        if (\strlen($output)) {
            $logger->log_output(
                $logger->source,
                \var_export($output, true),
                $logger->error
            );
        }
    }

    $error = $logger->error;
    $queued = $logger->queued;
    $logger = $logger->logger;
    if ($queued) {
        foreach ($queued as $event) {
            list($type, $data) = $event;
            switch ($type) {
                case namespace\LOG_EVENT_PASS:
                    $logger->log_pass($data);
                    break;

                case namespace\LOG_EVENT_FAIL:
                    list($source, $reason) = $data;
                    $logger->log_failure($source, $reason);
                    break;

                case namespace\LOG_EVENT_ERROR:
                    list($source, $reason) = $data;
                    $logger->log_error($source, $reason);
                    break;

                case namespace\LOG_EVENT_SKIP:
                    list($source, $reason) = $data;
                    $logger->log_skip($source, $reason);
                    break;

                case namespace\LOG_EVENT_OUTPUT:
                    list($source, $reason) = $data;
                    $logger->log_output($source, $reason, $error);
                    break;

                case namespace\LOG_EVENT_DEBUG:
                    list($source, $reason) = $data;
                    $logger->log_debug($source, $reason, $error);
                    break;
            }
        }
    }

    return $logger;
}


function _reset_buffer(BufferingLogger $logger) {
    $level = \ob_get_level();

    if ($level < $logger->ob_level_start) {
        $logger->log_error(
            $logger->source,
            "EasyTest's output buffer was deleted! Please start (and delete) your own\noutput buffer(s) using PHP's output control functions."
        );
        \ob_start();
        $logger->ob_level_start = $logger->ob_level_current = \ob_get_level();
        return;
    }

    $buffers = array();
    if ($level > $logger->ob_level_start) {
        // Somebody else is doing their own output buffering, so we don't
        // want to mess it with. If their buffering started before we last
        // reset our own buffer then we don't need to do anything. But if
        // their buffering started after we last reset our own buffer, then
        // we want to pop off the other buffer(s), handle our own, and then
        // put the other buffer(s) back.
        if ($logger->ob_level_current > $logger->ob_level_start) {
            return;
        }

        $logger->ob_level_current = $level;
        while ($level-- > $logger->ob_level_start) {
            $buffers[] = \ob_get_clean();
        }
    }

    $output = \ob_get_contents();
    \ob_clean();
    if (\strlen($output)) {
        $logger->log_output(
            $logger->source,
            \var_export($output, true),
            $logger->error
        );
    }

    while ($buffers) {
        // Output buffers stack, so the first buffer we read was the last
        // buffer that was written. To restore them in the correct order,
        // we output them in reverse order from which we read them
        $buffer = \array_pop($buffers);
        \ob_start();
        echo $buffer;
    }
    assert($logger->ob_level_current === \ob_get_level());
}
