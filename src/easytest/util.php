<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


function diff($from, $to, $from_name, $to_name) {
    if ($from_name === $to_name) {
        throw new \Exception('Parameters $from_name and $to_name must be different');
    }

    $diff = array();
    namespace\_diff_variables(
        namespace\_process_variable($from),
        namespace\_process_variable($to),
        $diff
    );

    $diff = \implode("\n", \array_reverse($diff));
    return "- $from_name\n+ $to_name\n\n$diff";
}



const _FORMAT_INDENT = '    ';


final class _DiffCopy {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "  {$this->value}";
    }
}


final class _DiffInsert {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "+ {$this->value}";
    }
}


final class _DiffDelete {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function __toString() {
        return "- {$this->value}";
    }
}


final class _Edit extends struct {
    public $flen;
    public $tlen;
    public $m;

    public function __construct($flen, $tlen, $m) {
        $this->flen = $flen;
        $this->tlen = $tlen;
        $this->m = $m;
    }
}


class _Variable extends struct {
    public $key;
    public $value;
    public $cost;
    public $substructure = array();

    public function __construct($key, $value, $cost = 1) {
        $this->key = $key;
        $this->value = $value;
        $this->cost = $cost;
    }
}



function _process_variable($var, $key = null) {
    if (\is_string($var)) {
        $result = new _Variable($key, $var, 0);
        foreach (\explode("\n", $var) as $line) {
            ++$result->cost;
            $result->substructure[] = new _Variable(null, $line);
        }
        return $result;
    }

    if (\is_array($var)) {
        $result = new _Variable($key, $var);
        foreach ($var as $key => $value) {
            $value = namespace\_process_variable($value, $key);
            $result->cost += $value->cost;
            $result->substructure[] = $value;
        }
        return $result;
    }

    if (\is_object($var)) {
        $result = new _Variable($key, $var);
        foreach ((array)$var as $key => $value) {
            $value = namespace\_process_variable($value, $key);
            $result->cost += $value->cost;
            $result->substructure[] = $value;
        }
        return $result;
    }

    return new _Variable($key, $var);
}


function _lcs_array(array $from, array $to) {
    $m = array();
    $flen = \count($from);
    $tlen = \count($to);

    for ($f = 0; $f <= $flen; ++$f) {
        for ($t = 0; $t <= $tlen; ++$t) {
            if (!$f || !$t) {
                $m[$f][$t] = 0;
                continue;
            }

            $fvalue = $from[$f-1];
            $tvalue = $to[$t-1];
            if (namespace\_lcs_variables($fvalue, $tvalue, $lcs)){
                $lcs += $m[$f-1][$t-1];
            }
            else {
                $lcs += \max($m[$f-1][$t], $m[$f][$t-1]);
            }
            $m[$f][$t] = $lcs;
        }
    }
    return new _Edit($flen, $tlen, $m);
}


function _diff_variables(_Variable $from, _Variable $to, array &$diff, $indent = '') {
    if (\is_array($from->value) && \is_array($to->value)) {
        if (0 === \count($from->value) || 0 === \count($to->value)) {
            namespace\_insert_value($diff, $to, $indent);
            namespace\_delete_value($diff, $from, $indent);
        }
        else {
            $edit = namespace\_lcs_array($from->substructure, $to->substructure);
            namespace\_copy_string($diff, "{$indent})");
            namespace\_build_diff_from_edit($from->substructure, $to->substructure, $edit, $diff, $indent . namespace\_FORMAT_INDENT);
            namespace\_copy_string($diff, "{$indent}array(");
        }
    }
    elseif (\is_string($from->value) && \is_string($to->value)) {
        if (1 === $from->cost && 1 === $to->cost) {
            namespace\_insert_value($diff, $to, $indent);
            namespace\_delete_value($diff, $from, $indent);
        }
        else {
            $edit = namespace\_lcs_array($from->substructure, $to->substructure);
            namespace\_build_diff_from_edit($from->substructure, $to->substructure, $edit, $diff, $indent);
        }
    }
    else {
        namespace\_insert_value($diff, $to, $indent);
        namespace\_delete_value($diff, $from, $indent);
    }
}


