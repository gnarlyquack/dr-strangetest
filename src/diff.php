<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.


namespace strangetest;

// For reference, PHP's handling of how different types of values are compared
// is described at:
// https://www.php.net/manual/en/language.operators.comparison.php
const DIFF_IDENTICAL = 0;
const DIFF_EQUAL     = 0x1;
const DIFF_GREATER   = 0x2;
const DIFF_LESS      = 0x4;
// @bc 5.5 define run-time constants instead of using constant expressions
\define('strangetest\\DIFF_GREATER_EQUAL', namespace\DIFF_EQUAL | namespace\DIFF_GREATER);
\define('strangetest\\DIFF_LESS_EQUAL', namespace\DIFF_EQUAL | namespace\DIFF_LESS);


// results for unequal comparisons
const _DIFF_MATCH_NONE    = 0; // Elements have neither matching values nor matching keys
const _DIFF_MATCH_PARTIAL = 1; // Elements have matching values but nonmatching keys
const _DIFF_MATCH_FULL    = 2; // Elements have both matching values and keys


// results for unequal comparisons
const _DIFF_MATCH_ERROR = 0; // incomparable values
const _DIFF_MATCH_PASS  = 1; // match is unequal and satisfies condition
const _DIFF_MATCH_FAIL  = 2; // match is unequal and doesn't satisfy condition
const _DIFF_MATCH_EQUAL = 3;

const _DIFF_LINE_FROM = 0x1;
const _DIFF_LINE_TO   = 0x2;
// @bc 5.5 define run-time constant instead of using a constant expression
\define('strangetest\\_DIFF_LINE_BOTH', namespace\_DIFF_LINE_FROM | namespace\_DIFF_LINE_TO);


/**
 * @api
 * @param mixed $actual
 * @param mixed $expected
 * @param string $actual_name
 * @param string $expected_name
 * @param int $cmp
 * @return string
 */
function diff(&$actual, &$expected, $actual_name, $expected_name, $cmp = namespace\DIFF_IDENTICAL)
{
    if ($actual_name === $expected_name)
    {
        throw new \Exception('Parameters $actual_name and $expected_name must be different');
    }

    $state = new _DiffState($cmp);
    $fvalue = namespace\_process_value($state, $actual_name, new _Key, $actual);
    $tvalue = namespace\_process_value($state, $expected_name, new _Key, $expected);

    if (($cmp === namespace\DIFF_GREATER) || ($cmp === namespace\DIFF_GREATER_EQUAL))
    {
        namespace\_diff_unequal_values($state, $fvalue, $tvalue);
        $diff = new ListForwardIterator($state->diff);
    }
    elseif (($cmp === namespace\DIFF_LESS) || ($cmp === namespace\DIFF_LESS_EQUAL))
    {
        namespace\_diff_unequal_values($state, $fvalue, $tvalue);
        $diff = new ListForwardIterator($state->diff);
    }
    else
    {
        namespace\_diff_equal_values($state, $fvalue, $tvalue);
        $diff = new ListReverseIterator($state->diff);
    }

    $result = namespace\_format_diff($diff, $actual_name, $expected_name, $cmp);
    return $result;
}


final class _DiffState extends struct
{
    /** @var int */
    public $cmp;

    /** @var array<string, array<string, _Edit>> */
    public $matrix_cache = array();

    /** @var _DiffOperation[] */
    public $diff = array();

    /** @var ReferenceChecker */
    public $references;


    /** @var VariableFormatter */
    public $formatter;

    /**
     * @param int $cmp
     */
    public function __construct($cmp)
    {
        $this->cmp = $cmp;
        $this->references = new ReferenceChecker;
        $this->formatter = new VariableFormatter(
            $cmp === namespace\DIFF_IDENTICAL,
            $this->references);
    }
}


abstract class _DiffOperation extends struct
{
    const DELETE         = 0;
    const DELETE_UNEQUAL = 1;
    const INSERT_UNEQUAL = 2;
    const INSERT         = 3;
    const COPY           = 4;

    /** @var string */
    public $value;

    /** @var _DiffOperation::* */
    public $type;

    abstract public function __toString();
}


