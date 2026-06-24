<?php

use PHPUnit\Framework\Attributes\DataProvider;

class PageLoadTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        return [
            // normal page load
            ["Admin", "admin/pi-mgmt.php", "/PI Management/"],
            ["Admin", "admin/user-mgmt.php", "/User Management/"],
            ["NonExistent", "panel/new_account.php", "/Register New Account/"],
            ["Disabled", "panel/disabled_account.php", "/Disabled Account/"],
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

    #[DataProvider("provider")]
    public function testLoadPage($nickname, $path, $regex, $do_validate_status_code = true)
    {
        $this->switchUser($nickname);
        $output = $this->http_get($path, do_validate_status_code: $do_validate_status_code);
        $this->assertMatchesRegularExpression($regex, $output);
    }

    public function testLoadAdminPageNotAnAdmin($path)
    {
        $this->switchUser("Blank");
        $output = $this->http_get("/admin/user-mgmt.php", do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/You are not an admin\./", $output);
    }

    public function testLoadPageNonexistentUser($path)
    {
        $this->switchUser("NonExistent");
        $output = $this->http_get("/panel/account.php", do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/panel\/new_account\.php/", $output);
    }

    public function testLoadPageDisabled($path)
    {
        $this->switchUser("Disabled");
        $output = $this->http_get("/panel/account.php", do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/panel\/disabled_account\.php/", $output);
    }

    public function testLoadPageLockedUser($path)
    {
        $this->switchUser("Locked");
        // TODO assert response code
        $output = $this->http_get("/panel/account.php", do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/Your account is locked\./", $output);
    }

    public function testLoadPIPageForAnotherGroup()
    {
        global $LDAP, $USER;
        $this->switchUser("CourseGroupManager");
        $gids = $LDAP->getPIGroupGIDsWithManagerUID($USER->uid);
        $this->assertTrue(count($gids) > 0);
        $gid = $gids[0];
        $output = $this->http_get("/panel/pi", [
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
        $output = $this->http_get("/panel/pi", ["gid" => $gid], do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/You cannot manage this group/", $output);
    }

    public function testLoadPIPageForNonexistentGroup()
    {
        $this->switchUser("Blank");
        $output = $this->http_get("/panel/pi", ["gid" => "foobar"], do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/This group does not exist/", $output);
    }

    public function testLoadPIPageForDisabledGroup()
    {
        $this->switchUser("ReenabledOwnerOfDisabledPIGroup");
        $output = $this->http_get("/panel/pi", do_validate_status_code: false);
        $this->assertMatchesRegularExpression("/This group is disabled/", $output);
    }

    public function testLoadPIPageForAnotherDisabledGroup()
    {
        $this->switchUser("DisabledPIGroup_user9_org3_test_Manager");
        $output = $this->http_get(
            "/panel/pi",
            ["gid" => "pi_user9_org3_test"],
            do_validate_status_code: false,
        );
        $this->assertMatchesRegularExpression("/This group is disabled/", $output);
    }

    public function testDisplayManagedGroups()
    {
        global $USER, $LDAP;
        $this->switchUser("CourseGroupManager");
        $gids = $LDAP->getPIGroupGIDsWithManagerUID($USER->uid);
        $this->assertTrue(count($gids) > 0);
        $output = $this->http_get("/panel/groups");
        foreach ($gids as $gid) {
            $this->assertMatchesRegularExpression("/name='gid' value='$gid'/", $output);
        }
    }
}
