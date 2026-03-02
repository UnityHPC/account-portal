<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

class ExpiryWorkerTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        $output = [];
        foreach ([["Blank", false], ["EmptyPIGroupOwner", true]] as [$nickname, $is_pi]) {
            $uid = self::$NICKNAME2UID[$nickname];
            array_push($output, [$uid, self::$UID2ATTRIBUTES[$uid][3], $is_pi]);
        }
        return $output;
    }

    private function assertOnlyOneWarningEmailSent(
        string $output,
        string $template,
        string $mail,
        int $day,
        bool $is_final,
    ) {
        $fmt =
            '/^sending %s email to "%s" with data \{"idle_days":%s,"expiration_date":"[\d\/]+","is_final_warning":%s(,"pi_group_gid":"[^"]+")?\}$/';
        $regex = sprintf($fmt, $template, $mail, $day, $is_final ? "true" : "false");
        $this->assertMatchesRegularExpression($regex, $output);
    }

    private function runExpiryWorker(int $idle_days, int $seconds_offset = 0): string
    {
        $days_since_epoch = $idle_days + 1; // assume last login was 1 day after epoch
        [$_, $output_lines] = executeWorker(
            "user-expiry.php",
            "--verbose --timestamp=" . $days_since_epoch * 24 * 60 * 60 + $seconds_offset,
        );
        return trim(implode("\n", $output_lines));
    }

    #[DataProvider("provider")]
    public function testExpiryWorker(string $uid, string $mail, bool $is_pi)
    {
        global $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Admin");
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $ssh_keys_before = $user->getSSHKeys();
        $last_login_before = $SQL->getUserLastLogin($uid);
        $this->assertFalse($user->getFlag(UserFlag::IDLELOCKED));
        $this->assertFalse($user->getFlag(UserFlag::DISABLED));
        if ($is_pi) {
            $this->assertFalse($user->getPIGroup()->getIsDisabled());
        }
        // see deployment/overrides/phpunit/config/config.ini
        $this->assertEquals(CONFIG["expiry"]["idlelock_warning_days"], [2, 3]);
        $this->assertEquals(CONFIG["expiry"]["idlelock_day"], 4);
        $this->assertEquals(CONFIG["expiry"]["disable_warning_days"], [6, 7]);
        $this->assertEquals(CONFIG["expiry"]["disable_day"], 8);
        try {
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $uid, 1 * 24 * 60 * 60);
            // one second before day 1 /////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 1, seconds_offset: -1);
            $this->assertEquals("", $output);
            // 1 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 1);
            $this->assertEquals("", $output);
            // 2 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 2);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "user_expiry_idlelock_warning",
                $mail,
                day: 2,
                is_final: false,
            );
            // 3 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 3);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "user_expiry_idlelock_warning",
                $mail,
                day: 3,
                is_final: true,
            );
            // 4 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 4);
            $this->assertMatchesRegularExpression("/idle-locking user '$uid'/", $output);
            $this->assertTrue($user->getFlag(UserFlag::IDLELOCKED));
            // 5 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 5);
            $this->assertEquals("", $output);
            // 6 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 6);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                $is_pi ? "user_expiry_disable_warning_pi" : "user_expiry_disable_warning_non_pi",
                $mail,
                day: 6,
                is_final: false,
            );
            // 7 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 7);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                $is_pi ? "user_expiry_disable_warning_pi" : "user_expiry_disable_warning_non_pi",
                $mail,
                day: 7,
                is_final: true,
            );
            // 8 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 8);
            $this->assertMatchesRegularExpression("/disabling user '$uid'/", $output);
            $this->assertTrue($user->getFlag(UserFlag::DISABLED));
            $this->assertEmpty($user->getSSHKeys());
            if ($is_pi) {
                $this->assertTrue($user->getPIGroup()->getIsDisabled());
            }
            // 9 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 9);
            $this->assertEquals("", $output);
        } finally {
            $user->setFlag(UserFlag::IDLELOCKED, false);
            if ($user->getFlag(UserFlag::DISABLED)) {
                $user->reEnable();
            }
            if ($is_pi && $user->getPIGroup()->getIsDisabled()) {
                callPrivateMethod($user->getPIGroup(), "reenable");
            }
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $uid, $last_login_before);
            }
            callPrivateMethod($user, "setSSHKeys", $ssh_keys_before);
        }
    }

    public function testExpiryIgnoresImmortal()
    {
        global $USER, $SQL;
        $this->switchUser("ImmortalNotPI");
        $ssh_keys_before = $USER->getSSHKeys();
        $last_login_before = callPrivateMethod($SQL, "getUserLastLogin", $USER->uid);
        $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
        $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
        // see deployment/overrides/phpunit/config/config.ini
        $this->assertEquals(CONFIG["expiry"]["idlelock_warning_days"], [2, 3]);
        $this->assertEquals(CONFIG["expiry"]["idlelock_day"], 4);
        $this->assertEquals(CONFIG["expiry"]["disable_warning_days"], [6, 7]);
        $this->assertEquals(CONFIG["expiry"]["disable_day"], 8);
        try {
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, 1 * 24 * 60 * 60);
            foreach (range(1, 9) as $day) {
                $output = $this->runExpiryWorker(idle_days: $day);
                $this->assertEquals("", $output);
            }
            $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
            $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
            $this->assertEqualsCanonicalizing($ssh_keys_before, $USER->getSSHKeys());
        } finally {
            $USER->setFlag(UserFlag::IDLELOCKED, false);
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $last_login_before);
            }
            callPrivateMethod($USER, "setSSHKeys", $ssh_keys_before);
        }
    }

    public function testWarningUserPassedOver()
    {
        global $USER, $SQL;
        $this->switchUser("Blank");
        $last_login_before = $SQL->getUserLastLogin($USER->uid);
        try {
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, 1 * 24 * 60 * 60);

            $this->assertEquals(CONFIG["expiry"]["idlelock_day"], 4);
            $output = $this->runExpiryWorker(idle_days: 5);
            $this->assertEquals(
                "WARNING: user '$USER->uid' should have already been idlelocked, but isn't!",
                $output,
            );

            $this->assertEquals(CONFIG["expiry"]["disable_day"], 8);
            $output = $this->runExpiryWorker(idle_days: 9);
            $this->assertEquals(
                implode("\n", [
                    "WARNING: user '$USER->uid' should have already been idlelocked, but isn't!",
                    "WARNING: user '$USER->uid' should have already been disabled, but isn't!",
                ]),
                $output,
            );
        } finally {
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $last_login_before);
            }
        }
    }

    public function testGroupMembersNotified()
    {
        global $USER, $SQL;
        $this->switchUser("EmptyPIGroupOwner");
        $last_login_before = $SQL->getUserLastLogin($USER->uid);
        $owner = $USER;
        $pi_group = $USER->getPIGroup();
        $this->switchUser("Blank");
        try {
            $owner->setFlag(UserFlag::IDLELOCKED, true);
            $pi_group->newUserRequest($USER, false);
            $pi_group->approveUser($USER, false);
            $this->assertEqualsCanonicalizing(
                [$owner->uid, $USER->uid],
                $pi_group->getMemberUIDs(),
            );
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $owner->uid, 1 * 24 * 60 * 60);
            $final_disable_warning_day =
                CONFIG["expiry"]["disable_warning_days"][
                    array_key_last(CONFIG["expiry"]["disable_warning_days"])
                ];
            $this->assertEquals(7, $final_disable_warning_day);
            $output = $this->runExpiryWorker(idle_days: 7);
            $this->assertEquals(
                sprintf(
                    "sending %s email to %s with data %s\nsending %s email to %s with data %s",
                    "user_expiry_disable_warning_pi",
                    '"' . $owner->getMail() . '"',
                    _json_encode([
                        "idle_days" => 7,
                        "expiration_date" => "1970/01/10",
                        "is_final_warning" => true,
                        "pi_group_gid" => $owner->getPIGroup()->gid,
                    ]),
                    "user_expiry_disable_warning_member",
                    '["' . $USER->getMail() . '"]',
                    _json_encode([
                        "idle_days" => 7,
                        "expiration_date" => "1970/01/10",
                        "is_final_warning" => true,
                        "pi_group_gid" => $owner->getPIGroup()->gid,
                    ]),
                ),
                $output,
            );
        } finally {
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $owner->uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $owner->uid, $last_login_before);
            }
            if ($pi_group->memberUIDExists($USER->uid)) {
                $pi_group->removeMemberUID($USER->uid);
            }
            $owner->setFlag(UserFlag::IDLELOCKED, false);
        }
    }

    public function testGroupOwnerManagersNotified()
    {
        global $USER, $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("EmptyPIGroupOwner");
        $owner = $USER;
        $pi_group = $USER->getPIGroup();
        $this->assertEmpty($pi_group->getManagerUIDs());
        $manager_uid = self::$NICKNAME2UID["Admin"];
        $manager = new UnityUser($manager_uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $this->switchUser("Blank");
        $last_login_before = $SQL->getUserLastLogin($USER->uid);
        try {
            $pi_group->newUserRequest($USER, false);
            $pi_group->approveUser($USER, false);
            $pi_group->newUserRequest($manager, false);
            $pi_group->approveUser($manager, false);
            $pi_group->addManagerUID($manager_uid);
            $this->assertEqualsCanonicalizing(
                [$owner->uid, $manager_uid, $USER->uid],
                $pi_group->getMemberUIDs(),
            );
            // set last login to one day after epoch
            callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, 1 * 24 * 60 * 60);
            // idlelock ////////////////////////////////////////////////////////////////////////////
            $idlelock_day = CONFIG["expiry"]["idlelock_day"];
            $this->assertEquals(4, $idlelock_day);
            $output = $this->runExpiryWorker(idle_days: 4);
            $this->assertEquals(
                sprintf(
                    "idle-locking user '%s'\nsending %s email to %s with data %s",
                    $USER->uid,
                    "group_user_idlelocked_owner",
                    _json_encode([
                        $pi_group->addPlusAddressToMail($manager->getMail()),
                        $owner->getMail(),
                    ]),
                    _json_encode([
                        "group" => $pi_group->gid,
                        "user" => $USER->uid,
                        "org" => $USER->getOrg(),
                        "name" => $USER->getFullname(),
                        "email" => $USER->getMail(),
                    ]),
                ),
                $output,
            );
            // disable /////////////////////////////////////////////////////////////////////////////
            $disable_day = CONFIG["expiry"]["disable_day"];
            $this->assertEquals(8, $disable_day);
            $output = $this->runExpiryWorker(idle_days: 8);
            $this->assertEquals(
                sprintf(
                    "disabling user '%s'\nsending %s email to %s with data %s",
                    $USER->uid,
                    "group_user_disabled_owner",
                    _json_encode([
                        $pi_group->addPlusAddressToMail($manager->getMail()),
                        $owner->getMail(),
                    ]),
                    _json_encode([
                        "group" => $pi_group->gid,
                        "user" => $USER->uid,
                        "org" => $USER->getOrg(),
                        "name" => $USER->getFullname(),
                        "email" => $USER->getMail(),
                    ]),
                ),
                $output,
            );
        } finally {
            if ($last_login_before === null) {
                callPrivateMethod($SQL, "removeUserLastLogin", $USER->uid);
            } else {
                callPrivateMethod($SQL, "setUserLastLogin", $USER->uid, $last_login_before);
            }
            if ($pi_group->memberUIDExists($USER->uid)) {
                $pi_group->removeMemberUID($USER->uid);
            }
            if ($pi_group->memberUIDExists($manager_uid)) {
                $pi_group->removeMemberUID($manager_uid);
            }
            $USER->setFlag(UserFlag::IDLELOCKED, false);
            if ($USER->getFlag(UserFlag::DISABLED)) {
                $USER->reEnable();
            }
        }
    }
}
