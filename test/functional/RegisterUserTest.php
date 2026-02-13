<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UserFlag;

class RegisterUserTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        return [
            // defaults/config.ini.default: ldap.offset_UIDGID=1000000
            // test/custom_user_mappings/test.csv has reservations for 1000000-1000004
            ["NonExistent", 1000005],
            // test/custom_user_mappings/test.csv: {user2001: 555}
            ["CustomMapped555", 555],
        ];
    }

    private function register()
    {
        http_post(__DIR__ . "/../../webroot/panel/new_account.php", ["eula" => "agree"]);
    }

    private function reEnable()
    {
        http_post(__DIR__ . "/../../webroot/panel/disabled_account.php", ["eula" => "agree"]);
    }

    #[DataProvider("provider")]
    public function testRegisterUserAndCreateOrg($nickname, $expected_uid_gid)
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser($nickname);
        $uid = $USER->uid;
        $org_gid = $SSO["org"];
        $user_entry = $LDAP->getUserEntry($uid);
        $user_group_entry = $LDAP->getUserGroupEntry($uid);
        $org_entry = $LDAP->getOrgGroupEntry($org_gid);
        $this->assertFalse($user_entry->exists());
        $this->assertFalse($user_group_entry->exists());
        $this->assertFalse($org_entry->exists());
        try {
            $this->register();
            $this->assertTrue($user_entry->exists());
            $this->assertTrue($user_group_entry->exists());
            $this->assertTrue($org_entry->exists());
            $this->assertEquals($expected_uid_gid, $user_entry->getAttribute("uidnumber")[0]);
            $this->assertEquals($expected_uid_gid, $user_group_entry->getAttribute("gidnumber")[0]);
        } finally {
            ensureOrgGroupDoesNotExist($org_gid);
            ensureUserDoesNotExist($uid);
        }
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
