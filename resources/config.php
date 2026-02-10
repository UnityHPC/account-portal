<?php

use UnityWebPortal\lib\UnityConfig;

define("CONFIG", UnityConfig::getConfig(__DIR__ . "/../defaults", __DIR__ . "/../deployment"));

UnityConfig::validateConfig(CONFIG);
