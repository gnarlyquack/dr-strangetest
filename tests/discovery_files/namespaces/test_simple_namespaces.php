<?php

namespace ns2;

class TestNamespace {
    public function test() {}
}

/* ensure the namespace operator isn't confused for a namespace declaration */
const FOO = 'foo';
namespace\FOO;


namespace/* Yup, this is valid! */
         // as is this!
         ns3;

class TestNamespace {
    public function test() {}
}
