<?php
use UnityWebPortal\lib\UserFlag;

class PiDisbandTest extends UnityWebPortalTestCase
{
    public function testDisbandGroupByAdmin()
    {
        global $USER, $LDAP;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $memberuids_before = $pi_group->getMemberUIDs();
        $this->assertFalse($pi_group->getIsDefunct());
        $this->assertNotEmpty($pi_group->getMemberUIDs());
        $this->assertTrue($pi_group->getOwner()->getFlag(UserFlag::QUALIFIED));
        try {
            $this->switchUser("Admin");
            http_post(__DIR__ . "/../../webroot/admin/pi-mgmt.php", [
                "form_type" => "disband",
                "pi" => $pi_group->gid,
            ]);
            $this->assertTrue($pi_group->getIsDefunct());
            $this->assertEmpty($pi_group->getMemberUIDs());
            $this->assertFalse($pi_group->getOwner()->getFlag(UserFlag::QUALIFIED));
        } finally {
            $entry = $LDAP->getPIGroupEntry($pi_group->gid);
            $entry->setAttribute("memberuid", $memberuids_before);
            $entry->setAttribute("isDefunct", "FALSE");
            $pi_group->getOwner()->setFlag(UserFlag::QUALIFIED, true);
        }
    }

    public function testDisbandGroupByPI()
    {
        global $USER, $LDAP;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $memberuids_before = $pi_group->getMemberUIDs();
        $this->assertFalse($pi_group->getIsDefunct());
        $this->assertNotEmpty($pi_group->getMemberUIDs());
        $this->assertTrue($pi_group->getOwner()->getFlag(UserFlag::QUALIFIED));
        try {
            http_post(__DIR__ . "/../../webroot/panel/pi.php", ["form_type" => "disband"]);
            $this->assertTrue($pi_group->getIsDefunct());
            $this->assertEmpty($pi_group->getMemberUIDs());
            $this->assertFalse($pi_group->getOwner()->getFlag(UserFlag::QUALIFIED));
        } finally {
            $entry = $LDAP->getPIGroupEntry($pi_group->gid);
            $entry->setAttribute("memberuid", $memberuids_before);
            $entry->setAttribute("isDefunct", "FALSE");
            $pi_group->getOwner()->setFlag(UserFlag::QUALIFIED, true);
        }
    }

    public function testMemberBecomesUnqualified()
    {
        global $USER, $LDAP;
        $this->switchUser("Blank");
        $new_user = $USER;
        $this->assertFalse($new_user->getFlag(UserFlag::QUALIFIED));
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $memberuids_before = $pi_group->getMemberUIDs();
        try {
            $pi_group->newUserRequest($new_user);
            $pi_group->approveUser($new_user);
            $this->assertTrue($new_user->getFlag(UserFlag::QUALIFIED));
            http_post(__DIR__ . "/../../webroot/panel/pi.php", ["form_type" => "disband"]);
            $this->assertFalse($new_user->getFlag(UserFlag::QUALIFIED));
        } finally {
            $entry = $LDAP->getPIGroupEntry($pi_group->gid);
            $entry->setAttribute("memberuid", $memberuids_before);
            $entry->setAttribute("isDefunct", "FALSE");
            $pi_group->getOwner()->setFlag(UserFlag::QUALIFIED, true);
            $new_user->setFlag(UserFlag::QUALIFIED, false);
        }
    }
}
