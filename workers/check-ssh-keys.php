#!/usr/bin/env php
<?php
include __DIR__ . "/init.php";

$errors = [];
foreach (
    $LDAP->getAllNativeUsersAttributes(["uid", "sshPublicKey"], ["sshPublicKey" => []])
    as $attrs
) {
    foreach ($attrs["sshpublickey"] as $i => $key) {
        try {
            tokenizeSSHKey($key);
            getSSHKeyInfo($key);
        } catch (Throwable $e) {
            array_push($errors, [
                "user" => $attrs["uid"][0],
                "key" => $key,
                "exception message" => $e->getMessage(),
            ]);
        }
    }
}

echo _json_encode($errors, JSON_PRETTY_PRINT);
echo "\n";

