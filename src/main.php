<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;

const PROGRAM_NAME    = 'Dr. Strangetest';
const PROGRAM_VERSION = '0.1.0';

const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;


const EVENT_PASS   = 1;
const EVENT_FAIL   = 2;
const EVENT_ERROR  = 3;
const EVENT_SKIP   = 4;
const EVENT_OUTPUT = 5;

const LOG_QUIET  = 0x0;
const LOG_PASS   = 0x01;
const LOG_SKIP   = 0x02;
const LOG_OUTPUT = 0x04;
// @bc 5.5 Use define() to define constant expressions
define('strangetest\\LOG_VERBOSE', namespace\LOG_SKIP | namespace\LOG_OUTPUT);
// Right now just used for debugging
define('strangetest\\LOG_ALL',     namespace\LOG_PASS | namespace\LOG_VERBOSE);



interface Log {
    /**
     * @return int
     */
    public function pass_count();

    /**
     * @return int
     */
    public function failure_count();

    /**
     * @return int
     */
    public function error_count();

    /**
     * @return int
     */
    public function skip_count();

    /**
     * @return int
     */
    public function output_count();

    /**
     * @return float
     */
    public function seconds_elapsed();

    /**
     * @return float
     */
    public function memory_used();

    /**
     * @return array{int, string, string|\Throwable|null}[]
     */
    public function get_events();
}

interface Logger {
    /**
     * @param string $source
     * @return void
     */
    public function log_pass($source);

    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @return void
     */
    public function log_failure($source, $reason);

    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @return void
     */
    public function log_error($source, $reason);

    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @param ?bool $during_error
     * @return void
     */
    public function log_skip($source, $reason, $during_error = false);

    /**
     * @param string $source
     * @param string|\Throwable|null $reason
     * @param ?bool $during_error
     * @return void
     */
    public function log_output($source, $reason, $during_error = false);
}



// @bc 5.3 Use an abstract class instead of (potentially) a trait
abstract class struct {
    /**
     * @param string $name
     * @param mixed $value
     * @return never
     * @throws \Exception
     */
    final public function __set($name, $value)
    {
        throw new \Exception(
            \sprintf("Undefined property: %s::%s", \get_class($this), $name)
        );
    }


    /**
     * @param string $name
     * @return never
     * @throws \Exception
     */
    final public function __get($name)
    {
        throw new \Exception(
            \sprintf("Undefined property: %s::%s", \get_class($this), $name)
        );
    }
}


/**
 * @api
 */
final class Error extends \ErrorException {
    /**
     * @param string $message
     * @param int $severity
     * @param string $file
     * @param int $line
     */
    public function __construct($message, $severity, $file, $line)
    {
        parent::__construct($message, 0, $severity, $file, $line);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (!$this->string)
        {
            $this->string =  \sprintf(
                "%s\nin %s on line %s\n\nStack trace:\n%s",
                $this->message,
                $this->file,
                $this->line,
                $this->getTraceAsString()
            );
        }
        return $this->string;
    }

    /** @var string */
    private $string;
}


final class State extends struct {
    /** @var array<string, array{'group': int, 'runs': bool[]}> */
    public $results = array();

    /** @var array<string, FunctionDependency> */
    public $postponed = array();

    /** @var array<string, mixed[]> */
    public $fixture = array();

    /** @var array<int[]> */
    public $groups = array(0 => array(0));

    /** @var RunInfo[] */
    public $runs = array();
}



/**
 * @param int $argc
 * @param string[] $argv
 * @return never
 */
function main($argc, $argv)
{
    namespace\_enable_error_handling();

    $cwd = \getcwd();
    if (false === $cwd)
    {
        \fwrite(\STDERR, "Unable to determine current working directory (getcwd failed)\n");
        exit(namespace\EXIT_FAILURE);
    }
    \assert(\DIRECTORY_SEPARATOR !== \substr($cwd, -1));
    $cwd .= \DIRECTORY_SEPARATOR;

    namespace\_try_loading_composer($cwd);
    namespace\_load_strangetest();

    // @todo Add configuration option to explicitly set the test root directory
    list($options, $args) = namespace\_parse_arguments($argc, $argv);

    $logger = new BasicLogger($options['verbose']);
    $buffering = new BufferingLogger(new LiveUpdatingLogger($logger));
    $state = new State();

    namespace\output_header(namespace\_get_version());
    $start = namespace\_microtime();
    $tests = namespace\discover_tests($state, $buffering, $cwd);
    if ($tests)
    {
        if ($args)
        {
            $targets = namespace\process_specifiers($logger, $tests, $args);
            if ($targets)
            {
                namespace\run_tests($state, $buffering, $tests, $targets);
            }
        }
        else
        {
            namespace\run_tests($state, $buffering, $tests, $tests);
        }
    }
    $end = namespace\_microtime();

    $log = $logger->get_log();
    $log->megabytes_used = \round(\memory_get_peak_usage() / 1048576, 3);
    $log->seconds_elapsed = \round(($end - $start) / 1000000, 3);
    namespace\output_log($log);

    exit(
        $log->failure_count() || $log->error_count()
        ? namespace\EXIT_FAILURE
        : namespace\EXIT_SUCCESS
    );
}


