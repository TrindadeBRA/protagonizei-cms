<?php

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase {
    public function test_soma() {
        $this->assertEquals(
            4, 
            2 + 2,
            '4 deve ser igual a 2 + 2'
        );
    }
}
