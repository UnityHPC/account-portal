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

    #[DataProvider("provider")]
    public function testExpiry(string $uid, string $mail)
    {
        global $LDAP, $SQL, $MAILER, $WEBHOOK;
        $this->switchUser("Admin");
        $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
        $this->assertFalse($user->getFlag(UserFlag::IDLELOCKED));
        $this->assertFalse($user->getFlag(UserFlag::DISABLED));
        $warnings_sent = $SQL->getUserExpirationWarningDaysSent($uid);
        $this->assertEmpty($warnings_sent["idlelock"]);
        $this->assertEmpty($warnings_sent["disable"]);
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
            $this->assertMatchesRegularExpression(
                "/^sending idlelock email to \"$mail\" with data \{\"idle_days\":2,\"expiration_date\":\"[\d\/]+\",\"warning_number\":1,\"is_final_warning\":false\}$/",
                $output,
            );
            // 3 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 3);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertMatchesRegularExpression(
                "/^sending idlelock email to \"$mail\" with data \{\"idle_days\":3,\"expiration_date\":\"[\d\/]+\",\"warning_number\":2,\"is_final_warning\":true\}$/",
                $output,
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
            $this->assertMatchesRegularExpression(
                "/^sending disable email to \"$mail\" with data \{\"idle_days\":6,\"expiration_date\":\"[\d\/]+\",\"warning_number\":1,\"is_final_warning\":false\}$/",
                $output,
            );
            // 7 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 7);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
            $this->assertMatchesRegularExpression(
                "/^sending disable email to \"$mail\" with data \{\"idle_days\":7,\"expiration_date\":\"[\d\/]+\",\"warning_number\":2,\"is_final_warning\":true\}$/",
                $output,
            );
            // 8 ///////////////////////////////////////////////////////////////////////////////////
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 8);
            [$_, $output_lines] = executeWorker("user-expiry.php", "--verbose");
            $output = trim(implode("\n", $output_lines));
        } finally {
            $SQL->resetUserExpirationWarningDaysSent($uid);
            $user->setFlag(UserFlag::IDLELOCKED, false);
            if ($user->getFlag(UserFlag::DISABLED)) {
                $user->reEnable();
            }
            callPrivateMethod($SQL, "setUserLastLoginDaysAgo", $uid, 0);
        }
    }
}