final class _DiffCopy extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::COPY;
    }

    public function __toString()
    {
        return "  {$this->value}";
    }
}


final class _DiffInsert extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::INSERT;
    }

    public function __toString()
    {
        return "+ {$this->value}";
    }
}


final class _DiffInsertGreater extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::INSERT_UNEQUAL;
    }

    public function __toString()
    {
        return "> {$this->value}";
    }
}


final class _DiffInsertLess extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::INSERT_UNEQUAL;
    }

    public function __toString()
    {
        return "< {$this->value}";
    }
}


final class _DiffDelete extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::DELETE;
    }

    public function __toString()
    {
        return "- {$this->value}";
    }
}


final class _DiffDeleteGreater extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::DELETE_UNEQUAL;
    }

    public function __toString()
    {
        return "> {$this->value}";
    }
}


final class _DiffDeleteLess extends _DiffOperation
{
    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
        $this->type = self::DELETE_UNEQUAL;
    }

    public function __toString()
    {
        return "< {$this->value}";
    }
}



final class _Edit extends struct
{
    /** @var _Value[] */
    public $fvalues;

    /** @var _Value[] */
    public $tvalues;

    /** @var int */
    public $flen;

    /** @var int */
    public $tlen;

    /** @var array<int, int[]> */
    public $m;

    /**
     * @param _Value[] $fvalues
     * @param _Value[] $tvalues
     * @param int $flen
     * @param int $tlen
     * @param array<int, int[]> $m
     */
    public function __construct(array $fvalues, array $tvalues, $flen, $tlen, $m)
    {
        $this->fvalues = $fvalues;
        $this->tvalues = $tvalues;
        $this->flen = $flen;
        $this->tlen = $tlen;
        $this->m = $m;
    }
}



final class _Key extends struct
{
    const TYPE_NONE     = 0; // not actually a key
    const TYPE_INDEX    = 1; // array index
    const TYPE_PROPERTY = 2; // object property

    /** @var _Key::TYPE_* */
    public $type = self::TYPE_NONE;

    /** @var null|int|string */
    public $value = null;

    /** @var bool */
    public $in_list = false;

    /** @var string */
    public $formatted = '';

    /** @var string */
    public $line_end = '';
}



final class _Value extends struct
{
    const TYPE_ARRAY = 1;
    const TYPE_OBJECT = 2;
    const TYPE_REFERENCE = 3;
    const TYPE_SCALAR = 4;
    const TYPE_STRING = 5;
    const TYPE_STRING_PART = 6;
    const TYPE_RESOURCE = 7;

    /** @var _Value::TYPE_* */
    public $type;

    /** @var string */
    public $name;

    /** @var _Key */
    public $key;

    /** @var int */
    public $index;

    /** @var mixed */
    public $value;

    /** @var int */
    public $indent_level;

    /** @var int */
    public $cost;

    /** @var _Value[] */
    public $subvalues = array();

    /** @var array<mixed, int> */
    public $keys = array();

    /** @var bool */
    public $is_list = false;

