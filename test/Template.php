<?php

use PHPUnit\Framework\Attributes\DataProvider;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;

class FoobarTest extends UnityWebPortalTestCase
{
    public static function provider(): TRegxDataProvider
    {
        return TRegxDataProvider::list("foo", "bar");
    }

    #[DataProvider("provider")]
    public function testFoobar(string $x)
    {
        $this->assertTrue(true);
    }
}
