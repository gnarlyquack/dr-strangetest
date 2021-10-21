<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace strangetest;

/**
 * @api
 * @param mixed $from
 * @param mixed $to
 * @param string $from_name
 * @param string $to_name
 * @param bool $loose
 * @return string
 */
function diff(&$from, &$to, $from_name, $to_name, $loose = false)
{
    if ($from_name === $to_name)
    {
        throw new \Exception('Parameters $from_name and $to_name must be different');
    }

    $state = new _DiffState($loose);

    namespace\_diff_values(
        namespace\_process_value($state, $from_name, new _NullKey(), $from),
        namespace\_process_value($state, $to_name, new _NullKey(), $to),
        $state
    );

    $diff = \implode("\n", \array_reverse($state->diff));
    return "- $from_name\n+ $to_name\n\n$diff";
}


final class _DiffState extends struct {
    /** @var bool */
    public $loose;

    /** @var string[] */
    public $diff = array();

    /** @var array{'byval': mixed[], 'byref': mixed[]} */
    public $seen = array('byval' => array(), 'byref' => array());

    /** @var array{'byref': null, 'byval': \stdClass} */
    public $sentinels;

    /**
     * @param bool $loose
     */
    public function __construct($loose)
    {
        $this->loose = $loose;
        $this->sentinels = array('byref' => null, 'byval' => new \stdClass());
    }
}



const _FORMAT_INDENT = '    ';


final class _DiffCopy {
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


final class _DiffInsert {
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


final class _DiffDelete {
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


final class _DiffPosition {
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


final class _ValueType {
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
     * @param bool $loose
     * @param int $indent_level
     * @param int $cost
     * @param _Value[] $subvalues
     */
    public function __construct($name, _Key $key, &$value, $loose, $indent_level, $cost, array $subvalues)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->loose = $loose;
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
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\_format_array($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
    {
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        return "{$indent}{$key}array(";
    }

    /**
     * @return string
     */
    public function end_value()
    {
        $indent = namespace\_format_indent($this->indent_level);
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
     * @param bool $loose
     * @param int $indent_level
     * @param int $cost
     * @param _Value[] $subvalues
     */
    public function __construct($name, _Key $key, &$value, $loose, $indent_level, $cost, array $subvalues)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->loose = $loose;
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
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $seen = array('byval' => array(), 'byref' => array());
        $sentinels = array('byref' => null, 'byval' => new \stdClass());
        $result = namespace\_format_object($this->value, $this->name, $this->loose, $seen, $sentinels, $indent);
        return "{$indent}{$key}{$result}{$line_end}";
    }

    /**
     * @return string
     */
    public function start_value()
    {
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $class = namespace\_format_object_start($this->value, $this->loose);
        return "{$indent}{$key}{$class}";
    }

    /**
     * @return string
     */
    public function end_value()
    {
        $indent = namespace\_format_indent($this->indent_level);
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
        $indent = namespace\_format_indent($this->indent_level);
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
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\_format_resource($this->value);
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
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\_format_scalar($this->value);
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
        $indent = namespace\_format_indent($this->indent_level);
        $key = $this->key->format_key();
        $line_end = $this->key->line_end();

        $result = namespace\_format_scalar($this->value);
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
        $indent = namespace\_format_indent($this->indent_level);
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
    $reference = namespace\_check_reference($value, $name, $state->seen, $state->sentinels);
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
        if ($state->loose)
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
        return new _Array($name, $key, $value, $state->loose, $indent_level, $cost, $subvalues);
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
        return new _Object($name, $key, $value, $state->loose, $indent_level, $cost, $subvalues);
    }

    return new _Scalar($key, $value, $indent_level);
}


/**
 * @return void
 */
function _diff_values(_Value $from, _Value $to, _DiffState $state)
{
    if (namespace\_lcs_values($from, $to, $state->loose, $lcs))
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
        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $state->loose);
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);
    }
    else
    {
        namespace\_copy_string($state->diff, $from->end_value());

        $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $state->loose);
        namespace\_build_diff_from_edit($from->subvalues(), $to->subvalues(), $edit, $state);

