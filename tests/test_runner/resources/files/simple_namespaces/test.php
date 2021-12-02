<?php

namespace ns02;

class TestNamespaces {
    public function test() {}
}

/* ensure the namespace operator isn't confused for a namespace declaration */
const BAR = 'bar';
$bar = namespace\BAR;


namespace/* Yup, this is valid! */
         // as is this!
         ns03;

class TestNamespaces {
    public function test() {}
}
