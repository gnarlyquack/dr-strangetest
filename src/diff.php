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
const DIFF_EQUAL = 1;
const DIFF_GREATER = 2;
const DIFF_GREATER_EQUAL = 3;
const DIFF_LESS = 4;
const DIFF_LESS_EQUAL = 5;


const _DIFF_MATCH_NONE    = 0; // Elements have neither matching values nor matching keys
const _DIFF_MATCH_PARTIAL = 1; // Elements have matching values but nonmatching keys
const _DIFF_MATCH_FULL    = 2; // Elements have both matching values and keys


const _DIFF_LINE_MIDDLE = 0;
const _DIFF_LINE_FIRST  = 0x1;
const _DIFF_LINE_LAST   = 0x2;

const _DIFF_LINE_FROM = 0x1;
const _DIFF_LINE_TO   = 0x2;
// @bc 5.5 define run-time constant instead of using a constant expression
define('strangetest\\_DIFF_LINE_BOTH', namespace\_DIFF_LINE_FROM | namespace\_DIFF_LINE_TO);


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

    $fvalue = namespace\_process_value($state, $from_name, new _Key, $from);
    $tvalue = namespace\_process_value($state, $to_name, new _Key, $to);

    if (($cmp === namespace\DIFF_GREATER) || ($cmp === namespace\DIFF_GREATER_EQUAL))
    {
        namespace\_diff_unequal_values($state, $fvalue, $tvalue, new _GreaterThanComparator($state, $cmp === namespace\DIFF_GREATER_EQUAL));
    }
    elseif (($cmp === namespace\DIFF_LESS) || ($cmp === namespace\DIFF_LESS_EQUAL))
    {
        namespace\_diff_unequal_values($state, $fvalue, $tvalue, new _LessThanComparator($state, $cmp === namespace\DIFF_LESS_EQUAL));
    }
    else
    {
        namespace\_diff_equal_values($state, $fvalue, $tvalue);
    }

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

    /** @var bool */
    public $is_list;

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
    $reference = namespace\check_reference($value, $name, $state->seen, $state->sentinels);

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

        $prev_index = -1;
        foreach ($value as $k => &$v)
        {
            $result->is_list = $result->is_list && ($k === ++$prev_index);

            $subkey = new _Key;
            $subkey->type = _Key::TYPE_INDEX;
            $subkey->value = $k;
            $subkey->in_list =& $result->is_list;

            $subname = \sprintf('%s[%s]', $name, \var_export($k, true));
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

        $cost = 1;
        // @bc 5.4 use variable for array cast in order to create references
        $values = (array)$value;
        foreach ($values as $k => &$v)
        {
            $subkey = new _Key;
            $subkey->type = _Key::TYPE_PROPERTY;
            $subkey->value = $k;

            $subname = \sprintf('%s->%s', $name, $k);
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
    $cmp = namespace\_lcs_values($state, $from, $to, $state->cmp);

    $show_key = !$from->key->in_list || !$to->key->in_list;

    if ($cmp->matches === namespace\_DIFF_MATCH_FULL)
    {
        namespace\_copy_value($state, $from, $show_key);
    }
    elseif (0 === $cmp->lcs)
    {
        namespace\_insert_value($state, $to, $show_key);
        namespace\_delete_value($state, $from, $show_key);
    }
    else
    {
        \assert(
            (($from->type === _Value::TYPE_STRING) || ($from->type === _Value::TYPE_ARRAY) || ($from->type === _Value::TYPE_OBJECT))
            && ($to->type === $from->type));

        if ($cmp->matches === namespace\_DIFF_MATCH_PARTIAL)
        {
            // If values match, then only the key is different, so we don't
            // need to generate a difference between the two values.
            $indent = namespace\format_indent($from->indent_level);
            $key = namespace\_format_key($from->key);
            $line_end = namespace\_line_end($from->key);

            $value = $from->value;
            $to_value = $to->value;

            $seen = array('byval' => array(), 'byref' => array());
            $sentinels = array('byref' => null, 'byval' => new \stdClass());
            if ($from->type === _Value::TYPE_ARRAY)
            {
                $string = namespace\format_array($value, $from->name, $state->cmp === namespace\DIFF_IDENTICAL, $seen, $sentinels, $indent);
            }
            elseif ($from->type === _Value::TYPE_OBJECT)
            {
                $string = namespace\format_object($value, $from->name, $state->cmp === namespace\DIFF_IDENTICAL, $seen, $sentinels, $indent);
            }
            else
            {
                \assert($from->type !== _Value::TYPE_STRING_PART);
                $string = namespace\format_scalar($value);
            }

            list($start, $rest) = namespace\split_line_first($string);
            $from_start = $indent . namespace\_format_key($from->key) . $start;
            $to_start = $indent . namespace\_format_key($to->key) . $start;
            $rest .= namespace\_line_end($from->key);

            namespace\_copy_string($state, $rest);
            namespace\_insert_string($state, $to_start);
            namespace\_delete_string($state, $from_start);
        }
        elseif ($from->type === _Value::TYPE_STRING)
        {
            $edit = namespace\_lcs_array($state, $from, $to, $state->cmp);
            namespace\_build_diff($state, $from->subvalues, $to->subvalues, $edit, $show_key);
        }
        else
        {
            namespace\_copy_string($state, namespace\_end_value($from));

            $edit = namespace\_lcs_array($state, $from, $to, $state->cmp);
            namespace\_build_diff($state, $from->subvalues, $to->subvalues, $edit, ($from->type === _Value::TYPE_OBJECT) || !$from->is_list || !$to->is_list);

            if (($from->key() === $to->key())
                && (($state->cmp !== namespace\DIFF_IDENTICAL)
                    || (_Value::TYPE_ARRAY === $from->type)))
            {
                namespace\_copy_string($state, namespace\_start_value($from, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
            }
            else
            {
                namespace\_insert_string($state, namespace\_start_value($to, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
                namespace\_delete_string($state, namespace\_start_value($from, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
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
    $match = namespace\_compare_equal_values($from, $to, $cmp === namespace\DIFF_IDENTICAL);

    if ($match === namespace\_DIFF_MATCH_FULL)
    {
        $result = new _ComparisonResult;
        $result->matches = $match;
        $result->lcs = \max($from->cost, $to->cost);
        return $result;
    }

    $lcs = 0;
    if ($from->type === $to->type)
    {
        // if $from and $to are the same type and are composite types, then
        // generate a diff for their subtypes
        if ($from->type === _Value::TYPE_STRING)
        {
            if (($from->cost > 1) || ($to->cost > 1))
            {
                $edit = namespace\_lcs_array($state, $from, $to, $cmp);
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            (($from->type === _Value::TYPE_ARRAY) && ($to->type === _Value::TYPE_ARRAY)) ||
            (($from->type === _Value::TYPE_OBJECT) && ($to->type === _Value::TYPE_OBJECT) && ($from->value instanceof $to->value)))
        {
            $lcs = 1;
            if (($from->cost > 1) && ($to->cost > 1))
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
 * @param int $cmp
 * @return _Edit
 */
function _lcs_array(_DiffState $state, _Value $from, _Value $to, $cmp)
{
    \assert(
        ($from->type === $to->type)
        && (($from->type === _Value::TYPE_ARRAY) || ($from->type === _Value::TYPE_OBJECT) || ($from->type === _Value::TYPE_STRING)));

    if (isset($state->matrix_cache[$from->name][$to->name]))
    {
        $edit = $state->matrix_cache[$from->name][$to->name];
        return $edit;
    }

    $m = array();
    $fvalues = $from->subvalues;
    $tvalues = $to->subvalues;
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
        if (($from->key->in_list && $to->key->in_list) || ($from->key() === $to->key()))
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
function _build_diff(_DiffState $state, array $from, array $to, _Edit $edit, $show_key)
{
    $m = $edit->m;
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
                namespace\_copy_value($state, $from[$f], $show_key, $pos);
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
                namespace\_insert_value($state, $to[$t], $show_key, $pos);
            }
            else
            {
                --$f;
                $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_FROM);
                namespace\_delete_value($state, $from[$f], $show_key, $pos);
            }
        }
        elseif ($f)
        {
            --$f;
            $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_FROM);
            namespace\_delete_value($state, $from[$f], $show_key, $pos);
        }
        else
        {
            --$t;
            $pos = namespace\_get_line_pos($f, $t, $edit->flen, $edit->tlen, namespace\_DIFF_LINE_TO);
            namespace\_insert_value($state, $to[$t], $show_key, $pos);
        }
    }
}


/**
 * @return bool
 */
function _diff_unequal_values(_DiffState $state, _Value $from, _Value $to, _UnequalComparator $cmp)
{
    $show_key = !$from->key->in_list || !$to->key->in_list;

    $result = $cmp->compare_values($from, $to);

    if ($result === _UnequalComparator::MATCH_ERROR)
    {
        namespace\_delete_value($state, $from, $show_key);
        namespace\_insert_value($state, $to, $show_key);
    }
    elseif (($result === _UnequalComparator::MATCH_PASS)
        || (($result === _UnequalComparator::MATCH_EQUAL) && $cmp->equals_ok()))
    {
        namespace\_copy_value($state, $from, $show_key);
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
                $result = $cmp->compare_values($fvalues[$f], $tvalues[$t]);
                $equal = $result === _UnequalComparator::MATCH_EQUAL;

                if ($equal && $cmp->equals_ok())
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_BOTH);
                    namespace\_copy_value($state, $fvalues[$f], $show_key, $pos);
                }
                else
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    $cmp->delete_value($fvalues[$f], $show_key, $pos);
                    $cmp->insert_value($tvalues[$t], $show_key, $pos);
                }
            }

            if ($equal && $cmp->equals_ok())
            {
                for ( ; $f < $flen; ++$f)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    namespace\_delete_value($state, $fvalues[$f], $show_key, $pos);
                }

                for ( ; $t < $tlen; ++$t)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_TO);
                    namespace\_insert_value($state, $tvalues[$t], $show_key, $pos);
                }
            }
            else
            {
                for ( ; $f < $flen; ++$f)
                {
                    $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                    namespace\_copy_value($state, $fvalues[$f], $show_key, $pos);
                }
            }
        }
        else
        {
            $show_subkey = ($from->type === _Value::TYPE_OBJECT) || !$from->is_list || !$to->is_list;

            namespace\_copy_string($state, namespace\_start_value($from, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));

            for ($f = 0; ($flen - $f) > $tlen; ++$f)
            {
                $pos = namespace\_get_line_pos($f, 0, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                namespace\_delete_value($state, $fvalues[$f], $show_subkey, $pos);
            }
            for ($t = 0; ($tlen - $t) > $flen; ++$t)
            {
                $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_TO);
                namespace\_insert_value($state, $tvalues[$t], $show_subkey, $pos);
            }

            $equal = true;
            for ( ; ($f < $flen) && $equal; ++$f, ++$t)
            {
                $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_BOTH);
                $equal = namespace\_diff_unequal_values($state, $fvalues[$f], $tvalues[$t], $cmp);
            }

            for ( ; $f < $flen; ++$f)
            {
                $pos = namespace\_get_line_pos($f, $t, $flen, $tlen, namespace\_DIFF_LINE_FROM);
                namespace\_copy_value($state, $fvalues[$f], $show_subkey, $pos);
            }

            namespace\_copy_string($state, namespace\_end_value($from));
        }
    }
    else
    {
        $cmp->delete_value($from, $show_key);
        $cmp->insert_value($to, $show_key);
    }

    return $result === _UnequalComparator::MATCH_EQUAL;
}


interface _UnequalComparator
{
    const MATCH_ERROR = 0;
    const MATCH_PASS  = 1;
    const MATCH_FAIL  = 2;
    const MATCH_EQUAL = 3;

    /**
     * @return _UnequalComparator::*
     */
    public function compare_values(_Value $from, _Value $to);


    /**
     * @return bool
     */
    public function equals_ok();


    /**
     * @param bool $show_key
     * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
     * @return void
     */
    public function delete_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE);

    /**
     * @param bool $show_key
     * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
     * @return void
     */
    public function insert_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE);
}


