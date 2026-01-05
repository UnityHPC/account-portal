<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\exceptions\NoDieException;
use TRegx\PhpUnit\DataProviders\DataProvider as TRegxDataProvider;

class PageLoadTest extends UnityWebPortalTestCase
{
    public static function findPHPFiles($path)
    {
        // if I do this recursively I get the ajax and modal files, which aren't appropriate
        // for these tests, so instead I just list the directory
        // $directory_iterator = new RecursiveDirectoryIterator($path);
        // $iterator_iterator = new RecursiveIteratorIterator($directory_iterator);
        // $regex_iterator = new RegexIterator(
        //     $iterator_iterator,
        //     '/^.+\.php$/i',
        //     RecursiveRegexIterator::GET_MATCH
        // );
        // return array_keys(iterator_to_array($regex_iterator)));
        $files = [];
        foreach (new DirectoryIterator($path) as $file) {
            if (str_ends_with($file->getFilename(), ".php")) {
                array_push($files, join("/", [$path, $file->getFilename()]));
            }
        }
        return $files;
    }

    public static function providerAdmin()
    {
        return TRegxDataProvider::list(...self::findPHPFiles(__DIR__ . "/../../webroot/admin"));
    }

    public static function providerNonexistentUser()
    {
        $dir = __DIR__ . "/../../webroot/panel";
        $excludePages = array_map(fn($x) => "$dir/$x.php", [
            "pi", // requires user to be a PI
            "new_account", // this is the one page that does not redirect to new_account
        ]);
        $panelPages = self::findPHPFiles($dir);
        $output = array_diff($panelPages, $excludePages);
        return TRegxDataProvider::list(...$output);
    }

    public static function providerMisc()
    {
        return [
            // normal page load
            ["Admin", "admin/pi-mgmt.php", "/PI Management/"],
            ["Admin", "admin/user-mgmt.php", "/User Management/"],
            ["Admin", "admin/content.php", "/Page Content Management/"],
            ["Admin", "admin/notices.php", "/Cluster Notice Management/"],
            ["NonExistent", "panel/new_account.php", "/Register New Account/"],
            ["Blank", "panel/account.php", "/Account Settings/"],
            ["Blank", "panel/groups.php", "/My Principal Investigators/"],
            ["Blank", "panel/support.php", "/Support/"],
            ["EmptyPIGroupOwner", "panel/pi.php", "/My Users/"],
            // new_account.php should redirect to account.php if account already exists
            ["Blank", "panel/new_account.php", "/panel\/account\.php/"],
            // non-PI can't access pi.php
            ["Blank", "panel/pi.php", "/You are not a PI./"],
            // ghost account technically exists but new_account.php should not redirect
            ["Ghost", "panel/new_account.php", "/Register New Account/"],
        ];
    }

    #[DataProvider("providerMisc")]
    public function testLoadPage($nickname, $path, $regex)
    {
        $this->switchUser($nickname);
        $output = http_get(__DIR__ . "/../../webroot/" . $path);
        $this->assertMatchesRegularExpression($regex, $output);
    }

    #[DataProvider("providerAdmin")]
    public function testLoadAdminPageNotAnAdmin($path)
    {
        $this->switchUser("Blank");
        $output = http_get($path);
        $this->assertMatchesRegularExpression("/You are not an admin\./", $output);
    }

    #[DataProvider("providerNonexistentUser")]
    public function testLoadPageNonexistentUser($path)
    {
        $this->switchUser("NonExistent");
        $output = http_get($path);
        $this->assertMatchesRegularExpression("/panel\/new_account\.php/", $output);
    }

    public function testLoadPageLockedUser()
    {
        ob_start();
        try {
            $this->switchUser("Locked");
        } catch (NoDieException) {
            // ignore
        }
        $output = ob_get_clean();
        $this->assertMatchesRegularExpression("/Your account is locked\./", $output);
    }
}
