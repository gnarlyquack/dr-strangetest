<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestArgParse {

    public function test_parses_empty_argv() {
        // argv[0] is always the current executable name
        $argv = ['foo'];
        list($opts, $args) = easytest\_parse_arguments(count($argv), $argv);

        easytest\assert_identical([], $opts, 'Options weren\'t parsed');
        easytest\assert_identical([], $args, 'Arguments weren\'t parsed');
    }


    public function test_parses_arguments() {
        // argv[0] is always the current executable name
        $argv = ['foo', 'one', 'two'];
        list($opts, $args) = easytest\_parse_arguments(count($argv), $argv);

        easytest\assert_identical([], $opts, 'Options weren\'t parsed');
        easytest\assert_identical(
            ['one', 'two'],
            $args,
            'Arguments weren\'t parsed'
        );
    }


    public function test_parses_long_option() {
        // argv[0] is always the current executable name
        $argv = ['foo', '--verbose'];
        list($opts, $args) = easytest\_parse_arguments(count($argv), $argv);

        easytest\assert_identical(
            ['verbose' => true],
            $opts,
            'Options weren\'t parsed');
        easytest\assert_identical([], $args, 'Arguments weren\'t parsed');
    }


    public function test_parses_short_option() {
        // argv[0] is always the current executable name
        $argv = ['foo', '-v'];
        list($opts, $args) = easytest\_parse_arguments(count($argv), $argv);

        easytest\assert_identical(
            ['verbose' => true],
            $opts,
            'Options weren\'t parsed');
        easytest\assert_identical([], $args, 'Arguments weren\'t parsed');
    }
}