function _lcs_variables(_Variable $from, _Variable $to, &$lcs) {
    if (namespace\_compare_variables($from, $to)) {
        $lcs = \max($from->cost, $to->cost);
        return true;
    }

    $lcs = 0;
    if (\is_string($from->value) && \is_string($to->value)) {
        if ($from->cost > 1 && $to->cost > 1) {
            $edit = namespace\_lcs_array($from->substructure, $to->substructure);
            $lcs = $edit->m[$edit->flen][$edit->tlen];
        }
    }
    elseif (\is_array($from->value) && \is_array($to->value)) {
        if (\count($from->value) > 0 && \count($to->value) > 0) {
            $edit = namespace\_lcs_array($from->substructure, $to->substructure);
            $lcs = $edit->m[$edit->flen][$edit->tlen];
        }
    }

    return false;
}


function _compare_variables(_Variable $from, _Variable $to) {
    return $from->key === $to->key && $from->value === $to->value;
}



function _build_diff_from_edit(array $from, array $to, _Edit $edit, array &$diff, $indent) {
    $m = $edit->m;
    $f = $edit->flen;
    $t = $edit->tlen;

    while ($f || $t) {
        if ($f > 0 && $t > 0) {
            if (namespace\_compare_variables($from[$f-1], $to[$t-1])) {
                --$f;
                --$t;
                namespace\_copy_value($diff, $from[$f], $indent);
            }
            elseif ($m[$f-1][$t] < $m[$f][$t] && $m[$f][$t-1] < $m[$f][$t]) {
                --$f;
                --$t;
                namespace\_diff_variables($from[$f], $to[$t], $diff, $indent);
            }
            elseif ($m[$f][$t-1] >= $m[$f-1][$t]) {
                --$t;
                namespace\_insert_value($diff, $to[$t], $indent);
            }
            else {
                --$f;
                namespace\_delete_value($diff, $from[$f], $indent);
            }
        }
        elseif ($f) {
            --$f;
            namespace\_delete_value($diff, $from[$f], $indent);
        }
        else {
            --$t;
            namespace\_insert_value($diff, $to[$t], $indent);
        }
    }
}



function _copy_value(array &$diff, _Variable $value, $indent) {
    if (isset($value->key)) {
        $key = "{$value->key} => ";
    }
    else {
        $key = '';
    }

    namespace\_copy_string(
        $diff,
        "{$indent}{$key}" . namespace\_format_variable_indented($value->value, $indent)
    );
}


function _insert_value(array &$diff, _Variable $value, $indent) {
    if (isset($value->key)) {
        $key = "{$value->key} => ";
    }
    else {
        $key = '';
    }

    namespace\_insert_string(
        $diff,
        "{$indent}{$key}" . namespace\_format_variable_indented($value->value, $indent)
    );
}


function _delete_value(array &$diff, _Variable $value, $indent) {
    if (isset($value->key)) {
        $key = "{$value->key} => ";
    }
    else {
        $key = '';
    }

    namespace\_delete_string(
        $diff,
        "{$indent}{$key}" . namespace\_format_variable_indented($value->value, $indent)
    );
}


function _copy_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffCopy($v);
    }
}


function _insert_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffInsert($v);
    }
}


function _delete_string(array &$diff, $string) {
    foreach (\array_reverse(\explode("\n", $string)) as $v) {
        $diff[] = new _DiffDelete($v);
    }
}


function format_failure_message($assertion, $description = null, $detail = null) {
    $message = array();
    if ($assertion) {
        $message[] = $assertion;
    }
    if ($description) {
        $message[] = $description;
    }
    if (!$message) {
        $message[] = 'Assertion failed';
    }
    if ($detail) {
        $message[] = '';
        $message[] = $detail;
    }
    return \implode("\n", $message);
}



// Format a variable for display.
//
// This provides more readable(?) formatting of variables than PHP's built-in
// variable-printing functions (print_r(), var_dump(), var_export()) and also
// handles recursive references.

function format_variable(&$var) {
    return namespace\_format_variable_indented($var);
}


