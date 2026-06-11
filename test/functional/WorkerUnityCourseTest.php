<?php

class WorkerUnityCourseTest extends UnityWebPortalTestCase
{
    private static string $course_owner_uid = "cs124_org1_test";
    private static string $course_gid = "pi_cs124_org1_test";
    private static array $course_owner_name = ["cs124", "Fall 2025"];
    private static string $manager_uid = "user2_org1_test";
    private static string $manager_mail = "user2@org1.test";
    private static string $courseOwnerMail = "user2+cs124@org1.test";

    public function testCreateCourse()
    {
        global $LDAP, $USER;
        $this->switchUser("Blank");
        $this->assertEquals(self::$manager_uid, $USER->uid);
        $this->assertEquals(self::$manager_mail, $USER->getMail());
        $manager = $USER;
        $pi_group_entry = $LDAP->getPIGroupEntry(self::$course_gid);
        $owner_user_entry = $LDAP->getUserEntry(self::$course_owner_uid);
        $this->assertFalse($pi_group_entry->exists());
        $this->assertFalse($owner_user_entry->exists());
        $stdin_file = writeLinesToTmpFile([
            self::$course_owner_name[0],
            self::$course_owner_name[1],
            self::$course_owner_uid,
            self::$manager_uid,
            "1970/1/2",
        ]);
        $stdin_file_path = getPathFromFileHandle($stdin_file);
        try {
            executeWorker("unity-course.php", stdinFilePath: $stdin_file_path);
            // error_log(implode("\n", $output_lines));
            $this->switchUser("Admin");
            $pi_group_entry = $LDAP->getPIGroupEntry(self::$course_gid);
            $owner_user_entry = $LDAP->getUserEntry(self::$course_owner_uid);
            $this->assertTrue($pi_group_entry->exists());
            $this->assertTrue($owner_user_entry->exists());
            $this->assertEquals(self::$courseOwnerMail, $owner_user_entry->getAttribute("mail")[0]);
            $this->assertEqualsCanonicalizing(
                [self::$course_owner_uid, $manager->uid],
                $pi_group_entry->getAttribute("memberuid"),
            );
            $this->assertEqualsCanonicalizing(
                [$manager->uid],
                $pi_group_entry->getAttribute("manageruid"),
            );
        } finally {
            ensurePIGroupDoesNotExist(self::$course_gid);
            ensureUserDoesNotExist(self::$course_owner_uid);
            unlink($stdin_file_path);
        }
    }
}
