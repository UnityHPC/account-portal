<?php

use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;
use PHPUnit\Framework\Attributes\DataProvider;

class PIRemoveUserTest extends UnityWebPortalTestCase
{
    // two different ways to remove a user from a PI group
    public static function provider()
    {
        return [
            [
                function ($uid, $gid) {
                    global $USER;
                    assert($USER->getPIGroup()->gid === $gid);
                    http_post(__DIR__ . "/../../webroot/panel/pi.php", [
                        "form_type" => "remUser",
                        "uid" => $uid,
                    ]);
                },
            ],
            [
                function ($uid, $gid) {
                    http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                        "form_type" => "remUserChild",
                        "pi" => $gid,
                        "uid" => $uid,
                    ]);
                },
            ],
        ];
    }

    #[DataProvider("provider")]
    public function testRemoveUser($func)
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
                if ($memberToDelete->hasRequestedAccountDeletion()) {
                    continue;
                }
                break;
            }
        }
        $this->assertNotEquals($pi->uid, $memberToDelete->uid);
        $this->assertTrue($memberToDelete->getFlag(UserFlag::QUALIFIED));
        $this->assertTrue($piGroup->memberUIDExists($memberToDelete->uid));
        try {
            $func($memberToDelete->uid, $piGroup->gid);
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
    public function testRemovePIFromTheirOwnGroup($func)
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("NormalPI");
        $pi = $USER;
        $piGroup = $USER->getPIGroup();
        $this->expectException(Exception::class);
        try {
            $func($pi->uid, $piGroup->gid);
            $this->assertTrue($piGroup->memberUIDExists($pi->uid));
        } finally {
            if (!$piGroup->memberUIDExists($pi->uid)) {
                $piGroup->newUserRequest($pi);
                $piGroup->approveUser($pi);
            }
        }
    }
}
