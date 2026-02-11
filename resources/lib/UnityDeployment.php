<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\InvalidConfigurationException;

class UnityDeployment
{
    public static function getTemplatePath(string $basename): string
    {
        $deployment = __DIR__ . "/../../deployment/";
        $host = $_SERVER["HTTP_HOST"] ?? null;
        if ($host !== null) {
            $domain_override_template = "$deployment/domain_overrides/$host/templates/$basename";
            if (file_exists($domain_override_template)) {
                return $domain_override_template;
            }
        }
        $deployment_template = "$deployment/templates/$basename";
        if (file_exists($deployment_template)) {
            return $deployment_template;
        }
        $output = __DIR__ . "/../templates/$basename";
        if (file_exists($output)) {
            return $output;
        } else {
            throw new \Exception("no such template: '$basename'");
        }
    }

    public static function getMailPath(string $name): string
    {
        $deployment = __DIR__ . "/../../deployment/";
        $host = $_SERVER["HTTP_HOST"] ?? null;
        if ($host !== null) {
            $override_mail = "$deployment/domain_overrides/$host/mail/$name.php";
            if (file_exists($override_mail)) {
                return $override_mail;
            }
        }
        $deployment_mail = "$deployment/mail/$name.php";
        if (file_exists($deployment_mail)) {
            return $deployment_mail;
        }
        $output = __DIR__ . "/../mail/$name.php";
        if (file_exists($output)) {
            return $output;
        } else {
            throw new \Exception("no such mail: '$name'");
        }
    }

    /** @return array<string, int> */
    public static function getCustomIDMappings(): array
    {
        $output = [];
        $dir = new \DirectoryIterator(self::getCustomIDMappingsDirPath());
        foreach ($dir as $fileinfo) {
            $filename = $fileinfo->getFilename();
            if ($fileinfo->isDot() || $filename == "README.md") {
                continue;
            }
            if ($fileinfo->getExtension() == "csv") {
                $handle = _fopen($fileinfo->getPathname(), "r");
                while (($row = fgetcsv($handle, null, ",")) !== false) {
                    array_push($output, $row);
                }
            } else {
                UnityHTTPD::errorLog(
                    "warning",
                    "custom ID mapping file '$filename' ignored, extension != .csv",
                );
            }
        }
        $output_map = [];
        foreach ($output as $i => $row) {
            $num_columns = count($row);
            if ($num_columns !== 2) {
                throw new \Exception(
                    sprintf(
                        "custom user mapping %s has %s columns, expected 2 columns",
                        _json_encode($row),
                        $num_columns,
                    ),
                );
            }
            [$uid, $uidNumber_str] = $row;
            if ($uidNumber_str === null) {
                throw new \Exception("uidNumber_str is null");
            }
            $output_map[$uid] = digits2int($uidNumber_str);
        }
        return $output_map;
    }

    private static function getCustomIDMappingsDirPath(): string
    {
        $output = __DIR__ . "/../../deployment/" . CONFIG["ldap"]["custom_user_mappings_dir"];
        if (is_dir($output)) {
            return $output;
        } else {
            throw new \Exception("no custom_user_mappings directory found");
        }
    }

    /** @return mixed[] */
    public static function getConfig(): array
    {
        $CONFIG = [];
        $deployment = __DIR__ . "/../../deployment/";
        $CONFIG = self::mergeConfig($CONFIG, "$deployment/config.base.ini");
        $CONFIG = self::mergeConfig($CONFIG, "$deployment/config.ini");
        $host = $_SERVER["HTTP_HOST"] ?? null;
        if ($host !== null) {
            self::assertHttpHostValid($host);
            $CONFIG = self::mergeConfig($CONFIG, "$deployment/domain_overrides/$host/config.ini");
        }
        self::validateConfig($CONFIG);
        return $CONFIG;
    }

    /**
     * @param mixed[] $CONFIG
     * @return mixed[]
     */
    private static function mergeConfig(array $CONFIG, string $path): array
    {
        if (file_exists($path)) {
            $override = _parse_ini_file($path, true, INI_SCANNER_TYPED);
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
        if (!_preg_match("/^[a-zA-Z0-9._-]+$/", $host)) {
            throw new \Exception("HTTP_HOST '$host' contains invalid characters!");
        }
    }
}
