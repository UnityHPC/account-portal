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
            # regular user
            [
                "simonLeary42",
                [
                    "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJdU7whk873eXktcZjoFExweGjD5juJwfhy5I6h9oSa9",
                    "sk-ssh-ed25519@openssh.com AAAAGnNrLXNzaC1lZDI1NTE5QG9wZW5zc2guY29tAAAAIHny+H1YtAqqEnJ1GBBBzqMyx9L5dg0oO00lA1wxBmsSAAAABHNzaDo=",
                    "sk-ssh-ed25519@openssh.com AAAAGnNrLXNzaC1lZDI1NTE5QG9wZW5zc2guY29tAAAAILepF2TNRmt4akVYOC8SwaH+yDYKrRd5jY1snylMRcrgAAAABHNzaDo=",
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
