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

    private function setLastLogin(int $days_ago, int $seconds_offset = 0): void
    {
        global $SQL, $USER;
        $x = time() - $days_ago * 24 * 60 * 60 + $seconds_offset;
        callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $x);
    }

    public function testInactivityTimerResetConfirmation()
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
        try {
            // null ////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
            // 1 second before 1st warning /////////////////////////////////////////////////////////
            $this->setLastLogin(days_ago: 1, seconds_offset: -1);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
            // 1 ///////////////////////////////////////////////////////////////////////////////////
            $this->setLastLogin(days_ago: 1);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNumberOfMessages(0);
            UnityHTTPD::clearMessages();
            // 2 ///////////////////////////////////////////////////////////////////////////////////
            $this->setLastLogin(days_ago: 2);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Inactivity Timer Reset/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // 3 ///////////////////////////////////////////////////////////////////////////////////
            $this->setLastLogin(days_ago: 3);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Inactivity Timer Reset/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // idlelocked //////////////////////////////////////////////////////////////////////////
            $this->setLastLogin(days_ago: 4);
            $USER->setFlag(UserFlag::IDLELOCKED, true);
            session_destroy();
            http_get(__DIR__ . "/../../resources/init.php");
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::SUCCESS,
                "/Account Unlocked/",
                "/.*/",
            );
            $this->assertNumberOfMessages(1);
            UnityHTTPD::clearMessages();
            // disabled ////////////////////////////////////////////////////////////////////////////
            $USER->setFlag(UserFlag::IDLELOCKED, true);
            $USER->disable();
            session_destroy();
            http_get(__DIR__ . "/../../webroot/panel/account.php", ignore_die: true);
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
