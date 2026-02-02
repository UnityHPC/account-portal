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
require_once __DIR__ . "/lib/UnityConfig.php";
require_once __DIR__ . "/lib/UnityWebhook.php";
require_once __DIR__ . "/lib/UnityGithub.php";
require_once __DIR__ . "/lib/utils.php";
require_once __DIR__ . "/lib/CSRFToken.php";
require_once __DIR__ . "/lib/exceptions/NoDieException.php";
require_once __DIR__ . "/lib/exceptions/SSOException.php";
require_once __DIR__ . "/lib/exceptions/ArrayKeyException.php";
require_once __DIR__ . "/lib/exceptions/CurlException.php";
require_once __DIR__ . "/lib/exceptions/EntryNotFoundException.php";
require_once __DIR__ . "/lib/exceptions/EnsureException.php";
require_once __DIR__ . "/lib/exceptions/EncodingUnknownException.php";
require_once __DIR__ . "/lib/exceptions/EncodingConversionException.php";
require_once __DIR__ . "/lib/exceptions/UnityHTTPDMessageNotFoundException.php";

require_once __DIR__ . "/config.php";
require __DIR__ . "/init.php";