    /**
     * @return null|int|string
     */
    public function key()
    {
        if (($this->type === _Value::TYPE_STRING_PART) && $this->index)
        {
            return null;
        }
        else
        {
            return $this->key->value;
        }
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
    $result = new _Value;
    $reference = $state->references->check_variable($value, $name);

    if ($reference)
    {
        $result->type = _Value::TYPE_REFERENCE;
        $result->name = $reference;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;
        $result->cost = 1;
    }
    elseif (\is_resource($value))
    {
        $result->type = _Value::TYPE_RESOURCE;
        $result->name = $name;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;
        $result->cost = 1;
    }
    elseif (\is_string($value))
    {
        $result->type = _Value::TYPE_STRING;
        $result->name = $name;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;

        $lines = \explode("\n", $value);
        foreach ($lines as $i => &$line)
        {
            $string = new _Value;
            $string->type = _Value::TYPE_STRING_PART;
            $string->key = $key;
            $string->value = &$line;
            $string->indent_level = $indent_level;
            $string->index = $i;
            $string->cost = 1;

            ++$result->cost;
            $result->subvalues[] = $string;
        }
    }
    elseif (\is_array($value))
    {
        if ($state->cmp !== namespace\DIFF_IDENTICAL)
        {
            \ksort($value);
        }

        $result->type =  _Value::TYPE_ARRAY;
        $result->name = $name;
        $result->is_list = true;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;
        $result->cost = 1;

        $index = 0;
        foreach ($value as $k => &$v)
        {
            $result->is_list = $result->is_list && ($k === $index);
            $result->keys[$k] = $index++;

            $subkey = new _Key;
            $subkey->type = _Key::TYPE_INDEX;
            $subkey->value = $k;
            $subkey->in_list =& $result->is_list;
            $subkey->formatted = namespace\format_array_index($k, $formatted);
            $subkey->line_end = VariableFormatter::ARRAY_ELEMENT_SEPARATOR;

            $subname = \sprintf('%s[%s]', $name, $formatted);
            $subvalue = namespace\_process_value($state, $subname, $subkey, $v, $indent_level + 1);
            $result->cost += $subvalue->cost;
            $result->subvalues[] = $subvalue;
        }
    }
    elseif (\is_object($value))
    {
        $result->type = _Value::TYPE_OBJECT;
        $result->name = $name;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;
        $result->cost = 1;

        $class = \get_class($value);
        $cost = 1;
        $index = 0;
        // @bc 5.4 use variable for array cast in order to create references
        $values = (array)$value;
        foreach ($values as $k => &$v)
        {
            $result->keys[$k] = $index++;

            $subkey = new _Key;
            $subkey->type = _Key::TYPE_PROPERTY;
            $subkey->value = $k;
            $subkey->formatted = namespace\format_property($k, $class, $formatted);
            $subkey->line_end = VariableFormatter::PROPERTY_TERMINATOR;

            $subname = \sprintf('%s->%s', $name, $formatted);
            $subvalue = namespace\_process_value($state, $subname, $subkey, $v, $indent_level + 1);
            $result->cost += $subvalue->cost;
            $result->subvalues[] = $subvalue;
        }
    }
    else
    {
        $result->type = _Value::TYPE_SCALAR;
        $result->name = $name;
        $result->key = $key;
        $result->value = &$value;
        $result->indent_level = $indent_level;
        $result->cost = 1;
    }

    return $result;
}


/**
 * @return void
 */
function _diff_equal_values(_DiffState $state, _Value $from, _Value $to)
{
    $show_key = !$from->key->in_list || !$to->key->in_list;
    $cmp = namespace\_lcs_values($state, $from, $to);

    if ($cmp->matches === namespace\_DIFF_MATCH_FULL)
    {
        namespace\_copy_value($state, $from, $show_key, true);
    }
    elseif (0 === $cmp->lcs)
    {
        namespace\_insert_value($state, $to, $show_key, true);
        namespace\_delete_value($state, $from, $show_key, true);
    }
    // for $from and $to to not match but have an $lcs > 0, they must be
    // composite values of the same type with something in common
    elseif ($cmp->matches === namespace\_DIFF_MATCH_PARTIAL)
    {
        // Values match but the key is different, so we don't need to generate
        // a difference between the two values. Instead, we can just format one
        // of the values and then show that the keys are different
        $suffix = $from->key->line_end;
        $formatted = new FormatResult;
        if ($from->type === _Value::TYPE_ARRAY)
        {
            $state->formatter->format_array($formatted, $from->value, $from->name, $from->indent_level, '', $suffix);
        }
        elseif ($from->type === _Value::TYPE_OBJECT)
        {
            $state->formatter->format_object($formatted, $from->value, $from->name, $from->indent_level, '', $suffix);
        }
        else
        {
            $state->formatter->format_string($formatted, $from->value, '', $suffix);
        }
        $strings = $formatted->formatted;

        $indent = $state->formatter->format_indent($from->indent_level);
        $from_start = $indent . $from->key->formatted . $strings[0];
        $to_start = $indent . $to->key->formatted . $strings[0];

        for ($c = \count($strings), $i = $c - 1; $i > 0; --$i)
        {
            $state->diff[] = new _DiffCopy($strings[$i]);
        }
        $state->diff[] = new _DiffInsert($to_start);
        $state->diff[] = new _DiffDelete($from_start);
    }
    elseif ($from->type === _Value::TYPE_STRING)
    {
        // One of the strings has be multiline if they don't match but $lcs > 0
        \assert($to->type === _Value::TYPE_STRING);
        $edit = namespace\_lcs_array($state, $from, $to);
        namespace\_build_diff($state, $edit, $show_key);
    }
    else
    {
        \assert($from->type === $to->type);

        $state->diff[] = new _DiffCopy(namespace\_end_value($state->formatter, $from));

        $edit = namespace\_lcs_array($state, $from, $to);
        namespace\_build_diff($state, $edit, !$from->is_list || !$to->is_list);

        if ((!$show_key || ($from->key() === $to->key()))
            && (($state->cmp !== namespace\DIFF_IDENTICAL)
                || (_Value::TYPE_ARRAY === $from->type)))
        {
            $state->diff[] = new _DiffCopy(namespace\_start_value($state->formatter, $from, $show_key));
        }
        else
        {
            $state->diff[] = new _DiffInsert(namespace\_start_value($state->formatter, $to, $show_key));
            $state->diff[] = new _DiffDelete(namespace\_start_value($state->formatter, $from, $show_key));
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
 * @return _ComparisonResult
 */
function _lcs_values(_DiffState $state, _Value $from, _Value $to)
{
    $match = namespace\_compare_equal_values($from, $to, $state->cmp === namespace\DIFF_IDENTICAL);

    if ($match === namespace\_DIFF_MATCH_FULL)
    {
        $result = new _ComparisonResult;
        $result->matches = $match;
        $result->lcs = \max($from->cost, $to->cost);
        return $result;
    }
    else
    {
        $lcs = 0;
        if (($from->type === _Value::TYPE_STRING) && ($to->type === _Value::TYPE_STRING))
        {
            if (($from->cost > 1) || ($to->cost > 1))
            {
                $edit = namespace\_lcs_array($state, $from, $to);
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            (($from->type === _Value::TYPE_ARRAY) && ($to->type === _Value::TYPE_ARRAY))
            || (($from->type === _Value::TYPE_OBJECT) && ($to->type === _Value::TYPE_OBJECT) && ($from->value instanceof $to->value)))
        {
            $lcs = 1;
            if (($from->cost > 1) && ($to->cost > 1))
            {
                $edit = namespace\_lcs_array($state, $from, $to);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }

        $result = new _ComparisonResult;
        $result->matches = $match;
        $result->lcs = $lcs;
        return $result;
    }
}


/**
 * @return _Edit
 */
function _lcs_array(_DiffState $state, _Value $from, _Value $to)
{
    if (!isset($state->matrix_cache[$from->name][$to->name]))
    {
        $m = array();
        $fvalues = $from->subvalues;
        $tvalues = $to->subvalues;
        $flen = \count($fvalues);
        $tlen = \count($tvalues);

        for ($f = 0; $f <= $flen; ++$f)
        {
            for ($t = 0; $t <= $tlen; ++$t)
            {
                if (($f === 0) || ($t === 0))
                {
                    $m[$f][$t] = 0;
                    continue;
                }

                $fvalue = $fvalues[$f-1];
                $tvalue = $tvalues[$t-1];
                $result = namespace\_lcs_values($state, $fvalue, $tvalue);
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

        $edit = new _Edit($fvalues, $tvalues, $flen, $tlen, $m);
        $state->matrix_cache[$from->name][$to->name] = $edit;
    }

    $result = $state->matrix_cache[$from->name][$to->name];
    return $result;
}


/**
 * @param bool $strict
 * @return int
 */
function _compare_equal_values(_Value $from, _Value $to, $strict)
{
    $result = namespace\_DIFF_MATCH_NONE;

    if ($strict)
    {
        if ($from->value === $to->value)
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
        }
    }
    else
    {
        if ($from->value == $to->value)
        {
            $result = namespace\_DIFF_MATCH_PARTIAL;
        }
    }

    if ($result)
    {
        if (($from->key->in_list && $to->key->in_list)
            || (($from->key->type === $to->key->type) && ($from->key() === $to->key())))
        {
            $result = namespace\_DIFF_MATCH_FULL;
        }
    }

    return $result;
}


/**
 * @param bool $show_key
 * @return void
 */
function _build_diff(_DiffState $state, _Edit $edit, $show_key)
{
    $m = $edit->m;
    $from = $edit->fvalues;
    $to = $edit->tvalues;
    $f = $edit->flen;
    $t = $edit->tlen;

    while ($f || $t)
    {
        if ($f > 0 && $t > 0)
        {
            if (namespace\_compare_equal_values($from[$f-1], $to[$t-1], $state->cmp === namespace\DIFF_IDENTICAL) === namespace\_DIFF_MATCH_FULL)
            {
                --$f;
                --$t;
                $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_BOTH);
                namespace\_copy_value($state, $from[$f], $show_key, true, $pos);
            }
            elseif ($m[$f-1][$t] < $m[$f][$t] && $m[$f][$t-1] < $m[$f][$t])
            {
                --$f;
                --$t;
                namespace\_diff_equal_values($state, $from[$f], $to[$t]);
            }
            elseif ($m[$f][$t-1] >= $m[$f-1][$t])
            {
                --$t;
                $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_TO);
                namespace\_insert_value($state, $to[$t], $show_key, true, $pos);
            }
            else
            {
                --$f;
                $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_FROM);
                namespace\_delete_value($state, $from[$f], $show_key, true, $pos);
            }
        }
        elseif ($f)
        {
            --$f;
            $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_FROM);
            namespace\_delete_value($state, $from[$f], $show_key, true, $pos);
        }
        else
        {
            --$t;
            $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_TO);
            namespace\_insert_value($state, $to[$t], $show_key, true, $pos);
        }
    }
}


/**
 * @return bool
 */
function _diff_unequal_values(_DiffState $state, _Value $from, _Value $to)
{
    $show_key = !$from->key->in_list || !$to->key->in_list;

    $result = namespace\_compare_unequal_values($from, $to, (bool)($state->cmp & namespace\DIFF_GREATER));

    if (($result === _DIFF_MATCH_PASS)
        || (($result === _DIFF_MATCH_EQUAL) && ($state->cmp & namespace\DIFF_EQUAL)))
    {
        namespace\_copy_value($state, $from, $show_key, false);
    }
    elseif (($to->type === $from->type)
        && (($from->type === _Value::TYPE_STRING) || ($from->type === _Value::TYPE_ARRAY) || (($from->type === _Value::TYPE_OBJECT) && ($to->value instanceof $from->value))))
    {
        $fvalues = $from->subvalues;
        $tvalues = $to->subvalues;

        $flen = \count($fvalues);
        $tlen = \count($tvalues);
        $min = \min($flen, $tlen);

        if ($from->type === _Value::TYPE_STRING)
        {
            $equal = true;
            for ($f = 0, $t = 0; ($f < $min) && $equal; ++$f, ++$t)
            {
                $result = namespace\_compare_unequal_values($fvalues[$f], $tvalues[$t], (bool)($state->cmp & namespace\DIFF_GREATER));
                $equal = $result === _DIFF_MATCH_EQUAL;

                if ($equal && ($state->cmp & namespace\DIFF_EQUAL))
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_BOTH);
                    namespace\_copy_value($state, $fvalues[$f], $show_key, false, $pos);
                }
                else
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    namespace\_delete_unequal_value($state, $fvalues[$f], $show_key, $pos);
                    namespace\_insert_unequal_value($state, $tvalues[$t], $show_key, $pos);
                }
            }

            if ($equal && ($state->cmp & namespace\DIFF_EQUAL))
            {
                for ( ; $f < $flen; ++$f)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    namespace\_delete_value($state, $fvalues[$f], $show_key, false, $pos);
                }

                for ( ; $t < $tlen; ++$t)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_TO);
                    namespace\_insert_value($state, $tvalues[$t], $show_key, false, $pos);
                }
            }
            else
            {
                for ( ; $f < $flen; ++$f)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    namespace\_copy_value($state, $fvalues[$f], $show_key, false, $pos);
                }
            }
        }
        else
        {
            $show_subkey = ($from->type === _Value::TYPE_OBJECT) || !$from->is_list || !$to->is_list;

            $state->diff[] = new _DiffCopy(namespace\_start_value($state->formatter, $from, $show_key));

            $fkeys = $from->keys;
            $tkeys = $to->keys;
            $equal = true;
            foreach($fkeys as $key => $index)
            {
                $fvalue = $fvalues[$index];

                if (!isset($tkeys[$key]))
                {
                    namespace\_delete_value($state, $fvalue, $show_subkey, false);
                }
                elseif ($equal)
                {
                    $tvalue = $tvalues[$tkeys[$key]];
                    $equal = namespace\_diff_unequal_values($state, $fvalue, $tvalue);
                }
                else
                {
                    namespace\_copy_value($state, $fvalue, $show_subkey, false);
                }
                unset($tkeys[$key]);
            }

            foreach ($tkeys as $index)
            {
                namespace\_insert_value($state, $tvalues[$index], $show_subkey, false);
            }

            $state->diff[] = new _DiffCopy(namespace\_end_value($state->formatter, $from));
        }
    }
    elseif($result === _DIFF_MATCH_ERROR)
    {
        namespace\_delete_value($state, $from, $show_key, false);
        namespace\_insert_value($state, $to, $show_key, false);
    }
    else
    {
        namespace\_delete_unequal_value($state, $from, $show_key);
        namespace\_insert_unequal_value($state, $to, $show_key);
    }

    return $result === _DIFF_MATCH_EQUAL;
}


