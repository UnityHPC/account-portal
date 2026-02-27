<?php
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityGroup;

class LeaveGroupTest extends UnityWebPortalTestCase
{
    public function testLeaveGroupDisqualified()
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
            $this->http_post(__DIR__ . "/../../webroot/panel/groups.php", [
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

    // if an admin goes messing around with LDAP entries by hand and also forgets to update the
    // qualified users group, the portal should update the user's qualified status the next time
    // they log in
    public function testRemovedFromGroupManuallyDisqualifiedOnLogin()
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
            $old_memberuids = $pi_group->getMemberUIDs();
            $new_memberuids = array_values(
                array_filter($old_memberuids, fn($x) => $x !== $USER->uid),
            );
            $pi_group_entry = $LDAP->getPIGroupEntry($pi_group->gid);
            $pi_group_entry->setAttribute("memberuid", $new_memberuids);
            $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
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
