<?php

use UnityWebPortal\lib\UnitySQL;

class WorkerSpoofUserRegistrationTest extends UnityWebPortalTestCase
{
    private static string $first_name = "Spoof";
    private static string $last_name = "Tester";
    private static string $eppn = "spoof.pi.group.request@org1.test";
    private static string $mail = "spoof.pi.group.request@org1.test";
    private static string $uid = "spoof_pi_group_request_org1_test";

    public function testSpoofUserRegistration()
    {
        global $LDAP, $SQL;
        $this->switchUser("Blank");
        $user_entry = $LDAP->getUserEntry(self::$uid);
        $this->assertFalse($user_entry->exists());
        $this->assertFalse($SQL->requestExists(self::$uid, UnitySQL::REQUEST_BECOME_PI));

        $args = [self::$first_name, self::$last_name, self::$eppn, self::$mail];
        try {
            executeWorker("spoof-user-registration.php", implode(" ", $args));
            $this->assertTrue($user_entry->exists());
        } finally {
            ensureUserDoesNotExist(self::$uid);
        }
    }

    public function testSpoofUserRegistrationUserAlreadyExists()
    {
        global $LDAP, $USER;
        $this->switchUser("Blank");
        $user_entry = $LDAP->getUserEntry($USER->uid);
        $this->assertTrue($user_entry->exists());

        $eppn = self::$UID2ATTRIBUTES[$USER->uid][0];
        $args = ["foo", "foo", $eppn, "foo"];
        [$rc, $output_lines] = executeWorker(
            "spoof-user-registration.php",
            args: implode(" ", $args),
            doThrowIfNonzero: false,
        );
        $output = implode("\n", $output_lines);
        $this->assertEquals(1, $rc);
        $this->assertStringContainsString("login as them in user-mgmt.php", $output);
    }
}