/**
 * @param bool $greater
 * @return _DIFF_MATCH_PASS|_DIFF_MATCH_FAIL|_DIFF_MATCH_EQUAL|_DIFF_MATCH_ERROR
 */
function _compare_unequal_values(_Value $from, _Value $to, $greater)
{
    if ($from->value > $to->value)
    {
        return $greater ? _DIFF_MATCH_PASS : _DIFF_MATCH_FAIL;
    }
    elseif ($from->value < $to->value)
    {
        return $greater ? _DIFF_MATCH_FAIL : _DIFF_MATCH_PASS;
    }
    elseif ($from->value != $to->value)
    {
        // incomparable values
        return _DIFF_MATCH_ERROR;
    }
    else
    {
        return _DIFF_MATCH_EQUAL;
    }
}


/**
 * @param int $f
 * @param int $t
 * @param int $flen
 * @param int $tlen
 * @param _DIFF_LINE_FROM|_DIFF_LINE_TO|_DIFF_LINE_BOTH $which
 * @return VariableFormatter::STRING_*
 */
function _get_line_pos($f, $t, $flen, $tlen, $which)
{
    $result = VariableFormatter::STRING_WHOLE;

    if ($which & namespace\_DIFF_LINE_FROM)
    {
        if ($f === 0)
        {
            $result &= VariableFormatter::STRING_START;
        }
        elseif ($f === ($flen - 1))
        {
            $result &= VariableFormatter::STRING_END;
        }
        else
        {
            $result &= VariableFormatter::STRING_MIDDLE;
        }
    }

    if ($which & namespace\_DIFF_LINE_TO)
    {
        if ($t === 0)
        {
            $result &= VariableFormatter::STRING_START;
        }
        elseif ($t === ($tlen - 1))
        {
            $result &= VariableFormatter::STRING_END;
        }
        else
        {
            $result &= VariableFormatter::STRING_MIDDLE;
        }
    }

    return $result;
}



