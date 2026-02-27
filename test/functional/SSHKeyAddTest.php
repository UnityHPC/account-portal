<?php

use UnityWebPortal\lib\UnityGithub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SSHKeyAddTest extends UnityWebPortalTestCase
{
    public static function keyProvider()
    {
        $validKey =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a foobar";
        $invalidKey = "foobar";
        return [[false, $invalidKey], [true, $validKey]];
    }

    public static function keysProvider()
    {
        $validKey =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a foobar";
        $validKeyDuplicateDifferentComment =
            "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB+XqO25MUB9x/pS04I3JQ7rMGboWyGXh0GUzkOrTi7a foobar2";
        $validKey2 =
            "ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBF/dSI9/7YWeyB8wa4rEWRdeb9pQbrGxZwYFV2ulr0agXdbiJIApp0MWDYlIc9XI+4Y+cVAj66PQ2YaRz44BV+o=";
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
}
