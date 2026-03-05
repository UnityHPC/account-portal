<?php

class BumpLastLoginApiTest extends UnityWebPortalTestCase
{
    public function testBumpLastLoginApi()
    {
        global $USER, $SQL;
        $this->switchUser("Blank");
        $last_login_before = $SQL->getUserLastLogin($USER->uid);
        try {
            $this_year = date("Y");
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, 1 * 24 * 60 * 60);
            $old_timestamp_year = date("Y", $SQL->getUserLastLogin($USER->uid));
            $this->assertNotEquals($this_year, $old_timestamp_year);
            $this->http_post(
                __DIR__ . "/../../webroot/lan/api/bump-last-login.php",
                [],
                query_parameters: ["uid" => $USER->uid],
                bearer_token: "phpunit_api_key",
            );
            $new_timestamp_year = date("Y", $SQL->getUserLastLogin($USER->uid));
            $this->assertEquals($this_year, $new_timestamp_year);
        } finally {
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $last_login_before);
        }
    }
}
