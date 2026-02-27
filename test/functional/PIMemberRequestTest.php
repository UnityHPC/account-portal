<?php

use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UnityHTTPD;
use PHPUnit\Framework\Attributes\DataProvider;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;
use UnityWebPortal\lib\UserFlag;

class PIMemberRequestTest extends UnityWebPortalTestCase
{
    private function requestMembership(string $gid_or_mail, bool $do_validate_messages = true)
    {
        $this->http_post(
            __DIR__ . "/../../webroot/panel/groups.php",
            [
                "form_type" => "addPIform",
                "pi" => $gid_or_mail,
                "tos" => "agree",
            ],
            do_validate_messages: $do_validate_messages,
        );
    }

    private function cancelRequest(string $gid)
    {
        $this->http_post(__DIR__ . "/../../webroot/panel/groups.php", [
            "form_type" => "cancelPIForm",
            "pi" => $gid,
        ]);
    }

    private function approveUserByPI(string $uid, string $gid)
    {
        global $USER;
        assert($USER->getPIGroup()->gid === $gid, "signed in user must be the group owner");
        $this->http_post(__DIR__ . "/../../webroot/panel/pi.php", [
            "form_type" => "userReq",
            "action" => "Approve",
            "uid" => $uid,
        ]);
    }

    private function approveUserByAdmin(string $uid, string $gid)
    {
        $this->switchUser("Admin");
        try {
            $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "reqChild",
                "action" => "Approve",
                "pi" => $gid,
                "uid" => $uid,
            ]);
        } finally {
            $this->switchBackUser();
        }
    }

    private function denyRequestByPI(string $uid)
    {
        $this->http_post(__DIR__ . "/../../webroot/panel/pi.php", [
            "form_type" => "userReq",
            "action" => "Deny",
            "uid" => $uid,
        ]);
    }

    private function denyRequestByAdmin(string $uid)
    {
        global $USER;
        $gid = $USER->getPIGroup()->gid;
        $this->switchUser("Admin");
        try {
            $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "reqChild",
                "action" => "Deny",
                "pi" => $gid,
                "uid" => $uid,
            ]);
        } finally {
            $this->switchBackUser();
        }
    }

    public function testRequestMembershipAndCancel()
    {
        global $USER, $SQL;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $this->switchUser("Blank");
        try {
            $this->requestMembership($pi_group->gid);
            $this->assertTrue($pi_group->requestExists($USER));
            $this->cancelRequest($pi_group->gid);
            $this->assertFalse($pi_group->requestExists($USER));
        } finally {
            if ($SQL->requestExists($USER->uid, $pi_group->gid)) {
                $SQL->removeRequest($USER->uid, $pi_group->gid);
            }
        }
    }

    public function testRequestMembershipBogus()
    {
        global $USER, $SQL;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $this->switchUser("Blank");
        try {
            UnityHTTPD::clearMessages();
            $this->requestMembership("asdlkjasldkj", do_validate_messages: false);
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::ERROR,
                "/^This PI Doesn't Exist$/",
                "/.*/",
            );
            $this->assertFalse($pi_group->requestExists($USER));
        } finally {
            if ($SQL->requestExists($USER->uid, $pi_group->gid)) {
                $SQL->removeRequest($USER->uid, $pi_group->gid);
            }
        }
    }

    public function testRequestMembershipDuplicate()
    {
        global $USER, $SQL;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $this->switchUser("Blank");
        $this->assertNumberRequests(0);
        try {
            $this->requestMembership($pi_group->gid);
            $this->assertNumberRequests(1);
            $this->requestMembership($pi_group->gid, do_validate_messages: false);
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::ERROR,
                "/.*/",
                "/already requested this/",
            );
            $this->assertNumberRequests(1);
        } finally {
            if ($SQL->requestExists($USER->uid, $pi_group->gid)) {
                $SQL->removeRequest($USER->uid, $pi_group->gid);
            }
        }
    }

    public function testRequestBecomePiAlreadyInGroup()
    {
        global $USER, $SQL;
        $this->switchUser("Normal");
        $pi_group_gids = $USER->getPIGroupGIDs();
        $this->assertGreaterThanOrEqual(1, count($pi_group_gids));
        $gid = $pi_group_gids[0];
        $this->assertNumberRequests(0);
        try {
            $this->requestMembership($gid, do_validate_messages: false);
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::ERROR,
                "/.*/",
                "/already in this PI group/",
            );
            $this->assertNumberRequests(0);
        } finally {
            if ($SQL->requestExists($USER->uid, $gid)) {
                $SQL->removeRequest($USER->uid, $gid);
            }
        }
    }

    public static function providerApprove(): TRegxDataProvider
    {
        return TRegxDataProvider::list("approveUserByPI", "approveUserByAdmin");
    }

    #[DataProvider("providerApprove")]
    public function testApproveNonexistentRequest($methodName)
    {
        global $USER;
        $this->switchUser("Blank");
        $uid = $USER->uid;
        $this->switchUser("EmptyPIGroupOwner");
        $piGroup = $USER->getPIGroup();
        try {
            $this->expectException(Exception::class); // FIXME more specific exception type
            $this->$methodName($uid, $piGroup->gid);
        } finally {
            $this->switchUser("Blank", validate: false);
            ensureUserNotInPIGroup($piGroup);
        }
    }

    #[DataProvider("providerApprove")]
    public function testApproveMember($methodName)
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_uid = $USER->uid;
        $pi_group = $USER->getPIGroup();
        $gid = $pi_group->gid;
        $this->switchUser("Blank");
        try {
            $pi_group->newUserRequest($USER);
            $this->assertTrue($pi_group->requestExists($USER));
            $this->assertRequestedMembership(true, $gid);

            $approve_uid = $SSO["user"];
            $this->switchUser("EmptyPIGroupOwner", validate: false);
            $this->$methodName($approve_uid, $gid);
            $this->switchUser("Blank", validate: false);

            $this->assertFalse($pi_group->requestExists($USER));
            $this->assertRequestedMembership(false, $gid);
            $this->assertTrue($pi_group->memberUIDExists($USER->uid));
            $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
        } finally {
            $this->switchUser("Blank", validate: false);
            ensureUserNotInPIGroup($pi_group);
            $this->assertGroupMembers($pi_group, [$pi_uid]);
            $SQL->removeRequest($USER->uid, $gid);
        }
    }

    public static function providerDeny()
    {
        return TRegxDataProvider::list("denyRequestByPI", "denyRequestByAdmin");
    }

    #[DataProvider("providerDeny")]
    public function testDenyRequest(string $methodName)
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $requestedUser = $USER;
        $this->switchUser("EmptyPIGroupOwner");
        $pi = $USER;
        $piGroup = $USER->getPIGroup();
        $this->assertEmpty($piGroup->getRequests());
        $this->assertEqualsCanonicalizing([$pi->uid], $piGroup->getMemberUIDs());
        try {
            $piGroup->newUserRequest($requestedUser);
            $this->assertNotEmpty($piGroup->getRequests());
            $this->$methodName($requestedUser->uid);
            $this->assertEmpty($piGroup->getRequests());
            $this->assertEqualsCanonicalizing([$pi->uid], $piGroup->getMemberUIDs());
        } finally {
            $SQL->removeRequest($requestedUser->uid, $piGroup->gid);
        }
    }
}
