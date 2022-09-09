<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

// @todo Improve performance of the diff algorithm?
// References to potentially improve the performance
// -   "a high-performance library in multiple languages that manipulates plain text"
//      https://github.com/google/diff-match-patch
// -   "Utility to do an N-way diff and N-way merge, for N > 2"
//      https://github.com/Quuxplusone/difdef

// @todo Limit the number of lines shown before and after differing text

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

// results for equal comparisons
const _CMP_EQ_NONE        = 0; // Elements have neither matching values nor matching keys
const _CMP_EQ_VALUES_ONLY = 1; // Elements have matching values but nonmatching keys
const _CMP_EQ_FULL        = 2; // Elements have both matching values and keys

// results for unequal comparisons
const _CMP_NEQ_ERROR = 0; // incomparable values
const _CMP_NEQ_PASS  = 1; // match is unequal and satisfies condition
const _CMP_NEQ_FAIL  = 2; // match is unequal and doesn't satisfy condition
const _CMP_NEQ_EQUAL = 3;

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

    if ($cmp & namespace\DIFF_GREATER)
    {
        namespace\_diff_unequal_values($state, $fvalue, $tvalue);
        $diff = new ListForwardIterator($state->diff);
    }
    elseif ($cmp & namespace\DIFF_LESS)
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

    /** @var _DiffOperation[] */
    public $diff = array();

    /** @var VariableFormatter */
    public $formatter;

    /** @var array<string, array<string, _Edit>> */
    public $matrix_cache = array();

    /** @var ReferenceChecker */
    public $references;


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


class _ComparisonResult
{
    /** @var int */
    public $lcs;

    /** @var _CMP_EQ_NONE|_CMP_EQ_VALUES_ONLY|_CMP_EQ_FULL */
    public $result;
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
    public function __construct(array $fvalues, array $tvalues, $flen, $tlen, array $m)
    {
        $this->fvalues = $fvalues;
        $this->tvalues = $tvalues;
        $this->flen = $flen;
        $this->tlen = $tlen;
        $this->m = $m;
    }
}


final class _DiffOperation extends struct
{
    const DELETE_UNEQUAL = 0;
    const INSERT_UNEQUAL = 1;
    const INSERT         = 2;
    const DELETE         = 3;
    const COPY           = 4;

    /** @var string */
    public $string;

    /** @var _DiffOperation::* */
    public $type;
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

    if ($cmp->result === namespace\_CMP_EQ_FULL)
    {
        namespace\_copy_value($state, $from, $show_key, true);
    }
    elseif (0 === $cmp->lcs)
    {
        namespace\_insert_value($state, $to, $show_key, true);
        namespace\_delete_value($state, $from, $show_key, true);
    }
    // if $from and $to don't match but have an $lcs > 0, they must be
    // composite values of the same type with something in common
    elseif ($cmp->result === namespace\_CMP_EQ_VALUES_ONLY)
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
            \assert($from->type === _Value::TYPE_STRING);
            $state->formatter->format_string($formatted, $from->value, '', $suffix);
        }
        $strings = $formatted->formatted;

        $indent = $state->formatter->format_indent($from->indent_level);
        $from_start = $indent . $from->key->formatted . $strings[0];
        $to_start = $indent . $to->key->formatted . $strings[0];

        for ($c = \count($strings), $i = $c - 1; $i > 0; --$i)
        {
            namespace\_diff_copy($state, $strings[$i]);
        }
        namespace\_diff_insert($state, $to_start);
        namespace\_diff_delete($state, $from_start);
    }
    elseif ($from->type === _Value::TYPE_STRING)
    {
        // One of the strings has be multiline if they don't match but $lcs > 0
        \assert($to->type === _Value::TYPE_STRING);
        $edit = namespace\_lcs_composite_values($state, $from, $to);
        namespace\_build_diff($state, $edit, $show_key);
    }
    else
    {
        \assert((($from->type === _Value::TYPE_ARRAY) && ($to->type === _Value::TYPE_ARRAY))
            || (($from->type === _Value::TYPE_OBJECT) && ($to->type === _Value::TYPE_OBJECT) && ($from->value instanceof $to->value)));

        namespace\_diff_copy($state, namespace\_end_value($state->formatter, $from));

        $edit = namespace\_lcs_composite_values($state, $from, $to);
        namespace\_build_diff($state, $edit, !$from->is_list || !$to->is_list);

        if ((($state->cmp !== namespace\DIFF_IDENTICAL) || (_Value::TYPE_ARRAY === $from->type))
            && namespace\_compare_keys($from, $to))
        {
            namespace\_diff_copy($state, namespace\_start_value($state->formatter, $from, $show_key));
        }
        else
        {
            namespace\_diff_insert($state, namespace\_start_value($state->formatter, $to, $show_key));
            namespace\_diff_delete($state, namespace\_start_value($state->formatter, $from, $show_key));
        }
    }
}


