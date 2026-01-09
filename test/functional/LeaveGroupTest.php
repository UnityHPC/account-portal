<?php
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityGroup;

class LeaveGroupTest extends UnityWebPortalTestCase
{
    public function testLeaveGroupDequalified()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Normal");
        $pi_gids = $USER->getPIGroupGIDs();
        $this->assertEquals(1, count($pi_gids));
        $gid = $pi_gids[0];
        $pi_group = new UnityGroup($gid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $this->assertTrue($pi_group->memberUIDExists($USER->uid));
        $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
        try {
            http_post(__DIR__ . "/../../webroot/panel/groups.php", [
                "form_type" => "removePIForm",
                "pi" => $gid,
            ]);
            $this->assertFalse($pi_group->memberUIDExists($USER->uid));
            $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
        } finally {
            if (!$pi_group->memberUIDExists($USER->uid)) {
                $pi_group->newUserRequest($USER);
                $pi_group->approveUser($USER);
            }
            $this->assertTrue($pi_group->memberUIDExists($USER->uid));
            $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
        }
    }
}
