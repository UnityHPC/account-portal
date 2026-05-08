<?php

use UnityWebPortal\lib\UnityGithub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;

#[AllowMockObjectsWithoutExpectations]
class SSHKeyAddTest extends UnityWebPortalTestCase
{
    public static function keyProvider()
    {
        $validKey =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPUef6kU0/P0lTO5KBZq6aFVm7nBHhB85SaG4HB0nh7p foobar";
        $invalidKey = "foobar";
        return [[false, $invalidKey], [true, $validKey]];
    }

    public static function keysProvider()
    {
        $validKey =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPUef6kU0/P0lTO5KBZq6aFVm7nBHhB85SaG4HB0nh7p foobar";
        $validKeyDuplicateDifferentComment =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPUef6kU0/P0lTO5KBZq6aFVm7nBHhB85SaG4HB0nh7p foobar2";
        $validKey2 =
            "ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBF5Ossk5huH48Gdyw1nuC+1TKajZzF+83rwbFhml0b915mWzYbKqFtjFze8+4uW+xBjLmwx4e+vGiZbNR4ucm6w=";
        $invalidKey = "foobar";
        return [
            [0, []],
            [0, [$invalidKey]],
            [1, [$validKey]],
            [1, [$validKey, $invalidKey]],
            [1, [$validKey, $validKey]],
            [1, [$validKey, $validKeyDuplicateDifferentComment]],
            [2, [$validKey, $validKey2]],
        ];
    }

    public function getKeyCount()
    {
        global $USER;
        return count($USER->getSSHKeys());
    }

    #[DataProvider("keyProvider")]
    public function testAddSshKeyPaste(bool $expectedKeyAdded, string $key)
    {
        global $USER;
        $this->switchUser("HasNoSshKeys");
        $numKeysBefore = $this->getKeyCount();
        $this->assertEquals(0, $numKeysBefore);
        try {
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "addKey",
                    "add_type" => "paste",
                    "key" => $key,
                ],
                do_validate_messages: $expectedKeyAdded,
            );
            $numKeysAfter = $this->getKeyCount();
            if ($expectedKeyAdded) {
                $this->assertEquals(1, $numKeysAfter - $numKeysBefore);
            } else {
                $this->assertEquals(0, $numKeysAfter - $numKeysBefore);
            }
        } finally {
            callPrivateMethod($USER, "setSSHKeys", []);
        }
    }

    #[DataProvider("keyProvider")]
    public function testAddSshKeyImport(bool $expectedKeyAdded, string $key)
    {
        global $USER;
        $this->switchUser("HasNoSshKeys");
        $numKeysBefore = $this->getKeyCount();
        $this->assertEquals(0, $numKeysBefore);
        try {
            $tmp = tmpfile();
            $tmp_path = getPathFromFileHandle($tmp);
            fwrite($tmp, $key);
            $_FILES["keyfile"] = ["tmp_name" => $tmp_path];
            try {
                $this->http_post(
                    __DIR__ . "/../../webroot/panel/account.php",
                    [
                        "form_type" => "addKey",
                        "add_type" => "import",
                    ],
                    do_validate_messages: $expectedKeyAdded,
                );
                $this->assertFalse(file_exists($tmp_path));
            } finally {
                unset($_FILES["keyfile"]);
            }
            $numKeysAfter = $this->getKeyCount();
            if ($expectedKeyAdded) {
                $this->assertEquals(1, $numKeysAfter - $numKeysBefore);
            } else {
                $this->assertEquals(0, $numKeysAfter - $numKeysBefore);
            }
        } finally {
            callPrivateMethod($USER, "setSSHKeys", []);
        }
    }

    #[DataProvider("keyProvider")]
    public function testAddSshKeyGenerate(bool $expectedKeyAdded, string $key)
    {
        global $USER;
        $this->switchUser("HasNoSshKeys");
        $numKeysBefore = $this->getKeyCount();
        $this->assertEquals(0, $numKeysBefore);
        try {
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "addKey",
                    "add_type" => "generate",
                    "gen_key" => $key,
                ],
                do_validate_messages: $expectedKeyAdded,
            );
            $numKeysAfter = $this->getKeyCount();
            if ($expectedKeyAdded) {
                $this->assertEquals(1, $numKeysAfter - $numKeysBefore);
            } else {
                $this->assertEquals(0, $numKeysAfter - $numKeysBefore);
            }
        } finally {
            callPrivateMethod($USER, "setSSHKeys", []);
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider("keysProvider")]
    public function testAddSshKeysGithub(int $expectedKeysAdded, array $keys)
    {
        global $USER, $GITHUB;
        $this->switchUser("HasNoSshKeys");
        $numKeysBefore = $this->getKeyCount();
        $this->assertEquals(0, $numKeysBefore);
        $oldGithub = $GITHUB;
        $GITHUB = $this->createMock(UnityGithub::class);
        $GITHUB->method("getSshPublicKeys")->willReturn($keys);
        try {
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "addKey",
                    "add_type" => "github",
                    "gh_user" => "foobar",
                ],
                do_validate_messages: $expectedKeysAdded > 0 && $expectedKeysAdded == count($keys),
            );
            $numKeysAfter = $this->getKeyCount();
            $this->assertEquals($expectedKeysAdded, $numKeysAfter - $numKeysBefore);
        } finally {
            $GITHUB = $oldGithub;
            callPrivateMethod($USER, "setSSHKeys", []);
        }
    }

    public function testShareKeysBetweenUsers()
    {
        global $USER;
        $key =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPUef6kU0/P0lTO5KBZq6aFVm7nBHhB85SaG4HB0nh7p foobar";
        $this->switchUser("Admin");
        $user1 = $USER;
        $this->switchUser("Blank");
        $user2 = $USER;
        $user1_keys_before = $user1->getSSHKeys();
        $user2_keys_before = $user2->getSSHKeys();
        try {
            $user1->addSSHKey($key);
            // as user2, try to add the key that user1 already added
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "addKey",
                    "add_type" => "paste",
                    "key" => $key,
                ],
                do_validate_messages: false,
            );
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::WARNING,
                "/.*/",
                "/This incident has been reported/",
            );
            $this->assertEquals($user2_keys_before, $user2->getSSHKeys());
        } finally {
            callPrivateMethod($user1, "setSSHKeys", $user1_keys_before);
            callPrivateMethod($user2, "setSSHKeys", $user2_keys_before);
        }
    }

    /*
    while attempting to share keys between users says "this incident has been reported"
    you should not see this message if you add the same key to your account twice
    */
    public function testAddDuplicateKey()
    {
        global $USER;
        $key =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPUef6kU0/P0lTO5KBZq6aFVm7nBHhB85SaG4HB0nh7p foobar";
        $this->switchUser("Blank");
        $this->assertEmpty($USER->getSSHKeys());
        try {
            $USER->addSSHKey($key);
            $this->assertEquals([$key], $USER->getSSHKeys());
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "addKey",
                    "add_type" => "paste",
                    "key" => $key,
                ],
                do_validate_messages: false,
            );
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::WARNING,
                "/Key Already Added/",
                "/.*/",
            );
            $this->assertEquals([$key], $USER->getSSHKeys());
        } finally {
            callPrivateMethod($USER, "setSSHKeys", []);
        }
    }
}
