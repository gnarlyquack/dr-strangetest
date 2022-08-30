<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


namespace strangetest;

// @todo Are their implications for handling spaceship comparison?
// The spaceship comparison operator was added in PHP 7.0
//
// For reference, PHP's handling of how different types of values are compared
// is described at:
// https://www.php.net/manual/en/language.operators.comparison.php
const DIFF_IDENTICAL = 0;
const DIFF_EQUAL = 1;
const DIFF_GREATER = 2;
const DIFF_GREATER_EQUAL = 3;
const DIFF_LESS = 4;
const DIFF_LESS_EQUAL = 5;


const _DIFF_MATCH_NONE    = 0; // Elements have neither matching values nor matching keys
const _DIFF_MATCH_PARTIAL = 1; // Elements have matching values but nonmatching keys
const _DIFF_MATCH_FULL    = 2; // Elements have both matching values and keys


/**
 * @todo Consider changing diff parameters from $from, $to to $actual, $expected
 * @api
 * @param mixed $from
 * @param mixed $to
 * @param string $from_name
 * @param string $to_name
 * @param int $cmp
 * @return string
 */
function diff(&$from, &$to, $from_name, $to_name, $cmp = namespace\DIFF_IDENTICAL)
{
    if ($from_name === $to_name)
    {
        throw new \Exception('Parameters $from_name and $to_name must be different');
    }

    $state = new _DiffState($cmp);

    namespace\_diff_values(
        namespace\_process_value($state, $from_name, new _NullKey(), $from),
        namespace\_process_value($state, $to_name, new _NullKey(), $to),
        $state
    );

    $result = namespace\_format_diff($state->diff, $from_name, $to_name, $cmp);
    return $result;
}


final class _DiffState extends struct {
    /** @var int */
    public $cmp;

    /** @var array<string, array<string, _Edit>> */
    public $matrix_cache = array();

    /** @var _DiffOperation[] */
    public $diff = array();

    /** @var array{'byval': mixed[], 'byref': mixed[]} */
    public $seen = array('byval' => array(), 'byref' => array());

    /** @var array{'byref': null, 'byval': \stdClass} */
    public $sentinels;

    /**
     * @param int $cmp
     */
    public function __construct($cmp)
    {
        $this->cmp = $cmp;
        $this->sentinels = array('byref' => null, 'byval' => new \stdClass());
    }
}


interface _DiffOperation
{
    const COPY = 1;
    const INSERT = 2;
    const DELETE = 3;

    /**
     * @return _DiffOperation::*
     */
    public function operation();

    public function __toString();
}


final class _DiffCopy extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::COPY;
    }

    public function __toString()
    {
        return "  {$this->value}";
    }
}


final class _DiffInsert extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::INSERT;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "+ {$this->value}";
    }
}


final class _DiffInsertGreater extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::INSERT;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "> {$this->value}";
    }
}


final class _DiffInsertLess extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::INSERT;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "< {$this->value}";
    }
}


final class _DiffDelete extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::DELETE;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "- {$this->value}";
    }
}


final class _DiffDeleteGreater extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::DELETE;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "> {$this->value}";
    }
}


final class _DiffDeleteLess extends struct implements _DiffOperation
{
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function operation()
    {
        return self::DELETE;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "< {$this->value}";
    }
}



final class _Edit extends struct {
    /** @var int */
    public $flen;

    /** @var int */
    public $tlen;

    /** @var array<int, int[]> */
    public $m;

    /**
     * @param int $flen
     * @param int $tlen
     * @param array<int, int[]> $m
     */
    public function __construct($flen, $tlen, $m)
    {
        $this->flen = $flen;
        $this->tlen = $tlen;
        $this->m = $m;
    }
}


final class _DiffPosition extends struct {
    const NONE = 0;
    const START = 1;
    const MIDDLE = 2;
    const END = 3;
}


interface _Key {
    /**
     * @return null|int|string
     */
    public function key();

    /**
     * @return string
     */
    public function format_key();

    /**
     * @return string
     */
    public function line_end();

    /**
     * @return bool
     */
    public function in_list();
}


final class _NullKey extends struct implements _Key {
    /**
     * @return null;
     */
    public function key()
    {
        return null;
    }

    public function format_key()
    {
        return '';
    }

    /**
     * @return ''
     */
    public function line_end()
    {
        return '';
    }

    public function in_list()
    {
        return false;
    }
}


final class _ArrayIndex extends struct implements _Key {
    /** @var int|string */
    private $index;

    /** @var bool */
    public $in_list;

