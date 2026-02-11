<?php

use UnityWebPortal\lib\UnityUser;

class WorkerRemoveUsersFromGroupTest extends UnityWebPortalTestCase
{
    public function testRemoveUsersFromGroup()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("EmptyPIGroupOwner");
        $pi = $USER;
        $pi_group = $USER->getPIGroup();
        $this->assertTrue($pi->isPI());
        $this->assertEqualsCanonicalizing([$pi->uid], $pi_group->getMemberUIDs());
        $this->assertEqualsCanonicalizing([$pi->uid], $pi_group->getMemberUIDs());
        $this->assertEqualsCanonicalizing([], $pi_group->getRequests());
        $uids = getSomeUIDsOfQualifiedUsers();
        $uids_to_remove = array_slice($uids, 0, 3);
        $expected_new_uids = array_diff(array_merge([$pi->uid], $uids), $uids_to_remove);
        $remove_uids_file = writeLinesToTmpFile($uids_to_remove);
        $remove_uids_file_path = getPathFromFileHandle($remove_uids_file);
        try {
            foreach ($uids as $uid) {
                $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
                $pi_group->newUserRequest($user, false);
                $pi_group->approveUser($user, false);
            }
            [$_, $output] = executeWorker(
                "remove-users-from-group.php",
                "$pi_group->gid $remove_uids_file_path",
            );
            print implode("\n", $output);
            $this->switchUser("EmptyPIGroupOwner", validate: false);
            $pi = $USER;
            $pi_group = $USER->getPIGroup();
            $this->assertEqualsCanonicalizing($expected_new_uids, $pi_group->getMemberUIDs());
            $this->assertEqualsCanonicalizing($expected_new_uids, $pi_group->getMemberUIDs());
        } finally {
            foreach ($uids as $uid) {
                $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
                $pi_group->removeUser($user);
            }
            unlink($remove_uids_file_path);
        }
    }

    public function testNonexistentFile()
    {
        [$rc, $output_lines] = executeWorker(
            "remove-users-from-group.php",
            "pi_user1_org1_test asdlkjasldkj",
            doThrowIfNonzero: false,
        );
        $this->assertEquals(255, $rc);
        $this->assertStringContainsString(
            "No such file or directory",
            implode("\n", $output_lines),
        );
    }

    public function testRemoveFromNonexistentGroup()
    {
        $remove_uids_file = writeLinesToTmpFile(["foo", "bar"]);
        $remove_uids_file_path = getPathFromFileHandle($remove_uids_file);
        [$rc, $output_lines] = executeWorker(
            "remove-users-from-group.php",
            "alskdj $remove_uids_file_path",
            doThrowIfNonzero: false,
        );
        $this->assertEquals(1, $rc);
        $this->assertStringContainsString("No such group", implode("\n", $output_lines));
        unlink($remove_uids_file_path);
    }

    public function testRemoveNonexistentUID()
    {
        global $USER;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $members_before = $pi_group->getMemberUIDs();
        $remove_uids_file = writeLinesToTmpFile(["foo", "bar"]);
        $remove_uids_file_path = getPathFromFileHandle($remove_uids_file);
        try {
            [$rc, $output_lines] = executeWorker(
                "remove-users-from-group.php",
                "$pi_group->gid $remove_uids_file_path",
                doThrowIfNonzero: false,
            );
            $output = implode("\n", $output_lines);
            $this->assertEquals(0, $rc);
            $members_after = $pi_group->getMemberUIDs();
            $this->assertEqualsCanonicalizing($members_before, $members_after);
            $this->assertStringContainsString("Skipping 'foo'", $output);
            $this->assertStringContainsString("Skipping 'bar'", $output);
        } finally {
            unlink($remove_uids_file_path);
        }
    }
}