        if (($from->key() === $to->key())
            && ($state->loose || (_ValueType::ARRAY_ === $from->type())))
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
 * @param bool $loose
 * @param int $lcs
 * @return bool
 */
function _lcs_values(_Value $from, _Value $to, $loose, &$lcs)
{
    if (namespace\_compare_values($from, $to, $loose))
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
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $loose);
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
                $edit = namespace\_lcs_array($from->subvalues(), $to->subvalues(), $loose);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }
    }
    return false;
}


/**
 * @param _Value[] $from
 * @param _Value[] $to
 * @param bool $loose
 * @return _Edit
 */
function _lcs_array(array $from, array $to, $loose)
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
            if (namespace\_lcs_values($fvalue, $tvalue, $loose, $lcs))
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
 * @param bool $loose
 * @return bool
 */
function _compare_values(_Value $from, _Value $to, $loose)
{
    $result = $from->key() === $to->key();
    if ($result)
    {
        if ($loose)
        {
            $result = $from->value() == $to->value();
        }
        else
        {
            $result = $from->value() === $to->value();
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
            if (namespace\_compare_values($from[$f-1], $to[$t-1], $state->loose))
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


/**
 * @param int $indent_level
 * @return string
 */
function _format_indent($indent_level)
{
    return \str_repeat(namespace\_FORMAT_INDENT, $indent_level);
}


/**
 * @api
 * @param ?string $assertion
 * @param ?string $description
 * @param ?string $detail
 * @return string
 */
function format_failure_message($assertion, $description = null, $detail = null)
{
    $message = array();
    if ($assertion)
    {
        $message[] = $assertion;
    }
    if ($description)
    {
        $message[] = $description;
    }
    if (!$message)
    {
        $message[] = 'Assertion failed';
    }
    if ($detail)
    {
        $message[] = '';
        $message[] = $detail;
    }
    return \implode("\n", $message);
}



/**
 * Format a string representation of a variable.
 *
 * This provides more readable(?) formatting of variables than PHP's built-in
 * variable-printing functions (print_r(), var_dump(), var_export()) and also
 * handles recursive references.
 *
 * @api
 * @param mixed $variable The variable to format. $variable is received as a
 *                        reference in order to detect self-referencing values.
 * @return string A string representation of $var
 */
function format_variable(&$variable)
{
    $name = \is_object($variable) ? \get_class($variable) : \gettype($variable);
    $seen = array('byval' => array(), 'byref' => array());
    // We'd really like to make this a constant static variable, but PHP won't
    // let us do that with an object instance. As a mitigation, we'll just
    // create the sentinels once at the start and then pass it around
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    return namespace\_format_recursive_variable($variable, $name, false, $seen, $sentinels, '');
}


/**
 * @param mixed $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $indent
 * @return string
 */
function _format_recursive_variable(&$var, $name, $loose, &$seen, $sentinels, $indent)
{
    $reference = namespace\_check_reference($var, $name, $seen, $sentinels);
    if ($reference)
    {
        return $reference;
    }
    if (\is_scalar($var) || null === $var)
    {
        return namespace\_format_scalar($var);
    }
    if (\is_resource($var))
    {
        return namespace\_format_resource($var);
    }
    if (\is_array($var))
    {
        return namespace\_format_array($var, $name, $loose, $seen, $sentinels, $indent);
    }
    if (\is_object($var))
    {
        return namespace\_format_object($var, $name, $loose, $seen, $sentinels, $indent);
    }
    throw new \Exception(
        \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
    );
}


/**
 * @param scalar $var
 * @return string
 */
function _format_scalar(&$var)
{
    return \var_export($var, true);
}


/**
 * @param resource $var
 * @return string
 */
function _format_resource(&$var)
{
    return \sprintf(
        '%s of type "%s"',
        \print_r($var, true),
        \get_resource_type($var)
    );
}


/**
 * @template T
 * @param T[] $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $padding
 * @return string
 */
function _format_array(array &$var, $name, $loose, &$seen, $sentinels, $padding)
{
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    if ($var)
    {
        foreach ($var as $key => &$value)
        {
            $key = \var_export($key, true);
            $out .= \sprintf(
                "\n%s%s => %s,",
                $indent,
                $key,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
                    $loose,
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    return "array($out)";
}


/**
 * @param object $var
 * @param string $name
 * @param bool $loose
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @param string $padding
 * @return string
 */
function _format_object(&$var, $name, $loose, &$seen, $sentinels, $padding)
{
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    $start = namespace\_format_object_start($var, $loose, $class);
    $values = (array)$var;
    if ($values)
    {
        foreach ($values as $key => &$value)
        {
            // Object properties are cast to array keys as follows:
            //     public    $property -> "property"
            //     protected $property -> "\0*\0property"
            //     private   $property -> "\0class\0property"
            //         where "class" is the name of the class where the
            //         property is declared
            $key = \explode("\0", $key);
            $property = '$' . \array_pop($key);
            if ($key && $key[1] !== '*' && $key[1] !== $class)
            {
                $property = "$key[1]::$property";
            }
            $out .= \sprintf(
                "\n%s%s = %s;",
                $indent,
                $property,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $loose,
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    $end = namespace\_format_object_end();
    return "{$start}{$out}{$end}";
}


/**
 * @param object $object
 * @param bool $loose
 * @param ?string $class
 * @return string
 */
function _format_object_start(&$object, $loose, &$class = null)
{
    $class = \get_class($object);
    if (!$loose)
    {
        // @bc 7.1 use spl_object_hash instead of spl_object_id
        $id = \function_exists('spl_object_id')
            ? \spl_object_id($object)
            : \spl_object_hash($object);
        return "$class #$id {";
    }
    return "$class {";
}


/**
 * @return string
 */
function _format_object_end()
{
    return '}';
}


// Check if $var is a reference to another value in $seen.
//
// If $var is normally pass-by-value, then it can only be an explicit
// reference. If it's normally pass-by-reference, then it can either be an
// object reference or an explicit reference. Explicit references are
// marked with the reference operator, i.e., '&'.
//
// Since PHP has no built-in way to determine if a variable is a reference,
// references are identified using jank wherein $var is changed and $seen
// is checked for an equivalent change.
/**
 * @param mixed $var
 * @param string $name
 * @param array{'byval': mixed[], 'byref': mixed[]} $seen
 * @param array{'byref': null, 'byval': \stdClass} $sentinels
 * @return false|string
 */
function _check_reference(&$var, $name, &$seen, $sentinels)
{
    if (\is_scalar($var) || \is_array($var) || null === $var)
    {
        $copy = $var;
        $var = $sentinels['byval'];
        $reference = \array_search($var, $seen['byval'], true);
        if (false === $reference)
        {
            $seen['byval'][$name] = &$var;
        }
        else
        {
            $reference = "&$reference";
        }
        $var = $copy;
    }
    else
    {
        $reference = \array_search($var, $seen['byref'], true);
        if (false === $reference)
        {
            $seen['byref'][$name] = &$var;
        }
        else
        {
            \assert(\is_string($reference));
            \assert(\strlen($reference) > 0);
            $copy = $var;
            $var = $sentinels['byref'];
            if ($var === $seen['byref'][$reference])
            {
                $reference = "&$reference";
            }
            $var = $copy;
        }
    }
    return $reference;
}



/**
 * @param mixed[] $array
 * @param mixed[] $seen
 * @return void
 */
function ksort_recursive(&$array, &$seen = array())
{
    if (!\is_array($array))
    {
        return;
    }

    /* Prevent infinite recursion for arrays with recursive references. */
    $temp = $array;
    $array = null;
    $sorted = \in_array($array, $seen, true);
    $array = $temp;
    unset($temp);

    if (false !== $sorted)
    {
        return;
    }
    $seen[] = &$array;
    \ksort($array);
    foreach ($array as &$value)
    {
        namespace\ksort_recursive($value, $seen);
    }
}
