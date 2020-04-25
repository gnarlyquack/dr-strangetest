<?php

namespace ns1\ns1 {

class TestNamespace {
    public function test() {}
}

/* ensure the namespace operator isn't confused for a namespace declaration */
const FOO = 'foo';
namespace\FOO;

}

namespace
    ns1 // parent namespace
    \   // namespace separator
    ns2 // sub namespace
{

class TestNamespace {
    public function test() {}
}

const FOO = 'foo';
namespace\FOO;

}

namespace { // global namespace

class TestNamespace {
    public function test() {}
}

const FOO = 'foo';
namespace\FOO;

}