/**
 * @param bool $show_key
 * @param bool $reverse
 * @param VariableFormatter::STRING_* $pos
 * @return void
 */
function _copy_value(_DiffState $state, _Value $value, $show_key, $reverse, $pos = VariableFormatter::STRING_WHOLE)
{
    $formatted = namespace\_format_value($state, $value, $pos, $show_key);

    if ($reverse)
    {
        $iter = new ListReverseIterator($formatted);
    }
    else
    {
        $iter = new ListForwardIterator($formatted);
    }

    while ($iter->valid())
    {
        $state->diff[] = new _DiffCopy($iter->next());
    }
}


/**
 * @param bool $show_key
 * @param bool $reverse
 * @param VariableFormatter::STRING_* $pos
 * @return void
 */
function _insert_value(_DiffState $state, _Value $value, $show_key, $reverse, $pos = VariableFormatter::STRING_WHOLE)
{
    $formatted = namespace\_format_value($state, $value, $pos, $show_key);

    if ($reverse)
    {
        $iter = new ListReverseIterator($formatted);
    }
    else
    {
        $iter = new ListForwardIterator($formatted);
    }

    while ($iter->valid())
    {
        $state->diff[] = new _DiffInsert($iter->next());
    }
}


/**
 * @param bool $show_key
 * @param bool $reverse
 * @param VariableFormatter::STRING_* $pos
 * @return void
 */
