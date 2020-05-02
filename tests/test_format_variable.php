<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

// Some helper classes to test formatting

class ObjectFormat {
    public $one = 'parent public';
    protected $two = 'parent protected';
    private $three = 'parent private';
}

class InheritFormat extends ObjectFormat {
    public $one = 'child public';
    protected $two = 'child protected';
    private $three = 'child private';
}

class IntegerProperties {
    public function __construct() {
        $this->{0} = 'zero';
        $this->{1} = 'one';
    }
}



class TestFormatVariable {

    public function test_formats_scalars() {
        $tests = array(
            array(1, '1'),
            array(1.5, '1.5'),
            array("Here's a string", "'Here\\'s a string'"),
            array(true, 'true'),
            array(null, 'NULL')
        );

        foreach ($tests as $test) {
            list($variable, $expected) = $test;
            $actual = easytest\format_variable($variable);
            easytest\assert_identical($expected, $actual);
        }
    }


    public function test_formats_empty_array() {
        $variable = array();
        $actual = easytest\format_variable($variable);
        easytest\assert_identical('array()', $actual);
    }


    public function test_formats_array() {
        $variable = array(
            'one' => 'one',
            'two' => 2,
            array('one', 2, 'three', null),
            'three' => array(1, 2, 3),
        );
        $expected = <<<'EXPECTED'
array(
    'one' => 'one',
    'two' => 2,
    0 => array(
        0 => 'one',
        1 => 2,
        2 => 'three',
        3 => NULL,
    ),
    'three' => array(
        0 => 1,
        1 => 2,
        2 => 3,
    ),
)
EXPECTED;
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }


    public function test_formats_empty_object() {
        $variable = new stdClass();
        $actual = easytest\format_variable($variable);
        easytest\assert_identical('stdClass {}', $actual);
    }


    public function test_formats_object() {
        $variable = new InheritFormat();
        $expected = <<<'EXPECTED'
InheritFormat {
    $one = 'child public';
    $two = 'child protected';
    $three = 'child private';
    ObjectFormat::$three = 'parent private';
}
EXPECTED;
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }


    public function test_formats_resource() {
        $variable = fopen(__FILE__, 'r');
        $resource = print_r($variable, true);

        $expected = sprintf(
            '%s of type "%s"',
            $resource,
            get_resource_type($variable)
        );
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }


    public function test_handles_recursive_array() {
        $variable = array(
            'one' => 'one',
            'two' => array('one', 2, 'three', null),
        );
        $variable['three'] = $variable['one'];
        $variable['four'] = $variable['two'];
        $variable['five'] = &$variable['one'];
        $variable['six'] = &$variable['two'];
        $variable['seven'] = &$variable['four'][1];
        $variable['eight'] = &$variable['six'][1];
        $variable['nine'] = &$variable;

        $expected = <<<'EXPECTED'
array(
    'one' => 'one',
    'two' => array(
        0 => 'one',
        1 => 2,
        2 => 'three',
        3 => NULL,
    ),
    'three' => 'one',
    'four' => array(
        0 => 'one',
        1 => 2,
        2 => 'three',
        3 => NULL,
    ),
    'five' => &array['one'],
    'six' => &array['two'],
    'seven' => &array['four'][1],
    'eight' => &array['two'][1],
    'nine' => &array,
)
EXPECTED;
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }


    public function test_handles_recursive_object() {
        $variable = new ObjectFormat();
        $variable->one = new ObjectFormat();
        $variable->one->one = $variable;
        $variable->one->six = &$variable->one;

        $expected = <<<'EXPECTED'
ObjectFormat {
    $one = ObjectFormat {
        $one = ObjectFormat;
        $two = 'parent protected';
        $three = 'parent private';
        $six = &ObjectFormat->$one;
    };
    $two = 'parent protected';
    $three = 'parent private';
}
EXPECTED;
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }


    function test_formats_integer_object_properties() {
        $variable = new IntegerProperties();

        $expected = <<<'EXPECTED'
IntegerProperties {
    $0 = 'zero';
    $1 = 'one';
}
EXPECTED;
        $actual = easytest\format_variable($variable);
        easytest\assert_identical($expected, $actual);
    }
}
