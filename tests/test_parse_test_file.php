<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestParseTestFile {
    private $root;
    private $logger;


    public function setup_object() {
        $this->root = \sprintf(
            '%1$s%2$ssample_files%2$s',
            __DIR__, \DIRECTORY_SEPARATOR
        );
    }

    public function setup() {
        $this->logger = new easytest\BasicLogger(true);
    }


    // helper assertions

    private function assert_log(array $expected) {
        easytest\assert_identical(
            $expected,
            $this->logger->get_log()->get_events(),
            'Unexpected events during parsing'
        );

    }


    private function assert_discovered(easytest\FileTest $expected, easytest\FileTest $discovered, array $seen) {
        easytest\assert_identical(
            (array)$expected, (array)$discovered,
            'Failed parsing names'
        );

        easytest\assert_identical(
            $this->seen_names($expected), $seen,
            'Failed adding seen names'
        );
    }


    // helper function

    private function seen_names(easytest\FileTest $discovered) {
        $seen = [];
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
        $seen = [];
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([
            [
                easytest\LOG_EVENT_OUTPUT,
                $filepath,
                "'class TestTextBefore {}\n\n\nclass TestTextAfter {}\n'",
            ],
        ]);

        $expected = new easytest\FileTest();
        $expected->identifiers = [
            ['Test', easytest\TYPE_CLASS],
            ['Test', easytest\TYPE_FUNCTION],
            ['testTwo', easytest\TYPE_FUNCTION],
            ['test2', easytest\TYPE_CLASS],
            ['TestThree', easytest\TYPE_CLASS],
            ['test_three', easytest\TYPE_FUNCTION],
        ];
        $this->assert_discovered($expected, $discovered, $seen);
    }


    public function test_does_not_discover_anonymous_class() {
        // #BC(5.6): Check if anonymous classes are supported
        if (\version_compare(\PHP_VERSION, '7.0', '<')) {
            easytest\skip('PHP 7 introduced anonymous classes');
        }

        $filepath = "{$this->root}anonymous_class.php";
        $seen = [];
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([]);

        $expected = new easytest\FileTest();
        $expected->identifiers = [
            ['TestUsesAnonymousClass', easytest\TYPE_CLASS],
        ];
        $this->assert_discovered($expected, $discovered, $seen);
    }


    public function test_parses_simple_namespaces() {
        $filepath = "{$this->root}namespaces_simple.php";
        $seen = [];
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([]);

        $expected = new easytest\FileTest();
        $expected->identifiers = [
            ['ns2\\TestNamespace', easytest\TYPE_CLASS],
            ['ns2\\TestNamespace', easytest\TYPE_FUNCTION],
            ['ns3\\TestNamespace', easytest\TYPE_FUNCTION],
            ['ns3\\TestNamespace', easytest\TYPE_CLASS],
        ];
        $this->assert_discovered($expected, $discovered, $seen);
    }


    public function test_parses_bracketed_namespaces() {
        $filepath = "{$this->root}namespaces_bracketed.php";
        $seen = [];
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([]);

        $expected = new easytest\FileTest();
        $expected->identifiers = [
            ['ns1\\ns1\\TestNamespace', easytest\TYPE_CLASS],
            ['ns1\\ns1\\TestNamespace', easytest\TYPE_FUNCTION],
            ['ns1\\ns2\\TestNamespace', easytest\TYPE_CLASS],
            ['ns1\\ns2\\TestNamespace', easytest\TYPE_FUNCTION],
            ['TestNamespace', easytest\TYPE_FUNCTION],
            ['TestNamespace', easytest\TYPE_CLASS],
        ];
        $this->assert_discovered($expected, $discovered, $seen);
    }


    public function test_parses_conditional_definitions() {
        $filepath = "{$this->root}conditional1.php";
        $seen = [];
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([]);

        $expected_discovered = new easytest\FileTest();
        $expected_discovered->identifiers = [
            ['conditional\\TestA', easytest\TYPE_CLASS],
            ['conditional\\test_a', easytest\TYPE_FUNCTION],
        ];
        easytest\assert_identical(
            (array)$expected_discovered, (array)$discovered,
            'Failed parsing names'
        );

        $expected_seen = [
            'class conditional\\TestA' => true,
            'function conditional\\test_a' => true,
        ];
        easytest\assert_identical(
            $expected_seen, $seen,
            'Failed adding seen names'
        );


        // Ensure already-seen names aren't re-discovered

        $filepath = "{$this->root}conditional2.php";
        $discovered = easytest\_parse_test_file($this->logger, $filepath, $seen);

        $this->assert_log([]);

        $expected_discovered = new easytest\FileTest();
        $expected_discovered->identifiers = [
            ['conditional\\TestB', easytest\TYPE_CLASS],
            ['conditional\\test_b', easytest\TYPE_FUNCTION],
        ];
        easytest\assert_identical(
            (array)$expected_discovered, (array)$discovered,
            'Failed parsing names'
        );

        $expected_seen['class conditional\\TestB'] = true;
        $expected_seen['function conditional\\test_b'] = true;
        easytest\assert_identical(
            $expected_seen, $seen,
            'Failed adding seen names'
        );
    }
}
