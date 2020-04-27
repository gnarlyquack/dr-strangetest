<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

const VERSION = '0.2.2';

const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;

const LOG_EVENT_PASS   = 1;
const LOG_EVENT_FAIL   = 2;
const LOG_EVENT_ERROR  = 3;
const LOG_EVENT_SKIP   = 4;
const LOG_EVENT_OUTPUT = 5;



interface Log {
    public function pass_count();

    public function failure_count();

    public function error_count();

    public function skip_count();

    public function output_count();

    public function get_events();
}

interface Logger {
    public function log_pass();

    public function log_failure($source, $reason);

    public function log_error($source, $reason);

    public function log_skip($source, $reason);

    public function log_output($source, $reason, $during_error);

    public function get_log();
}



final class ErrorHandler {
    public function handle_assertion($file, $line, $code, $desc = null) {
        if (!\ini_get('assert.exception')) {
            if (!$desc) {
                $desc = $code ? "assert($code) failed" : 'assert() failed';
            }
            throw new Failure($desc);
        }
    }

    public function handle_error($errno, $errstr, $errfile, $errline) {
        if (!(\error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new Error($errstr, $errno, $errfile, $errline);
    }
}



final class State {
    public $seen = [];
    public $files = [];
}



final class ArgList {
    private $args;

    public function __construct($arg) {
        $this->args = \func_get_args();
    }

    public function args() {
        return $this->args;
    }
}

function args($arg) {
    // #BC(5.5): Use proxy function for argument unpacking
    return namespace\_unpack_construct('easytest\\ArgList', \func_get_args());
}



function main($argc, $argv) {
    namespace\_enable_error_handling();
    namespace\_try_loading_composer();
    namespace\_load_easytest();

    list($options, $tests) = namespace\_parse_arguments($argc, $argv);
    if (!$tests) {
        $tests[] = \getcwd();
    }
    $logger = new BufferingLogger(
        new LiveUpdatingLogger(
            new BasicLogger($options['verbose'])
        )
    );

    namespace\output_header('EasyTest ' . namespace\VERSION);
    namespace\discover_tests($logger, $tests);

    $log = $logger->get_log();
    namespace\output_log($log);

    exit(
        $log->failure_count() || $log->error_count()
        ? namespace\EXIT_FAILURE
        : namespace\EXIT_SUCCESS
    );
}


function _try_loading_composer() {
    $files = ['/../../../../autoload.php', '/../../vendor/autoload.php'];
    foreach ($files as $file) {
        $file = __DIR__ . $file;
        if (\file_exists($file)) {
            require $file;
            return;
        }
    }
}


function _load_easytest() {
    $files = ['assertions', 'exceptions', 'log', 'output', 'runner', 'util'];
    // #BC(5.5): Implement proxy functions for argument unpacking
    // PHP 5.6's argument unpacking syntax causes a syntax error in earlier PHP
    // versions, so we need to include version-dependent proxy functions to do
    // the unpacking for us. When support for PHP < 5.6 is dropped, this can
    // all be eliminated and we can just use the argument unpacking syntax
    // directly at the call site.
    if (\version_compare(\PHP_VERSION, '5.6', '<')) {
        $files[] = 'unpack5.5';
    }
    else {
        $files[] = 'unpack';
    }
    foreach ($files as $file) {
        require __DIR__ . "/{$file}.php";
    }
}


function _enable_error_handling() {
    $eh = new ErrorHandler();

    // #BC(5.3): Include E_STRICT in error_reporting()
    \error_reporting(\E_ALL | \E_STRICT);
    \set_error_handler([$eh, 'handle_error'], \error_reporting());

    // #BC(5.6): Check if PHP 7 assertion options are supported
    if (\version_compare(\PHP_VERSION, '7.0', '>=')) {
        if ('-1' === \ini_get('zend.assertions')) {
            \fwrite(\STDERR, "EasyTest should not be run in a production environment.\n");
            exit(namespace\EXIT_FAILURE);
        }
        \ini_set('zend.assertions', 1);

        // #BC(7.1): Check whether or not to enable assert.exception
        // Since PHP 7.2 deprecates calling assert() with a string assertion,
        // there seems to be no reason to keep assert's legacy behavior enabled
        if (\version_compare(\PHP_VERSION, '7.2', '>=')) {
            \ini_set('assert.exception', 1);
        }
    }
    // Although the documentation discourages using these configuration
    // directives for PHP 7-only code (which, admittedly, we're not), we want
    // to ensure that assert() is always in a known configuration
    \assert_options(\ASSERT_ACTIVE, 1);
    \assert_options(\ASSERT_WARNING, 0);
    \assert_options(\ASSERT_BAIL, 0);
    \assert_options(\ASSERT_QUIET_EVAL, 0);
    \assert_options(\ASSERT_CALLBACK, [$eh, 'handle_assertion']);
}


function _parse_arguments($argc, $argv) {
    $opts = ['verbose' => false];
    $args = \array_slice($argv, 1);

    while ($args) {
        $arg = $args[0];

        if ('--' === \substr($arg, 0, 2)) {
            list($opts, $args) = namespace\_parse_long_option($args, $opts);
        }
        elseif ('-' === \substr($arg, 0, 1)) {
            list($opts, $args) = namespace\_parse_short_option($args, $opts);
        }
        else {
            break;
        }
    }

    return [$opts, $args];
}


function _parse_long_option($args, $opts) {
    $opt = \array_shift($args);
    $opt = \substr($opt, 2);
    return namespace\_parse_option($opt, $args, $opts);
}


function _parse_short_option($args, $opts) {
    $opt = \array_shift($args);
    $opt = \substr($opt, 1);
    return namespace\_parse_option($opt, $args, $opts);
}


function _parse_option($opt, $args, $opts) {
    switch ($opt) {
        case 'q':
        case 'quiet':
            $opts['verbose'] = false;
            break;

        case 'v':
        case 'verbose':
            $opts['verbose'] = true;
            break;
    }

    return [$opts, $args];
}
