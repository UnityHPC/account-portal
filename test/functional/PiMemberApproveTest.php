<?php

use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;

class PIMemberApproveTest extends UnityWebPortalTestCase
{
    private function approveUserByPI(string $uid, string $gid)
    {
        global $USER;
        assert($USER->getPIGroup()->gid === $gid, "signed in user must be the group owner");
        http_post(__DIR__ . "/../../webroot/panel/pi.php", [
            "form_type" => "userReq",
            "action" => "Approve",
            "uid" => $uid,
        ]);
    }

    private function approveUserByAdmin(string $uid, string $gid)
    {
        global $USER;
        $this->switchUser("Admin");
        try {
            http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "reqChild",
                "action" => "Approve",
                "pi" => $gid,
                "uid" => $uid,
            ]);
        } finally {
            $this->switchBackUser();
        }
    }

    public static function provider(): TRegxDataProvider
    {
        return TRegxDataProvider::list("approveUserByPI", "approveUserByAdmin");
    }

    #[DataProvider("provider")]
    public function testApproveNonexistentRequest($methodName)
    {
        global $USER;
        $this->switchUser("Blank");
        $uid = $USER->uid;
        $this->switchUser("EmptyPIGroupOwner");
        $piGroup = $USER->getPIGroup();
        try {
            $this->expectException(Exception::class); // FIXME more specific exception type
            call_user_func([self::class, $methodName], $uid, $piGroup->gid);
        } finally {
            $this->switchUser("Blank", validate: false);
            ensureUserNotInPIGroup($piGroup);
        }
    }

    #[DataProvider("provider")]
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
            call_user_func([self::class, $methodName], $approve_uid, $gid);
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
}
