<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestParseTestFile {
    private $root;
    private $logger;
    private $state;


    public function setup_object() {
        $this->root = \sprintf(
            '%1$s%2$ssample_files%2$sparse_file%2$s',
            __DIR__, \DIRECTORY_SEPARATOR
        );
    }

    public function setup() {
        $this->logger = new easytest\BasicLogger(true);
        $this->state = new easytest\State();
    }


    // helper assertions

    private function assert_log(array $expected) {
        easytest\assert_identical(
            $expected,
            $this->logger->get_log()->get_events(),
            'Unexpected events during parsing'
        );

    }


    private function assert_discovered(easytest\FileTest $expected, easytest\FileTest $discovered) {
        easytest\assert_identical(
            (array)$expected, (array)$discovered,
            'Failed parsing names'
        );

        easytest\assert_identical(
            $this->seen_names($expected), $this->state->seen,
            'Failed adding seen names'
        );
    }


    // helper function

    private function seen_names(easytest\FileTest $discovered) {
        $seen = array();
        foreach ($discovered->identifiers as $item) {
            list($name, $type) = $item;
            switch ($type) {
            case easytest\TYPE_CLASS:
                $seenname = "class $name";
                break;

            case easytest\TYPE_FUNCTION:
                $seenname = "function $name";
                break;

            default:
                easytest\fail("Unexpected test type: $type");
                break;
            }

            $seen[$seenname] = true;
        }
        return $seen;
    }


    // tests

    public function test_parses_unnamespaced_file() {
        $filepath = "{$this->root}unnamespaced.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array(
            array(
                easytest\LOG_EVENT_OUTPUT,
                $filepath,
                "'class TestTextBefore {}\n\n\nclass TestTextAfter {}\n'",
            ),
        ));

        $expected = new easytest\FileTest();
        $expected->filepath = $filepath;
        $expected->identifiers = array(
            array('Test', easytest\TYPE_CLASS),
            array('Test', easytest\TYPE_FUNCTION),
            array('testTwo', easytest\TYPE_FUNCTION),
            array('test2', easytest\TYPE_CLASS),
            array('TestThree', easytest\TYPE_CLASS),
            array('test_three', easytest\TYPE_FUNCTION),
        );
        $this->assert_discovered($expected, $discovered);
    }


    public function test_does_not_discover_anonymous_class() {
        // #BC(5.6): Check if anonymous classes are supported
        if (\version_compare(\PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced anonymous classes');
        }

        $filepath = "{$this->root}anonymous_class.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array());

        $expected = new easytest\FileTest();
        $expected->filepath = $filepath;
        $expected->identifiers = array(
            array('TestUsesAnonymousClass', easytest\TYPE_CLASS),
        );
        $this->assert_discovered($expected, $discovered);
    }


    public function test_parses_simple_namespaces() {
        $filepath = "{$this->root}namespaces_simple.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array());

        $expected = new easytest\FileTest();
        $expected->filepath = $filepath;
        $expected->identifiers = array(
            array('ns2\\TestNamespace', easytest\TYPE_CLASS),
            array('ns2\\TestNamespace', easytest\TYPE_FUNCTION),
            array('ns3\\TestNamespace', easytest\TYPE_FUNCTION),
            array('ns3\\TestNamespace', easytest\TYPE_CLASS),
        );
        $this->assert_discovered($expected, $discovered);
    }


    public function test_parses_bracketed_namespaces() {
        $filepath = "{$this->root}namespaces_bracketed.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array());

        $expected = new easytest\FileTest();
        $expected->filepath = $filepath;
        $expected->identifiers = array(
            array('ns1\\ns1\\TestNamespace', easytest\TYPE_CLASS),
            array('ns1\\ns1\\TestNamespace', easytest\TYPE_FUNCTION),
            array('ns1\\ns2\\TestNamespace', easytest\TYPE_CLASS),
            array('ns1\\ns2\\TestNamespace', easytest\TYPE_FUNCTION),
            array('TestNamespace', easytest\TYPE_FUNCTION),
            array('TestNamespace', easytest\TYPE_CLASS),
        );
        $this->assert_discovered($expected, $discovered);
    }


    public function test_parses_conditional_definitions() {
        $filepath = "{$this->root}conditional1.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array());

        $expected_discovered = new easytest\FileTest();
        $expected_discovered->filepath = $filepath;
        $expected_discovered->identifiers = array(
            array('conditional\\TestA', easytest\TYPE_CLASS),
            array('conditional\\test_a', easytest\TYPE_FUNCTION),
        );
        easytest\assert_identical(
            (array)$expected_discovered, (array)$discovered,
            'Failed parsing names'
        );

        $expected_seen = array(
            'class conditional\\TestA' => true,
            'function conditional\\test_a' => true,
        );
        easytest\assert_identical(
            $expected_seen, $this->state->seen,
            'Failed adding seen names'
        );


        // Ensure already-seen names aren't re-discovered

        $filepath = "{$this->root}conditional2.php";
        $discovered = easytest\_discover_file($this->state, $this->logger, $filepath);

        $this->assert_log(array());

        $expected_discovered = new easytest\FileTest();
        $expected_discovered->filepath = $filepath;
        $expected_discovered->identifiers = array(
            array('conditional\\TestB', easytest\TYPE_CLASS),
            array('conditional\\test_b', easytest\TYPE_FUNCTION),
        );
        easytest\assert_identical(
            (array)$expected_discovered, (array)$discovered,
            'Failed parsing names'
        );

        $expected_seen['class conditional\\TestB'] = true;
        $expected_seen['function conditional\\test_b'] = true;
        easytest\assert_identical(
            $expected_seen, $this->state->seen,
            'Failed adding seen names'
        );
    }
}
