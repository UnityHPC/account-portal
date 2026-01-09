<?php

require_once __DIR__ . "/../../../resources/autoload.php";  // Load required libs

use UnityWebPortal\lib\UnityHTTPD;

$search_query = UnityHTTPD::getQueryParameter("search");
if (empty($search_query)) {
    echo "<span>No Results</span>";
    UnityHTTPD::die();
}

$assocs = $LDAP->getAllNonDefunctPIGroups($SQL, $MAILER, $WEBHOOK);

$MAX_COUNT = 10;  // Max results of PI search

$out = array();
foreach ($assocs as $assoc_obj) {
    $assoc = $assoc_obj->gid;
    // loop through each association
    if (strpos($assoc, $search_query) !== false) {
        array_push($out, $assoc);
        if (count($out) >= $MAX_COUNT) {
            break;
        }
    }
    try {
        $fn = strtolower($assoc_obj->getOwner()->getFullName());
    } catch (Throwable $e) {
        UnityHTTPD::errorLog(
            "warning",
            "failed to get owner name for PI group '$assoc'",
            error: $e
        );
        $fn = "";
    }
    if (strpos($fn, strtolower($search_query)) !== false) {
        if (!in_array($assoc, $out)) {
            array_push($out, $assoc);
            if (count($out) >= $MAX_COUNT) {
                break;
            }
        }
    }
}

foreach ($out as $pi_acct) {
    echo "<span>$pi_acct</span>";
}
