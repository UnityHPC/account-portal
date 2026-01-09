<?php

class DefunctUnsetOrFalseTest extends UnityWebPortalTestCase
{
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
            $entry->removeAttribute("isDefunct");
        }
    }
}
