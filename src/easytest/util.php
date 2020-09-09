<?php
// This file is part of EasyTest. It is subject to the license terms in the
// LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

namespace easytest;


function diff(&$from, &$to, $from_name, $to_name) {
    if ($from_name === $to_name) {
        throw new \Exception('Parameters $from_name and $to_name must be different');
    }

    $state = new _DiffState();

    namespace\_diff_variables(
        namespace\_process_variable($from, $from_name, $state),
        namespace\_process_variable($to, $to_name, $state),
        $state
    );

    $diff = \implode("\n", \array_reverse($state->diff));
    return "- $from_name\n+ $to_name\n\n$diff";
}


final class _DiffState extends struct {
    public $diff = array();
    public $seen = array('byval' => array(), 'byref' => array());
    public $sentinels = array('byref' => null);

    public function __construct() {
        $this->sentinels['byval'] = new \stdClass();
    }
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
    const TYPE_ARRAY = 1;
    const TYPE_OBJECT = 2;
    const TYPE_REFERENCE = 3;
    const TYPE_SCALAR = 4;
    const TYPE_STRING = 5;
    const TYPE_RESOURCE = 6;

    public $name;
    public $key;
    public $value;
    public $cost;

    /** @var self::TYPE_* */
    public $type;

    /** @var int */
    public $scope;

    public $substructure = array();


    /**
     * @param self::TYPE_* $type
     * @param int $scope
     */
    public function __construct($name, $key, &$value, $type, $scope, $cost = 1) {
        $this->name = $name;
        $this->key = $key;
        $this->value = &$value;
        $this->type = $type;
        $this->cost = $cost;
        $this->scope = $scope;
    }


    /**
     * @return string
     */
    public function format() {
        $indent = \str_repeat(namespace\_FORMAT_INDENT, $this->scope);
        $result = $indent;

        if (isset($this->key)) {
            $result .= "{$this->key} => ";
        }

        if (_Variable::TYPE_ARRAY === $this->type) {
            $seen = array('byval' => array(), 'byref' => array());
            $sentinels = array('byref' => null, 'byval' => new \stdClass());
            $result .= namespace\_format_array($this->value, $this->name, $seen, $sentinels, $indent);
        }
        elseif (_Variable::TYPE_OBJECT === $this->type) {
            $seen = array('byval' => array(), 'byref' => array());
            $sentinels = array('byref' => null, 'byval' => new \stdClass());
            $result .= namespace\_format_object($this->value, $this->name, $seen, $sentinels, $indent);
        }
        elseif (_Variable::TYPE_REFERENCE === $this->type) {
            $result .= $this->name;
        }
        elseif (_Variable::TYPE_RESOURCE === $this->type) {
            $result .= namespace\_format_resource($this->value);
        }
        else {
            $result .= namespace\_format_scalar($this->value);
        }

        return $result;
    }


    /**
     * @return string
     */
    public function format_open() {
        if (_Variable::TYPE_ARRAY === $this->type) {
            $result = \str_repeat(namespace\_FORMAT_INDENT, $this->scope);
            if (isset($this->key)) {
                $result .= "{$this->key} => ";
            }
            $result .= 'array(';
            return $result;
        }
        elseif (_Variable::TYPE_OBJECT === $this->type) {
            $result = \str_repeat(namespace\_FORMAT_INDENT, $this->scope);
            if (isset($this->key)) {
                $result .= "{$this->key} => ";
            }
            $result .= \get_class($this->value) . ' {';
            return $result;
        }
        else {
            throw new \Exception('Cannot format open on non-container value');
        }
    }


    /**
     * @return string
     */
    public function format_close() {
        if (_Variable::TYPE_ARRAY === $this->type) {
            return \str_repeat(namespace\_FORMAT_INDENT, $this->scope) . ')';
        }
        elseif (_Variable::TYPE_OBJECT === $this->type) {
            return \str_repeat(namespace\_FORMAT_INDENT, $this->scope) . '}';
        }
        else {
            throw new \Exception('Cannot format close on non-container value');
        }
    }
}


/**
 * @param int $scope
 */
function _process_variable(&$var, $name, _DiffState $state, $key = null, $scope = 0) {
    $reference = namespace\_check_reference($var, $name, $state->seen, $state->sentinels);
    if ($reference) {
        $result = new _Variable($reference, $key, $var, _Variable::TYPE_REFERENCE, $scope);
        return $result;
    }

    if (\is_resource($var)) {
        return new _Variable($name, $key, $var, _Variable::TYPE_RESOURCE, $scope);
    }

    if (\is_string($var)) {
        $result = new _Variable($name, $key, $var, _Variable::TYPE_STRING, $scope, 0);
        // #BC(5.4): explode() into a variable in order to create references
        $lines = \explode("\n", $var);
        foreach ($lines as $i => &$line) {
            ++$result->cost;
            $subname = \sprintf('%s[%d]', $name, $i);
            $subkey = $i ? null : $key;
            $result->substructure[] = new _Variable($subname, $subkey, $line, _Variable::TYPE_STRING, $scope);
        }
        return $result;
    }

    if (\is_array($var)) {
        $result = new _Variable($name, $key, $var, _Variable::TYPE_ARRAY, $scope);
        foreach ($var as $key => &$value) {
            $subname = \sprintf('%s[%s]', $name, \var_export($key, true));
            $subvalue = namespace\_process_variable($value, $subname, $state, $key, $scope + 1);
            $result->cost += $subvalue->cost;
            $result->substructure[] = $subvalue;
        }
        return $result;
    }

    if (\is_object($var)) {
        $result = new _Variable($name, $key, $var, _Variable::TYPE_OBJECT, $scope);
        // #BC(5.4): use variable for array cast in order to create references
        $values = (array)$var;
        foreach ($values as $key => &$value) {
            $subname = \sprintf('%s->%s', $name, $key);
            $subvalue = namespace\_process_variable($value, $subname, $state, $key, $scope + 1);
            $result->cost += $subvalue->cost;
            $result->substructure[] = $subvalue;
        }
        return $result;
    }

    return new _Variable($name, $key, $var, _Variable::TYPE_SCALAR, $scope);
}


