<?php

use UnityWebPortal\lib\UnityGroup;

class WorkerGroupExpiryTest extends UnityWebPortalTestCase
{
    private function runGroupExpiryWorker(int $timestamp)
    {
        [$_, $output_lines] = executeWorker("group-expiry.php", "--timestamp=$timestamp");
        return $output_lines;
    }

    public function testGroupExpiryWorker()
    {
        global $USER, $LDAP, $SQL;
        $this->switchUser("NormalPI");
        $gid = UnityGroup::ownerUID2GID($USER->uid);
        $pi_group_entry = $LDAP->getPIGroupEntry($gid);
        $member_uids_before = $pi_group_entry->getAttribute("memberUid");
        sort($member_uids_before);
        $manager_uids_before = $pi_group_entry->getAttribute("managerUid");
        $disabled_before = $pi_group_entry->getAttribute("isDisabled")[0] ?? null;
        $expiration_date_before = $SQL->getPIGroupExpirationDate($gid);
        try {
            $time = time();
            $SQL->setPIGroupExpirationDate($gid, $time);
            $output_lines = $this->runGroupExpiryWorker($time - 1);
            $this->assertEquals([], $output_lines);
            $output_lines = $this->runGroupExpiryWorker($time + 1);
            $this->assertEquals(
                [
                    sprintf(
                        "group '%s' expired on %s, disabling group and removing members %s",
                        $gid,
                        date("Y/m/d", $time),
                        _json_encode($member_uids_before),
                    ),
                ],
                $output_lines,
            );
            $output_lines = $this->runGroupExpiryWorker($time + 1);
            $this->assertEquals([], $output_lines);
        } finally {
            $pi_group_entry->setAttribute("memberUid", $member_uids_before);
            $pi_group_entry->setAttribute("managerUid", $manager_uids_before);
            if ($disabled_before === null) {
                if ($pi_group_entry->hasAttribute("isDisabled")) {
                    $pi_group_entry->removeAttribute("isDisabled");
                }
            } else {
                $pi_group_entry->setAttribute("isDisabled", $disabled_before);
            }
            if ($expiration_date_before === null) {
                $SQL->removePIGroupExpirationDate($gid);
            } else {
                $SQL->setPIGroupExpirationDate($gid, $expiration_date_before);
            }
            $LDAP->userFlagGroups["qualified"]->overwriteMemberUIDs(
                array_merge(
                    $LDAP->userFlagGroups["qualified"]->getMemberUIDs(),
                    $member_uids_before,
                ),
            );
        }
    }
}
