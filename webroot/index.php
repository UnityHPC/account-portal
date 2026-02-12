<?php

require_once __DIR__ . "/../resources/autoload.php";

use UnityWebPortal\lib\UnityDeployment;

require UnityDeployment::getTemplatePath("header.php");
require UnityDeployment::getTemplatePath("home.php");
require UnityDeployment::getTemplatePath("footer.php");
