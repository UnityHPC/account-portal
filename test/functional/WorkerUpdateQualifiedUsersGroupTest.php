<?php

use UnityWebPortal\lib\UserFlag;

class WorkerUpdateQualifiedUsersGroupTest extends UnityWebPortalTestCase
{
    public function testQualifyUser()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("EmptyPIGroupOwner");
        $pi_group = $USER->getPIGroup();
        $this->switchUser("Blank");
        $user = $USER;
        $expectedOutput = [
            "added" => [$user->uid],
            "removed" => [],
            "not removed (non-native)" => [],
        ];
        try {
            $pi_group_entry = $LDAP->getPIGroupEntry($pi_group->gid);
            $pi_group_entry->appendAttribute("memberuid", $user->uid);
            [$_, $output_lines] = executeWorker("update-qualified-users-group.php");
            $output_str = implode("\n", $output_lines);
            $output = _json_decode($output_str, associative: true);
            $this->assertEquals($expectedOutput, $output);
            $this->switchUser("Blank", validate: false);
            $user = $USER;
            $this->assertTrue($user->getFlag(UserFlag::QUALIFIED));
        } finally {
            if ($pi_group->memberUIDExists($user->uid)) {
                $pi_group->removeUser($user);
            }
            $this->assertFalse($user->getFlag(UserFlag::QUALIFIED));
        }
    }

    public function testDisqualifyUser()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $expectedOutput = [
            "added" => [],
            "removed" => [$USER->uid],
            "not removed (non-native)" => [],
        ];
        try {
            $qualified_user_group = $LDAP->userFlagGroups["qualified"];
            $qualified_user_group->addMemberUID($USER->uid);
            [$_, $output_lines] = executeWorker("update-qualified-users-group.php");
            $output_str = implode("\n", $output_lines);
            $output = _json_decode($output_str, associative: true);
            $this->assertEquals($expectedOutput, $output);
            $this->switchUser("Blank", validate: false);
            $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
        } finally {
            if ($USER->getFlag(UserFlag::QUALIFIED)) {
                $USER->setFlag(UserFlag::QUALIFIED, false);
            }
        }
    }

    public function testIgnoreNonNativeUser()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Blank");
        $expectedOutput = [
            "added" => [],
            "removed" => [],
            "not removed (non-native)" => ["non_native_user1"],
        ];
        try {
            $qualified_user_group = $LDAP->userFlagGroups["qualified"];
            $qualified_user_group->addMemberUID("non_native_user1");
            [$_, $output_lines] = executeWorker("update-qualified-users-group.php");
            $output_str = implode("\n", $output_lines);
            $output = _json_decode($output_str, associative: true);
            $this->assertEquals($expectedOutput, $output);
            $this->switchUser("Blank");
            $this->assertTrue(
                $LDAP->userFlagGroups["qualified"]->memberUIDExists("non_native_user1"),
            );
        } finally {
            if ($LDAP->userFlagGroups["qualified"]->memberUIDExists("non_native_user1")) {
                $LDAP->userFlagGroups["qualified"]->removeMemberUID("non_native_user1");
            }
        }
    }
}
