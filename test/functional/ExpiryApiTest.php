<?php

class ExpiryApiTest extends UnityWebPortalTestCase
{
    public function testExpiryAPI()
    {
        global $USER, $SQL;

        $this->switchUser("Normal");
        // set last login to one day after epoch
        callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, 1 * 24 * 60 * 60);
        $this->assertEquals(CONFIG["expiry"]["idlelock_day"], 4);
        $this->assertEquals(CONFIG["expiry"]["disable_day"], 8);
        $output_str = http_get(__DIR__ . "/../../webroot/lan/api/expiry.php", [
            "uid" => $USER->uid,
        ]);
        $output_data = _json_decode($output_str, associative: true);
        $this->assertEquals(
            [
                "uid" => $USER->uid,
                "idlelock_day" => "1970/01/05",
                "disable_day" => "1970/01/09",
            ],
            $output_data,
        );
    }
}