    /**
     * @param int|string $index
     * @param bool $in_list
     */
    public function __construct($index, &$in_list)
    {
        $this->index = $index;
        $this->in_list =& $in_list;
    }

    /**
     * @return int|string
     */
    public function key()
    {
        return $this->index;
    }

    public function format_key()
    {
        return \sprintf('%s => ', \var_export($this->index, true));
    }

    /**
     * @return string
     */
    public function line_end()
    {
        return ',';
    }

    public function in_list()
    {
        return $this->in_list;
    }
}


final class _PropertyName extends struct implements _Key {
    /** @var int|string */
    public $name;

    /**
     * @param int|string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return int|string
     */
    public function key()
    {
        return $this->name;
    }

    public function format_key()
    {
        return "\${$this->name} = ";
    }

    /**
     * @return string
     */
    public function line_end()
    {
        return ';';
    }

    public function in_list()
    {
        return false;
    }
}


final class _ValueType extends struct {
    // @BC(5.6): Don't use ARRAY as an identifier to prevent parse error
    const ARRAY_ = 1;
    const OBJECT = 2;
    const REFERENCE = 3;
    const SCALAR = 4;
    const STRING = 5;
    const STRING_PART = 6;
    const RESOURCE = 7;
}


interface _Value {
    /**
     * @return _ValueType::*
     */
    public function type();

    /**
     * @return null|int|string
     */
    public function key();

    /**
     * @return mixed
     */
    public function &value();

    /**
     * @return int
     */
    public function cost();

    /**
     * @return _Value[]
     */
    public function subvalues();


    /**
     * @param _DiffPosition::* $pos
     * @param bool $show_key
     * @return string
     */
    public function format_value($pos, $show_key);

    /**
     * @param bool $show_key
     * @return string
     */
    public function start_value($show_key);

    /**
     * @return string
     */
    public function end_value();
}


final class _Array extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var mixed[] */
    private $value;

    /** @var bool */
    public $loose;

    /** @var int */
    public $indent_level;

    /** @var int */
    private $cost;

    /** @var _Value[] */
    private $subvalues;

    /** @var bool */
    public $is_list;

    /**
     * @param string $name
     * @param bool $is_list
     * @param _Key $key
     * @param mixed[] $value
     * @param int $cmp
     * @param int $indent_level
     * @param int $cost
     * @param _Value[] $subvalues
     */
    public function __construct($name, $is_list, _Key $key, &$value, $cmp, $indent_level, $cost, array $subvalues)
    {
        $this->name = $name;
        $this->is_list = $is_list;
        $this->key = $key;
        $this->value = &$value;
        $this->loose = $cmp !== namespace\DIFF_IDENTICAL;
        $this->indent_level = $indent_level;
        $this->cost = $cost;
        $this->subvalues = $subvalues;
    }

    /**
     * @return _ValueType::ARRAY_
     */
    public function type()
    {
        return _ValueType::ARRAY_;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return mixed[]
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function cost()
    {
        return $this->cost;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        return $this->subvalues;
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\format_array($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        return "{$indent}{$key}array(";
    }

    /**
     * @return string
     */
    public function end_value()
    {
        $indent = namespace\format_indent($this->indent_level);
        $line_end = $this->key->line_end();
        return "{$indent}){$line_end}";
    }
}


final class _Object extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var object */
    private $value;

    /** @var bool */
    public $loose;

    /** @var int */
    public $indent_level;

    /** @var int */
    private $cost;

    /** @var _Value[] */
    private $subvalues;

    /**
     * @param string $name
     * @param _Key $key
     * @param object $value
     * @param int $cmp
     * @param int $indent_level
     * @param int $cost
     * @param _Value[] $subvalues
     */
    public function __construct($name, _Key $key, &$value, $cmp, $indent_level, $cost, array $subvalues)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->loose = $cmp !== namespace\DIFF_IDENTICAL;
        $this->indent_level = $indent_level;
        $this->cost = $cost;
        $this->subvalues = $subvalues;
    }

    /**
     * @return _ValueType::OBJECT
     */
    public function type()
    {
        return _ValueType::OBJECT;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return object
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function cost()
    {
        return $this->cost;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        return $this->subvalues;
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\format_object($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $class = namespace\format_object_start($this->value, $this->loose);
        return "{$indent}{$key}{$class}";
    }

    /**
     * @return string
     */
    public function end_value()
    {
        $indent = namespace\format_indent($this->indent_level);
        $line_end = $this->key->line_end();
        return "{$indent}}{$line_end}";
    }
}


final class _Reference extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var mixed */
    private $value;

    /** @var int */
    public $indent_level;

    /**
     * @param string $name
     * @param _Key $key
     * @param mixed $value
     * @param int $indent_level
     */
    public function __construct($name, _Key $key, &$value, $indent_level)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->indent_level = $indent_level;
    }

    /**
     * @return _ValueType::REFERENCE
     */
    public function type()
    {
        return _ValueType::REFERENCE;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return mixed
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return 1
     */
    public function cost()
    {
        return 1;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        throw new InvalidCodePath('References have no subvalues');
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $result = $this->name;
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        throw new InvalidCodePath('Cannot start a non-container value');
    }

    /**
     * @return string
     */
    public function end_value()
    {
        throw new InvalidCodePath('Cannot end a non-container value');
    }
}


