<?php

use UnityWebPortal\lib\UnitySQL;

class WorkerSpoofPIGroupRequestTest extends UnityWebPortalTestCase
{
    private static string $first_name = "Spoof";
    private static string $last_name = "Tester";
    private static string $eppn = "spoof.pi.group.request@org1.test";
    private static string $mail = "spoof.pi.group.request@org1.test";
    private static string $uid = "spoof_pi_group_request_org1_test";

    public function testSpoofPIGroupRequest()
    {
        global $LDAP, $SQL;
        $this->switchUser("Blank");
        $user_entry = $LDAP->getUserEntry(self::$uid);
        $this->assertFalse($user_entry->exists());
        $this->assertFalse($SQL->requestExists(self::$uid, UnitySQL::REQUEST_BECOME_PI));

        $stdin_file = writeLinesToTmpFile([
            self::$first_name,
            self::$last_name,
            self::$eppn,
            self::$mail,
        ]);
        $stdin_file_path = getPathFromFileHandle($stdin_file);

        try {
            executeWorker("spoof-pi-group-request.php", stdinFilePath: $stdin_file_path);
            $this->assertTrue($SQL->requestExists(self::$uid, UnitySQL::REQUEST_BECOME_PI));
        } finally {
            if ($SQL->requestExists(self::$uid, UnitySQL::REQUEST_BECOME_PI)) {
                $SQL->removeRequest(self::$uid, UnitySQL::REQUEST_BECOME_PI);
            }
            ensureUserDoesNotExist(self::$uid);
            unlink($stdin_file_path);
        }
    }

    public function testSpoofPIGroupRequestUserAlreadyExists()
    {
        global $LDAP, $SQL, $USER;
        $this->switchUser("Blank");
        $user_entry = $LDAP->getUserEntry($USER->uid);
        $this->assertTrue($user_entry->exists());
        $this->assertFalse($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));

        $eppn = self::$UID2ATTRIBUTES[$USER->uid][0];
        $stdin_file = writeLinesToTmpFile(["foo", "foo", $eppn, "foo"]);
        $stdin_file_path = getPathFromFileHandle($stdin_file);

        try {
            [$rc, $output_lines] = executeWorker(
                "spoof-pi-group-request.php",
                stdinFilePath: $stdin_file_path,
                doThrowIfNonzero: false,
            );
            $output = implode("\n", $output_lines);
            $this->assertEquals(1, $rc);
            $this->assertStringContainsString("login as them in user-mgmt.php", $output);
            $this->assertFalse($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
        } finally {
            if ($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI)) {
                $SQL->removeRequest($USER->uid, UnitySQL::REQUEST_BECOME_PI);
            }
            ensureUserDoesNotExist($USER->uid);
            unlink($stdin_file_path);
        }
    }
}
