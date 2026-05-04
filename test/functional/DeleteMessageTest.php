<?php

use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;

class DeleteMessageTest extends UnityWebPortalTestCase
{
    public function testDeleteMessage(): void
    {
        $this->switchUser("Blank");
        $initial = UnityHTTPD::getMessages();
        $this->assertEmpty($initial);
        UnityHTTPD::messageDebug("foo1", "bar1");
        UnityHTTPD::messageDebug("foo2", "bar2");
        UnityHTTPD::messageDebug("foo3", "bar3");
        UnityHTTPD::messageError("foo", "bar");
        UnityHTTPD::messageInfo("foo", "bar");
        UnityHTTPD::messageSuccess("foo", "bar");
        UnityHTTPD::messageWarning("foo", "bar");
        try {
            $before = array_map("_json_encode", UnityHTTPD::getMessages());
            $this->http_post(
                __DIR__ . "/../../webroot/panel/ajax/delete_message.php",
                [
                    "level" => base64_encode("debug"),
                    "title" => base64_encode("foo2"),
                    "body" => base64_encode("bar2"),
                ],
                do_validate_messages: false,
            );
            $after = array_map("_json_encode", UnityHTTPD::getMessages());
            $difference = array_diff($before, $after);
            $message_expected_removed = ["foo2", "bar2", UnityHTTPDMessageLevel::DEBUG];
            $this->assertEqualsCanonicalizing(
                [_json_encode($message_expected_removed)],
                $difference,
            );
        } finally {
            UnityHTTPD::clearMessages();
        }
    }
}
