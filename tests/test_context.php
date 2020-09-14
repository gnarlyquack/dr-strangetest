<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace test\context;

use easytest;
use easytest\BasicLogger;
use easytest\Context;
use easytest\FunctionTest;
use easytest\State;


function setup() {
    $logger = new BasicLogger(true);
    $test = new FunctionTest();
    $test->name = 'test_function';
    $context = new Context(new State(), $logger, $test, null);
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
    easytest\assert_identical($expected, $actual);
    easytest\assert_identical(true, $result);
    easytest\assert_identical(easytest\RESULT_PASS, $context->result());
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
            easytest\EVENT_FAIL => 1,
            'events' => array(
                array(easytest\EVENT_FAIL, $test->name, 'Expected to catch ExpectedException but no exception was thrown'),
            ),
        ),
        $logger
    );
    easytest\assert_identical(null, $actual);
    easytest\assert_identical(false, $result);
    easytest\assert_identical(easytest\RESULT_FAIL, $context->result());
}


function test_assert_throws_throws_unexpected_exception(
    Context $context, BasicLogger $logger, FunctionTest $test
) {
    $to_throw = new \UnexpectedException();
    $result = easytest\assert_throws(
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

    easytest\assert_identical(
        'Expected to catch ExpectedException but instead caught UnexpectedException',
        $result->getMessage()
    );
    easytest\assert_identical($to_throw, $result->getPrevious());
}
