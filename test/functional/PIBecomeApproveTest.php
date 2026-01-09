<?php

use UnityWebPortal\lib\UserFlag;

class PIBecomeApproveTest extends UnityWebPortalTestCase
{
    private function requestGroupCreation()
    {
        http_post(__DIR__ . "/../../webroot/panel/account.php", [
            "form_type" => "pi_request",
            "tos" => "agree",
            "account_policy" => "agree",
        ]);
    }

    private function cancelRequestGroupCreation()
    {
        http_post(__DIR__ . "/../../webroot/panel/account.php", [
            "form_type" => "cancel_pi_request",
        ]);
    }

    private function approveGroup($uid)
    {
        http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
            "form_type" => "req",
            "action" => "Approve",
            "uid" => $uid,
        ]);
    }

    public function testApprovePI()
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $pi_group = $USER->getPIGroup();
        try {
            $this->requestGroupCreation();
            $this->assertRequestedPIGroup(true);

            // $second_request_failed = false;
            // try {
            $this->requestGroupCreation();
            // } catch(Exception) {
            //     $second_request_failed = true;
            // }
            // $this->assertTrue($second_request_failed);
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

            // $third_request_failed = false;
            // try {
            $this->requestGroupCreation();
            // } catch(Exception) {
            //     $third_request_failed = true;
            // }
            // $this->assertTrue($third_request_failed);
            $this->assertRequestedPIGroup(false);
        } finally {
            $this->switchUser("Blank", validate: false);
            ensurePIGroupDoesNotExist();
            $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
        }
    }

    public function testReinstateDefunctGroup()
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("ResurrectedOwnerOfDefunctPIGroup");
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
                $pi_group->setIsDefunct(true);
                assert(!$user->isPI());
            }
        }
    }
}