function _delete_value(_DiffState $state, _Value $value, $show_key, $reverse, $pos = VariableFormatter::STRING_WHOLE)
{
    $formatted = namespace\_format_value($state, $value, $pos, $show_key);

    if ($reverse)
    {
        $iter = new ListReverseIterator($formatted);
    }
    else
    {
        $iter = new ListForwardIterator($formatted);
    }

    while ($iter->valid())
    {
        $state->diff[] = new _DiffDelete($iter->next());
    }
}


/**
 * @param bool $show_key
 * @param VariableFormatter::STRING_* $pos
 * @return void
 */
function _insert_unequal_value(_DiffState $state, _Value $value, $show_key, $pos = VariableFormatter::STRING_WHOLE)
{
    $formatted = namespace\_format_value($state, $value, $pos, $show_key);

    if ($state->cmp & namespace\DIFF_GREATER)
    {
        foreach ($formatted as $string)
        {
            $state->diff[] = new _DiffInsertLess($string);
        }
    }
    else
    {
        \assert(($state->cmp & namespace\DIFF_LESS) === namespace\DIFF_LESS);
        foreach ($formatted as $string)
        {
            $state->diff[] = new _DiffInsertGreater($string);
        }
    }
}


/**
 * @param bool $show_key
 * @param VariableFormatter::STRING_* $pos
 * @return void
 */
