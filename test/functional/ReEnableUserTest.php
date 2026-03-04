<?php

use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UserFlag;

class RegisterUserTest extends UnityWebPortalTestCase
{
    private function reEnable()
    {
        $this->http_post(__DIR__ . "/../../webroot/panel/disabled_account.php", [
            "eula" => "agree",
        ]);
    }

    public function testReEnableUser()
    {
        global $USER;
        $this->switchUser("DisabledNotPI");
        $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
        try {
            $this->reEnable();
            $this->assertMessageExists(UnityHTTPDMessageLevel::SUCCESS, "/Re-Enabled/", "/.*/");
            $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        } finally {
            $USER->setFlag(UserFlag::DISABLED, true);
        }
    }

    public function testReEnableUserWithDisabledGroup()
    {
        global $USER;
        $this->switchUser("DisabledOwnerOfDisabledPIGroup");
        $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
        $this->assertFalse($USER->isPI());
        try {
            $this->reEnable();
            $this->assertMessageExists(UnityHTTPDMessageLevel::SUCCESS, "/Re-Enabled/", "/.*/");
            $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
            $this->assertFalse($USER->isPI());
        } finally {
            $USER->setFlag(UserFlag::DISABLED, true);
        }
    }
}
