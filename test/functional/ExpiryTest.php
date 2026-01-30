<?php

use PHPUnit\Framework\Attributes\DataProvider;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UserFlag;

class ExpiryTest extends UnityWebPortalTestCase
{
    public static function provider()
    {
        return [
            [self::$NICKNAME2UID["Blank"], self::$UID2ATTRIBUTES[self::$NICKNAME2UID["Blank"]][3]],
            [
                self::$NICKNAME2UID["EmptyPIGroupOwner"],
                self::$UID2ATTRIBUTES[self::$NICKNAME2UID["EmptyPIGroupOwner"]][3],
            ],
        ];
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

    #[DataProvider("provider")]
    public function testExpiry(string $uid, string $mail)
    {
        global $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Admin");
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $ssh_keys_before = $user->getSSHKeys();
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
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertEquals("", $output);
            // 1 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 1);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertEquals("", $output);
            // 2 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 2);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "idlelock",
                $mail,
                day: 2,
                is_final: false,
            );
            // 3 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 3);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "idlelock",
                $mail,
                day: 3,
                is_final: true,
            );
            // 4 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 4);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            // 5 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 5);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertEquals("", $output);
            // 6 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 6);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertOnlyOneWarningEmailSent(
                $output,
                "disable",
                $mail,
                day: 6,
                is_final: false,
            );
            // 7 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 7);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertOnlyOneWarningEmailSent($output, "disable", $mail, day: 7, is_final: true);
            // 8 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 8);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
        } finally {
            $user->setFlag(UserFlag::IDLELOCKED, false);
            if ($user->getFlag(UserFlag::DISABLED)) {
                $user->reEnable();
            }
            if ($user->getPIGroup()->exists() && $user->getPIGroup()->getIsDisabled()) {
                callPrivateMethod($user->getPIGroup(), "reenable");
            }
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 0);
            callPrivateMethod($user, "setSSHKeys", $ssh_keys_before);
        }
    }
}
