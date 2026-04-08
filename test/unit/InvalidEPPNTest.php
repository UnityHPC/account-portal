<?php

use UnityWebPortal\lib\UnitySSO;
use UnityWebPortal\lib\exceptions\SSOException;
use PHPUnit\Framework\Attributes\DataProvider;

class InvalidEPPNTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        return [["", false], ["a", false], ["a@b", true], ["a@b@c", false]];
    }

    #[DataProvider("provider")]
    public function testInitGetSSO(string $eppn, bool $is_valid): void
    {
        $original_server = $_SERVER;
        if (!$is_valid) {
            $this->expectException(SSOException::class);
        }
        try {
            $_SERVER["REMOTE_USER"] = $eppn;
            $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
            $_SERVER["eppn"] = $eppn;
            $_SERVER["givenName"] = "foo";
            $_SERVER["sn"] = "bar";
            UnitySSO::getSSO();
        } finally {
            $_SERVER = $original_server;
        }
        $this->assertTrue(true); // if $is_valid, there are no other assertions
    }
}
