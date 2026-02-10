<?php

class ExpiryApiTest extends UnityWebPortalTestCase
{
    public function testExpiryAPI()
    {
        global $USER;

        $this->switchUser("Normal");
        $expected_idlelock_timestamp = time() + CONFIG["expiry"]["idlelock_day"] * 24 * 60 * 60;
        $expected_disable_timestamp = time() + CONFIG["expiry"]["disable_day"] * 24 * 60 * 60;
        $expected_idlelock_day = date("Y/m/d", $expected_idlelock_timestamp);
        $expected_disable_day = date("Y/m/d", $expected_disable_timestamp);
        $output_str = http_get(__DIR__ . "/../../webroot/lan/api/expiry.php", [
            "uid" => $USER->uid,
        ]);
        $output_data = _json_decode($output_str, associative: true);
        $this->assertEqualsCanonicalizing(
            [
                "uid" => $USER->uid,
                "idlelock_day" => $expected_idlelock_day,
                "disable_day" => $expected_disable_day,
            ],
            $output_data,
        );
    }
}
