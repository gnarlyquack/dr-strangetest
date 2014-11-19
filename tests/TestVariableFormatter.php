<?php

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
            assert('$expected === $actual');
        }
    }

    public function test_empty_array() {
        $variable = [];
        $actual = $this->formatter->format_var($variable);
        assert('"array()" === $actual');
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
        assert('$expected === $actual');
    }

    public function test_empty_object() {
        $actual = $this->formatter->format_var(new stdClass());
        assert('"stdClass {}" === $actual');
    }

    public function test_object() {
        $expected = <<<'EXPECTED'
ObjectFormat {
    $one = 'parent public';
    $two = 'parent protected';
    $three = 'parent private';
}
EXPECTED;
        $actual = $this->formatter->format_var(new ObjectFormat());
        assert('$expected === $actual');
    }

    public function test_object_inheritance() {
        $expected = <<<'EXPECTED'
InheritFormat {
    $one = 'child public';
    $two = 'child protected';
    $three = 'child private';
    ObjectFormat::$three = 'parent private';
}
EXPECTED;
        $actual = $this->formatter->format_var(new InheritFormat());
        assert('$expected === $actual');
    }

    public function test_resource() {
        $variable = fopen(__FILE__, 'r');
        $resource = print_r($variable, true);
        assert('preg_match("~^Resource id #\d+$~", $resource)');

        $expected = sprintf(
            '%s of type "%s"',
            $resource,
            get_resource_type($variable)
        );
        $actual = $this->formatter->format_var($variable);
        assert('$expected === $actual');
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
        assert('$expected === $actual');
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
        assert('$expected === $actual');
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
        assert('$expected === $actual');
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
        assert('$expected === $actual');
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
