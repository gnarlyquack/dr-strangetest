<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

class TestVariableFormatter {
    private $formatter;

    public function setup() {
        $this->formatter = new easytest\VariableFormatter();
    }

    public function test_scalars() {
        $tests = [
            [1, '1'],
            [1.5, '1.5'],
            ["Here's a string", "'Here\\'s a string'"],
            [true, 'true'],
            [null, 'NULL']
        ];

        foreach ($tests as $test) {
            list($variable, $expected) = $test;
            $actual = $this->formatter->format_var($variable);
            easytest\assert_identical($expected, $actual);
        }
    }

    public function test_empty_array() {
        $variable = [];
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical('array()', $actual);
    }

    public function test_array() {
        $variable = [
            'one' => 'one',
            'two' => 2,
            ['one', 2, 'three', null],
            'three' => [1, 2, 3],
        ];
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
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_empty_object() {
        $variable = new stdClass();
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical('stdClass {}', $actual);
    }

    public function test_object() {
        $variable = new ObjectFormat();
        $expected = <<<'EXPECTED'
ObjectFormat {
    $one = 'parent public';
    $two = 'parent protected';
    $three = 'parent private';
}
EXPECTED;
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_object_inheritance() {
        $variable = new InheritFormat();
        $expected = <<<'EXPECTED'
InheritFormat {
    $one = 'child public';
    $two = 'child protected';
    $three = 'child private';
    ObjectFormat::$three = 'parent private';
}
EXPECTED;
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_resource() {
        $variable = fopen(__FILE__, 'r');
        $resource = print_r($variable, true);
        assert(preg_match('~^Resource id #\\d+$~', $resource));

        $expected = sprintf(
            '%s of type "%s"',
            $resource,
            get_resource_type($variable)
        );
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_array_reference() {
        $variable = [
            'one' => 'one',
            'two' => ['one', 2, 'three', null],
        ];
        $variable['three'] = $variable['one'];
        $variable['four'] = $variable['two'];
        $variable['five'] = &$variable['one'];
        $variable['six'] = &$variable['two'];
        $variable['seven'] = &$variable['four'][1];
        $variable['eight'] = &$variable['six'][1];

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
)
EXPECTED;
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_array_recursive_reference() {
        $variable = [];
        $variable[] = &$variable;
        $expected = <<<'EXPECTED'
array(
    0 => &array,
)
EXPECTED;
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_object_reference() {
        $variable = [
            new ObjectFormat(),
            new ObjectFormat(),
        ];
        $variable[] = $variable[0];
        $variable[] = &$variable[0];

        $expected = <<<'EXPECTED'
array(
    0 => ObjectFormat {
        $one = 'parent public';
        $two = 'parent protected';
        $three = 'parent private';
    },
    1 => ObjectFormat {
        $one = 'parent public';
        $two = 'parent protected';
        $three = 'parent private';
    },
    2 => array[0],
    3 => &array[0],
)
EXPECTED;
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }

    public function test_object_recursive_reference() {
        $variable = new ObjectFormat();
        $variable->one = new ObjectFormat();
        $variable->one->one = $variable;
        $variable->one->six = $variable->one;

        $expected = <<<'EXPECTED'
ObjectFormat {
    $one = ObjectFormat {
        $one = ObjectFormat;
        $two = 'parent protected';
        $three = 'parent private';
        $six = ObjectFormat->$one;
    };
    $two = 'parent protected';
    $three = 'parent private';
}
EXPECTED;
        $actual = $this->formatter->format_var($variable);
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
        $actual = $this->formatter->format_var($variable);
        easytest\assert_identical($expected, $actual);
    }
}


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
