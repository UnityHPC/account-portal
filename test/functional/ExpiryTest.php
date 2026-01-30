<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

class ExpiryTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        $output = [];
        foreach (["Blank", "EmptyPIGroupOwner"] as $nickname) {
            $uid = self::$NICKNAME2UID[$nickname];
            array_push($output, [$uid, self::$UID2ATTRIBUTES[$uid][3]]);
        }
        return $output;
    }

    private function assertOnlyOneWarningEmailSent(
        string $output,
        string $type,
        string $mail,
        int $day,
        bool $is_final,
    ) {
        $fmt =
            '/^sending %s email to "%s" with data \{"idle_days":%s,"expiration_date":"[\d\/]+","is_final_warning":%s(,"pi_group_gid":"[^"]+")?\}$/';
        $regex = sprintf($fmt, $type, $mail, $day, $is_final ? "true" : "false");
        $this->assertMatchesRegularExpression($regex, $output);
    }

    private function runExpiryWorker(int $idle_days, int $seconds_offset = 0): string
    {
        $days_since_epoch = $idle_days + 1; // last login was 1 day after epoch
        [$_, $output_lines] = executeWorker(
            "user-expiry.php",
            "--verbose --timestamp=" . $days_since_epoch * 24 * 60 * 60 + $seconds_offset,
        );
        return trim(implode("\n", $output_lines));
    }

    #[DataProvider("provider")]
    public function testExpiry(string $uid, string $mail)
    {
        global $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Admin");
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $ssh_keys_before = $user->getSSHKeys();
        $last_login_before = callPrivateMethod($SQL, "getUserLastLogin", $uid);
        $this->assertFalse($user->getFlag(UserFlag::IDLELOCKED));
        $this->assertFalse($user->getFlag(UserFlag::DISABLED));
        if ($user->getPIGroup()->exists()) {
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
                "idlelock",
                $mail,
                day: 2,
                is_final: false,
            );
            // 3 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 3);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "idlelock",
                $mail,
                day: 3,
                is_final: true,
            );
            // 4 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 4);
            $this->assertEquals("idle-locking user '$uid'", $output);
            $this->assertTrue($user->getFlag(UserFlag::IDLELOCKED));
            // 5 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 5);
            $this->assertEquals("", $output);
            // 6 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 6);
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "disable",
                $mail,
                day: 6,
                is_final: false,
            );
            // 7 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 7);
            $this->assertOnlyOneWarningEmailSent($output, "disable", $mail, day: 7, is_final: true);
            // 8 ///////////////////////////////////////////////////////////////////////////////////
            $output = $this->runExpiryWorker(idle_days: 8);
            $this->assertEquals("disabling user '$uid'", $output);
            $this->assertTrue($user->getFlag(UserFlag::DISABLED));
            if ($user->getPIGroup()->exists()) {
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
            if ($user->getPIGroup()->exists() && $user->getPIGroup()->getIsDisabled()) {
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
}
