<?php

use PHPUnit\Framework\Attributes\DataProvider;

class AjaxSshValidateTest extends UnityWebPortalTestCase
{
    public static function providerTestSshValidate()
    {
        // sanity check only, see UnityHTTPDTest for more comprehensive test cases
        return [
            [false, "foobar"],
            [
                true,
                "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a",
            ],
        ];
    }

    #[DataProvider("providerTestSshValidate")]
    public function testSshValidate(bool $is_valid, string $pubkey)
    {
        $this->switchUser("Normal");
        $output_str = $this->http_post(__DIR__ . "/../../webroot/js/ajax/ssh_validate.php", [
            "key" => $pubkey,
        ]);
        $output = _json_decode($output_str, true);
        $this->assertEquals($is_valid, $output["is_valid"]);
    }
}