function _format_variable_indented(&$var, $indent = '') {
    $name = \is_object($var) ? \get_class($var) : \gettype($var);
    $seen = array('byval' => array(), 'byref' => array());
    // We'd really like to make this a constant static variable, but PHP won't
    // let us do that with an object instance. As a mitigation, we'll just
    // create the sentinels once at the start and then pass it around
    $sentinels = array('byref' => null, 'byval' => new \stdClass());
    return namespace\_format_recursive_variable($var, $name, $seen, $sentinels, $indent);
}


function _format_recursive_variable(&$var, $name, &$seen, $sentinels, $indent) {
    $reference = namespace\_check_reference($var, $name, $seen, $sentinels);
    if ($reference) {
        return $reference;
    }
    if (\is_scalar($var) || null === $var) {
        return namespace\_format_scalar($var);
    }
    if (\is_resource($var)) {
        return namespace\_format_resource($var);
    }
    if (\is_array($var)) {
        return namespace\_format_array($var, $name, $seen, $sentinels, $indent);
    }
    if (\is_object($var)) {
        return namespace\_format_object($var, $name, $seen, $sentinels, $indent);
    }
    throw new \Exception(
        \sprintf('Unexpected/unknown variable type: %s', \gettype($var))
    );
}


function _format_scalar(&$var) {
    return \var_export($var, true);
}


function _format_resource(&$var) {
    return \sprintf(
        '%s of type "%s"',
        \print_r($var, true),
        \get_resource_type($var)
    );
}


function _format_array(&$var, $name, &$seen, $sentinels, $padding) {
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    if ($var) {
        foreach ($var as $key => &$value) {
            $key = \var_export($key, true);
            $out .= \sprintf(
                "\n%s%s => %s",
                $indent,
                $key,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s[%s]', $name, $key),
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


function _format_object(&$var, $name, &$seen, $sentinels, $padding) {
    $indent = $padding . namespace\_FORMAT_INDENT;
    $out = '';

    $class = \get_class($var);
    // #BC(7.1): use spl_object_hash instead of spl_object_id
    $id = \version_compare(\PHP_VERSION, '7.2', '<')
        ? \spl_object_hash($var)
        : \spl_object_id($var);
    $values = (array)$var;
    if ($values) {
        foreach ($values as $key => &$value) {
            // Object properties are cast to array keys as follows:
            //     public    $property -> "property"
            //     protected $property -> "\0*\0property"
            //     private   $property -> "\0class\0property"
            //         where "class" is the name of the class where the
            //         property is declared
            $key = \explode("\0", $key);
            $property = '$' . \array_pop($key);
            if ($key && $key[1] !== '*' && $key[1] !== $class) {
                $property = "$key[1]::$property";
            }
            $out .= \sprintf(
                "\n%s%s = %s;",
                $indent,
                $property,
                namespace\_format_recursive_variable(
                    $value,
                    \sprintf('%s->%s', $name, $property),
                    $seen,
                    $sentinels,
                    $indent
                )
            );
        }
        $out .= "\n$padding";
    }
    return "$class #$id {{$out}}";
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
function _check_reference(&$var, $name, &$seen, $sentinels) {
    if (\is_scalar($var) || \is_array($var) || null === $var) {
        $copy = $var;
        $var = $sentinels['byval'];
        $reference = \array_search($var, $seen['byval'], true);
        if (false === $reference) {
            $seen['byval'][$name] = &$var;
        }
        else {
            $reference = "&$reference";
        }
        $var = $copy;
    }
    else {
        $reference = \array_search($var, $seen['byref'], true);
        if (false === $reference) {
            $seen['byref'][$name] = &$var;
        }
        else {
            $copy = $var;
            $var = $sentinels['byref'];
            if ($var === $seen['byref'][$reference]) {
                $reference = "&$reference";
            }
            $var = $copy;
        }
    }
    return $reference;
}



function ksort_recursive(&$array, &$seen = array()) {
    if (!\is_array($array)) {
        return;
    }

    /* Prevent infinite recursion for arrays with recursive references. */
    $temp = $array;
    $array = null;
    $sorted = \in_array($array, $seen, true);
    $array = $temp;
    unset($temp);

    if (false !== $sorted) {
        return;
    }
    $seen[] = &$array;
    \ksort($array);
    foreach ($array as &$value) {
        namespace\ksort_recursive($value, $seen);
    }
}
