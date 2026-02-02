<?php

use UnityWebPortal\lib\exceptions\EnsureException;
use UnityWebPortal\lib\exceptions\EncodingUnknownException;
use UnityWebPortal\lib\exceptions\EncodingConversionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use UnityWebPortal\lib\exceptions\CurlException;

/**
 * like assert() but not subject to zend.assertions config
 * @throws EnsureException
 */
function ensure(bool $condition, ?string $message = null): void
{
    if (!$condition) {
        throw new EnsureException($message ?? "ensure condition is false");
    }
}

/**
 * key must take the form "KEY_TYPE KEY_DATA OPTIONAL_COMMENT"
 * @throws ValueError
 */
function removeSSHKeyOptionalCommentSuffix(string $key): string
{
    $matches = [];
    if (preg_match("/^(\S+ \S+)/", $key, $matches)) {
        return $matches[1];
    } else {
        throw new \ValueError("invalid SSH key: $key");
    }
}

/**
 * return values: [is_valid, invalid_explanation]
 * @return array{0: bool, 1: string}
 */
function testValidSSHKey(string $key): array
{
    if ($key != trim($key)) {
        return [false, "Key must not have leading or trailing whitespace"];
    }
    if (substr_count($key, "\n") != 0) {
        return [false, "Key must not span multiple lines"];
    }
    $exploded = explode(" ", $key, 2);
    if (count($exploded) == 1) {
        return [false, "Key must have at least 2 words"];
    }
    $key_type = $exploded[0];
    if (!in_array($key_type, CONFIG["ldap"]["allowed_ssh_key_types"])) {
        return [
            false,
            sprintf(
                "Key type '%s' is not allowed. Allowed key types are: %s",
                shortenString($key_type, 5, 5),
                _json_encode(CONFIG["ldap"]["allowed_ssh_key_types"]),
            ),
        ];
    }
    try {
        PublicKeyLoader::loadPublicKey($key);
        return [true, ""];
    } catch (NoKeyLoadedException $e) {
        // phpseclib internally catches any throwable to make NoKeyLoadedException,
        // so I am not comfortable sharing the exception message with the user
        return [false, "Invalid key"];
    }
}

/**
 * @param int<1,max> $depth
 * @throws \JsonException
 */
function _json_encode(mixed $value, int $flags = 0, int $depth = 512): string
{
    $flags |= JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES;
    $output = json_encode($value, $flags, $depth);
    if ($output === false) {
        throw new Exception("json_encode returned false!");
    }
    return $output;
}

/**
 * @param int<1,max> $depth
 */
function _json_decode(string $x, ?bool $associative = null, int $depth = 512, int $flags = 0): mixed
{
    $flags |= JSON_THROW_ON_ERROR;
    $output = json_decode($x, $associative, $depth, $flags);
    return $output;
}

/** @throws EncodingConversionException */
function _mb_convert_encoding(
    string $string,
    string $to_encoding,
    ?string $from_encoding = null,
): string {
    $output = mb_convert_encoding($string, $to_encoding, $from_encoding);
    if ($output === false) {
        throw new EncodingConversionException(
            _json_encode([
                "to" => $to_encoding,
                "from" => $from_encoding,
                "base64" => base64_encode($string),
            ]),
        );
    }
    return $output;
}

/**
 * @param null|string|string[] $encodings
 * @throws EncodingUnknownException
 */
function _mb_detect_encoding(string $string, mixed $encodings = null, mixed $_ = null): string
{
    $output = mb_detect_encoding($string, $encodings, strict: true);
    if ($output === false) {
        throw new EncodingUnknownException(base64_encode($string));
    }
    return $output;
}

/* https://stackoverflow.com/a/15575293/18696276 */
function pathNormalize(string $path): string
{
    return _preg_replace("#/+#", "/", $path);
}

function getURL(string ...$relative_url_components): string
{
    if (!preg_match("#^\w+://#", CONFIG["site"]["url"])) {
        throw new RuntimeException('CONFIG[site][url] does not have a scheme! (ex: "https://")');
    }
    $matches = [];
    preg_match("#(^\w+://)(.*)#", CONFIG["site"]["url"], $matches);
    [$_, $site_url_scheme, $site_url_noscheme] = $matches;
    $path = join("/", [$site_url_noscheme, CONFIG["site"]["prefix"], ...$relative_url_components]);
    $path_normalized = pathNormalize($path);
    return $site_url_scheme . $path_normalized;
}

function getHyperlink(string $text, string ...$url_components): string
{
    $text = htmlspecialchars($text);
    $url = getURL(...$url_components);
    return "<a href='$url'>$text</a>";
}

/**
 * negative numbers not allowed
 * @throws ValueError
 */
function digits2int(string $x, int $base = 10): int
{
    if (ctype_digit($x)) {
        return intval($x, $base);
    } else {
        throw new ValueError("not digits: $x");
    }
}

/* example with 3 leading chars and 3 trailing chars: "foobarbaz" -> "foo...baz" */
function shortenString(
    string $x,
    int $leading_chars,
    int $trailing_chars,
    string $ellipsis = "...",
): string {
    if ($leading_chars + strlen($ellipsis) + $trailing_chars > strlen($x)) {
        return $x;
    }
    return substr($x, 0, $leading_chars) .
        $ellipsis .
        substr($x, -1 * $trailing_chars, $trailing_chars);
}

function getTemplatePath(string $basename): string
{
    $template_path = __DIR__ . "/../templates/$basename";
    $override_path = __DIR__ . "/../../deployment/templates_overrides/$basename";
    if (file_exists($override_path)) {
        return $override_path;
    }
    return $template_path;
}

/**
 * @param-out int $count
 * @throws Exception
 */
function _preg_replace(
    string $pattern,
    string $replacement,
    string $subject,
    int $limit = -1,
    ?int &$count = null,
): string {
    $output = preg_replace($pattern, $replacement, $subject, $limit, $count);
    if ($output === null) {
        throw new Exception("preg_replace returned null!");
    }
    return $output;
}

/**
 * @param 0|256|512|768 $flags
 * @param null|array{} $matches
 * @param-out array<list<int|string|null>|string|null> $matches
 * @throws Exception
 */
function _preg_match(
    string $pattern,
    string $subject,
    ?array &$matches = null,
    int $flags = 0,
    int $offset = 0,
): int {
    $output = preg_match($pattern, $subject, $matches, $flags, $offset);
    if ($output === false) {
        throw new Exception("preg_match returned false!");
    }
    return $output;
}

/**
 * @return mixed[]
 * @throws Exception
 */
function _parse_ini_file(
    string $filename,
    bool $process_sections = false,
    int $scanner_mode = INI_SCANNER_NORMAL,
): array {
    $output = parse_ini_file($filename, $process_sections, $scanner_mode);
    if ($output === false) {
        throw new Exception("parse_ini_file returned false!");
    }
    return $output;
}

/**
 * @param resource $context
 * @return resource
 * @throws Exception
 */
function _fopen(
    string $filename,
    string $mode,
    bool $use_include_path = false,
    mixed $context = null,
): mixed {
    $output = fopen($filename, $mode, $use_include_path, $context);
    if ($output === false) {
        throw new Exception("fopen returned false!");
    }
    return $output;
}

/** @throws Exception */
function _ob_get_clean(): string
{
    $output = ob_get_clean();
    if ($output === false) {
        throw new Exception("ob_get_clean returned false!");
    }
    return $output;
}

/** @throws Exception */
function _curl_exec(CurlHandle $handle): string
{
    $output = curl_exec($handle);
    if (is_bool($output)) {
        throw new CurlException(curl_error($handle));
    }
    return $output;
}
