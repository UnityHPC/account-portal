<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\InvalidConfigurationException;

class UnityConfig
{
    /** @return mixed[] */
    public static function getConfig(string $def_config_loc, string $deploy_loc): array
    {
        $CONFIG = _parse_ini_file($def_config_loc . "/config.ini.default", true, INI_SCANNER_TYPED);
        $CONFIG = self::pullConfig($CONFIG, $deploy_loc);
        if (array_key_exists("HTTP_HOST", $_SERVER)) {
            $cur_url = $_SERVER["HTTP_HOST"];
            self::assertHttpHostValid($cur_url);
            $url_override_path = $deploy_loc . "/overrides/" . $cur_url;
            if (is_dir($url_override_path)) {
                $CONFIG = self::pullConfig($CONFIG, $url_override_path);
            }
        }
        return $CONFIG;
    }

    /**
     * @param mixed[] $CONFIG
     * @return mixed[]
     */
    private static function pullConfig(array $CONFIG, string $loc): array
    {
        $file_loc = $loc . "/config/config.ini";
        if (file_exists($file_loc)) {
            $override = _parse_ini_file($file_loc, true, INI_SCANNER_TYPED);
            foreach ($override as $key1 => $val1) {
                foreach ($val1 as $key2 => $val2) {
                    $CONFIG[$key1][$key2] = $val2;
                }
            }
        }
        return $CONFIG;
    }

    /** @param mixed[] $x */
    private static function doesArrayHaveOnlyIntegerValues(array $x): bool
    {
        foreach ($x as $value) {
            if (!is_int($value)) {
                return false;
            }
        }
        return true;
    }

    /** @param int[] $x */
    private static function isArrayMonotonicallyIncreasing(array $x): bool
    {
        if (count($x) <= 1) {
            return true;
        }
        $remaining_values = $x;
        $last_value = array_shift($remaining_values);
        while (count($remaining_values)) {
            $this_value = array_shift($remaining_values);
            if ($this_value < $last_value) {
                return false;
            }
            $last_value = $this_value;
        }
        return true;
    }

    /** @param mixed[] $CONFIG */
    public static function validateConfig(array $CONFIG): void
    {
        $idlelock_warning_days = CONFIG["expiry"]["idlelock_warning_days"];
        $idlelock_day = CONFIG["expiry"]["idlelock_day"];
        $disable_warning_days = CONFIG["expiry"]["disable_warning_days"];
        $disable_day = CONFIG["expiry"]["disable_day"];
        if (count($idlelock_warning_days) === 0) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["idlelock_warning_days"] must not be empty!',
            );
        }
        if (count($disable_warning_days) === 0) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["idlelock_warning_days"] must not be empty!',
            );
        }
        if (!self::doesArrayHaveOnlyIntegerValues($idlelock_warning_days)) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["idlelock_warning_days"] must be a list of integers!',
            );
        }
        if (!self::doesArrayHaveOnlyIntegerValues($disable_warning_days)) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["disable_warning_days"] must be a list of integers!',
            );
        }
        if (!self::isArrayMonotonicallyIncreasing($idlelock_warning_days)) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["idlelock_warning_days"] must be monotonically increasing!',
            );
        }
        if (!self::isArrayMonotonicallyIncreasing($disable_warning_days)) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["disable_warning_days"] must be monotonically increasing!',
            );
        }

        $final_disable_warning_day = _array_last($disable_warning_days);
        $final_idlelock_warning_day = _array_last($idlelock_warning_days);
        if ($disable_day <= $final_disable_warning_day) {
            throw new InvalidConfigurationException(
                "disable day must be greater than the last disable warning day",
            );
        }
        if ($idlelock_day <= $final_idlelock_warning_day) {
            throw new InvalidConfigurationException(
                "idlelock day must be greater than the last idlelock warning day",
            );
        }
        if ($disable_day <= $idlelock_day) {
            throw new InvalidConfigurationException(
                "disable day must be greater than idlelock day",
            );
        }
    }

    private static function assertHttpHostValid(string $host): void
    {
        if (!_preg_match("/^[a-zA-Z0-9.-_]+$/", $host)) {
            throw new \Exception("HTTP_HOST '$host' contains invalid characters!");
        }
    }
}
