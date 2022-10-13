<?php
// This file is part of Dr. Strangetest. It is subject to the license terms in
// the LICENSE.txt file found in the top-level directory of this distribution.
// No part of this project, including this file, may be copied, modified,
// propagated, or distributed except according to the terms contained in the
// LICENSE.txt file.

use strangetest\Context;
use strangetest\NamespaceInfo;


// helper functions

function resolve_function($name)
{
    $default_namespace = new NamespaceInfo('example\\');
    $default_namespace->use = array('three' => 'one\\two\\three');
    $default_namespace->use_function = array('three' => 'one\\two\\three');

    $result = strangetest\resolve_test_name($name, $default_namespace);
    return $result;
}


function resolve_method($name)
{
    $default_namespace = new NamespaceInfo('example\\');
    $default_namespace->use = array('three' => 'one\\two\\three');
    $default_namespace->use_function = array('three' => 'one\\two\\three');

    $default_class = 'example\\Example';
    $result = strangetest\resolve_test_name($name, $default_namespace, $default_class);
    return $result;
}



// helper assertions

function assert_function($name, $expected, Context $context)
{
    $context->subtest(
        function() use ($name, $expected)
        {
            strangetest\assert_identical(resolve_function($name), $expected);
        }
    );
}


function assert_invalid_function($name, $expected, Context $context)
{
    $assertion = function() use ($name, $expected)
    {
        $actual = strangetest\assert_throws(
            'Exception',
            function() use ($name) { resolve_function($name); }
        );
        strangetest\assert_identical($actual->getMessage(), $expected);
    };
    $context->subtest($assertion, 'Invalid function failed');
}


function assert_method($name, $expected, Context $context)
{
    $context->subtest(
        function() use ($name, $expected)
        {
            strangetest\assert_identical(resolve_method($name), $expected);
        }
    );
}


function assert_invalid_method($name, $expected, Context $context)
{
    $assertion = function() use ($name, $expected)
    {
        $actual = strangetest\assert_throws(
            'Exception',
            function() use ($name) { resolve_method($name); }
        );
        strangetest\assert_identical($actual->getMessage(), $expected);
    };
    $context->subtest($assertion, 'Invalid method failed');
}



// tests

function test_qualified_function_resolves_to_itself(Context $context)
{
    $name = 'foo\\bar';

    assert_function($name, 'example\\foo\\bar', $context);
    assert_method($name, 'example\\foo\\bar', $context);
}


function test_unqualified_function_resolves_to_current_class_or_namespace(Context $context)
{
    $name = 'bar';

    assert_function($name, 'example\\bar', $context);
    assert_method($name, 'example\\example::bar', $context);
}


function test_globally_namespaced_function_resolves_to_global_namespace(Context $context)
{
    $name = '\\bar';

    assert_function($name, 'bar', $context);
    assert_method($name, 'bar', $context);
}


function test_qualified_method_resolves_to_itself(Context $context)
{
    $name = 'foo\\Foo::bar';

    assert_function($name, 'example\\foo\\foo::bar', $context);
    assert_method($name, 'example\\foo\\foo::bar', $context);
}


function test_unqualified_method_resolves_to_current_namespace(Context $context)
{
    $name = 'Foo::bar';

    assert_function($name, 'example\\foo::bar', $context);
    assert_method($name, 'example\\foo::bar', $context);
}


function test_globally_namespaced_method_resolves_to_global_namespace(Context $context)
{
    $name = '\\Foo::bar';

    assert_function($name, 'foo::bar', $context);
    assert_method($name, 'foo::bar', $context);
}


function test_empty_class_resolves_to_function_in_current_namespace(Context $context)
{
    $name = '::bar';
    $error = '::bar: Force-resolving an identifier to a function can only be done from within a method';

    assert_invalid_function($name, $error, $context);
    assert_method($name, 'example\\bar', $context);
}


function test_globally_namespaced_name_with_empty_class_is_an_error(Context $context)
{
    $name = '\\::bar';
    $error = "\\::bar: Invalid identifier character ':' at position 1";

    assert_invalid_function($name, $error, $context);
    assert_invalid_method($name, $error, $context);
}


function test_namespaced_name_with_empty_class_is_an_error(Context $context)
{
    $name = '\\foo\\::bar';
    $error = "\\foo\\::bar: Invalid identifier character ':' at position 5";

    assert_invalid_function($name, $error, $context);
    assert_invalid_method($name, $error, $context);
}


function test_function_resolves_to_used_function(Context $context)
{
    $name = 'three';

    assert_function($name, 'one\\two\\three', $context);
    assert_method($name, 'example\\example::three', $context);
}


function test_unbound_function_resolves_to_used_function(Context $context)
{
    $name = '::three';
    $error = '::three: Force-resolving an identifier to a function can only be done from within a method';

    assert_invalid_function($name, $error, $context);
    assert_method($name, 'one\\two\\three', $context);
}


function test_class_resolves_to_used_class(Context $context)
{
    $name = 'three::test';

    assert_function($name, 'one\\two\\three::test', $context);
    assert_method($name, 'one\\two\\three::test', $context);
}


function test_namespace_resolves_to_used_namespace(Context $context)
{
    $name = 'three\\foo::test';

    assert_function($name, 'one\\two\\three\\foo::test', $context);
    assert_method($name, 'one\\two\\three\\foo::test', $context);
}
