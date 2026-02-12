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
    private static function validateAllValuesAreInts(array $x, string $name): void
    {
        foreach ($x as $value) {
            if (!is_int($value)) {
                throw new InvalidConfigurationException("all values of $name must be integers");
            }
        }
    }

    /** @param mixed[] $x */
    private static function validateArrayIsMonotonicallyIncreasing(array $x, string $name): void
    {
        self::validateArrayNotEmpty($x, $name);
        self::validateAllValuesAreInts($x, $name);
        if (count($x) === 1) {
            return;
        }
        $remaining_values = $x;
        $last_value = array_shift($remaining_values);
        while (count($remaining_values)) {
            $this_value = array_shift($remaining_values);
            if ($this_value < $last_value) {
                throw new InvalidConfigurationException("$name must be monotonically increasing");
            }
            $last_value = $this_value;
        }
    }

    /** @param mixed[] $CONFIG */
    public static function validateConfig(array $CONFIG): void
    {
        self::validateExpiryConfig($CONFIG);
        self::validateSmtpConfig($CONFIG);
    }

    /** @param mixed[] $CONFIG */
    private static function validateExpiryConfig(array $CONFIG): void
    {
        self::validateArrayIsMonotonicallyIncreasing(
            $CONFIG["expiry"]["idlelock_warning_days"],
            '$CONFIG["expiry"]["idlelock_warning_days"]',
        );
        self::validateArrayIsMonotonicallyIncreasing(
            $CONFIG["expiry"]["disable_warning_days"],
            '$CONFIG["expiry"]["disable_warning_days"]',
        );
        $idlelock_warning_days = $CONFIG["expiry"]["idlelock_warning_days"];
        $idlelock_day = $CONFIG["expiry"]["idlelock_day"];
        $disable_warning_days = $CONFIG["expiry"]["disable_warning_days"];
        $disable_day = $CONFIG["expiry"]["disable_day"];
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

    /** @param mixed[] $CONFIG */
    private static function validateSmtpConfig(array $CONFIG): void
    {
        self::validateStringNotEmpty($CONFIG["smtp"]["host"], '$CONFIG["smtp"]["host"]');
        self::validateStringNotEmpty($CONFIG["smtp"]["port"], '$CONFIG["smtp"]["port"]');
        self::validateOneOf($CONFIG["smtp"]["security"], '$CONFIG["smtp"]["security"]', [
            "",
            "tls",
            "ssl",
        ]);
        self::validateIsBool($CONFIG["smtp"]["ssl_verify"], '$CONFIG["smtp"]["ssl_verify"]');
    }

    /** @param mixed[] $x */
    private static function validateArrayNotEmpty(array $x, string $name): void
    {
        if (count($x) === 0) {
            throw new InvalidConfigurationException("$name must not be empty");
        }
    }

    private static function validateStringNotEmpty(string $x, string $name): void
    {
        if (empty($x)) {
            throw new InvalidConfigurationException("$name must not be empty");
        }
    }

    private static function validateIsBool(mixed $x, string $name): void
    {
        if (!is_bool($x)) {
            throw new InvalidConfigurationException("$name must be a boolean");
        }
    }

    /** @param mixed[] $options */
    private static function validateOneOf(string $x, string $name, array $options): void
    {
        foreach ($options as $option) {
            if ($x === $option) {
                return;
            } else {
                var_dump($x);
                echo "is not equal to...\n";
                var_dump($option);
            }
        }
        throw new InvalidConfigurationException(
            sprintf("%s must be one of %s", $name, _json_encode($options)),
        );
    }

    private static function assertHttpHostValid(string $host): void
    {
        if (!_preg_match("/^[a-zA-Z0-9._:-]+$/", $host)) {
            throw new \Exception("HTTP_HOST '$host' contains invalid characters!");
        }
    }
}
