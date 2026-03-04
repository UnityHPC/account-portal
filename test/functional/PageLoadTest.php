<?php

use PHPUnit\Framework\Attributes\DataProvider;

class PageLoadTest extends UnityWebPortalTestCase
{
    public static function providerMisc()
    {
        return [
            // normal page load
            ["Admin", "admin/pi-mgmt.php", "/PI Management/"],
            ["Admin", "admin/user-mgmt.php", "/User Management/"],
            ["NonExistent", "panel/new_account.php", "/Register New Account/"],
            ["Blank", "panel/account.php", "/Account Settings/"],
            ["Blank", "panel/groups.php", "/My Principal Investigators/"],
            ["EmptyPIGroupOwner", "panel/pi.php", "/My Users/"],
            // non-PI can't access pi.php
            ["Blank", "panel/pi.php", "/You are not a PI./", true],
            // nonexistent users should be redirected from anywhere to new_account.php
            // existent users should be redirected from new_account.php to account.php
            // nonexistent users should not be redirected from new_account.php
            ["NonExistent", "panel/account.php", "/panel\/new_account\.php/", true],
            ["Blank", "panel/new_account.php", "/panel\/account\.php/", true],
            ["NonExistent", "panel/new_account.php", "/Register New Account/", true],
            // disabled users should be redirected from anywhere to disabled_account.php
            // non-disabled users should be redirected from disabled_account.php to account.php
            // disabled users should not be redirected from new_account.php
            ["Disabled", "panel/account.php", "/panel\/disabled_account\.php/", true],
            ["Blank", "panel/disabled_account.php", "/panel\/account\.php/", true],
            ["Disabled", "panel/disabled_account.php", "/Disabled Account/", true],
        ];
    }

    #[DataProvider("validUserForAllPages")]
    public function testLoadPage($nickname, $path)
    {
        $this->switchUser($nickname);
        $this->http_get(__DIR__ . "/../../webroot/" . $path);
    }

    #[DataProvider("providerMisc")]
    public function testLoadPageAssertOutput($nickname, $path, $regex, $ignore_die = false)
    {
        $this->switchUser($nickname);
        $output = $this->http_get(__DIR__ . "/../../webroot/" . $path, ignore_die: $ignore_die);
        $this->assertMatchesRegularExpression($regex, $output);
    }

    #[DataProvider("providerAdmin")]
    public function testLoadAdminPageNotAnAdmin($path)
    {
        $this->switchUser("Blank");
        $output = $this->http_get($path, ignore_die: true);
        $this->assertMatchesRegularExpression("/You are not an admin\./", $output);
    }

    #[DataProvider("phpFilesWithNormalHeaderRedirects")]
    public function testLoadPageNonexistentUser($path)
    {
        $this->switchUser("NonExistent");
        $output = $this->http_get($path, ignore_die: true);
        $this->assertMatchesRegularExpression("/panel\/new_account\.php/", $output);
    }

    #[DataProvider("phpFilesWithNormalHeaderRedirects")]
    public function testLoadPageDisabled($path)
    {
        $this->switchUser("Disabled");
        $output = $this->http_get($path, ignore_die: true);
        $this->assertMatchesRegularExpression("/panel\/disabled_account\.php/", $output);
    }

    #[DataProvider("phpFilesWithNormalHeaderRedirects")]
    public function testLoadPageLockedUser($path)
    {
        $this->switchUser("Locked");
        $output = $this->http_get($path, ignore_die: true);
        $this->assertMatchesRegularExpression("/Your account is locked\./", $output);
    }

    public function testLoadPIPageForAnotherGroup()
    {
        global $LDAP, $USER;
        $this->switchUser("CourseGroupManager");
        $gids = $LDAP->getNonDisabledPIGroupGIDsWithManagerUID($USER->uid);
        $this->assertTrue(count($gids) > 0);
        $gid = $gids[0];
        $output = $this->http_get(__DIR__ . "/../../webroot/panel/pi.php", [
            "gid" => $gid,
        ]);
        $this->assertMatchesRegularExpression("/PI Group '$gid'/", $output);
    }

    public function testLoadPIPageForAnotherGroupForbidden()
    {
        global $USER;
        $this->switchUser("EmptyPIGroupOwner");
        $gid = $USER->getPIGroup()->gid;
        $this->switchUser("Blank");
        $output = $this->http_get(
            __DIR__ . "/../../webroot/panel/pi.php",
            ["gid" => $gid],
            ignore_die: true,
        );
        $this->assertMatchesRegularExpression("/You cannot manage this group/", $output);
    }

    public function testLoadPIPageForNonexistentGroup()
    {
        $this->switchUser("Blank");
        $output = $this->http_get(
            __DIR__ . "/../../webroot/panel/pi.php",
            ["gid" => "foobar"],
            ignore_die: true,
        );
        $this->assertMatchesRegularExpression("/This group does not exist/", $output);
    }

    public function testLoadPIPageForDisabledGroup()
    {
        $this->switchUser("ReenabledOwnerOfDisabledPIGroup");
        $output = $this->http_get(__DIR__ . "/../../webroot/panel/pi.php", ignore_die: true);
        $this->assertMatchesRegularExpression("/This group is disabled/", $output);
    }

    public function testLoadPIPageForAnotherDisabledGroup()
    {
        $this->switchUser("DisabledPIGroup_user9_org3_test_Manager");
        $output = $this->http_get(
            __DIR__ . "/../../webroot/panel/pi.php",
            ["gid" => "pi_user9_org3_test"],
            ignore_die: true,
        );
        $this->assertMatchesRegularExpression("/This group is disabled/", $output);
    }

    public function testDisplayManagedGroups()
    {
        global $USER, $LDAP;
        $this->switchUser("CourseGroupManager");
        $gids = $LDAP->getNonDisabledPIGroupGIDsWithManagerUID($USER->uid);
        $this->assertTrue(count($gids) > 0);
        $output = $this->http_get(__DIR__ . "/../../webroot/panel/groups.php");
        foreach ($gids as $gid) {
            $this->assertMatchesRegularExpression("/name='gid' value='$gid'/", $output);
        }
    }
}
