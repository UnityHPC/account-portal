<?php

/**
 * autoload.php - this is the first file that is loaded on every webroot php file
 */

// Load Composer Libs
require_once __DIR__ . "/../vendor/autoload.php";

// submodule
require_once __DIR__ . "/lib/phpopenldaper/src/PHPOpenLDAPer/LDAPEntry.php";
require_once __DIR__ . "/lib/phpopenldaper/src/PHPOpenLDAPer/LDAPConn.php";

// load libs
require_once __DIR__ . "/lib/UnityLDAP.php";
require_once __DIR__ . "/lib/UnityUser.php";
require_once __DIR__ . "/lib/PosixGroup.php";
require_once __DIR__ . "/lib/UnityGroup.php";
require_once __DIR__ . "/lib/UnityOrg.php";
require_once __DIR__ . "/lib/UnitySQL.php";
require_once __DIR__ . "/lib/UnityMailer.php";
require_once __DIR__ . "/lib/UnitySSO.php";
require_once __DIR__ . "/lib/UnityHTTPD.php";
require_once __DIR__ . "/lib/UnityDeployment.php";
require_once __DIR__ . "/lib/UnityGithub.php";
require_once __DIR__ . "/lib/utils.php";
require_once __DIR__ . "/lib/CSRFToken.php";
require_once __DIR__ . "/lib/exceptions.php";
require_once __DIR__ . "/lib/UnitySlimFramework.php";
require_once __DIR__ . "/controllers/panel/account.php";
require_once __DIR__ . "/controllers/panel/groups.php";
require_once __DIR__ . "/controllers/panel/new_account.php";
require_once __DIR__ . "/controllers/panel/disabled_account.php";
require_once __DIR__ . "/controllers/panel/pi.php";
require_once __DIR__ . "/controllers/panel/modal.php";
require_once __DIR__ . "/controllers/panel/ajax.php";
require_once __DIR__ . "/controllers/lan/api.php";
require_once __DIR__ . "/controllers/admin/pi-mgmt.php";
require_once __DIR__ . "/controllers/admin/user-mgmt.php";
require_once __DIR__ . "/controllers/admin/ajax.php";
