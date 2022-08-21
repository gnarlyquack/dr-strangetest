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
const DIFF_GREATER = 4;
const DIFF_GREATER_EQUAL = 5;
const DIFF_LESS = 2;
const DIFF_LESS_EQUAL = 3;


/**
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

    $diff = \implode("\n", \array_reverse($state->diff));
    return "- $from_name\n+ $to_name\n\n$diff";
}


final class _DiffState extends struct {
    /** @var int */
    public $cmp;

    /** @var string[] */
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



final class _DiffCopy extends struct {
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return "  {$this->value}";
    }
}


final class _DiffInsert extends struct {
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "+ {$this->value}";
    }
}


final class _DiffDelete extends struct {
    /** @var string */
    public $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "- {$this->value}";
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
}


final class _NullKey extends struct implements _Key {
    /**
     * @return null;
     */
    public function key()
    {
        return null;
    }

    /**
     * @return ''
     */
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
}


final class _ArrayIndex extends struct implements _Key {
    /** @var int|string */
    private $index;

    /**
     * @param int|string $index
     */
    public function __construct($index)
    {
        $this->index = $index;
    }

    /**
     * @return int|string
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @return string
     */
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
}


final class _PropertyName extends struct implements _Key {
    /** @var int|string */
    private $name;

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

    /**
     * @return string
     */
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
     * @return string
     */
    public function format_value($pos);

    /**
     * @return string
     */
    public function start_value();

    /**
     * @return string
     */
    public function end_value();
}


final class _Array extends struct implements _Value {
    /** @var string */
    private $name;

    /** @var _Key */
    private $key;

    /** @var mixed[] */
    private $value;

    /** @var bool */
    private $loose;

    /** @var int */
    private $indent_level;

    /** @var int */
    private $cost;

    /** @var _Value[] */
    private $subvalues;

    /**
     * @param string $name
     * @param _Key $key
     * @param mixed[] $value
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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\format_array($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
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
    private $name;

    /** @var _Key */
    private $key;

    /** @var object */
    private $value;

    /** @var bool */
    private $loose;

