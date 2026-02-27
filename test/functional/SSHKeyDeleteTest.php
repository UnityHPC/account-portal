<?php

use PHPUnit\Framework\Attributes\DataProvider;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;

class SSHKeyDeleteTest extends UnityWebPortalTestCase
{
    static $initialKeys;

    public function setUp(): void
    {
        parent::setUp();
        global $USER;
        $this->switchUser("HasOneSshKey");
        self::$initialKeys = $USER->getSSHKeys();
    }

    private function deleteKey(string $key, bool $do_validate_messages = true): void
    {
        $this->http_post(
            __DIR__ . "/../../webroot/panel/account.php",
            [
                "form_type" => "delKey",
                "delKey" => $key,
            ],
            do_validate_messages: $do_validate_messages,
        );
    }

    public static function getGarbageKeys()
    {
        global $HTTP_HEADER_TEST_INPUTS;
        return TRegxDataProvider::list(...$HTTP_HEADER_TEST_INPUTS);
    }

    #[DataProvider("getGarbageKeys")]
    public function testDeleteKeyGarbageInput(string $key)
    {
        global $USER;
        try {
            $this->deleteKey($key, do_validate_messages: false);
            $this->assertEquals(self::$initialKeys, $USER->getSSHKeys());
            $this->assertMessageExists(UnityHTTPDMessageLevel::ERROR, "/.*/", "/Key not found/");
        } finally {
            callPrivateMethod($USER, "setSSHKeys", self::$initialKeys);
        }
    }

    public function testDeleteKey()
    {
        global $USER;
        try {
            $key = self::$initialKeys[0];
            $this->assertNotNull($key);
            $this->deleteKey($key);
            $this->assertEquals([], $USER->getSSHKeys());
        } finally {
            callPrivateMethod($USER, "setSSHKeys", self::$initialKeys);
        }
    }
}
