<?php

use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityGroup;

class PIBecomeRequestTest extends UnityWebPortalTestCase
{
    private function requestGroupCreation($do_validate_messages = true)
    {
        $this->http_post(
            __DIR__ . "/../../webroot/panel/account.php",
            [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ],
            do_validate_messages: $do_validate_messages,
        );
    }

    private function cancelRequestGroupCreation()
    {
        $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
            "form_type" => "cancel_pi_request",
        ]);
    }

    private function approveGroup($uid, $gid)
    {
        $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
            "form_type" => "req",
            "action" => "Approve",
            "pi" => $gid,
            "uid" => $uid,
        ]);
    }

    public function testRequestBecomePi()
    {
        global $USER, $SQL;
        $this->switchUser("Blank");
        $gid = UnityGroup::ownerUID2NamesakeGID($USER->uid);
        $request = "$USER->uid:$gid";
        $this->assertNumberPiBecomeRequests(0);
        try {
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ]);
            $this->assertNumberPiBecomeRequests(1);
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "cancel_pi_request",
            ]);
            $this->assertNumberPiBecomeRequests(0);
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "pi_request",
                "tos" => "agree",
                "account_policy" => "agree",
            ]);
            $this->assertNumberPiBecomeRequests(1);
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "pi_request",
                    "tos" => "agree",
                    "account_policy" => "agree",
                ],
                do_validate_messages: false,
            );
            $this->assertNumberPiBecomeRequests(1);
        } finally {
            if ($SQL->requestExists($request, UnitySQL::REQUEST_CREATE_PI_GROUP)) {
                $SQL->removeRequest($request, UnitySQL::REQUEST_CREATE_PI_GROUP);
            }
        }
    }

    public function testApprovePI()
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER;
        $this->switchUser("Blank");
        $pi_group = $USER->getNamesakePIGroup();
        try {
            $this->requestGroupCreation();
            $this->assertRequestedPIGroup(true);

            $this->requestGroupCreation(do_validate_messages: false);
            $this->assertMessageExists(UnityHTTPDMessageLevel::ERROR, "/.*/", "/already exists/");
            UnityHTTPD::clearMessages();
            $this->assertRequestedPIGroup(true);

            $this->cancelRequestGroupCreation();
            $this->assertRequestedPIGroup(false);

            $this->requestGroupCreation();
            $this->assertRequestedPIGroup(true);

            $approve_uid = $SSO["user"];
            $this->switchUser("Admin");
            $this->approveGroup($approve_uid, $pi_group->gid);
            $this->switchUser("Blank", validate: false);

            $this->assertRequestedPIGroup(false);
            $this->assertTrue($pi_group->exists());
            $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));

            $this->requestGroupCreation(do_validate_messages: false);
            $this->assertMessageExists(UnityHTTPDMessageLevel::ERROR, "/.*/", "/Already a PI/");
            UnityHTTPD::clearMessages();
            $this->assertRequestedPIGroup(false);
        } finally {
            ensurePIGroupDoesNotExist($pi_group->gid);
            $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
        }
    }

    public function testReenableGroup()
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER;
        $this->switchUser("ReenabledOwnerOfDisabledPIGroup");
        $this->assertFalse($USER->isPI());
        $user = $USER;
        $pi_group = $USER->getNamesakePIGroup();
        $approve_uid = $USER->uid;
        try {
            $this->requestGroupCreation();
            $this->assertRequestedPIGroup(true);
            $this->switchUser("Admin");
            $this->approveGroup($approve_uid, $pi_group->gid);
            $this->assertTrue($user->isPI());
        } finally {
            if ($pi_group->memberUIDExists($approve_uid)) {
                $pi_group->removeMemberUID($approve_uid);
                callPrivateMethod($pi_group, "setIsDisabled", true);
                assert(!$user->isPI());
            }
        }
    }

    public function testDenyPiBecomeRequest()
    {
        global $USER, $LDAP, $SQL, $MAILER;
        $this->switchUser("Blank");
        $uid = $USER->uid;
        $piGroup = $USER->getNamesakePIGroup();
        $this->assertFalse($piGroup->exists());
        $request = "$USER->uid:$piGroup->gid";
        $this->assertFalse($SQL->requestExists($request, UnitySQL::REQUEST_CREATE_PI_GROUP));
        $piGroup->requestGroup($USER->uid);
        try {
            $this->assertTrue($SQL->requestExists($request, UnitySQL::REQUEST_CREATE_PI_GROUP));
            $this->switchUser("Admin");
            $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "req",
                "action" => "Deny",
                "uid" => $uid,
                "pi" => $piGroup->gid,
            ]);
            $this->assertFalse($piGroup->exists());
            $this->assertFalse($SQL->requestExists($request, UnitySQL::REQUEST_CREATE_PI_GROUP));
        } finally {
            if ($SQL->requestExists($request, UnitySQL::REQUEST_CREATE_PI_GROUP)) {
                $SQL->removeRequest($request, UnitySQL::REQUEST_CREATE_PI_GROUP);
            }
        }
    }
}
