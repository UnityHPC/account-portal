<?php

use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;

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

    private function approveGroup($uid)
    {
        $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
            "form_type" => "req",
            "action" => "Approve",
            "uid" => $uid,
        ]);
    }

    public function testRequestBecomePi()
    {
        global $USER, $SQL;
        $this->switchUser("Blank");
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
            if ($SQL->requestExists($USER, UnitySQL::REQUEST_BECOME_PI)) {
                $SQL->removeRequest($USER->uid, UnitySQL::REQUEST_BECOME_PI);
            }
        }
    }

    public function testApprovePI()
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $pi_group = $USER->getPIGroup();
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
            $this->approveGroup($approve_uid);
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
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("ReenabledOwnerOfDisabledPIGroup");
        $this->assertFalse($USER->isPI());
        $user = $USER;
        $pi_group = $USER->getPIGroup();
        $approve_uid = $USER->uid;
        try {
            $this->requestGroupCreation();
            $this->assertRequestedPIGroup(true);
            $this->switchUser("Admin");
            $this->approveGroup($approve_uid);
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
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $piGroup = $USER->getPIGroup();
        $this->assertFalse($piGroup->exists());
        $this->assertFalse($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
        $piGroup->requestGroup();
        try {
            $this->assertTrue($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
            $this->switchUser("Admin");
            $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "req",
                "action" => "Deny",
                "uid" => $piGroup->getOwner()->uid,
            ]);
            $this->switchBackUser();
            $this->assertFalse($piGroup->exists());
            $this->assertFalse($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
        } finally {
            if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                $SQL->removeRequest($USER->uid, UnitySQL::REQUEST_BECOME_PI);
            }
        }
    }
}
