<?php

namespace UnityWebPortal\lib;

use AssertionError;
use UnityWebPortal\lib\exceptions\InvalidConfigurationException;

class UnityDeployment
{
    /** @return mixed[] */
    public static function getConfig(): array
    {
        $CONFIG = [];
        $deployment = __DIR__ . "/../../deployment/";
        $CONFIG = self::pullConfig($CONFIG, "$deployment/config.base.ini");
        $CONFIG = self::pullConfig($CONFIG, "$deployment/config.ini");
        $host = $_SERVER["HTTP_HOST"] ?? null;
        if ($host !== null) {
            self::assertHttpHostValid($host);
            $CONFIG = self::pullConfig($CONFIG, "$deployment/domain_overrides/$host/config.ini");
        }
        self::validateConfig($CONFIG);
        return $CONFIG;
    }

    /**
     * @param mixed[] $CONFIG
     * @return mixed[]
     */
    private static function pullConfig(array $CONFIG, string $loc): array
    {
        $file_loc = $loc;
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
    private static function validateConfig(array $CONFIG): void
    {
        $idlelock_warning_days = $CONFIG["expiry"]["idlelock_warning_days"];
        $idlelock_day = $CONFIG["expiry"]["idlelock_day"];
        $disable_warning_days = $CONFIG["expiry"]["disable_warning_days"];
        $disable_day = $CONFIG["expiry"]["disable_day"];
        if (count($idlelock_warning_days) === 0) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["idlelock_warning_days"] must not be empty!',
            );
        }
        if (count($disable_warning_days) === 0) {
            throw new InvalidConfigurationException(
                '$CONFIG["expiry"]["disable_warning_days"] must not be empty!',
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
        if (!_preg_match("/^[a-zA-Z0-9._:-]+$/", $host)) {
            throw new \Exception("HTTP_HOST '$host' contains invalid characters!");
        }
    }

    public static function getTemplatePath(string $basename): string
    {
        $dirs = self::getTemplateDirs();
        foreach ($dirs as $dir) {
            $path = "$dir/$basename";
            if (file_exists($path)) {
                return $path;
            }
        }
        throw new \Exception("no such template: '$basename'. searched in: " . _json_encode($dirs));
    }

    /** @return string[] */
    public static function getTemplateDirs(): array
    {
        $output = [];
        $deployment = __DIR__ . "/../../deployment/";
        if (($host = $_SERVER["HTTP_HOST"] ?? null) !== null) {
            $domain_override_templates_dir = "$deployment/domain_overrides/$host/templates/";
            if (is_dir($domain_override_templates_dir)) {
                array_push($output, $domain_override_templates_dir);
            }
        }
        if (is_dir("$deployment/mail/")) {
            array_push($output, "$deployment/templates/");
        }
        array_push($output, __DIR__ . "/../templates/");
        return $output;
    }

    /** @return string[] */
    public static function getMailDirs(): array
    {
        $output = [];
        $deployment = __DIR__ . "/../../deployment/";
        if (($host = $_SERVER["HTTP_HOST"] ?? null) !== null) {
            $domain_override_templates_dir = "$deployment/domain_overrides/$host/mail/";
            if (is_dir($domain_override_templates_dir)) {
                array_push($output, $domain_override_templates_dir);
            }
        }
        if (is_dir("$deployment/mail/")) {
            array_push($output, "$deployment/mail/");
        }
        array_push($output, __DIR__ . "/../mail/");
        return $output;
    }

    /** @return array<string, int> */
    public static function getCustomIDMappings(): array
    {
        $output = [];
        $dir_path = __DIR__ . "/../../" . CONFIG["ldap"]["custom_user_mappings_dir"];
        if (!is_dir($dir_path)) {
            throw new \Exception("custom_user_mappings directory '$dir_path' is not a directory");
        }
        $dir = new \DirectoryIterator($dir_path);
        foreach ($dir as $fileinfo) {
            $filename = $fileinfo->getFilename();
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->getExtension() !== "csv") {
                UnityHTTPD::errorLog("warning", "ID map file $filename ignored, extension != .csv");
                continue;
            }
            $i = 1;
            $handle = _fopen($fileinfo->getPathname(), "r");
            try {
                while (($row = fgetcsv($handle, separator: ",")) !== false) {
                    try {
                        assert(count($row) === 2);
                        assert(is_string($row[1]));
                        assert(ctype_digit($row[1]));
                    } catch (AssertionError $e) {
                        throw new \Exception("bad ID mapping $filename row $i", previous: $e);
                    }
                    $output[$row[0]] = digits2int($row[1]);
                    $i++;
                }
            } finally {
                _fclose($handle);
            }
        }
        return $output;
    }
}
