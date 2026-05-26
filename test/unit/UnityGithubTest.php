<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\UnityGithub;

class UnityGithubTest extends UnityWebPortalTestCase
{
    public static function providerTestGetGithubKeys()
    {
        return [
            # empty
            ["", []],
            # nonexistent user
            ["asdfkljhasdflkjashdflkjashdflkasjd", []],
            # user with no keys
            ["sheldor1510", []],
            # user with 1 key
            [
                "simonLeary42",
                [
                    "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJdU7whk873eXktcZjoFExweGjD5juJwfhy5I6h9oSa9",
                ],
            ],
        ];
    }

    #[DataProvider("providerTestGetGithubKeys")]
    public function testGetGithubKeys(string $username, array $expected)
    {
        $GITHUB = new UnityGithub();
        $this->assertEquals($expected, $GITHUB->getSshPublicKeys($username));
    }
}