/**
 * @return _ComparisonResult
 */
function _lcs_values(_DiffState $state, _Value $from, _Value $to)
{
    $match = namespace\_compare_equal_values($from, $to, $state->cmp === namespace\DIFF_IDENTICAL);

    if ($match === namespace\_CMP_EQ_FULL)
    {
        $result = new _ComparisonResult;
        $result->result = $match;
        $result->lcs = \max($from->cost, $to->cost);
        return $result;
    }
    elseif ($match === namespace\_CMP_EQ_VALUES_ONLY)
    {
        $result = new _ComparisonResult;
        $result->result = $match;
        $result->lcs = \max($from->cost, $to->cost) - 1;
        return $result;
    }
    else
    {
        $lcs = 0;
        if (($from->type === _Value::TYPE_STRING) && ($to->type === _Value::TYPE_STRING))
        {
            if (($from->cost > 1) || ($to->cost > 1))
            {
                $edit = namespace\_lcs_composite_values($state, $from, $to);
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
                $edit = namespace\_lcs_composite_values($state, $from, $to);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }

        $result = new _ComparisonResult;
        $result->result = $match;
        $result->lcs = $lcs;
        return $result;
    }
}


/**
 * @return _Edit
 */
function _lcs_composite_values(_DiffState $state, _Value $from, _Value $to)
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

                $result = namespace\_lcs_values($state, $fvalues[$f-1], $tvalues[$t-1]);
                if ($result->result === namespace\_CMP_EQ_FULL)
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
 * @return _CMP_EQ_NONE|_CMP_EQ_VALUES_ONLY|_CMP_EQ_FULL
 */
function _compare_equal_values(_Value $from, _Value $to, $strict)
{
    $result = namespace\_CMP_EQ_NONE;

    if ($strict)
    {
        if ($from->value === $to->value)
        {
            $result = namespace\_CMP_EQ_VALUES_ONLY;
        }
    }
    else
    {
        if ($from->value == $to->value)
        {
            $result = namespace\_CMP_EQ_VALUES_ONLY;
        }
    }

    if ($result)
    {
        if (namespace\_compare_keys($from, $to))
        {
            $result = namespace\_CMP_EQ_FULL;
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
            if (namespace\_compare_equal_values($from[$f-1], $to[$t-1], $state->cmp === namespace\DIFF_IDENTICAL) === namespace\_CMP_EQ_FULL)
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
    $greater = ($state->cmp & namespace\DIFF_GREATER) === namespace\DIFF_GREATER;

    $result = namespace\_compare_unequal_values($from, $to, $greater);

    if (($result === namespace\_CMP_NEQ_PASS)
        || (($result === namespace\_CMP_NEQ_EQUAL) && ($state->cmp & namespace\DIFF_EQUAL)))
    {
        namespace\_copy_value($state, $from, $show_key, false);
    }
    elseif (($from->type === _Value::TYPE_STRING) && ($to->type === _Value::TYPE_STRING))
    {
        $fvalues = $from->subvalues;
        $tvalues = $to->subvalues;

        $flen = \count($fvalues);
        $tlen = \count($tvalues);
        $min = \min($flen, $tlen);

        $equal = true;
        for ($f = 0, $t = 0; ($f < $min) && $equal; ++$f, ++$t)
        {
            $strcmp = namespace\_compare_unequal_values($fvalues[$f], $tvalues[$t], $greater);
            $equal = $strcmp === namespace\_CMP_NEQ_EQUAL;

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
            // Both strings are equal, so $from needs to be either trimmed or
            // extended to match $to to satisfy the comparison
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
            // If anything is left of $from, it can simply be copied. Since
            // strings are not allowed to be equal, a change in an earlier part
            // of $from will satisfy the comparison
            for ( ; $f < $flen; ++$f)
            {
                $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                namespace\_copy_value($state, $fvalues[$f], $show_key, false, $pos);
            }
        }
    }
    elseif ((($from->type === _Value::TYPE_ARRAY) && ($to->type === _Value::TYPE_ARRAY))
        || (($from->type === _Value::TYPE_OBJECT) && ($to->type === _Value::TYPE_OBJECT) && ($to->value instanceof $from->value)))
    {
        namespace\_diff_copy($state, namespace\_start_value($state->formatter, $from, $show_key));

        $fvalues = $from->subvalues;
        $tvalues = $to->subvalues;
        $fkeys = $from->keys;
        $tkeys = $to->keys;
        $show_subkey = !$from->is_list || !$to->is_list;
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

        namespace\_diff_copy($state, namespace\_end_value($state->formatter, $from));
    }
    elseif($result === namespace\_CMP_NEQ_ERROR)
    {
        namespace\_delete_value($state, $from, $show_key, false);
        namespace\_insert_value($state, $to, $show_key, false);
    }
    else
    {
        namespace\_delete_unequal_value($state, $from, $show_key);
        namespace\_insert_unequal_value($state, $to, $show_key);
    }

    return $result === namespace\_CMP_NEQ_EQUAL;
}


/**
 * @param bool $greater
 * @return _CMP_NEQ_PASS|_CMP_NEQ_FAIL|_CMP_NEQ_EQUAL|_CMP_NEQ_ERROR
 */
function _compare_unequal_values(_Value $from, _Value $to, $greater)
{
    if ($from->value > $to->value)
    {
        return $greater ? namespace\_CMP_NEQ_PASS : namespace\_CMP_NEQ_FAIL;
    }
    elseif ($from->value < $to->value)
    {
        return $greater ? namespace\_CMP_NEQ_FAIL : namespace\_CMP_NEQ_PASS;
    }
    elseif ($from->value != $to->value)
    {
        // incomparable values
        return namespace\_CMP_NEQ_ERROR;
    }
    else
    {
        return namespace\_CMP_NEQ_EQUAL;
    }
}


/**
 * @return bool
 */
function _compare_keys(_Value $from, _Value $to)
{
    if ($from->key->in_list && $to->key->in_list)
    {
        return true;
    }
    elseif (($from->key->type === $to->key->type) && ($from->key->value === $to->key->value))
    {
        return true;
    }
    elseif (($from->type === _Value::TYPE_STRING_PART)
        && ($to->type === _Value::TYPE_STRING_PART)
        && $from->index
        && $to->index)
    {
        return true;
    }
    else
    {
        return false;
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
    $from = VariableFormatter::STRING_WHOLE;
    $to = VariableFormatter::STRING_WHOLE;

    if ($which & namespace\_DIFF_LINE_FROM)
    {
        $from = VariableFormatter::STRING_MIDDLE;
        if ($f === 0)
        {
            $from |= VariableFormatter::STRING_START;
        }
        if ($f === ($flen - 1))
        {
            $from |= VariableFormatter::STRING_END;
        }
    }

    if ($which & namespace\_DIFF_LINE_TO)
    {
        $to = VariableFormatter::STRING_MIDDLE;
        if ($t === 0)
        {
            $to |= VariableFormatter::STRING_START;
        }
        if ($t === ($tlen - 1))
        {
            $to |= VariableFormatter::STRING_END;
        }
    }

    $result = $from & $to;
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
        namespace\_diff_copy($state, $iter->next());
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
        namespace\_diff_insert($state, $iter->next());
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
        namespace\_diff_delete($state, $iter->next());
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
            namespace\_diff_insert_less($state, $string);
        }
    }
    else
    {
        \assert(($state->cmp & namespace\DIFF_LESS) === namespace\DIFF_LESS);
        foreach ($formatted as $string)
        {
            namespace\_diff_insert_greater($state, $string);
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
            namespace\_diff_delete_greater($state, $string);
        }
    }
    else
    {
        \assert(($state->cmp & namespace\DIFF_LESS) === namespace\DIFF_LESS);
        foreach ($formatted as $string)
        {
            namespace\_diff_delete_less($state, $string);
        }
    }
}


/**
 * @param string $string
 * @return void
 */
function _diff_copy(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::COPY;
    $result->string = '  ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_delete(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::DELETE;
    $result->string = '- ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_delete_greater(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::DELETE_UNEQUAL;
    $result->string = '> ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_delete_less(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::DELETE_UNEQUAL;
    $result->string = '< ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_insert(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::INSERT;
    $result->string = '+ ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_insert_greater(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::INSERT_UNEQUAL;
    $result->string = '> ' . $string;

    $state->diff[] = $result;
}


/**
 * @param string $string
 * @return void
 */
function _diff_insert_less(_DiffState $state, $string)
{
    $result = new _DiffOperation;
    $result->type = _DiffOperation::INSERT_UNEQUAL;
    $result->string = '< ' . $string;

    $state->diff[] = $result;
}


/**
 * @param VariableFormatter::STRING_* $pos
 * @param bool $show_key
 * @return string[]
 */
function _format_value(_DiffState $state, _Value $value, $pos, $show_key)
{
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
    $result = $formatter->format_indent($value->indent_level);

    if ($show_key)
    {
        $result .= $value->key->formatted;
    }

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= VariableFormatter::ARRAY_START;
    }
    else
    {
        \assert($value->type === _Value::TYPE_OBJECT);
        $result .= $formatter->format_object_start($value->value);
    }

    return $result;
}

/**
 * @return string
 */
function _end_value(VariableFormatter $formatter, _Value $value)
{
    $result = $formatter->format_indent($value->indent_level);

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= VariableFormatter::ARRAY_END;
    }
    else
    {
        \assert($value->type === _Value::TYPE_OBJECT);
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
    if ($cmp & namespace\DIFF_GREATER)
    {
        $result = "-> $from_name\n+< $to_name\n";
    }
    elseif ($cmp & namespace\DIFF_LESS)
    {
        $result = "-< $from_name\n+> $to_name\n";
    }
    else
    {
        $result = "- $from_name\n+ $to_name\n";
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
            $result .= "\n" . $operation->string;
        }
        elseif ($operation->type === _DiffOperation::COPY)
        {
            foreach ($prev_operations as $key => $prevs)
            {
                if ($prevs)
                {
                    foreach ($prevs as $prev)
                    {
                        $result .= "\n" . $prev->string;
                    }
                    $prev_operations[$key] = array();
                }
            }

            $result .= "\n" . $operation->string;
        }
        else
        {
            $prev_operations[$operation->type][] = $operation;
        }
    }

    foreach ($prev_operations as $key => $prevs)
    {
        foreach ($prevs as $prev)
        {
            $result .= "\n" . $prev->string;
        }
    }

    return $result;
}
