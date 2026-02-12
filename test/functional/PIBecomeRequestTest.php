<?php

use UnityWebPortal\lib\UnitySQL;

class PIBecomeRequestTest extends UnityWebPortalTestCase
{
    public function testRequestBecomePi()
    {
        global $USER, $SQL;
        $this->switchUser("Blank");
        $this->assertNumberPiBecomeRequests(0);
        try {
            http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ]);
            $this->assertNumberPiBecomeRequests(1);
            http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "cancel_pi_request",
            ]);
            $this->assertNumberPiBecomeRequests(0);
            http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ]);
            $this->assertNumberPiBecomeRequests(1);
            http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ]);
            $this->assertNumberPiBecomeRequests(1);
        } finally {
            if ($SQL->requestExists($USER, UnitySQL::REQUEST_BECOME_PI)) {
                $SQL->removeRequest($USER->uid, UnitySQL::REQUEST_BECOME_PI);
            }
        }
    }
}
