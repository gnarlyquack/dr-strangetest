<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestArgParse {
    public function test_parses_empty_argv() {
        // argv[0] is always the current executable name
        $argv = array('foo');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_QUIET, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_identical(
            $args,
            array(),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_parses_arguments() {
        // argv[0] is always the current executable name
        $argv = array('foo', 'one', 'two');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_QUIET, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_equal(
            $args,
            array('one', 'two'),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_parses_long_option() {
        // argv[0] is always the current executable name
        $argv = array('foo', '--verbose');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_VERBOSE, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_identical(
            $args,
            array(),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_parses_short_option() {
        // argv[0] is always the current executable name
        $argv = array('foo', '-v');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_VERBOSE, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_identical(
            $args,
            array(),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_parses_multiple_short_options() {
        // argv[0] is always the current executable name
        $argv = array('foo', '-vqv');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_VERBOSE, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_identical(
            $args,
            array(),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_ends_parsing_on_first_argument() {
        // argv[0] is always the current executable name
        $argv = array('foo', 'bar', '--verbose');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_QUIET, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_equal(
            $args,
            array('bar', '--verbose'),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_treats_single_dash_as_an_argument() {
        // argv[0] is always the current executable name
        $argv = array('foo', '-', '--verbose');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_QUIET, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_equal(
            $args,
            array('-', '--verbose'),
            "Arguments weren't parsed correctly"
        );
    }


    public function test_ends_parsing_on_double_dash() {
        // argv[0] is always the current executable name
        $argv = array('foo', '--', '--verbose');
        list($opts, $args) = strangetest\_parse_arguments(count($argv), $argv);

        strangetest\assert_identical(
            $opts,
            array('verbose' => strangetest\LOG_QUIET, 'debug' => false),
            "Options weren't parsed correctly"
        );
        strangetest\assert_identical(
            $args,
            array('--verbose'),
            "Arguments weren't parsed correctly"
        );
    }
}