/**
 * @return void
 */
function _enable_error_handling()
{
    // @bc 5.3 Include E_STRICT in error_reporting()
    \error_reporting(\E_ALL | \E_STRICT);
    \set_error_handler('strangetest\\_handle_error', \error_reporting());

    // @bc 5.6 Check if PHP 7 assertion options are supported
    if (\version_compare(\PHP_VERSION, '7.0', '>='))
    {
        if ('-1' === \ini_get('zend.assertions'))
        {
            \fwrite(\STDERR, "Dr. Strangetest should not be run in a production environment.\n");
            exit(namespace\EXIT_FAILURE);
        }
        \ini_set('zend.assertions', '1');

        // @bc 7.1 Check whether or not to enable assert.exception
        // Since PHP 7.2 deprecates calling assert() with a string assertion,
        // there seems to be no reason to keep assert's legacy behavior enabled
        if (\version_compare(\PHP_VERSION, '7.2', '>='))
        {
            \ini_set('assert.exception', '1');
        }
    }
    // Although the documentation discourages using these configuration
    // directives for PHP 7-only code (which, admittedly, we're not), we want
    // to ensure that assert() is always in a known configuration
    \assert_options(\ASSERT_ACTIVE, 1);
    \assert_options(\ASSERT_WARNING, 0); // Default is 1
    \assert_options(\ASSERT_BAIL, 0);
    // @bc 7.4 check if ASSERT_QUIET_EVAL is defined
    if (\defined('ASSERT_QUIET_EVAL'))
    {
        \assert_options(\ASSERT_QUIET_EVAL, 0);
    }
    \assert_options(\ASSERT_CALLBACK, 'strangetest\\_handle_assertion');
}


/**
 * @param string $file
 * @param int $line
 * @param ?string $code
 * @param ?string $desc
 * @return void
 * @throws Failure
 */
function _handle_assertion($file, $line, $code, $desc = null)
{
    if (!\ini_get('assert.exception'))
    {
        // @bc 7.4 Check that $code is not an empty string
        // PHP 8 appears to pass null instead of an empty string if $code is empty
        if (isset($code) && \strlen($code) > 0)
        {
            $code = "assert($code) failed";
        }
        $message = namespace\format_failure_message($code, $desc);
        throw new Failure($message);
    }
}


/**
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 * @throws Error
 */
function _handle_error($errno, $errstr, $errfile, $errline)
{
    if (!(\error_reporting() & $errno))
    {
        // This error code is not included in error_reporting
        return false;
    }
    throw new Error($errstr, $errno, $errfile, $errline);
}


/**
 * @param string $cwd
 * @return void
 */
function _try_loading_composer($cwd)
{
    \assert(\DIRECTORY_SEPARATOR === \substr($cwd, -1));
    // @todo Check for composer.json before attempting to load the autoloader?
    $autoloader = \sprintf('%svendor%sautoload.php', $cwd, \DIRECTORY_SEPARATOR);
    // @todo Check composer.json if the autoloader isn't in the default location
    // By default, the autoloader is placed in the vendor directory, but this
    // can be overridden with the vendor-dir config option.
    if (\file_exists($autoloader))
    {
        require $autoloader;
    }
}


/**
 * @return void
 */
function _load_strangetest()
{
    $files = array(
        'assertions',
        'buffer',
        'dependency',
        'discover',
        'exceptions',
        'log',
        'output',
        'run',
        'targets',
        'tests',
        'util',
    );
    // @bc 5.5 Implement proxy functions for argument unpacking
    // PHP 5.6's argument unpacking syntax causes a syntax error in earlier PHP
    // versions, so we need to include version-dependent proxy functions to do
    // the unpacking for us. When support for PHP < 5.6 is dropped, this can
    // all be eliminated and we can just use the argument unpacking syntax
    // directly at the call site.
    if (\version_compare(\PHP_VERSION, '5.6', '<'))
    {
        $files[] = 'unpack5.5';
    }
    else
    {
        $files[] = 'unpack';
    }
    // @bc 7.0 Include implementation for is_iterable()
    if (!\function_exists('is_iterable'))
    {
        $files[] = 'is_iterable';
    }
    foreach ($files as $file)
    {
        require \sprintf('%s%s%s.php', __DIR__, \DIRECTORY_SEPARATOR, $file);
    }
}


