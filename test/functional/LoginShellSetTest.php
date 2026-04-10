<?php

use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use UnityWebPortal\lib\UnityHTTPD;

class LoginShellSetTest extends UnityWebPortalTestCase
{
    public function testSetLoginShell(): void
    {
        global $USER;
        $this->switchUser("Blank");
        $before = $USER->getLoginShell();
        try {
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "loginshell",
                "shellSelect" => "/bin/bash",
            ]);
            $this->assertEquals("/bin/bash", $USER->getLoginShell());
            $this->http_post(__DIR__ . "/../../webroot/panel/account.php", [
                "form_type" => "loginshell",
                "shellSelect" => "/bin/zsh",
            ]);
            $this->assertEquals("/bin/zsh", $USER->getLoginShell());
            UnityHTTPD::clearMessages();
            $this->http_post(
                __DIR__ . "/../../webroot/panel/account.php",
                [
                    "form_type" => "loginshell",
                    "shellSelect" => "foobar",
                ],
                do_validate_messages: false,
            );
            $this->assertMessageExists(
                UnityHTTPDMessageLevel::ERROR,
                "/.*/",
                "/invalid login shell/",
            );
            $this->assertEquals("/bin/zsh", $USER->getLoginShell());
        } finally {
            $USER->setLoginShell($before);
        }
    }
}
