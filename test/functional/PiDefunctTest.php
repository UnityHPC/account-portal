<?php

class PiDefunctTest extends UnityWebPortalTestCase
{
    public function testGetDefunctSetDefunct()
    {
        global $USER, $LDAP;
        $this->switchUser("NormalPI");
        $pi_group = $USER->getPIGroup();
        $entry = $LDAP->getPIGroupEntry($pi_group->gid);
        $this->assertEquals([], $entry->getAttribute("isDefunct"));
        try {
            $pi_group->setIsDefunct(false);
            $this->assertFalse($pi_group->getIsDefunct());
            $pi_group->setIsDefunct(true);
            $this->assertTrue($pi_group->getIsDefunct());
            $entry->removeAttribute("isDefunct");
            $this->assertFalse($pi_group->getIsDefunct());
        } finally {
            if ($entry->hasAttribute("isDefunct")) {
                $entry->removeAttribute("isDefunct");
            }
        }
    }

    public function testPIMgmtShowsBothGroupsWithDefunctAttributeSetFalseAndUnset()
    {
        global $USER, $LDAP;
        $this->switchUser("NormalPI");
        $pi_group = $USER->getPIGroup();
        $entry = $LDAP->getPIGroupEntry($pi_group->gid);
        $this->assertEquals([], $entry->getAttribute("isDefunct"));
        $this->assertStringContainsString(
            $pi_group->gid,
            http_get(__DIR__ . "/../../webroot/admin/pi-mgmt.php"),
        );
        try {
            $pi_group->setIsDefunct(false);
            $this->assertEquals(["FALSE"], $entry->getAttribute("isDefunct"));
            $this->assertStringContainsString(
                $pi_group->gid,
                http_get(__DIR__ . "/../../webroot/admin/pi-mgmt.php"),
            );
        } finally {
            if ($entry->hasAttribute("isDefunct")) {
                $entry->removeAttribute("isDefunct");
            }
        }
    }
}
