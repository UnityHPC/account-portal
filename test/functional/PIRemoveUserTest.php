<?php

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityGroup;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;

class PIRemoveUserTest extends UnityWebPortalTestCase
{
    private function removeUserByPI(string $uid, string $gid)
    {
        global $USER;
        assert($USER->getPIGroup()->gid === $gid, "signed in user must be the group owner");
        $this->http_post(__DIR__ . "/../../webroot/panel/pi.php", [
            "form_type" => "remUser",
            "uid" => $uid,
        ]);
    }

    private function removeUserByAdmin(string $uid, string $gid)
    {
        global $USER;
        $this->switchUser("Admin");
        try {
            $this->http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "remUserChild",
                "pi" => $gid,
                "uid" => $uid,
            ]);
        } finally {
            $this->switchBackUser();
        }
    }

    public static function provider(): TRegxDataProvider
    {
        return TRegxDataProvider::list("removeUserByPI", "removeUserByAdmin");
    }

    #[DataProvider("provider")]
    public function testRemoveUser($methodName)
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("NormalPI");
        $pi = $USER;
        $piUid = $USER->uid;
        $piGroup = $USER->getPIGroup();
        $memberUIDs = $piGroup->getMemberUIDs();
        // the ordering of the uids in getGroupMemberUIDs is different each time
        // use a linear search to find a user who is not the PI
        $memberToDelete = null;
        foreach ($memberUIDs as $uid) {
            if ($uid != $piUid) {
                $memberToDelete = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
                break;
            }
        }
        assert($memberToDelete !== null);
        $this->assertNotEquals($pi->uid, $memberToDelete->uid);
        $this->assertTrue($memberToDelete->getFlag(UserFlag::QUALIFIED));
        $this->assertTrue($piGroup->memberUIDExists($memberToDelete->uid));
        try {
            $this->$methodName($memberToDelete->uid, $piGroup->gid);
            $this->assertFalse($piGroup->memberUIDExists($memberToDelete->uid));
            $this->assertFalse($memberToDelete->getFlag(UserFlag::QUALIFIED));
        } finally {
            if (!$piGroup->memberUIDExists($memberToDelete->uid)) {
                $piGroup->newUserRequest($memberToDelete);
                $piGroup->approveUser($memberToDelete);
            }
        }
    }

    #[DataProvider("provider")]
    public function testRemovePIFromTheirOwnGroup($methodName)
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("NormalPI");
        $pi = $USER;
        $piGroup = $USER->getPIGroup();
        $this->expectException(Exception::class);
        try {
            $this->$methodName($piGroup->getOwner()->uid, $piGroup->gid);
            $this->assertTrue($piGroup->memberUIDExists($pi->uid));
        } finally {
            if (!$piGroup->memberUIDExists($pi->uid)) {
                $piGroup->newUserRequest($pi);
                $piGroup->approveUser($pi);
            }
        }
    }

    public function testRemoveMemberAlsoRemovesManager()
    {
        global $USER;
        $this->switchUser("CourseGroupOwner");
        $group = $USER->getPIGroup();
        $manager_uids = $group->getManagerUIDs();
        $this->assertNotEmpty($manager_uids);
        $manager_uid = $manager_uids[0];
        try {
            $group->removeMemberUID($manager_uid);
            $this->assertFalse($group->memberUIDExists($manager_uid));
            $this->assertFalse($group->managerUIDExists($manager_uid));
        } finally {
            if (!$group->memberUIDExists($manager_uid)) {
                $group->addMemberUID($manager_uid);
            }
            if (!$group->managerUIDExists($manager_uid)) {
                $group->addManagerUID($manager_uid);
            }
        }
    }

    public function testManagerRemovesThemselfRedirectsToGroups()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("CourseGroupManager");
        $managed_groups = $LDAP->getNonDisabledPIGroupGIDsWithManagerUID($USER->uid);
        $this->assertNotEmpty($managed_groups);
        $gid = $managed_groups[0];
        $group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        try {
            $output = $this->http_post(
                __DIR__ . "/../../webroot/panel/pi.php",
                ["form_type" => "remUser", "uid" => $USER->uid],
                ["gid" => $gid],
            );
            $this->assertMatchesRegularExpression("/panel\/groups\.php/", $output);
        } finally {
            if (!$group->memberUIDExists($USER->uid)) {
                $group->addMemberUID($USER->uid);
            }
            if (!$group->managerUIDExists($USER->uid)) {
                $group->addManagerUID($USER->uid);
            }
        }
    }
}
