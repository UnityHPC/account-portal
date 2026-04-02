#!/usr/bin/env php
<?php
/**
Emails PIs that have oustanding member requests once a week for 4 weeks.
Removes the request after 34 days have passed.
 */

include __DIR__ . "/init.php";
use UnityWebPortal\lib\UnityGroup;

$today = time();
$accounts = $LDAP->getAllNonDisabledPIGroups($SQL, $MAILER);
foreach ($accounts as $pi_group) {
    $pi_user = $pi_group->getOwner();
    $requests = $pi_group->getRequests();
    foreach ($requests as $request) {
        $request_date = strtotime($request[1]);
        $daysDifference = ($today - $request_date) / (60 * 60 * 24);
        if ($daysDifference > 34) {
            // No interface in UnityGroup for this, so use DB directly
            $SQL->removeRequest($request[0]->uid, $pi_group->gid);
        } elseif ($daysDifference > 1 && $daysDifference % 7 == 0) {
            $new_user = $request[0];
            // send email to PI
            $MAILER->sendMail($pi_user->getMail(), "group_user_request_owner", [
                "group" => $pi_group->gid,
                "user" => $new_user->uid,
                "name" => $new_user->getFullName(),
                "email" => $new_user->getMail(),
                "org" => $new_user->getOrg(),
            ]);
        }
    }
}

