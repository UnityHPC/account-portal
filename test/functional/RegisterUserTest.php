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

    #[DataProvider("provider")]
    public function testRegisterUserAndCreateOrg($nickname, $expected_uid_gid)
    {
        global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser($nickname);
        $user_entry = $LDAP->getUserEntry($USER->uid);
        $user_group_entry = $LDAP->getGroupEntry($USER->uid);
        $org_entry = $LDAP->getOrgGroupEntry($SSO["org"]);
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
            ensureOrgGroupDoesNotExist();
            ensureUserDoesNotExist();
        }
    }

    public function testResurrectNoHauntedGroup()
    {
        global $USER;
        $this->switchUser("GhostNotPI");
        $this->assertTrue($USER->getFlag(UserFlag::GHOST));
        try {
            $this->register();
            $this->assertMessageExists(UnityHTTPDMessageLevel::INFO, "/.*/", "/resurrected/");
            $this->assertFalse($USER->getFlag(UserFlag::GHOST));
        } finally {
            $USER->setFlag(UserFlag::GHOST, true);
        }
    }

    public function testResurrectWithHauntedGroup()
    {
        global $USER;
        $this->switchUser("GhostOwnerOfDefunctPIGroup");
        $this->assertTrue($USER->getFlag(UserFlag::GHOST));
        $this->assertFalse($USER->isPI());
        try {
            $this->register();
            $this->assertMessageExists(UnityHTTPDMessageLevel::INFO, "/.*/", "/resurrected/");
            $this->assertFalse($USER->getFlag(UserFlag::GHOST));
            $this->assertFalse($USER->isPI());
        } finally {
            $USER->setFlag(UserFlag::GHOST, true);
        }
    }
}