/**
 * @return float
 */
function _microtime()
{
    if (\function_exists('hrtime'))
    {
        list($sec, $nsec) = hrtime();
        return 1000000 * $sec + $nsec / 1000;
    }
    // @bc 7.2 Use microtime for timing
    else
    {
        list($usec, $sec) = \explode(' ', \microtime());
        return 1000000 * (int)$sec + (int)(1000000 * (float)$usec);
    }
}


/**
 * @param int $argc
 * @param string[] $argv
 * @return array{array<string, int>, string[]}
 */
function _parse_arguments($argc, $argv)
{
    $opts = array('verbose' => namespace\LOG_QUIET);
    $args = \array_slice($argv, 1);

    while ($args)
    {
        $arg = $args[0];

        if ('--' === \substr($arg, 0, 2))
        {
            if ('--' === $arg)
            {
                \array_shift($args);
                break;
            }
            list($opts, $args) = namespace\_parse_long_option($args, $opts);
        }
        elseif ('-' === \substr($arg, 0, 1))
        {
            if ('-' === $arg)
            {
                break;
            }
            list($opts, $args) = namespace\_parse_short_option($args, $opts);
        }
        else
        {
            break;
        }
    }

    return array($opts, $args);
}


/**
 * @param string[] $args
 * @param array<string, int> $opts
 * @return array{array<string, int>, string[]}
 */
function _parse_long_option($args, $opts)
{
    $opt = \array_shift($args);
    \assert(\is_string($opt));
    // Remove the leading dashes
    $opt = \substr($opt, 2);
    return namespace\_parse_option($opt, $args, $opts);
}


/**
 * @param string[] $args
 * @param array<string, int> $opts
 * @return array{array<string, int>, string[]}
 */
function _parse_short_option($args, $opts)
{
    // Remove the leading dash, but don't remove the option from $args in
    // case the option is concatenated with a value or other short options
    $args[0] = \substr($args[0], 1);
    $nargs = \count($args);

    while ($nargs === \count($args))
    {
        $opt = \substr($args[0], 0, 1);
        $args[0] = \substr($args[0], 1);
        // @bc 5.6 Loose comparison in case substr() returned false
        if ('' == $args[0])
        {
            \array_shift($args);
        }
        list($opts, $args) = namespace\_parse_option($opt, $args, $opts);
    }
    return array($opts, $args);
}


/**
 * @param string $opt
 * @param string[] $args
 * @param array<string, int> $opts
 * @return array{array<string, int>, string[]}
 */
function _parse_option($opt, $args, $opts)
{
    switch ($opt)
    {
        case 'q':
        case 'quiet':
            $opts['verbose'] = namespace\LOG_QUIET;
            break;

        case 'v':
        case 'verbose':
            $opts['verbose'] = namespace\LOG_VERBOSE;
            break;

        case 'version':
            namespace\output(namespace\_get_version());
            exit(namespace\EXIT_SUCCESS);

        case 'help':
            namespace\output(namespace\_get_help());
            exit(namespace\EXIT_SUCCESS);

        default:
            fwrite(\STDERR, "Unknown option: $opt\nPlease see 'strangetest --help'\n");
            exit(namespace\EXIT_FAILURE);
    }

    return array($opts, $args);
}


/**
 * @return string
 */
function _get_version()
{
    return \sprintf(
        '%s %s',
        namespace\PROGRAM_NAME,
        namespace\PROGRAM_VERSION
    );
}


/**
 * @return string
 */
function _get_help()
{
    return <<<'HELP'
Usage: strangetest [OPTION]... [PATH]...

Search for and run tests located in PATHs, which may be a list of directories
and/or files. If omitted, the current directory is searched by default.


Supported options:

  --help
    Show this help and exit.

  -q, --quiet
    Omit reporting skipped tests and output, unless they occurred in
    conjunction with an error or failed test. This is the default, and is
    provided to disable verbose reporting.

  -v, --verbose
    Include skipped tests and all output in reporting.

  --version
    Show version information and exit.

Please report bugs to: https://github.com/gnarlyquack/strangetest/issues
HELP;
}
