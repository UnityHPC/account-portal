<?php

class DefunctUnsetOrFalseTest extends UnityWebPortalTestCase
{
    public function testPIMgmtShowsBothGroupsWithDefunctAttributeSetFalseAndUnset()
    {
        global $USER;
        $this->switchUser("PIDefunctAttributeUnset");
        $gidWithAttributeUnset = $USER->getPIGroup()->gid;
        $this->switchUser("PIDefunctAttributeSetFalse");
        $gidWithAttributeSetFalse = $USER->getPIGroup()->gid;
        $this->switchUser("Admin");
        $output = http_get(__DIR__ . "/../../webroot/admin/pi-mgmt.php");
        $this->assertStringContainsString($gidWithAttributeUnset, $output);
        $this->assertStringContainsString($gidWithAttributeSetFalse, $output);
    }
}