final class _Resource extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var resource */
    private $value;

    /** @var int */
    public $indent_level;

    /**
     * @param string $name
     * @param _Key $key
     * @param resource $value
     * @param int $indent_level
     */
    public function __construct($name, _Key $key, &$value, $indent_level)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->indent_level = $indent_level;
    }

    /**
     * @return _ValueType::RESOURCE
     */
    public function type()
    {
        return _ValueType::RESOURCE;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return resource
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return 1
     */
    public function cost()
    {
        return 1;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        throw new InvalidCodePath('Resources have no subvalues');
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $result = namespace\format_resource($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        throw new InvalidCodePath('Cannot start a non-container value');
    }

    /**
     * @return string
     */
    public function end_value()
    {
        throw new InvalidCodePath('Cannot end a non-container value');
    }
}


final class _Scalar extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var bool|float|int|null */
    private $value;

    /** @var int */
    public $indent_level;

    /**
     * @param string $name
     * @param _Key $key
     * @param bool|float|int|null $value
     * @param int $indent_level
     */
    public function __construct($name, _Key $key, &$value, $indent_level)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->indent_level = $indent_level;
    }

    /**
     * @return _ValueType::SCALAR
     */
    public function type()
    {
        return _ValueType::SCALAR;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return bool|float|int|null
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return 1
     */
    public function cost()
    {
        return 1;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        throw new InvalidCodePath('Scalars have no subvalues');
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $result = namespace\format_scalar($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        throw new InvalidCodePath('Cannot start a non-container value');
    }

    /**
     * @return string
     */
    public function end_value()
    {
        throw new InvalidCodePath('Cannot end a non-container value');
    }
}


final class _String extends struct implements _Value {
    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var string */
    private $value;

    /** @var int */
    public $indent_level;

    /** @var int */
    private $cost;

    /** @var _StringPart[] */
    private $subvalues;

    /**
     * @param string $name
     * @param _Key $key
     * @param string $value
     * @param int $indent_level
     * @param int $cost
     * @param _StringPart[] $subvalues
     */
    public function __construct($name, _Key $key, &$value, $indent_level, $cost, array $subvalues)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->indent_level = $indent_level;
        $this->cost = $cost;
        $this->subvalues = $subvalues;
    }


    /**
     * @return _ValueType::STRING
     */
    public function type()
    {
        return _ValueType::STRING;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->key->key();
    }

    /**
     * @return string
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function cost()
    {
        return $this->cost;
    }

    /**
     * @return _StringPart[]
     */
    public function subvalues()
    {
        return $this->subvalues;
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $result = namespace\format_scalar($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    public function start_value($show_key)
    {
        throw new InvalidCodePath('Cannot start a non-container value');
    }

    /**
     * @return string
     */
    public function end_value()
    {
        throw new InvalidCodePath('Cannot end a non-container value');
    }
}


final class _StringPart extends struct implements _Value {
    /** @var _Key */
    public $key;

    /** @var string */
    private $value;

    /** @var int */
    public $indent_level;

    /** @var int */
    private $index;

    /**
     * @param _Key $key
     * @param string $value
     * @param int $indent_level
     * @param int $index
     */
    public function __construct(_Key $key, &$value, $indent_level, $index)
    {
        $this->key = $key;
        $this->value = &$value;
        $this->indent_level = $indent_level;
        $this->index = $index;
    }

    /**
     * @return _ValueType::STRING_PART
     */
    public function type()
    {
        return _ValueType::STRING_PART;
    }

    /**
     * @return null|int|string
     */
    public function key()
    {
        return $this->index ? null : $this->key->key();
    }

    /**
     * @return string
     */
    public function &value()
    {
        return $this->value;
    }

    /**
     * @return 1
     */
    public function cost()
    {
        return 1;
    }

    /**
     * @return _Value[]
     */
    public function subvalues()
    {
        throw new InvalidCodePath('String parts have no subvalues');
    }

    public function format_value($pos, $show_key)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $show_key ? $this->key->format_key() : '';
        $line_end = $this->key->line_end();

        $result = \str_replace(array('\\', "'",), array('\\\\', "\\'",), $this->value);
        if (_DiffPosition::START === $pos)
        {
            return "{$indent}{$key}'{$result}";
        }
        elseif (_DiffPosition::END === $pos)
        {
            return "{$result}'{$line_end}";
        }
        else
        {
            return $result;
        }
    }

    public function start_value($show_key)
    {
        throw new InvalidCodePath('Cannot start a non-container value');
    }

    /**
     * @return string
     */
    public function end_value()
    {
        throw new InvalidCodePath('Cannot end a non-container value');
    }
}


/**
 * @param _DiffState $state
 * @param string $name
 * @param _Key $key
 * @param mixed $value
 * @param int $indent_level
 * @return _Value
 */
function _process_value(_DiffState $state, $name, _Key $key, &$value, $indent_level = 0)
{
    $reference = namespace\check_reference($value, $name, $state->seen, $state->sentinels);
    if ($reference)
    {
        return new _Reference($reference, $key, $value, $indent_level);
    }

    if (\is_resource($value))
    {
        return new _Resource($name, $key, $value, $indent_level);
    }

    if (\is_string($value))
    {
        $lines = \explode("\n", $value);
        $cost = 0;
        $subvalues = array();
        foreach ($lines as $i => &$line)
        {
            ++$cost;
            $subvalues[] = new _StringPart($key, $line, $indent_level, $i);
        }
        return new _String($name, $key, $value, $indent_level, $cost, $subvalues);
    }

    if (\is_array($value))
    {
        if ($state->cmp !== namespace\DIFF_IDENTICAL)
        {
            \ksort($value);
        }

        $is_list = true;
        $prev_index = -1;
        $cost = 1;
        $subvalues = array();
        foreach ($value as $k => &$v)
        {
            $is_list = $is_list && ($k === ++$prev_index);

            $subname = \sprintf('%s[%s]', $name, \var_export($k, true));
            $subkey = new _ArrayIndex($k, $is_list);
            $subvalue = namespace\_process_value($state, $subname, $subkey, $v, $indent_level + 1);
            $cost += $subvalue->cost();
            $subvalues[] = $subvalue;
        }
        return new _Array($name, $is_list, $key, $value, $state->cmp, $indent_level, $cost, $subvalues);
    }

    if (\is_object($value))
    {
        $cost = 1;
        $subvalues = array();
        // @bc 5.4 use variable for array cast in order to create references
        $values = (array)$value;
        foreach ($values as $k => &$v)
        {
            $subname = \sprintf('%s->%s', $name, $k);
            $subkey = new _PropertyName($k);
            $subvalue = namespace\_process_value($state, $subname, $subkey, $v, $indent_level + 1);
            $cost += $subvalue->cost();
            $subvalues[] = $subvalue;
        }
        return new _Object($name, $key, $value, $state->cmp, $indent_level, $cost, $subvalues);
    }

    return new _Scalar($name, $key, $value, $indent_level);
}


/**
 * @return void
 */
function _diff_values(_Value $from, _Value $to, _DiffState $state)
{
    $cmp = namespace\_lcs_values($state, $from, $to, $state->cmp);

    \assert(isset($from->key));
    \assert(isset($to->key));
    $show_key = !$from->key->in_list() || !$to->key->in_list();

    if ($cmp->matches === namespace\_DIFF_MATCH_FULL)
    {
        namespace\_copy_value($state->diff, $from, $show_key);
    }
    elseif (0 === $cmp->lcs)
    {
        if (($state->cmp === namespace\DIFF_IDENTICAL) || ($state->cmp === namespace\DIFF_EQUAL))
        {
            namespace\_insert_value($state->diff, $to, $show_key);
            namespace\_delete_value($state->diff, $from, $show_key);
        }
        elseif (($from instanceof _String) && ($to instanceof _String) && (($from->cost() > 1) || ($to->cost() > 1)))
        {
            $edit = namespace\_lcs_array($state, $from, $to, $state->cmp);
            namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state, $show_key);
        }
        elseif(($state->cmp === namespace\DIFF_GREATER) || ($state->cmp === namespace\DIFF_GREATER_EQUAL))
        {
            namespace\_delete_greater_value($state->diff, $from, $show_key);
            namespace\_insert_lesser_value($state->diff, $to, $show_key);
        }
        else
        {
            \assert(($state->cmp === namespace\DIFF_LESS) || ($state->cmp === namespace\DIFF_LESS_EQUAL));
            namespace\_delete_lesser_value($state->diff, $from, $show_key);
            namespace\_insert_greater_value($state->diff, $to, $show_key);
        }
    }
    else
    {
        \assert(
            (($from instanceof _String) || ($from instanceof _Array) || ($from instanceof _Object))
            && ($to instanceof $from));

        if ($cmp->matches === namespace\_DIFF_MATCH_PARTIAL)
        {
            // If values match, then only the key is different, so we don't
            // need to generate a difference between the two values.
            \assert(isset($from->indent_level));
            \assert(isset($from->name));

            $indent = namespace\format_indent($from->indent_level);
            $key = $from->key->format_key();
            $line_end = $from->key->line_end();

            $value = $from->value();
            $to_value = $to->value();

            $seen = array('byval' => array(), 'byref' => array());
            $sentinels = array('byref' => null, 'byval' => new \stdClass());
            if ($from instanceof _Array)
            {
                $string = namespace\format_array($value, $from->name, $from->loose, $seen, $sentinels, $indent);
            }
            elseif ($from instanceof _Object)
            {
                $string = namespace\format_object($value, $from->name, $from->loose, $seen, $sentinels, $indent);
            }
            else
            {
                \assert($from->type() !== _ValueType::STRING_PART);
                $string = namespace\format_scalar($value);
            }

            list($start, $rest) = namespace\split_line_first($string);
            $from_start = $indent . $from->key->format_key() . $start;
            $to_start = $indent . $to->key->format_key() . $start;
            $rest .= $from->key->line_end();

            namespace\_copy_string($state->diff, $rest);
            namespace\_insert_string($state->diff, $to_start);
            namespace\_delete_string($state->diff, $from_start);
        }
        elseif ($from instanceof _String)
        {
            $edit = namespace\_lcs_array($state, $from, $to, $state->cmp);
            namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state, $show_key);
        }
        else
        {
            if (($state->cmp === namespace\DIFF_IDENTICAL) || ($state->cmp === namespace\DIFF_EQUAL))
            {
                namespace\_copy_string($state->diff, $from->end_value());
            }
            else
            {
                if (($from->key() === $to->key())
                    && (($state->cmp !== namespace\DIFF_IDENTICAL)
                        || (_ValueType::ARRAY_ === $from->type())))
                {
                    namespace\_copy_string($state->diff, $from->start_value($show_key));
                }
                else
                {
                    namespace\_insert_string($state->diff, $to->start_value($show_key));
                    namespace\_delete_string($state->diff, $from->start_value($show_key));
                }
            }

            $edit = namespace\_lcs_array($state, $from, $to, $state->cmp);
            namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state, ($from instanceof _Object) || !$from->is_list || !$to->is_list);

            if (($state->cmp === namespace\DIFF_IDENTICAL) || ($state->cmp === namespace\DIFF_EQUAL))
            {
                if (($from->key() === $to->key())
                    && (($state->cmp !== namespace\DIFF_IDENTICAL)
                        || (_ValueType::ARRAY_ === $from->type())))
                {
                    namespace\_copy_string($state->diff, $from->start_value($show_key));
                }
                else
                {
                    namespace\_insert_string($state->diff, $to->start_value($show_key));
                    namespace\_delete_string($state->diff, $from->start_value($show_key));
                }
            }
            else
            {
                namespace\_copy_string($state->diff, $from->end_value());
            }
        }
    }
}


