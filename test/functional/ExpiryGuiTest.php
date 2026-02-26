<?php

use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;


class ExpiryGuiTest extends UnityWebPortalTestCase
{
    public function testIdleUnlock()
    {
        global $USER, $LDAP;
        $this->switchUser("Admin");
        $idle_locked_group = $LDAP->userFlagGroups["idlelocked"];
        $members_before = $idle_locked_group->getMemberUIDs();
        try {
            $this->switchUser("IdleLocked");
            $this->assertContains($USER->uid, $members_before);
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/^Account Unlocked$/",
                "/.*inactivity.*/",
            );
            $members_after = $idle_locked_group->getMemberUIDs();
            $this->assertNotContains($USER->uid, $members_after);
        } finally {
            if (!$USER->getFlag(UserFlag::IDLELOCKED)) {
                $USER->setFlag(UserFlag::IDLELOCKED, true);
            }
            $members_finally = $idle_locked_group->getMemberUIDs();
            $this->assertContains($USER->uid, $members_finally);
        }
    }
}
