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
                    "sk-ecdsa-sha2-nistp256@openssh.com AAAAInNrLWVjZHNhLXNoYTItbmlzdHAyNTZAb3BlbnNzaC5jb20AAAAIbmlzdHAyNTYAAABBBEDzlKYBQTMc7QedbV24lPc2IjhS4R+9SJTdRwD5p4DwPQZCrEdeFo4z5PGvtrMPaatfwFenwT+6AJJ+gjyoI1EAAAALdGVybWl1cy5jb20=",
                    "sk-ecdsa-sha2-nistp256@openssh.com AAAAInNrLWVjZHNhLXNoYTItbmlzdHAyNTZAb3BlbnNzaC5jb20AAAAIbmlzdHAyNTYAAABBBNu1eVGTh2uO8WxQ1qqNenms/Qd8ChRtZKFdKEAy6O2Si3y6LsUq0xFCs7FvOU3usBlLe0LgloD266uJTxITl1EAAAALdGVybWl1cy5jb20=",
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
