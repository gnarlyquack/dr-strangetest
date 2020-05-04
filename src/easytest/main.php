<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;

const PROGRAM_NAME    = 'EasyTest';
const PROGRAM_VERSION = '0.2.2';

const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;

const LOG_EVENT_PASS   = 1;
const LOG_EVENT_FAIL   = 2;
const LOG_EVENT_ERROR  = 3;
const LOG_EVENT_SKIP   = 4;
const LOG_EVENT_OUTPUT = 5;
const LOG_EVENT_DEBUG  = 6;



interface Log {
    public function pass_count();

    public function failure_count();

    public function error_count();

    public function skip_count();

    public function output_count();

    public function get_events();
}

interface Logger {
    public function log_pass($source);

    public function log_failure($source, $reason);

    public function log_error($source, $reason);

    public function log_skip($source, $reason, $during_error);

    public function log_output($source, $reason, $during_error);

    public function log_debug($source, $reason);
}



abstract class struct {
    final public function __construct() {
        $this->init_from_array(\func_get_args());
    }


    final public function __set($name, $value) {
        throw new \Exception(
            \sprintf("Undefined property: %s::%s", \get_class($this), $name)
        );
    }


    final public function __get($name) {
        throw new \Exception(
            \sprintf("Undefined property: %s::%s", \get_class($this), $name)
        );
    }


    final static public function from_array(array $array) {
        $object = new static();
        $object->init_from_array($array);
        return $object;
    }


    final static public function from_map(array $map) {
        $object = new static();
        foreach ($map as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }


    private function init_from_array(array $args)  {
        if ($args) {
            $props = \array_keys(\get_object_vars($this));
            foreach ($args as $i => $value) {
                $this->{$props[$i]} = $value;
            }
        }
    }
}



final class ArgumentLists {
    private $arglists;


    public function __construct(array $arglists) {
        $this->arglists = $arglists;
    }


    public function arglists() {
        return $this->arglists;
    }
}

function arglists($arglists) {
    return new namespace\ArgumentLists($arglists);
}



function main($argc, $argv) {
    namespace\_enable_error_handling();
    namespace\_try_loading_composer();
    namespace\_load_easytest();

    list($options, $tests) = namespace\_parse_arguments($argc, $argv);
    $logger = new BasicLogger($options['verbose']);

    namespace\output_header(namespace\_get_version());
    $start = namespace\_microtime();
    namespace\discover_tests(new LiveUpdatingLogger($logger) , $tests);
    $end = namespace\_microtime();

    $log = $logger->get_log();
    namespace\output_log($log, \round(($end - $start) / 1000000, 3));

    exit(
        $log->failure_count() || $log->error_count()
        ? namespace\EXIT_FAILURE
        : namespace\EXIT_SUCCESS
    );
}


function _enable_error_handling() {
    // #BC(5.3): Include E_STRICT in error_reporting()
    \error_reporting(\E_ALL | \E_STRICT);
    \set_error_handler('easytest\\_handle_error', \error_reporting());

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
    \assert_options(\ASSERT_WARNING, 0); // Default is 1
    \assert_options(\ASSERT_BAIL, 0);
    \assert_options(\ASSERT_QUIET_EVAL, 0);
    \assert_options(\ASSERT_CALLBACK, 'easytest\\_handle_assertion');
}


function _handle_assertion($file, $line, $code, $desc = null) {
    if (!\ini_get('assert.exception')) {
        if ('' !== $code) {
            $code = "assert($code) failed";
        }
        $message = namespace\format_failure_message($code, $desc);
        throw new Failure($message);
    }
}

function _handle_error($errno, $errstr, $errfile, $errline) {
    if (!(\error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new Error($errstr, $errno, $errfile, $errline);
}


function _try_loading_composer() {
    $files = array(
        '%1$s%2$s..%2$s..%2$s..%2$s..%2$sautoload.php',
        '%1$s%2$s..%2$s..%2$svendor%2$sautoload.php',
    );
    foreach ($files as $file) {
        $file = \sprintf($file, __DIR__, \DIRECTORY_SEPARATOR);
        if (\file_exists($file)) {
            require $file;
            return;
        }
    }
}


function _load_easytest() {
    $files = array('assertions', 'buffer', 'exceptions', 'log', 'output',
        'runner', 'util');
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
        require \sprintf('%s%s%s.php', __DIR__, \DIRECTORY_SEPARATOR, $file);
    }
}


function _microtime() {
    if (\function_exists('hrtime')) {
        list($sec, $nsec) = hrtime();
        return 1000000 * $sec + $nsec / 1000;
    }
    // #BC(7.2): Use microtime for timing
    else {
        list($usec, $sec) = \explode(' ', \microtime());
        return 1000000 * $sec + 1000000 * $usec;
    }
}


function _parse_arguments($argc, $argv) {
    $opts = array('verbose' => false);
    $args = \array_slice($argv, 1);

    while ($args) {
        $arg = $args[0];

        if ('--' === \substr($arg, 0, 2)) {
            if ('--' === $arg) {
                \array_shift($args);
                break;
            }
            list($opts, $args) = namespace\_parse_long_option($args, $opts);
        }
        elseif ('-' === \substr($arg, 0, 1)) {
            if ('-' === $arg) {
                break;
            }
            list($opts, $args) = namespace\_parse_short_option($args, $opts);
        }
        else {
            break;
        }
    }

    return array($opts, $args);
}


function _parse_long_option($args, $opts) {
    $opt = \array_shift($args);
    // Remove the leading dashes
    $opt = \substr($opt, 2);
    return namespace\_parse_option($opt, $args, $opts);
}


function _parse_short_option($args, $opts) {
    // Remove the leading dash, but don't remove the option from $args in
    // case the option is concatenated with a value or other short options
    $args[0] = \substr($args[0], 1);
    $nargs = \count($args);

    while ($nargs === \count($args)) {
        $opt = \substr($args[0], 0, 1);
        $args[0] = \substr($args[0], 1);
        // #BC(5.6): Loose comparison in case substr() returned false
        if ('' == $args[0]) {
            \array_shift($args);
        }
        list($opts, $args) = namespace\_parse_option($opt, $args, $opts);
    }
    return array($opts, $args);
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

        case 'version':
            namespace\output(namespace\_get_version());
            exit(namespace\EXIT_SUCCESS);
            break;

        case 'help':
            namespace\output(namespace\_get_help());
            exit(namespace\EXIT_SUCCESS);
            break;
    }

    return array($opts, $args);
}


function _get_version() {
    return \sprintf(
        '%s %s',
        namespace\PROGRAM_NAME,
        namespace\PROGRAM_VERSION
    );
}


function _get_help() {
    return <<<'HELP'
Usage: easytest [OPTION]... [PATH]...

Search for and run tests located in PATHs, which may be a list of directories
and/or files. If omitted, the current directory is searched by default.


Supported options:

  --help
    Show this help and exit.

  -q, --quiet
    Omit reporting skipped tests and output, unless the output occurred in
    conjunction with an error or failed test. This is the default, and is
    provided to disable verbose reporting.

  -v, --verbose
    Include skipped tests and all output in reporting.

  --version
    Show the version information and exit

Please report bugs to: https://github.com/gnarlyquack/easytest/issues
HELP;
}
