<?php

class WorkerUnityCourseTest extends UnityWebPortalTestCase
{
    private static string $course_owner_uid = "user2_org1_test";
    private static string $course_gid = "pi_cs124_org1_test";
    private static array $course_info = ["cs124", "Fall 2025"];

    public function testCreateCourse()
    {
        global $LDAP, $USER;
        $this->switchUser("Blank");
        $this->assertEquals(self::$course_owner_uid, $USER->uid);
        $pi_group_entry = $LDAP->getPIGroupEntry(self::$course_gid);
        $this->assertFalse($pi_group_entry->exists());
        $stdin_file = writeLinesToTmpFile([
            self::$course_info[0],
            self::$course_info[1],
            self::$course_gid,
            self::$course_owner_uid,
        ]);
        $stdin_file_path = getPathFromFileHandle($stdin_file);
        try {
            executeWorker("unity-course.php", stdinFilePath: $stdin_file_path);
            // error_log(implode("\n", $output_lines));
            // our LDAP conn doesn't know about changes from subprocess
            unset($GLOBALS["ldapconn"]);
            $this->switchUser("Admin");
            $pi_group_entry = $LDAP->getPIGroupEntry(self::$course_gid);
            $this->assertTrue($pi_group_entry->exists());
            $this->assertEqualsCanonicalizing(
                [self::$course_owner_uid],
                $pi_group_entry->getAttribute("memberuid"),
            );
            $this->assertEqualsCanonicalizing(
                [self::$course_owner_uid],
                $pi_group_entry->getAttribute("owneruid"),
            );
            $this->assertEqualsCanonicalizing(
                [self::$course_info[0] . " " . self::$course_info[1]],
                $pi_group_entry->getAttribute("description"),
            );
        } finally {
            ensurePIGroupDoesNotExist(self::$course_gid);
            unlink($stdin_file_path);
        }
    }
}