function _delete_unequal_value(_DiffState $state, _Value $value, $show_key, $pos = VariableFormatter::STRING_WHOLE)
{
    $formatted = namespace\_format_value($state, $value, $pos, $show_key);

    if ($state->cmp & namespace\DIFF_GREATER)
    {
        foreach ($formatted as $string)
        {
            $state->diff[] = new _DiffDeleteGreater($string);
        }
    }
    else
    {
        \assert(($state->cmp & namespace\DIFF_LESS) === namespace\DIFF_LESS);
        foreach ($formatted as $string)
        {
            $state->diff[] = new _DiffDeleteLess($string);
        }
    }
}


/**
 * @param VariableFormatter::STRING_* $pos
 * @param bool $show_key
 * @return string[]
 */
function _format_value(_DiffState $state, _Value $value, $pos, $show_key)
{
    $show_object_id = $state->cmp === namespace\DIFF_IDENTICAL;

    $prefix = $state->formatter->format_indent($value->indent_level);
    if ($show_key)
    {
        $prefix .= $value->key->formatted;
    }

    $suffix = $value->key->line_end;

    $result = new FormatResult;
    if ($value->type === _Value::TYPE_ARRAY)
    {
        $state->formatter->format_array($result, $value->value, $value->name, $value->indent_level, $prefix, $suffix);
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $state->formatter->format_object($result, $value->value, $value->name, $value->indent_level, $prefix, $suffix);
    }
    elseif ($value->type === _Value::TYPE_REFERENCE)
    {
        $result->formatted[] = $prefix . $value->name . $suffix;
    }
    elseif ($value->type === _Value::TYPE_RESOURCE)
    {
        $state->formatter->format_resource($result, $value->value, $prefix, $suffix);
    }
    elseif ($value->type === _Value::TYPE_STRING)
    {
        $state->formatter->format_string($result, $value->value, $prefix, $suffix);
    }
    elseif ($value->type === _Value::TYPE_STRING_PART)
    {
        $state->formatter->format_string($result, $value->value, $prefix, $suffix, $pos);
    }
    else
    {
        $state->formatter->format_scalar($result, $value->value, $prefix, $suffix);
    }

    return $result->formatted;
}


