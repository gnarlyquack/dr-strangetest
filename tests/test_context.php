<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\context;

use strangetest;
use strangetest\BasicLogger;
use strangetest\Context;
use strangetest\FunctionTest;
use strangetest\State;


function setup() {
    $logger = new BasicLogger(true);
    $test = new FunctionTest();
    $test->name = 'test_function';
    $context = new Context(new State(), $logger, $test, array());
    return array($context, $logger, $test);
}


function test_assert_throws_passes(
    Context $context, BasicLogger $logger, FunctionTest $test
) {
    $expected = new \ExpectedException();
    $result = $context->assert_throws(
        \get_class($expected),
        function() use ($expected) { throw $expected; },
        'I pass?',
        $actual
    );

    \assert_log(array(), $logger);
    strangetest\assert_identical($expected, $actual);
    strangetest\assert_identical(true, $result);
    strangetest\assert_identical(strangetest\RESULT_PASS, $context->result());
}


function test_assert_throws_fails(
    Context $context, BasicLogger $logger, FunctionTest $test
) {
    $result = $context->assert_throws(
        'ExpectedException',
        function() {},
        null,
        $actual
    );

    \assert_log(
        array(
            strangetest\EVENT_FAIL => 1,
            'events' => array(
                array(strangetest\EVENT_FAIL, $test->name, 'Expected to catch ExpectedException but no exception was thrown'),
            ),
        ),
        $logger
    );
    strangetest\assert_identical(null, $actual);
    strangetest\assert_identical(false, $result);
    strangetest\assert_identical(strangetest\RESULT_FAIL, $context->result());
}


function test_assert_throws_throws_unexpected_exception(
    Context $context, BasicLogger $logger, FunctionTest $test
) {
    $to_throw = new \UnexpectedException();
    $result = strangetest\assert_throws(
        'Exception',
        function() use ($context, $to_throw) {
            $context->assert_throws(
                'ExpectedException',
                function() use ($to_throw) { throw $to_throw; },
                null,
                $actual
            );
        }
    );

    strangetest\assert_identical(
        'Expected to catch ExpectedException but instead caught UnexpectedException',
        $result->getMessage()
    );
    strangetest\assert_identical($to_throw, $result->getPrevious());
}
