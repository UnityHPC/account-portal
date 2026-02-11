<?php
use UnityWebPortal\lib\UserFlag;

class UserDisableTest extends UnityWebPortalTestCase
{
    public function testDisableUser()
    {
        global $USER;
        $this->switchUser("Normal");
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        try {
            http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "disable",
            ]);
            $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
        } finally {
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
        }
    }
}