/**
 * @param bool $show_key
 * @return string
 */
function _start_value(VariableFormatter $formatter, _Value $value, $show_key)
{
    \assert(($value->type === _Value::TYPE_ARRAY) || ($value->type === _Value::TYPE_OBJECT));

    $result = $formatter->format_indent($value->indent_level);

    if ($show_key)
    {
        $result .= $value->key->formatted;
    }

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= VariableFormatter::ARRAY_START;
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $result .= $formatter->format_object_start($value->value);
    }

    return $result;
}

/**
 * @return string
 */
function _end_value(VariableFormatter $formatter, _Value $value)
{
    \assert(($value->type === _Value::TYPE_ARRAY) || ($value->type === _Value::TYPE_OBJECT));

    $result = $formatter->format_indent($value->indent_level);

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= VariableFormatter::ARRAY_END;
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $result .= VariableFormatter::OBJECT_END;
    }

    $result .= $value->key->line_end;
    return $result;
}


/**
 * @param ListIterator<_DiffOperation> $diff
 * @param string $from_name
 * @param string $to_name
 * @param int $cmp
 * @return string
 */
function _format_diff($diff, $from_name, $to_name, $cmp)
{
    if (($cmp === namespace\DIFF_IDENTICAL) || ($cmp === namespace\DIFF_EQUAL))
    {
        $result = "- $from_name\n+ $to_name\n";
    }
    elseif (($cmp === namespace\DIFF_GREATER) || ($cmp === namespace\DIFF_GREATER_EQUAL))
    {
        $result = "-> $from_name\n+< $to_name\n";
    }
    else
    {
        \assert(($cmp === namespace\DIFF_LESS) || ($cmp === namespace\DIFF_LESS_EQUAL));
        $result = "-< $from_name\n+> $to_name\n";
    }

    $prev_operations = array(
        _DiffOperation::DELETE_UNEQUAL => array(),
        _DiffOperation::INSERT_UNEQUAL => array(),
        _DiffOperation::INSERT         => array(),
    );

    while ($diff->valid())
    {
        $operation = $diff->next();

        if ($operation->type === _DiffOperation::DELETE)
        {
            $result .= "\n" . $operation;
        }
        elseif ($operation->type === _DiffOperation::COPY)
        {
            if ($prev_operations[_DiffOperation::DELETE_UNEQUAL])
            {
                foreach ($prev_operations[_DiffOperation::DELETE_UNEQUAL] as $prev)
                {
                    $result .= "\n" . $prev;
                }
                $prev_operations[_DiffOperation::DELETE_UNEQUAL] = array();
            }

            if ($prev_operations[_DiffOperation::INSERT_UNEQUAL])
            {
                foreach ($prev_operations[_DiffOperation::INSERT_UNEQUAL] as $prev)
                {
                    $result .= "\n" . $prev;
                }
                $prev_operations[_DiffOperation::INSERT_UNEQUAL] = array();
            }

            if ($prev_operations[_DiffOperation::INSERT])
            {
                foreach ($prev_operations[_DiffOperation::INSERT] as $prev)
                {
                    $result .= "\n" . $prev;
                }
                $prev_operations[_DiffOperation::INSERT] = array();
            }

            $result .= "\n" . $operation;
        }
        else
        {
            $prev_operations[$operation->type][] = $operation;
        }
    }

    if ($prev_operations[_DiffOperation::DELETE_UNEQUAL])
    {
        foreach ($prev_operations[_DiffOperation::DELETE_UNEQUAL] as $prev)
        {
            $result .= "\n" . $prev;
        }
    }

    if ($prev_operations[_DiffOperation::INSERT_UNEQUAL])
    {
        foreach ($prev_operations[_DiffOperation::INSERT_UNEQUAL] as $prev)
        {
            $result .= "\n" . $prev;
        }
    }

    if ($prev_operations[_DiffOperation::INSERT])
    {
        foreach ($prev_operations[_DiffOperation::INSERT] as $prev)
        {
            $result .= "\n" . $prev;
        }
    }

    return $result;
}
