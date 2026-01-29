<?php

use UnityWebPortal\lib\UserFlag;

// TODO use DataProvider
class ViewAsUserTest extends UnityWebPortalTestCase
{
    public function _testViewAsUser(string $beforeNickname, string $afterNickname)
    {
        global $USER;
        $afterUid = self::$NICKNAME2UID[$afterNickname];
        $this->switchUser($beforeNickname);
        // $this->assertTrue($USER->getFlag(UserFlag::ADMIN));
        $beforeUid = $USER->uid;
        // $this->assertNotEquals($afterUid, $beforeUid);
        http_post(__DIR__ . "/../../webroot/admin/user-mgmt.php", [
            "form_type" => "viewAsUser",
            "uid" => $afterUid,
        ]);
        $this->assertArrayHasKey("viewUser", $_SESSION);
        // redirect means that php process dies and user's browser will initiate a new one
        // this makes `require_once autoload.php` run again and init.php changes $USER
        session_write_close();
        http_get(__DIR__ . "/../../resources/init.php");
        // now we should be new user
        $this->assertEquals($afterUid, $USER->uid);
        http_post(__DIR__ . "/../../webroot/panel/account.php", [
            "form_type" => "clearView",
        ]);
        $this->assertArrayNotHasKey("viewUser", $_SESSION);
        // redirect means that php process dies and user's browser will initiate a new one
        // this makes `require_once autoload.php` run again and init.php changes $USER
        session_write_close();
        http_get(__DIR__ . "/../../resources/init.php");
        // now we should be back to original user
        $this->assertEquals($beforeUid, $USER->uid);
    }

    public function testViewAsUser()
    {
        $this->_testViewAsUser("Admin", "Blank");
    }

    public function testViewAsNonExistentUser()
    {
        $this->_testViewAsUser("Admin", "NonExistent");
    }

    public function testViewAsSelf()
    {
        $this->_testViewAsUser("Admin", "Admin");
    }

    public function testNonAdminViewAsAdmin()
    {
        global $USER;
        $this->switchUser("Admin");
        $adminUid = $USER->uid;
        $this->switchUser("Blank");
        http_post(__DIR__ . "/../../webroot/admin/user-mgmt.php", [
            "form_type" => "viewAsUser",
            "uid" => $adminUid,
        ]);
        $this->assertArrayNotHasKey("viewUser", $_SESSION);
    }

    public function testViewAsIdleLockedUserStillIdleLocked()
    {
        global $LDAP;
        $idleLockedUID = self::$NICKNAME2UID["IdleLocked"];
        $this->switchUser("Admin");
        $this->assertContains($idleLockedUID, $LDAP->userFlagGroups["idlelocked"]->getMemberUIDs());
        $this->_testViewAsUser("Admin", "IdleLocked");
        $this->assertContains($idleLockedUID, $LDAP->userFlagGroups["idlelocked"]->getMemberUIDs());
    }
}
