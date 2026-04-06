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
    private static function assertAllValuesAreInts(array $x, string $name): void
    {
        foreach ($x as $value) {
            assert(is_int($value), "all values of $name must be integers");
        }
    }

    /** @param mixed[] $x */
    private static function assertArrayIsMonotonicallyIncreasing(array $x, string $name): void
    {
        self::assertArrayNotEmpty($x, $name);
        self::assertAllValuesAreInts($x, $name);
        if (count($x) === 1) {
            return;
        }
        $remaining_values = $x;
        $last_value = array_shift($remaining_values);
        while (count($remaining_values)) {
            $this_value = array_shift($remaining_values);
            assert($this_value >= $last_value, "$name must be monotonically increasing");
            $last_value = $this_value;
        }
    }

    /** @param mixed[] $CONFIG */
    private static function validateConfig(array $CONFIG): void
    {
        try {
            self::validateExpiryConfig($CONFIG);
            self::validateSmtpConfig($CONFIG);
        } catch (AssertionError $e) {
            throw new InvalidConfigurationException(previous: $e);
        }
    }

    /** @param mixed[] $CONFIG */
    private static function validateExpiryConfig(array $CONFIG): void
    {
        $idlelock_warning_days = $CONFIG["expiry"]["idlelock_warning_days"];
        $idlelock_day = $CONFIG["expiry"]["idlelock_day"];
        $disable_warning_days = $CONFIG["expiry"]["disable_warning_days"];
        $disable_day = $CONFIG["expiry"]["disable_day"];
        self::assertArrayIsMonotonicallyIncreasing(
            $idlelock_warning_days,
            '$CONFIG["expiry"]["idlelock_warning_days"]',
        );
        self::assertArrayIsMonotonicallyIncreasing(
            $disable_warning_days,
            '$CONFIG["expiry"]["disable_warning_days"]',
        );
        $final_disable_warning_day = _array_last($disable_warning_days);
        $final_idlelock_warning_day = _array_last($idlelock_warning_days);
        assert(
            $disable_day > $final_disable_warning_day,
            "disable day must be greater than the last disable warning day",
        );
        assert(
            $idlelock_day > $final_idlelock_warning_day,
            "idlelock day must be greater than the last idlelock warning day",
        );
        assert($disable_day > $idlelock_day, "disable day must be greater than idlelock day");
    }

    /** @param mixed[] $CONFIG */
    private static function validateSmtpConfig(array $CONFIG): void
    {
        self::assertStringNotEmpty($CONFIG["smtp"]["host"], '$CONFIG["smtp"]["host"]');
        self::assertStringNotEmpty($CONFIG["smtp"]["port"], '$CONFIG["smtp"]["port"]');
        self::assertOneOf($CONFIG["smtp"]["security"], '$CONFIG["smtp"]["security"]', [
            "",
            "tls",
            "ssl",
        ]);
        self::assertIsBool($CONFIG["smtp"]["ssl_verify"], '$CONFIG["smtp"]["ssl_verify"]');
    }

    /** @param mixed[] $x */
    private static function assertArrayNotEmpty(array $x, string $name): void
    {
        assert(count($x) > 0, "$name must not be empty");
    }

    private static function assertStringNotEmpty(string $x, string $name): void
    {
        assert(!empty($x), "$name must not be empty");
    }

    private static function assertIsBool(mixed $x, string $name): void
    {
        assert(is_bool($x), "$name must be a boolean");
    }

    /** @param mixed[] $options */
    private static function assertOneOf(string $x, string $name, array $options): void
    {
        assert(
            in_array($x, $options, true),
            sprintf("%s must be one of %s", $name, _json_encode($options)),
        );
    }

    private static function assertHttpHostValid(string $host): void
    {
        if (!_preg_match("/^[a-zA-Z0-9._:-]+$/", $host)) {
            throw new \Exception("HTTP_HOST '$host' contains invalid characters!");
        }
    }

    public static function getTemplatePath(string $basename): string
    {
        $deployment = __DIR__ . "/../../deployment/";
        if (($host = $_SERVER["HTTP_HOST"] ?? null) !== null) {
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

    /** @return string[] */
    public static function getMailDirs(): array
    {
        $output = [];
        $deployment = __DIR__ . "/../../deployment/";
        if (($host = $_SERVER["HTTP_HOST"] ?? null) !== null) {
            $domain_override_templates_dir = "$deployment/domain_overrides/$host/mail/";
            if (file_exists($domain_override_templates_dir)) {
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
