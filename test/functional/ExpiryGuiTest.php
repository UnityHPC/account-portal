<?php

use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnityHTTPD;
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

    private function assertIdleDays(int $expected)
    {
        global $USER, $SQL;
        $this->assertEquals(
            $expected,
            $SQL->convertLastLoginToDaysIdle($SQL->getUserLastLogin($USER->uid)),
        );
    }

    public function testExpiryMessages()
    {
        global $SQL, $USER;
        $this->switchUser("Blank");
        $ssh_keys_before = $USER->getSSHKeys();
        $last_login_before = $SQL->getUserLastLogin($USER->uid);
        $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        // see deployment/overrides/phpunit/config/config.ini
        $this->assertEquals(CONFIG["expiry"]["idlelock_warning_days"][0], 2);
        $this->assertEquals(CONFIG["expiry"]["idlelock_day"], 4);
        $this->assertEquals(CONFIG["expiry"]["disable_day"], 8);
        try {
            // very first login ////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            $this->assertIdleDays(0);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
            // moment before 1st warning ///////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-2 days +1 second"));
            $this->assertIdleDays(1);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
            // moment of 1st warning ///////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-2 days"));
            $this->assertIdleDays(2);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Inactivity Timer Reset/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // moment before idlelock //////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-4 days +1 second"));
            $this->assertIdleDays(3);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Inactivity Timer Reset/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // moment of idlelock //////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-4 days"));
            $this->assertIdleDays(4);
            $USER->setFlag(UserFlag::IDLELOCKED, true);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Account Unlocked/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // moment before disable ///////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-8 days +1 second"));
            $this->assertIdleDays(7);
            $USER->setFlag(UserFlag::IDLELOCKED, true);
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Account Unlocked/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // moment of disable ///////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, strtotime("-8 days"));
            $this->assertIdleDays(8);
            $USER->setFlag(UserFlag::IDLELOCKED, true);
            $USER->disable();
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
        } finally {
            $USER->setFlag(UserFlag::IDLELOCKED, false);
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $last_login_before);
            }
            callPrivateMethod($USER, "setSSHKeys", $ssh_keys_before);
        }
    }
}