final class _GreaterThanComparator extends struct implements _UnequalComparator
{
    /** @var _DiffState */
    private $state;

    /** @var bool */
    private $equals_ok;

    /**
     * @param bool $equals_ok
     */
    public function __construct(_DiffState $state, $equals_ok)
    {
        $this->state = $state;
        $this->equals_ok = $equals_ok;
    }


    public function equals_ok()
    {
        return $this->equals_ok;
    }


    public function compare_values(_Value $from, _Value $to)
    {
        $fvalue = $from->value;
        $tvalue = $to->value;

        if ($fvalue > $tvalue)
        {
            return self::MATCH_PASS;
        }
        elseif ($fvalue < $tvalue)
        {
            return self::MATCH_FAIL;
        }
        elseif ($fvalue != $tvalue)
        {
            // incomparable values
            return self::MATCH_ERROR;
        }
        else
        {
            return self::MATCH_EQUAL;
        }
    }


    public function delete_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
    {
        $string = namespace\_format_value($value, $pos, $show_key, $this->state->cmp === namespace\DIFF_IDENTICAL);
        foreach (\array_reverse(\explode("\n", $string)) as $v)
        {
            $this->state->diff[] = new _DiffDeleteGreater($v);
        }
    }


    public function insert_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
    {
        $string = namespace\_format_value($value, $pos, $show_key, $this->state->cmp === namespace\DIFF_IDENTICAL);
        foreach (\array_reverse(\explode("\n", $string)) as $v)
        {
            $this->state->diff[] = new _DiffInsertLess($v);
        }
    }
}