class _ComparisonResult
{
    /** @var int */
    public $matches;

    /** @var int */
    public $lcs;
}


/**
 * @param int $cmp
 * @return _ComparisonResult
 */
function _lcs_values(_DiffState $state, _Value $from, _Value $to, $cmp)
{
    $match = namespace\_compare_values($from, $to, $cmp);

    if ($match === namespace\_DIFF_MATCH_FULL)
    {
        $result = new _ComparisonResult;
        $result->matches = $match;
        $result->lcs = \max($from->cost(), $to->cost());
        return $result;
    }

    $lcs = 0;
    if ($from->type() === $to->type())
    {
        // if $from and $to are the same type and are composite types, then
        // generate a diff for their subtypes
        if ($from instanceof _String)
        {
            \assert($to instanceof _String);
            if (($from->cost() > 1) || ($to->cost() > 1))
            {
                $edit = namespace\_lcs_array($state, $from, $to, $cmp);
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            (($from instanceof _Array) && ($to instanceof _Array)) ||
            (($from instanceof _Object) && ($to instanceof _Object)
                && (\get_class($from->value()) === \get_class($to->value()))))
        {
            $lcs = 1;
            if (($from->cost() > 1) && ($to->cost() > 1))
            {
                $edit = namespace\_lcs_array($state, $from, $to, $cmp);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }
    }

    $result = new _ComparisonResult;
    $result->matches = $match;
    $result->lcs = $lcs;
    return $result;
}


/**
 * @param _Array|_Object|_String $from
 * @param _Array|_Object|_String $to
 * @param int $cmp
 * @return _Edit
 */
function _lcs_array(_DiffState $state, _Value $from, _Value $to, $cmp)
{
    if (isset($state->matrix_cache[$from->name][$to->name]))
    {
        $edit = $state->matrix_cache[$from->name][$to->name];
        return $edit;
    }

    $m = array();
    $fvalues = $from->subvalues();
    $tvalues = $to->subvalues();
    $flen = \count($fvalues);
    $tlen = \count($tvalues);

    for ($f = 0; $f <= $flen; ++$f)
    {
        for ($t = 0; $t <= $tlen; ++$t)
        {
            if (!$f || !$t)
            {
                $m[$f][$t] = 0;
                continue;
            }

            $fvalue = $fvalues[$f-1];
            $tvalue = $tvalues[$t-1];
            $result = namespace\_lcs_values($state, $fvalue, $tvalue, $cmp);
            if ($result->matches === namespace\_DIFF_MATCH_FULL)
            {
                $result->lcs += $m[$f-1][$t-1];
            }
            else
            {
                $max = \max($m[$f-1][$t], $m[$f][$t-1]);
                if ($result->lcs)
                {
                    $max = \max($max, $m[$f-1][$t-1] + $result->lcs);
                }
                $result->lcs = $max;
            }
            $m[$f][$t] = $result->lcs;
        }
    }


    $result = new _Edit($flen, $tlen, $m);
    $state->matrix_cache[$from->name][$to->name] = $result;
    return $result;
}


/**
 * @param int $cmp
 * @return int
 */
function _compare_values(_Value $from, _Value $to, $cmp)
{
    $result = namespace\_DIFF_MATCH_NONE;
    $compare_keys = false;

    if ($cmp === namespace\DIFF_IDENTICAL)
    {
        if ($from->value() === $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
        }
    }
    elseif ($cmp === namespace\DIFF_EQUAL)
    {
        if ($from->value() == $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
        }
    }
    elseif ($cmp === namespace\DIFF_GREATER)
    {
        if ($from->value() > $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
            $compare_keys = true;
        }
    }
    elseif ($cmp === namespace\DIFF_GREATER_EQUAL)
    {
        if ($from->value() >= $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
            $compare_keys = true;
        }
    }
    elseif ($cmp === namespace\DIFF_LESS)
    {
        if ($from->value() < $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
            $compare_keys = true;
        }
    }
    elseif ($cmp === namespace\DIFF_LESS_EQUAL)
    {
        if ($from->value() <= $to->value())
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
            $compare_keys = true;
        }
    }
    else
    {
        throw new InvalidCodePath(\sprintf("Unknown diff comparison: %s\n", namespace\format_variable($cmp)));
    }

    if ($result)
    {
        if (!$compare_keys)
        {
            \assert(isset($from->key));
            \assert(isset($to->key));
            $compare_keys = !$from->key->in_list() || !$to->key->in_list;
        }

        if (!$compare_keys || ($from->key() === $to->key()))
        {
            $result = namespace\_DIFF_MATCH_FULL;
        }
    }

    return $result;
}


/**
 * @param _Value[] $from
 * @param _Value[] $to
 * @param bool $show_key
 * @return void
 */
function _build_diff_from_edit(array $from, array $to, _Edit $edit, _DiffState $state, $show_key)
{
    if (($state->cmp === namespace\DIFF_IDENTICAL) || ($state->cmp === namespace\DIFF_EQUAL))
    {
        $m = $edit->m;
        $f = $edit->flen;
        $t = $edit->tlen;

        while ($f || $t)
        {
            if ($f > 0 && $t > 0)
            {
                if (namespace\_compare_values($from[$f-1], $to[$t-1], $state->cmp) === namespace\_DIFF_MATCH_FULL)
                {
                    if ($f === 1 && $t === 1)
                    {
                        $pos = _DiffPosition::START;
                    }
                    elseif ($f === $edit->flen && $t === $edit->tlen)
                    {
                        $pos = _DiffPosition::END;
                    }
                    else
                    {
                        $pos = _DiffPosition::MIDDLE;
                    }

                    --$f;
                    --$t;
                    namespace\_copy_value($state->diff, $from[$f], $show_key, $pos);
                }
                elseif ($m[$f-1][$t] < $m[$f][$t] && $m[$f][$t-1] < $m[$f][$t])
                {
                    --$f;
                    --$t;
                    namespace\_diff_values($from[$f], $to[$t], $state);
                }
                elseif ($m[$f][$t-1] >= $m[$f-1][$t])
                {
                    if ($t === 1)
                    {
                        $pos = _DiffPosition::START;
                    }
                    elseif ($t === $edit->tlen)
                    {
                        $pos = _DiffPosition::END;
                    }
                    else
                    {
                        $pos = _DiffPosition::MIDDLE;
                    }

                    --$t;
                    namespace\_insert_value($state->diff, $to[$t], $show_key, $pos);
                }
                else
                {
                    if ($f === 1)
                    {
                        $pos = _DiffPosition::START;
                    }
                    elseif ($f === $edit->flen)
                    {
                        $pos = _DiffPosition::END;
                    }
                    else
                    {
                        $pos = _DiffPosition::MIDDLE;
                    }

                    --$f;
                    namespace\_delete_value($state->diff, $from[$f], $show_key, $pos);
                }
            }
            elseif ($f)
            {
                if ($f === 1)
                {
                    $pos = _DiffPosition::START;
                }
                elseif ($f === $edit->flen)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }

                --$f;
                namespace\_delete_value($state->diff, $from[$f], $show_key, $pos);
            }
            else
            {
                if ($t === 1)
                {
                    $pos = _DiffPosition::START;
                }
                elseif ($t === $edit->tlen)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }

                --$t;
                namespace\_insert_value($state->diff, $to[$t], $show_key, $pos);
            }
        }
    }
    elseif (($state->cmp === namespace\DIFF_GREATER) || ($state->cmp === namespace\DIFF_GREATER_EQUAL))
    {
        $flen = \count($from);
        $tlen = \count($to);

        $i = 0;
        $max = \max($flen, $tlen) - 1;
        $pos = _DiffPosition::START;

        for ($t = 0; ($tlen - $t) > $flen; ++$t)
        {
            namespace\_insert_value($state->diff, $to[$t], $show_key, $pos);
            if (++$i == $max)
            {
                $pos = _DiffPosition::END;
            }
            else
            {
                $pos = _DiffPosition::MIDDLE;
            }
        }

        for ($f = 0; $f < $flen; ++$f, ++$t)
        {
            if ($from[$f]->value() < $to[$t]->value())
            {
                if ((($from[$f] instanceof _String) || ($from[$f] instanceof _Array) || ($from[$f] instanceof _Object)) && ($to[$t] instanceof $from[$f]))
                {
                    namespace\_diff_values($from[$f], $to[$t], $state);
                }
                else
                {
                    namespace\_delete_greater_value($state->diff, $from[$f], $show_key, $pos);
                    namespace\_insert_lesser_value($state->diff, $to[$t], $show_key, $pos);
                }

                if (++$i == $max)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }
                break;
            }
            else
            {
                \assert($from[$f]->value() == $to[$t]->value());
                if ($state->cmp === namespace\DIFF_GREATER)
                {
                    namespace\_diff_values($from[$f], $to[$t], $state);
                }
                else
                {
                    namespace\_copy_value($state->diff, $from[$f], $show_key, $pos);
                }

                if (++$i == $max)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }
            }
        }

        for (++$f ; $f < $flen; ++$f)
        {
            namespace\_copy_value($state->diff, $from[$f], $show_key, $pos);
            if (++$i == $max)
            {
                $pos = _DiffPosition::END;
            }
            else
            {
                $pos = _DiffPosition::MIDDLE;
            }
        }
    }
    else
    {
        \assert(($state->cmp === namespace\DIFF_LESS) || ($state->cmp === namespace\DIFF_LESS_EQUAL));

        $flen = \count($from);
        $tlen = \count($to);

        $i = 0;
        $max = \max($flen, $tlen) - 1;
        $pos = _DiffPosition::START;

        for ($f = 0; ($flen - $f) > $tlen; ++$f)
        {
            namespace\_delete_value($state->diff, $from[$f], $show_key, $pos);
            if (++$i == $max)
            {
                $pos = _DiffPosition::END;
            }
            else
            {
                $pos = _DiffPosition::MIDDLE;
            }
        }

        for ($t = 0; $t < $tlen; ++$t, ++$f)
        {
            if ($from[$f]->value() > $to[$t]->value())
            {
                if ((($from[$f] instanceof _String) || ($from[$f] instanceof _Array) || ($from[$f] instanceof _Object)) && ($to[$t] instanceof $from[$f]))
                {
                    namespace\_diff_values($from[$f], $to[$t], $state);
                }
                else
                {
                    namespace\_delete_lesser_value($state->diff, $from[$f], $show_key, $pos);
                    namespace\_insert_greater_value($state->diff, $to[$t], $show_key, $pos);
                }

                if (++$i == $max)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }

                break;
            }
            else
            {
                \assert($from[$f]->value() == $to[$t]->value());
                if ($state->cmp === namespace\DIFF_LESS)
                {
                    namespace\_diff_values($from[$f], $to[$t], $state);
                }
                else
                {
                    namespace\_copy_value($state->diff, $from[$f], $show_key, $pos);
                }

                if (++$i == $max)
                {
                    $pos = _DiffPosition::END;
                }
                else
                {
                    $pos = _DiffPosition::MIDDLE;
                }
            }
        }

        for (++$f ; $f < $flen; ++$f)
        {
            namespace\_copy_value($state->diff, $from[$f], $show_key, $pos);
            if (++$i == $max)
            {
                $pos = _DiffPosition::END;
            }
            else
            {
                $pos = _DiffPosition::MIDDLE;
            }
        }
    }
}