    /** @var int */
    private $indent_level;

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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\format_object($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
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
    private $name;

    /** @var _Key */
    private $key;

    /** @var mixed */
    private $value;

    /** @var int */
    private $indent_level;

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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = $this->name;
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
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
    /** @var _Key */
    private $key;

    /** @var resource */
    private $value;

    /** @var int */
    private $indent_level;

    /**
     * @param _Key $key
     * @param resource $value
     * @param int $indent_level
     */
    public function __construct(_Key $key, &$value, $indent_level)
    {
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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\format_resource($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
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
    /** @var _Key */
    private $key;

    /** @var bool|float|int|null */
    private $value;

    /** @var int */
    private $indent_level;

    /**
     * @param _Key $key
     * @param bool|float|int|null $value
     * @param int $indent_level
     */
    public function __construct(_Key $key, &$value, $indent_level)
    {
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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\format_scalar($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
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
    /** @var _Key */
    private $key;

    /** @var string */
    private $value;

    /** @var int */
    private $indent_level;

    /** @var int */
    private $cost;

    /** @var _StringPart[] */
    private $subvalues;

    /**
     * @param _Key $key
     * @param string $value
     * @param int $indent_level
     * @param int $cost
     * @param _StringPart[] $subvalues
     */
    public function __construct(_Key $key, &$value, $indent_level, $cost, array $subvalues)
    {
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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\format_scalar($this->value);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
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
    private $key;

    /** @var string */
    private $value;

    /** @var int */
    private $indent_level;

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

    /**
     * @param _DiffPosition::* $pos
     * @return string
     */
    public function format_value($pos)
    {
        $indent = namespace\format_indent($this->indent_level);
        $key = $this->key->format_key();
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

    /**
     * @return string
     */
    public function start_value()
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
        return new _Resource($key, $value, $indent_level);
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
        return new _String($key, $value, $indent_level, $cost, $subvalues);
    }

    if (\is_array($value))
    {
        if ($state->cmp !== namespace\DIFF_IDENTICAL)
        {
            \ksort($value);
        }
        $cost = 1;
        $subvalues = array();
        foreach ($value as $k => &$v)
        {
            $subname = \sprintf('%s[%s]', $name, \var_export($k, true));
            $subkey = new _ArrayIndex($k);
            $subvalue = namespace\_process_value($state, $subname, $subkey, $v, $indent_level + 1);
            $cost += $subvalue->cost();
            $subvalues[] = $subvalue;
        }
        return new _Array($name, $key, $value, $state->cmp, $indent_level, $cost, $subvalues);
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

    return new _Scalar($key, $value, $indent_level);
}


/**
 * @return void
 */
function _diff_values(_Value $from, _Value $to, _DiffState $state)
{
    if (namespace\_lcs_values($from, $to, $state->cmp, $lcs))
    {
        namespace\_copy_value($state->diff, $from);
    }
    elseif (0 === $lcs)
    {
        namespace\_insert_value($state->diff, $to);
        namespace\_delete_value($state->diff, $from);
    }
    elseif (_ValueType::STRING === $from->type())
    {
        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $state->cmp);
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);
    }
    else
    {
        namespace\_copy_string($state->diff, $from->end_value());

        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $state->cmp);
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);

        if (($from->key() === $to->key())
            && (($state->cmp !== namespace\DIFF_IDENTICAL)
                || (_ValueType::ARRAY_ === $from->type())))
        {
            namespace\_copy_string($state->diff, $from->start_value());
        }
        else
        {
            namespace\_insert_string($state->diff, $to->start_value());
            namespace\_delete_string($state->diff, $from->start_value());
        }
    }
}


/**
 * @param int $cmp
 * @param int $lcs
 * @return bool
 */
function _lcs_values(_Value $from, _Value $to, $cmp, &$lcs)
{
    if (namespace\_compare_values($from, $to, $cmp))
    {
        $lcs = \max($from->cost(), $to->cost());
        return true;
    }

    $lcs = 0;
    if ($from->type() === $to->type())
    {
        if (_ValueType::STRING === $from->type())
        {
            if (($from->cost() > 1) || ($to->cost() > 1))
            {
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $cmp);
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            (_ValueType::ARRAY_ === $from->type())
            || ((_ValueType::OBJECT === $from->type())
                && (\get_class($from->value()) === \get_class($to->value()))))
        {
            $lcs = 1;
            if (($from->cost() > 1) && ($to->cost() > 1))
            {
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $cmp);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }
    }
    return false;
}


/**
 * @param _Value[] $from
 * @param _Value[] $to
 * @param int $cmp
 * @return _Edit
 */
function _lcs_array(array $from, array $to, $cmp)
{
    $m = array();
    $flen = \count($from);
    $tlen = \count($to);

    for ($f = 0; $f <= $flen; ++$f)
    {
        for ($t = 0; $t <= $tlen; ++$t)
        {
            if (!$f || !$t)
            {
                $m[$f][$t] = 0;
                continue;
            }

            $fvalue = $from[$f-1];
            $tvalue = $to[$t-1];
            if (namespace\_lcs_values($fvalue, $tvalue, $cmp, $lcs))
            {
                $lcs += $m[$f-1][$t-1];
            }
            else
            {
                $max = \max($m[$f-1][$t], $m[$f][$t-1]);
                if ($lcs)
                {
                    $max = \max($max, $m[$f-1][$t-1] + $lcs);
                }
                $lcs = $max;
            }
            $m[$f][$t] = $lcs;
        }
    }
    return new _Edit($flen, $tlen, $m);
}


/**
 * @param int $cmp
 * @return bool
 */
function _compare_values(_Value $from, _Value $to, $cmp)
{
    $result = $from->key() === $to->key();
    if ($result)
    {
        if ($cmp === namespace\DIFF_IDENTICAL)
        {
            $result = $from->value() === $to->value();
        }
        elseif ($cmp === namespace\DIFF_EQUAL)
        {
            $result = $from->value() == $to->value();
        }
        elseif ($cmp === namespace\DIFF_GREATER)
        {
            $result = $from->value() > $to->value();
        }
        elseif ($cmp === namespace\DIFF_GREATER_EQUAL)
        {
            $result = $from->value() >= $to->value();
        }
        elseif ($cmp === namespace\DIFF_LESS)
        {
            $result = $from->value() < $to->value();
        }
        elseif ($cmp === namespace\DIFF_LESS_EQUAL)
        {
            $result = $from->value() <= $to->value();
        }
        else
        {
            throw new InvalidCodePath(\sprintf("Unknown diff comparison: %s\n", namespace\format_variable($cmp)));
        }
    }
    return $result;
}


/**
 * @param _Value[] $from
 * @param _Value[] $to
 * @return void
 */
function _build_diff_from_edit(array $from, array $to, _Edit $edit, _DiffState $state)
{
    $m = $edit->m;
    $f = $edit->flen;
    $t = $edit->tlen;

    while ($f || $t)
    {
        if ($f > 0 && $t > 0)
        {
            if (namespace\_compare_values($from[$f-1], $to[$t-1], $state->cmp))
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
                namespace\_copy_value($state->diff, $from[$f], $pos);
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
                namespace\_insert_value($state->diff, $to[$t], $pos);
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
                namespace\_delete_value($state->diff, $from[$f], $pos);
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
            namespace\_delete_value($state->diff, $from[$f], $pos);
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
            namespace\_insert_value($state->diff, $to[$t], $pos);
        }
    }
}



/**
 * @param string[] $diff
 * @param _DiffPosition::* $pos
 * @return void
 */
function _copy_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE)
{
    namespace\_copy_string($diff, $value->format_value($pos));
}


/**
 * @param string[] $diff
 * @param _DiffPosition::* $pos
 * @return void
 */
function _insert_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE)
{
    namespace\_insert_string($diff, $value->format_value($pos));
}


/**
 * @param string[] $diff
 * @param _DiffPosition::* $pos
 * @return void
 */
function _delete_value(array &$diff, _Value $value, $pos = _DiffPosition::NONE)
{
    namespace\_delete_string($diff, $value->format_value($pos));
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
function _delete_string(array &$diff, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $diff[] = new _DiffDelete($v);
    }
}