final class _LessThanComparator extends struct implements _UnequalComparator
{
    /** @var _DiffState */
    private $state;

    /** @var bool */
    private $equals_ok;


    /**
     * @param bool $equals_ok
     */
    public function __construct(_DiffState $state, $equals_ok)
    {
        $this->state = $state;
        $this->equals_ok = $equals_ok;
    }


    public function equals_ok()
    {
        return $this->equals_ok;
    }


    public function compare_values(_Value $from, _Value $to)
    {
        $fvalue = $from->value;
        $tvalue = $to->value;

        if ($fvalue < $tvalue)
        {
            return self::MATCH_PASS;
        }
        elseif ($fvalue > $tvalue)
        {
            return self::MATCH_FAIL;
        }
        elseif ($fvalue != $tvalue)
        {
            // incomparable values
            return self::MATCH_ERROR;
        }
        else
        {
            return self::MATCH_EQUAL;
        }
    }


    public function delete_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
    {
        $string = namespace\_format_value($value, $pos, $show_key, $this->state->cmp === namespace\DIFF_IDENTICAL);
        foreach (\array_reverse(\explode("\n", $string)) as $v)
        {
            $this->state->diff[] = new _DiffDeleteLess($v);
        }
    }


    public function insert_value(_Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
    {
        $string = namespace\_format_value($value, $pos, $show_key, $this->state->cmp === namespace\DIFF_IDENTICAL);
        foreach (\array_reverse(\explode("\n", $string)) as $v)
        {
            $this->state->diff[] = new _DiffInsertGreater($v);
        }
    }
}


/**
 * @param int $f
 * @param int $t
 * @param int $flen
 * @param int $tlen
 * @param _DIFF_LINE_FROM|_DIFF_LINE_TO|_DIFF_LINE_BOTH $which
 * @return _DIFF_LINE_FIRST|_DIFF_LINE_MIDDLE|_DIFF_LINE_LAST
 */
function _get_line_pos($f, $t, $flen, $tlen, $which)
{
    $result = namespace\_DIFF_LINE_FIRST | namespace\_DIFF_LINE_LAST;

    if ($which & namespace\_DIFF_LINE_FROM)
    {
        if ($f === 0)
        {
            $result &= namespace\_DIFF_LINE_FIRST;
        }
        elseif ($f === ($flen - 1))
        {
            $result &= namespace\_DIFF_LINE_LAST;
        }
        else
        {
            $result &= namespace\_DIFF_LINE_MIDDLE;
        }
    }

    if ($which & namespace\_DIFF_LINE_TO)
    {
        if ($t === 0)
        {
            $result &= namespace\_DIFF_LINE_FIRST;
        }
        elseif ($t === ($tlen - 1))
        {
            $result &= namespace\_DIFF_LINE_LAST;
        }
        else
        {
            $result &= namespace\_DIFF_LINE_MIDDLE;
        }
    }

    \assert($result <= 2);
    return $result;
}



/**
 * @param bool $show_key
 * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
 * @return void
 */
function _copy_value(_DiffState $state, _Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
{
    namespace\_copy_string($state, namespace\_format_value($value, $pos, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
}


/**
 * @param bool $show_key
 * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
 * @return void
 */
function _insert_value(_DiffState $state, _Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
{
    namespace\_insert_string($state, namespace\_format_value($value, $pos, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
}


/**
 * @param bool $show_key
 * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
 * @return void
 */
function _delete_value(_DiffState $state, _Value $value, $show_key, $pos = namespace\_DIFF_LINE_MIDDLE)
{
    namespace\_delete_string($state, namespace\_format_value($value, $pos, $show_key, $state->cmp === namespace\DIFF_IDENTICAL));
}


/**
 * @param string $string
 * @return void
 */
function _copy_string(_DiffState $state, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $state->diff[] = new _DiffCopy($v);
    }
}


/**
 * @param string $string
 * @return void
 */
function _insert_string(_DiffState $state, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $state->diff[] = new _DiffInsert($v);
    }
}


/**
 * @param string $string
 * @return void
 */
function _delete_string(_DiffState $state, $string)
{
    foreach (\array_reverse(\explode("\n", $string)) as $v)
    {
        $state->diff[] = new _DiffDelete($v);
    }
}


/**
 * @return string
 */
function _format_key(_Key $key)
{
    if ($key->type === _Key::TYPE_INDEX)
    {
        return \sprintf('%s => ', \var_export($key->value, true));
    }
    elseif ($key->type === _Key::TYPE_PROPERTY)
    {
        return "\${$key->value} = ";
    }
    else
    {
        return '';
    }
}


/**
 * @param _DIFF_LINE_MIDDLE|_DIFF_LINE_FIRST|_DIFF_LINE_LAST $pos
 * @param bool $show_key
 * @param bool $show_object_id
 * @return string
 */
function _format_value(_Value $value, $pos, $show_key, $show_object_id)
{
    $result = $indent = namespace\format_indent($value->indent_level);

    if ($show_key)
    {
        $result .= namespace\_format_key($value->key);
    }
    $line_end = namespace\_line_end($value->key);

    $seen = array('byval' => array(), 'byref' => array());
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= namespace\format_array($value->value, $value->name, $show_object_id, $seen, $sentinels, $indent);
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $result .= namespace\format_object($value->value, $value->name, $show_object_id, $seen, $sentinels, $indent);
    }
    elseif ($value->type === _Value::TYPE_REFERENCE)
    {
        $result .= $value->name;
    }
    elseif ($value->type === _Value::TYPE_RESOURCE)
    {
        $result .= namespace\format_resource($value->value);
    }
    elseif ($value->type === _Value::TYPE_STRING_PART)
    {
        if (namespace\_DIFF_LINE_FIRST === $pos)
        {
            $result .= "'";
            $line_end = '';
        }
        elseif (namespace\_DIFF_LINE_LAST === $pos)
        {
            $result = '';
            $line_end = "'" . $line_end;
        }
        else
        {
            $result = $line_end = '';
        }

        \assert(\is_string($value->value));
        $result .= \str_replace(array('\\', "'",), array('\\\\', "\\'",), $value->value);
    }
    else
    {
        $result .= namespace\format_scalar($value->value);
    }

    $result .= $line_end;
    return $result;
}


/**
 * @param bool $show_key
 * @param bool $show_object_id
 * @return string
 */
function _start_value(_Value $value, $show_key, $show_object_id)
{
    \assert(($value->type === _Value::TYPE_ARRAY) || ($value->type === _Value::TYPE_OBJECT));

    $result = namespace\format_indent($value->indent_level);

    if ($show_key)
    {
        $result .= namespace\_format_key($value->key);
    }

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= 'array(';
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $result .= namespace\format_object_start($value->value, $show_object_id);
    }

    return $result;
}

/**
 * @return string
 */
function _end_value(_Value $value)
{
    \assert(($value->type === _Value::TYPE_ARRAY) || ($value->type === _Value::TYPE_OBJECT));

    $result = namespace\format_indent($value->indent_level);

    if ($value->type === _Value::TYPE_ARRAY)
    {
        $result .= ')';
    }
    elseif ($value->type === _Value::TYPE_OBJECT)
    {
        $result .= '}';
    }

    $result .= namespace\_line_end($value->key);
    return $result;
}


/**
 * @return string
 */
function _line_end(_Key $key)
{
    if ($key->type === _Key::TYPE_INDEX)
    {
        return ',';
    }
    elseif ($key->type === _Key::TYPE_PROPERTY)
    {
        return ';';
    }
    else
    {
        return '';
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
        $result = "-> $from_name\n+< $to_name\n";
    }
    else
    {
        \assert(($cmp === namespace\DIFF_LESS) || ($cmp === namespace\DIFF_LESS_EQUAL));
        $result = "-< $from_name\n+> $to_name\n";
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
