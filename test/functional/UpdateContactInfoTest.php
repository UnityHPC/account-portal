<?php

class UpdateContactInfoTest extends UnityWebPortalTestCase
{
    public function testUpdateContactInfo()
    {
        global $USER, $SSO;
        $this->switchUser("Blank");
        $server_before = $_SERVER;
        $before = [$USER->getFirstname(), $USER->getLastname(), $USER->getMail()];
        try {
            $_SERVER["givenName"] = "new first name";
            $_SERVER["sn"] = "new last name";
            $_SERVER["mail"] = "new mail";
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertEquals("new first name", $USER->getFirstname());
            $this->assertEquals("new last name", $USER->getLastname());
            $this->assertEquals("new mail", $USER->getMail());
        } finally {
            $USER->setFirstname($before[0]);
            $USER->setLastname($before[1]);
            $USER->setMail($before[2]);
            $_SERVER = $server_before;
        }
    }

    public function testViewAsUserDoesNotUpdateContactInfo()
    {
        global $USER, $SSO;
        $this->switchUser("Blank");
        $server_before = $_SERVER;
        $before = [$USER->getFirstname(), $USER->getLastname(), $USER->getMail()];
        $this->switchUser("Admin");
        $this->http_post(__DIR__ . "/../../webroot/admin/user-mgmt.php", [
            "form_type" => "viewAsUser",
            "uid" => self::$NICKNAME2UID["Blank"],
        ]);
        $this->assertArrayHasKey("viewUser", $_SESSION);
        try {
            $_SERVER["givenName"] = "new first name";
            $_SERVER["sn"] = "new last name";
            $_SERVER["mail"] = "new mail";
            session_write_close();
            $this->http_get(__DIR__ . "/../../resources/init.php");
            $this->assertNotEquals("new first name", $before[0]);
            $this->assertNotEquals("new last name", $before[1]);
            $this->assertNotEquals("new mail", $before[2]);
        } finally {
            $USER->setFirstname($before[0]);
            $USER->setLastname($before[1]);
            $USER->setMail($before[2]);
            $_SERVER = $server_before;
        }
    }
}