/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _copy_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_copy_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _insert_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_insert_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _insert_greater_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_insert_greater_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _insert_lesser_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_insert_lesser_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _delete_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_delete_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _delete_greater_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_delete_greater_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param bool $show_key
 * @param _DiffPosition::* $pos
 * @return void
 */
function _delete_lesser_value(array &$diff, _Value $value, $show_key, $pos = _DiffPosition::NONE)
{
    namespace\_delete_lesser_string($diff, $value->format_value($pos, $show_key));
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _copy_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffCopy($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _insert_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffInsert($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _insert_greater_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffInsertGreater($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _insert_lesser_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffInsertLess($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _delete_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffDelete($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _delete_greater_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffDeleteGreater($v);
    }
}


/**
 * @param string[] $diff
 * @param string $string
 * @return void
 */
function _delete_lesser_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffDeleteLess($v);
    }
}


/**
 * @param _DiffOperation[] $diff
 * @param string $from_name
 * @param string $to_name
 * @param int $cmp
 * @return string
 */
function _format_diff(array $diff, $from_name, $to_name, $cmp)
{
    if (($cmp === namespace\DIFF_IDENTICAL) || ($cmp === namespace\DIFF_EQUAL))
    {
        $result = "- $from_name\n+ $to_name\n";
    }
    elseif (($cmp === namespace\DIFF_GREATER) || ($cmp === namespace\DIFF_GREATER_EQUAL))
    {
        $result = " > $from_name\n+< $to_name\n";
    }
    else
    {
        \assert(($cmp === namespace\DIFF_LESS) || ($cmp === namespace\DIFF_LESS_EQUAL));
        $result = "-< $from_name\n > $to_name\n";
    }

    $prev_operation = null;
    $prev_operations = array();
    if (($cmp === namespace\DIFF_IDENTICAL) || ($cmp === namespace\DIFF_EQUAL))
    {
        for ($i = \count($diff) - 1; $i >= 0; --$i)
        {
            $operation = $diff[$i];

            if ($operation instanceof _DiffCopy)
            {
                if ($prev_operations)
                {
                    foreach ($prev_operations as $prev)
                    {
                        $result .= "\n$prev";
                    }
                    $prev_operations = array();
                }
                $result .= "\n$operation";
            }
            elseif($operation instanceof _DiffDelete)
            {
                $result .= "\n$operation";
            }
            else
            {
                \assert($operation instanceof _DiffInsert);
                $prev_operations[] = $operation;
                $prev_operation = $operation;
            }
        }
    }
    else
    {
        foreach($diff as $operation)
        {
            if ($operation->operation() === _DiffOperation::COPY)
            {
                if ($prev_operations)
                {
                    foreach ($prev_operations as $prev)
                    {
                        $result .= "\n$prev";
                    }
                    $prev_operations = array();
                }
                $result .= "\n$operation";
            }
            elseif($operation->operation() === _DiffOperation::DELETE)
            {
                if ($prev_operations
                    && ((($operation instanceof _DiffDeleteGreater) && !($prev_operation instanceof _DiffInsertLess))
                        || (($operation instanceof _DiffDeleteLess) && !($prev_operation instanceof _DiffInsertGreater))))
                {
                    foreach ($prev_operations as $prev)
                    {
                        $result .= "\n$prev";
                    }
                    $prev_operations = array();
                    $prev_operation = null;
                }
                $result .= "\n$operation";
            }
            else
            {
                \assert($operation->operation() === _DiffOperation::INSERT);
                $prev_operations[] = $operation;
                $prev_operation = $operation;
            }
        }
    }

    foreach ($prev_operations as $prev)
    {
        $result .= "\n$prev";
    }

    return $result;
}
