<?php
use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UserFlag;

class UserDisableTest extends UnityWebPortalTestCase
{
    public function testDisableUser()
    {
        global $USER;
        $this->switchUser("Blank");
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        try {
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "disable",
            ]);
            $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
        } finally {
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
        }
    }

    public function testDisableUserButIsPI()
    {
        global $USER;
        $this->switchUser("EmptyPIGroupOwner");
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        try {
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "disable",
                ],
                do_validate_messages: false,
            );
            $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
            $this->assertMessageExists(UnityHTTPDMessageLevel::ERROR, "/.*/", "/You are a PI/");
        } finally {
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
        }
    }

    public function testDisableUserButIsPIGroupMember()
    {
        global $USER;
        $this->switchUser("EmptyPIGroupOwner");
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        try {
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "disable",
                ],
                do_validate_messages: false,
            );
            $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
            $this->assertMessageExists(UnityHTTPDMessageLevel::ERROR, "/.*/", "/you are a member/");
        } finally {
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
        }
    }
}
