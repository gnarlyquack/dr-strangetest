<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


class TestDependencies {
    private $logger;
    private $root;
    private $path;


    public function setup() {
        $this->root = __DIR__ . '/resources/dependencies/';
        $this->path = '';
        $this->logger = new strangetest\BasicLogger(strangetest\LOG_ALL);
    }


    // helper assertions

    private function assert_events($expected) {
        $root = $this->root;
        $targets = strangetest\process_user_targets($this->logger, $root, array(), $errors);
        strangetest\assert_falsy($errors);

        $state = new strangetest\State;
        $logger = new strangetest\BufferingLogger($this->logger);
        $tests = strangetest\discover_directory($state, $logger, $root, 0);
        strangetest\assert_truthy($tests);
        strangetest\run_tests($state, $logger, $tests, $targets);

        assert_events($expected, $this->logger);
    }


    // tests

    public function test_tests_are_run_in_order_of_dependencies() {
        $this->root .= 'depends_pass/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'depends_pass\\test_one', null),
            array(strangetest\EVENT_PASS, 'depends_pass\\test::test_one', null),

            array(strangetest\EVENT_PASS, 'depends_pass\\test_two', null),
            array(strangetest\EVENT_PASS, 'depends_pass\\test_three', null),

            array(strangetest\EVENT_PASS, 'depends_pass\\test::test_two', null),
            array(strangetest\EVENT_PASS, 'depends_pass\\test::test_three', null),
        ));
    }


    public function test_tests_are_skipped_if_depending_on_a_failed_test() {
        $this->root .= 'depends_fail/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_FAIL, 'depends_fail\\test_one', 'I fail'),
            array(strangetest\EVENT_FAIL, 'depends_fail\\test::test_one', 'I fail'),

            array(strangetest\EVENT_SKIP, 'depends_fail\\test_two', "This test depends on 'depends_fail\\test_one', which did not pass"),
            array(strangetest\EVENT_SKIP, 'depends_fail\\test_three', "This test depends on 'depends_fail\\test_two', which did not pass"),

            array(strangetest\EVENT_SKIP, 'depends_fail\\test::test_two', "This test depends on 'depends_fail\\test::test_one', which did not pass"),
            array(strangetest\EVENT_SKIP, 'depends_fail\\test::test_three', "This test depends on 'depends_fail\\test::test_two', which did not pass"),
        ));
    }


    public function test_dependencies_resolve_to_correct_test_run() {
        $this->root .= 'depends_params/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'depends_params\\test_one (0)', null),
            array(strangetest\EVENT_PASS, 'depends_params\\test::test_one (0)', null),

            array(strangetest\EVENT_PASS, 'depends_params\\test_one (1)', null),
            array(strangetest\EVENT_PASS, 'depends_params\\test::test_one (1)', null),

            array(
                strangetest\EVENT_FAIL, 'depends_params\\test_two (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 2\n+ 1"
            ),
            array(
                strangetest\EVENT_SKIP, 'depends_params\\test_three (0)',
                "This test depends on 'depends_params\\test_two (0)', which did not pass"
            ),
            array(
                strangetest\EVENT_FAIL, 'depends_params\\test::test_two (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 2\n+ 1"
            ),
            array(
                strangetest\EVENT_SKIP, 'depends_params\\test::test_three (0)',
                "This test depends on 'depends_params\\test::test_two (0)', which did not pass"
            ),

            array(strangetest\EVENT_PASS, 'depends_params\\test_two (1)', null),
            array(strangetest\EVENT_PASS, 'depends_params\\test_three (1)', null),
            array(strangetest\EVENT_PASS, 'depends_params\\test::test_two (1)', null),
            array(strangetest\EVENT_PASS, 'depends_params\\test::test_three (1)', null),
        ));
    }


    public function test_dependee_tests_that_are_never_run_is_an_error() {
        $this->root .= 'unrun_depends/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_five', 'Skip me'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_six', "This test depends on 'unrun_depends\\test_five', which did not pass"),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test1::setup_object', 'Skip me'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_five', 'Skip me'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_six', "This test depends on 'unrun_depends\\test2::test_five', which did not pass"),

            array(strangetest\EVENT_ERROR, 'foobar', 'Other tests depend on this test, but this test was never run'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_one', "This test depends on 'foobar', which did not pass"),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_two', "This test depends on 'unrun_depends\\test_one', which did not pass"),

            array(strangetest\EVENT_ERROR, 'unrun_depends\\test1::test_one', 'Other tests depend on this test, but this test was never run'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_three', "This test depends on 'unrun_depends\\test1::test_one', which did not pass"),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test_four', "This test depends on 'unrun_depends\\test_three', which did not pass"),

            array(strangetest\EVENT_ERROR, 'frobitz', 'Other tests depend on this test, but this test was never run'),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_one', "This test depends on 'frobitz', which did not pass"),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_two', "This test depends on 'unrun_depends\\test2::test_one', which did not pass"),

            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_three', "This test depends on 'unrun_depends\\test1::test_one', which did not pass"),
            array(strangetest\EVENT_SKIP, 'unrun_depends\\test2::test_four', "This test depends on 'unrun_depends\\test2::test_three', which did not pass"),
        ));
    }


    public function test_cyclical_dependencies_are_an_error() {
        $this->root .= 'cyclical_depends/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_ERROR, 'cyclical_depends\\test_five', "This test has a cyclical dependency with the following tests:\n\tcyclical_depends\\test_four\n\tcyclical_depends\\test_three"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test_four', "This test depends on 'cyclical_depends\\test_five', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test_three', "This test depends on 'cyclical_depends\\test_four', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test_two', "This test depends on 'cyclical_depends\\test_three', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test_one', "This test depends on 'cyclical_depends\\test_two', which did not pass"),

            array(strangetest\EVENT_ERROR, 'cyclical_depends\\test::test_five', "This test has a cyclical dependency with the following tests:\n\tcyclical_depends\\test::test_four\n\tcyclical_depends\\test::test_three"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test::test_four', "This test depends on 'cyclical_depends\\test::test_five', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test::test_three', "This test depends on 'cyclical_depends\\test::test_four', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test::test_two', "This test depends on 'cyclical_depends\\test::test_three', which did not pass"),
            array(strangetest\EVENT_SKIP, 'cyclical_depends\\test::test_one', "This test depends on 'cyclical_depends\\test::test_two', which did not pass"),
        ));
    }


    public function test_multiple_dependencies_can_be_declared() {
        $this->root .= 'multiple_depends/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'multiple_depends\\test_seven', null),
            array(strangetest\EVENT_PASS, 'multiple_depends\\test_ten', null),

            array(strangetest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(strangetest\EVENT_PASS, 'multiple_depends\\test::test_six', null),
            array(strangetest\EVENT_FAIL, 'multiple_depends\\test::test_eight', 'I fail'),
            array(strangetest\EVENT_PASS, 'multiple_depends\\test::test_nine', null),

            array(strangetest\EVENT_PASS, 'multiple_depends\\test_five', null),

            array(strangetest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(strangetest\EVENT_PASS, 'multiple_depends\\test::test_two', null),

            array(strangetest\EVENT_PASS, 'multiple_depends\\test_three', null),

            array(strangetest\EVENT_OUTPUT, 'multiple_depends\\test', '.'),
            array(strangetest\EVENT_SKIP, 'multiple_depends\\test::test_four', "This test depends on 'multiple_depends\\test::test_eight', which did not pass"),

            array(strangetest\EVENT_SKIP, 'multiple_depends\\test_one', "This test depends on 'multiple_depends\\test::test_four', which did not pass"),
        ));
    }


    public function test_multiple_dependencies_can_be_declared_separately() {
        $this->root .= 'separate_depends/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'separate_depends\\test_seven', null),
            array(strangetest\EVENT_PASS, 'separate_depends\\test_ten', null),

            array(strangetest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(strangetest\EVENT_PASS, 'separate_depends\\test::test_six', null),
            array(strangetest\EVENT_FAIL, 'separate_depends\\test::test_eight', 'I fail'),
            array(strangetest\EVENT_PASS, 'separate_depends\\test::test_nine', null),

            array(strangetest\EVENT_PASS, 'separate_depends\\test_five', null),

            array(strangetest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(strangetest\EVENT_PASS, 'separate_depends\\test::test_two', null),

            array(strangetest\EVENT_PASS, 'separate_depends\\test_three', null),

            array(strangetest\EVENT_OUTPUT, 'separate_depends\\test', '.'),
            array(strangetest\EVENT_SKIP, 'separate_depends\\test::test_four', "This test depends on 'separate_depends\\test::test_eight', which did not pass"),

            array(strangetest\EVENT_SKIP, 'separate_depends\\test_one', "This test depends on 'separate_depends\\test::test_four', which did not pass"),
        ));
    }


    public function test_dependencies_between_parameterized_tests() {
        $this->root .= 'param_xdepends/';
        $this->path = array(
            'test_nonparam.php',
            'test_param.php',
        );

        $this->assert_events(array(
            array(strangetest\EVENT_PASS, 'param_xdepend\\nonparam\\test_one', null),
            array(strangetest\EVENT_FAIL, 'param_xdepend\\nonparam\\test_six', 'I fail'),

            array(strangetest\EVENT_SKIP, 'param_xdepend\\param\\test_five (0)', "This test depends on 'param_xdepend\\nonparam\\test_six', which did not pass"),
            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_one (0)', null),

            array(strangetest\EVENT_SKIP, 'param_xdepend\\param\\test_five (1)', "This test depends on 'param_xdepend\\nonparam\\test_six', which did not pass"),
            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_one (1)', null),


            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_two (0)', null),
            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_two (1)', null),
            array(strangetest\EVENT_PASS, 'param_xdepend\\nonparam\\test_two', null),


            array(
                strangetest\EVENT_FAIL,
                'param_xdepend\\param\\test_three (0)',
                "Assertion \"\$expected === \$actual\" failed\n\n- \$expected\n+ \$actual\n\n- 14\n+ 10",
            ),
            array(strangetest\EVENT_SKIP, 'param_xdepend\\param\\test_four (0)', "This test depends on 'param_xdepend\\param\\test_three (0)', which did not pass"),
            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_three (1)', null),
            array(strangetest\EVENT_PASS, 'param_xdepend\\param\\test_four (1)', null),


            array(strangetest\EVENT_SKIP, 'param_xdepend\\nonparam\\test_three', "This test depends on 'param_xdepend\\param\\test_four', which did not pass"),
            array(strangetest\EVENT_SKIP, 'param_xdepend\\nonparam\\test_four', "This test depends on 'param_xdepend\\param\\test_four', which did not pass"),
            array(strangetest\EVENT_SKIP, 'param_xdepend\\nonparam\\test_five', "This test depends on 'param_xdepend\\param\\test_four', which did not pass"),
            array(strangetest\EVENT_SKIP, 'param_xdepend\\param\\test_six (0)', "This test depends on 'param_xdepend\\param\\test_five (0)', which did not pass"),
            array(strangetest\EVENT_SKIP, 'param_xdepend\\param\\test_six (1)', "This test depends on 'param_xdepend\\param\\test_five (1)', which did not pass"),
        ));
    }


    public function test_an_error_supersedes_dependencies() {
        $this->root .= 'error_depend/';
        $this->path .= 'test.php';

        $this->assert_events(array(
            array(strangetest\EVENT_ERROR, 'teardown_function for error_depend\\test_one', 'I erred'),
            array(strangetest\EVENT_PASS, 'error_depend\\test_two', null),

            array(strangetest\EVENT_ERROR, 'teardown for error_depend\\test::test_one', 'I erred'),
            array(strangetest\EVENT_PASS, 'error_depend\\test::test_two', null),
        ));
    }
}