function _diff_variables(_Variable $from, _Variable $to, _DiffState $state) {
    if (namespace\_lcs_variables($from, $to, $lcs)) {
        namespace\_copy_value($state->diff, $from);
    }
    elseif (0 === $lcs) {
        namespace\_insert_value($state->diff, $to);
        namespace\_delete_value($state->diff, $from);
    }
    elseif (_Variable::TYPE_STRING === $from->type) {
        $edit = namespace\_lcs_array($from->substructure, $to->substructure);
        namespace\_build_diff_from_edit($from->substructure, $to->substructure, $edit, $state);
    }
    else {
        namespace\_copy_string($state->diff, $from->format_close());

        $edit = namespace\_lcs_array($from->substructure, $to->substructure);
        namespace\_build_diff_from_edit($from->substructure, $to->substructure, $edit, $state);

        if ($from->key === $to->key) {
            namespace\_copy_string($state->diff, $from->format_open());
        }
        else {
            namespace\_insert_string($state->diff, $to->format_open());
            namespace\_delete_string($state->diff, $from->format_open());
        }
    }
}


function _compare_variables(_Variable $from, _Variable $to) {
    return $from->key === $to->key && $from->value === $to->value;
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
            if (namespace\_lcs_variables($fvalue, $tvalue, $lcs)) {
                $lcs += $m[$f-1][$t-1];
            }
            else {
                $max = \max($m[$f-1][$t], $m[$f][$t-1]);
                if ($lcs) {
                    $max = \max($max, $m[$f-1][$t-1] + $lcs);
                }
                $lcs = $max;
            }
            $m[$f][$t] = $lcs;
        }
    }
    return new _Edit($flen, $tlen, $m);
}


function _lcs_variables(_Variable $from, _Variable $to, &$lcs) {
    if (namespace\_compare_variables($from, $to)) {
        $lcs = \max($from->cost, $to->cost);
        return true;
    }

    $lcs = 0;
    if ($from->type === $to->type) {
        if (_Variable::TYPE_STRING === $from->type) {
            if ($from->cost > 1 || $to->cost > 1) {
                $edit = namespace\_lcs_array($from->substructure, $to->substructure);
                $lcs = $edit->m[$edit->flen][$edit->tlen];
            }
        }
        elseif (
            _Variable::TYPE_ARRAY === $from->type
            || (_Variable::TYPE_OBJECT === $from->type
                && \get_class($from->value) === \get_class($to->value))
        ) {
            ++$lcs;
            if ($from->cost > 1 && $to->cost > 1) {
                $edit = namespace\_lcs_array($from->substructure, $to->substructure);
                $lcs += $edit->m[$edit->flen][$edit->tlen];
            }
        }
    }

    return false;
}


function _build_diff_from_edit(array $from, array $to, _Edit $edit, _DiffState $state) {
    $m = $edit->m;
    $f = $edit->flen;
    $t = $edit->tlen;

    while ($f || $t) {
        if ($f > 0 && $t > 0) {
            if (namespace\_compare_variables($from[$f-1], $to[$t-1])) {
                --$f;
                --$t;
                namespace\_copy_value($state->diff, $from[$f]);
            }
            elseif ($m[$f-1][$t] < $m[$f][$t] && $m[$f][$t-1] < $m[$f][$t]) {
                --$f;
                --$t;
                namespace\_diff_variables($from[$f], $to[$t], $state);
            }
            elseif ($m[$f][$t-1] >= $m[$f-1][$t]) {
                --$t;
                namespace\_insert_value($state->diff, $to[$t]);
            }
            else {
                --$f;
                namespace\_delete_value($state->diff, $from[$f]);
            }
        }
        elseif ($f) {
            --$f;
            namespace\_delete_value($state->diff, $from[$f]);
        }
        else {
            --$t;
            namespace\_insert_value($state->diff, $to[$t]);
        }
    }
}



function _copy_value(array &$diff, _Variable $value) {
    namespace\_copy_string($diff, $value->format());
}


function _insert_value(array &$diff, _Variable $value) {
    namespace\_insert_string($diff, $value->format());
}


function _delete_value(array &$diff, _Variable $value) {
    namespace\_delete_string($diff, $value->format());
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
    $name = \is_object($var) ? \get_class($var) : \gettype($var);
    return namespace\_format_variable_indented($var, $name);
}


function _format_variable_indented(&$var, $name, $indent = '') {
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
