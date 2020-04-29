<?php

class TestUsesAnonymousClass {
    public function test() {
        $this->test_anonymous_class(new class {});
    }

    private function test_anonymous_class($class) {}
}
