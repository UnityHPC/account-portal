<?php
// submodule
require_once __DIR__ . "/../resources/lib/phpopenldaper/src/PHPOpenLDAPer/LDAPEntry.php";
require_once __DIR__ . "/../resources/lib/phpopenldaper/src/PHPOpenLDAPer/LDAPConn.php";

require_once __DIR__ . "/../resources/lib/UnityLDAP.php";
require_once __DIR__ . "/../resources/lib/UnityUser.php";
require_once __DIR__ . "/../resources/lib/PosixGroup.php";
require_once __DIR__ . "/../resources/lib/UnityGroup.php";
require_once __DIR__ . "/../resources/lib/UnityOrg.php";
require_once __DIR__ . "/../resources/lib/UnitySQL.php";
require_once __DIR__ . "/../resources/lib/UnityMailer.php";
require_once __DIR__ . "/../resources/lib/UnitySSO.php";
require_once __DIR__ . "/../resources/lib/UnityHTTPD.php";
require_once __DIR__ . "/../resources/lib/UnityConfig.php";
require_once __DIR__ . "/../resources/lib/UnityWebhook.php";
require_once __DIR__ . "/../resources/lib/UnityGithub.php";
require_once __DIR__ . "/../resources/lib/utils.php";
require_once __DIR__ . "/../resources/lib/CSRFToken.php";
require_once __DIR__ . "/../resources/lib/exceptions/NoDieException.php";
require_once __DIR__ . "/../resources/lib/exceptions/SSOException.php";
require_once __DIR__ . "/../resources/lib/exceptions/ArrayKeyException.php";
require_once __DIR__ . "/../resources/lib/exceptions/CurlException.php";
require_once __DIR__ . "/../resources/lib/exceptions/EntryNotFoundException.php";
require_once __DIR__ . "/../resources/lib/exceptions/EnsureException.php";
require_once __DIR__ . "/../resources/lib/exceptions/EncodingUnknownException.php";
require_once __DIR__ . "/../resources/lib/exceptions/EncodingConversionException.php";
require_once __DIR__ . "/../resources/lib/exceptions/UnityHTTPDMessageNotFoundException.php";

use UnityWebPortal\lib\CSRFToken;
use UnityWebPortal\lib\exceptions\ArrayKeyException;
use UnityWebPortal\lib\UnityGroup;
use UnityWebPortal\lib\UnityUser;
use UnityWebPortal\lib\UnityHTTPD;
use UnityWebPortal\lib\UserFlag;
use UnityWebPortal\lib\UnitySQL;
use UnityWebPortal\lib\UnityHTTPDMessageLevel;
use PHPUnit\Framework\TestCase;

$_SERVER["HTTP_HOST"] = "phpunit"; // used for config override
require_once __DIR__ . "/../resources/config.php";

global $HTTP_HEADER_TEST_INPUTS;
$HTTP_HEADER_TEST_INPUTS = [
    "",
    "a",
    "Hello, World!",
    "  Some text  ",
    "   ",
    "12345",
    "abc123",
    "Hello@World!",
    str_repeat("a", 8190), // https://httpd.apache.org/docs/2.2/mod/core.html#limitrequestfieldsize
    "<p>This is a paragraph</p>",
    "'; DROP TABLE users; --",
    "<script>alert('XSS');</script>",
    "こんにちは世界",
    "Hello 👋 World 🌍",
    "Line 1\nLine 2",
    "Column1\tColumn2",
    "MiXeD cAsE",
    "https://www.example.com",
    "user@example.com",
    '{"key": "value"}',
    "SGVsbG8sIFdvcmxkIQ==",
    "Hello\x00World",
    _mb_convert_encoding("Hello, World!", "UTF-16"),
];

function http_post(
    string $phpfile,
    array $post_data,
    array $query_parameters = [],
    bool $do_generate_csrf_token = true,
): string {
    global $LDAP, $SQL, $MAILER, $WEBHOOK, $GITHUB, $SITE, $SSO, $USER, $LOC_HEADER, $LOC_FOOTER;
    $_PREVIOUS_SERVER = $_SERVER;
    $_SERVER["REQUEST_METHOD"] = "POST";
    $_SERVER["PHP_SELF"] = _preg_replace("/.*webroot\//", "/", $phpfile);
    $_SERVER["REQUEST_URI"] = _preg_replace("/.*webroot\//", "/", $phpfile); // Slightly imprecise because it doesn't include get parameters
    if (!array_key_exists("csrf_token", $post_data) && $do_generate_csrf_token) {
        $post_data["csrf_token"] = CSRFToken::generate();
    }
    $_POST = $post_data;
    $_GET = $query_parameters;
    ob_start();
    try {
        $post_did_redirect_or_die = false;
        try {
            include $phpfile;
        } catch (UnityWebPortal\lib\exceptions\NoDieException $e) {
            $post_did_redirect_or_die = true;
        }
        // https://en.wikipedia.org/wiki/Post/Redirect/Get
        ensure($post_did_redirect_or_die, "post did not redirect or die!");
        return _ob_get_clean();
    } catch (Exception $e) {
        _ob_get_clean(); //discard output
        throw $e;
    } finally {
        unset($_POST);
        unset($_GET);
        $_SERVER = $_PREVIOUS_SERVER;
    }
}

function http_get(string $phpfile, array $get_data = [], bool $ignore_die = false): string
{
    global $LDAP, $SQL, $MAILER, $WEBHOOK, $GITHUB, $SITE, $SSO, $USER, $LOC_HEADER, $LOC_FOOTER;
    $_PREVIOUS_SERVER = $_SERVER;
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["PHP_SELF"] = _preg_replace("/.*webroot\//", "/", $phpfile);
    $_SERVER["REQUEST_URI"] = _preg_replace("/.*webroot\//", "/", $phpfile); // Slightly imprecise because it doesn't include get parameters
    $_GET = $get_data;
    ob_start();
    try {
        include $phpfile;
        return _ob_get_clean();
    } catch (UnityWebPortal\lib\exceptions\NoDieException $e) {
        if ($ignore_die) {
            return _ob_get_clean();
        } else {
            _ob_get_clean(); // discard output
            throw $e;
        }
    } catch (Exception $e) {
        _ob_get_clean(); //discard output
        throw $e;
    } finally {
        unset($_GET);
        $_SERVER = $_PREVIOUS_SERVER;
    }
}

/**
 * runs a worker script, then refreshes all LDAP entries to pick up changes
 * @throws RuntimeException
 * @return array [return code, output lines]
 */
function executeWorker(
    string $basename,
    string $args = "",
    bool $doThrowIfNonzero = true,
    ?string $stdinFilePath = null,
): array {
    global $LDAP;
    $command = sprintf(
        "HTTP_HOST=phpunit %s %s %s 2>&1",
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . "/../workers/" . $basename),
        $args,
    );
    if ($stdinFilePath !== null) {
        $command .= " <" . escapeshellarg($stdinFilePath);
    }
    $output = [];
    $rc = null;
    exec($command, $output, $rc);
    if ($doThrowIfNonzero && $rc !== 0) {
        throw new RuntimeException(
            sprintf(
                "command failed! command='%s' rc=%d output=%s",
                $command,
                $rc,
                _json_encode($output),
            ),
        );
    }
    $entries = accessPrivateVariable($LDAP, "entries");
    foreach ($entries as $entry) {
        callPrivateMethod($entry, "pullObject");
    }
    return [$rc, $output];
}

// delete requests made by that user
// remove account deletion request made by that user
// delete user entry
// delete user group entry
// remove user from org group
// remove user from all UserFlag groups
// remove user from all PI groups
function ensureUserDoesNotExist(string $uid)
{
    global $SQL, $LDAP;
    $SQL->deleteRequestsByUser($uid);
    $SQL->deleteAccountDeletionRequest($uid);
    $user_entry = $LDAP->getUserEntry($uid);
    if ($user_entry->exists()) {
        $org_gid = $user_entry->getAttribute("o")[0];
        $org_entry = $LDAP->getOrgGroupEntry($org_gid);
        if ($org_entry->exists()) {
            $org_members = $org_entry->getAttribute("memberuid");
            if (in_array($uid, $org_members)) {
                $org_entry->removeAttributeEntryByValue("memberuid", $uid);
            }
        }
        $user_entry->delete();
    }
    $user_group_entry = $LDAP->getUserGroupEntry($uid);
    if ($user_group_entry->exists()) {
        $user_group_entry->delete();
    }
    foreach (UserFlag::cases() as $flag) {
        $flag_group = $LDAP->userFlagGroups[$flag->value];
        if ($flag_group->memberUIDExists($uid)) {
            $flag_group->removeMemberUID($uid);
        }
    }
    foreach ($LDAP->getNonDisabledPIGroupGIDsWithMemberUID($uid) as $gid) {
        $pi_group_entry = $LDAP->getPIGroupEntry($gid);
        $pi_group_members = $pi_group_entry->getAttribute("memberuid");
        if (in_array($uid, $pi_group_members)) {
            $pi_group_entry->removeAttributeEntryByValue("memberuid", $uid);
        }
        $managers = $pi_group_entry->getAttribute("manageruid");
        if (in_array($uid, $managers)) {
            $pi_group_entry->removeAttributeEntryByValue("manageruid", $uid);
        }
    }
}

function ensureOrgGroupDoesNotExist(string $gid)
{
    global $LDAP;
    $org_group_entry = $LDAP->getOrgGroupEntry($gid);
    if ($org_group_entry->exists()) {
        $org_group_entry->delete();
    }
}

function ensureUserNotRequestedAccountDeletion()
{
    global $USER, $SQL;
    if ($SQL->accDeletionRequestExists($USER->uid)) {
        $SQL->deleteAccountDeletionRequest($USER->uid);
    }
}

function ensureUserNotInPIGroup(UnityGroup $pi_group)
{
    global $USER;
    if ($pi_group->memberUIDExists($USER->uid)) {
        $pi_group->removeUser($USER);
        ensure(!$pi_group->memberUIDExists($USER->uid));
    }
}

function ensurePIGroupDoesNotExist(string $gid)
{
    global $LDAP, $SQL, $MAILER, $WEBHOOK;
    $pi_group_entry = $LDAP->getPIGroupEntry($gid);
    if ($pi_group_entry->exists()) {
        $member_uids_before = $pi_group_entry->getAttribute("memberuid");
        $pi_group_entry->removeAttribute("memberuid");
        foreach ($member_uids_before as $uid) {
            $user = new UnityUser($uid, $LDAP, $SQL, $MAILER, $WEBHOOK);
            $user->updateIsQualified();
        }
        $pi_group_entry->delete();
    }
}

function callPrivateMethod($obj, $name, ...$args)
{
    $class = new \ReflectionClass($obj);
    $method = $class->getMethod($name);
    return $method->invokeArgs($obj, $args);
}

function accessPrivateVariable($obj, string $name)
{
    $class = new \ReflectionClass($obj);
    $property = $class->getProperty($name);
    $property->setAccessible(true);
    return $property->getValue($obj);
}

class UnityWebPortalTestCase extends TestCase
{
    private ?string $last_user_nickname = null;
    private ?string $current_user_nickname = null;
    private array $nickname_to_latest_session_id = [];
    // FIXME these names are wrong
    protected static array $UID2ATTRIBUTES = [
        "user1_org1_test" => ["user1@org1.test", "foo", "bar", "user1@org1.test"],
        "user2_org1_test" => ["user2@org1.test", "foo", "bar", "user2@org1.test"],
        "user3_org1_test" => ["user3@org1.test", "foo", "bar", "user3@org1.test"],
        "user4_org1_test" => ["user4@org1.test", "foo", "bar", "user4@org1.test"],
        "user5_org2_test" => ["user5@org2.test", "foo", "bar", "user5@org2.test"],
        "user6_org1_test" => ["user6@org1.test", "foo", "bar", "user6@org1.test"],
        "user7_org1_test" => ["user7@org1.test", "foo", "bar", "user7@org1.test"],
        "user8_org1_test" => ["user8@org1.test", "foo", "bar", "user8@org1.test"],
        "user9_org3_test" => ["user9@org3.test", "foo", "bar", "user9@org3.test"],
        "user10_org1_test" => ["user10@org1.test", "foo", "bar", "user10@org1.test"],
        "user11_org1_test" => ["user11@org1.test", "foo", "bar", "user11@org1.test"],
        "user12_org1_test" => ["user12@org1.test", "foo", "bar", "user12@org1.test"],
        "user2001_org998_test" => ["user2001@org998.test", "foo", "bar", "user2001@org998.test"],
        "user2002_org998_test" => ["user2002@org998.test", "foo", "bar", "user2002@org998.test"],
        "user2003_org998_test" => ["user2003@org1.test", "foo", "bar", "user2001@org1.test"],
        "user2004_org998_test" => ["user2004@org1.test", "foo", "bar", "user2001@org1.test"],
        "user2005_org1_test" => ["user2005@org1.test", "foo", "bar", "user2005@org1.test"],
        "cs123_org1_test" => [
            "cs123@org1.test",
            "cs123",
            "Fall 2025",
            "user1+cs123_org1_test@org1.test",
        ],
    ];
    protected static array $NICKNAME2UID = [
        "Admin" => "user1_org1_test",
        "Blank" => "user2_org1_test",
        "EmptyPIGroupOwner" => "user5_org2_test",
        "CourseGroupOwner" => "cs123_org1_test",
        "CourseGroupManager" => "user1_org1_test",
        "CustomMapped555" => "user2002_org998_test",
        "Disabled" => "user7_org1_test",
        "DisabledNotPI" => "user7_org1_test",
        "DisabledOwnerOfDisabledPIGroup" => "user9_org3_test",
        "DisabledPIGroup_user9_org3_test_Manager" => "user12_org1_test",
        "ReenabledOwnerOfDisabledPIGroup" => "user10_org1_test",
        "HasNoSshKeys" => "user3_org1_test",
        "HasOneSshKey" => "user5_org2_test",
        "IdleLocked" => "user6_org1_test",
        "Locked" => "user8_org1_test",
        "NonExistent" => "user2001_org998_test",
        "Normal" => "user4_org1_test",
        "NormalPI" => "user11_org1_test",
    ];

    private function validateUser(string $nickname)
    {
        global $USER, $SQL, $LDAP;
        if (!array_key_exists($nickname, self::$NICKNAME2UID)) {
            throw new ArrayKeyException($nickname);
        }
        $this->assertEquals(self::$NICKNAME2UID[$nickname], $USER->uid);
        switch ($nickname) {
            case "Admin":
                $this->assertTrue($USER->getFlag(UserFlag::ADMIN));
                break;
            case "Blank":
                $this->assertTrue($USER->exists());
                $this->assertFalse($USER->isPI());
                $this->assertEqualsCanonicalizing([], $USER->getPIGroupGIDs());
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $this->assertEqualsCanonicalizing([], $SQL->getRequestsByUser($USER->uid));
                $this->assertFalse($USER->getFlag(UserFlag::ADMIN));
                $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
                $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
                $this->assertFalse($USER->getFlag(UserFlag::LOCKED));
                $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
                $this->assertTrue($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getUserGroupEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getOrgGroupEntry($USER->getOrg())->exists());
                break;
            case "CourseGroupOwner":
                $this->assertTrue($USER->getPIGroup()->exists());
                $this->assertNotEmpty($USER->getPIGroup()->getManagerUIDs());
                break;
            case "CourseGroupManager":
                $this->assertNotEmpty($LDAP->getNonDisabledPIGroupGIDsWithManagerUID($USER->uid));
                break;
            case "CustomMapped555":
                $this->assertFalse($USER->exists());
                $this->assertFalse($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertFalse($LDAP->getUserGroupEntry($USER->uid)->exists());
                break;
            case "EmptyPIGroupOwner":
                $this->assertTrue($USER->isPI());
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $pi_group = $USER->getPIGroup();
                $this->assertEqualsCanonicalizing([$USER->uid], $pi_group->getMemberUIDs());
                $this->assertEqualsCanonicalizing([], $pi_group->getRequests());
                break;
            case "Disabled":
                $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
                break;
            case "DisabledOwnerOfDisabledPIGroup":
                $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
                $this->assertTrue($USER->getPIGroup()->exists());
                $this->assertTrue($USER->getPIGroup()->getIsDisabled());
                break;
            case "DisabledNotPI":
                $this->assertTrue($USER->getFlag(UserFlag::DISABLED));
                $this->assertFalse($USER->getPIGroup()->exists());
                break;
            case "DisabledPIGroup_user9_org3_test_Manager":
                $pi_group_entry = $LDAP->getPIGroupEntry("pi_user9_org3_test");
                $this->assertContains($USER->uid, $pi_group_entry->getAttribute("manageruid"));
                $this->assertEquals("TRUE", $pi_group_entry->getAttribute("isDisabled")[0]);
                break;
            case "ReenabledOwnerOfDisabledPIGroup":
                $this->assertTrue($USER->exists());
                $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
                $this->assertFalse($USER->isPI());
                $this->assertTrue($USER->getPIGroup()->exists());
                $this->assertTrue($USER->getPIGroup()->getIsDisabled());
                break;
            case "HasNoSshKeys":
                $this->assertEqualsCanonicalizing([], $USER->getSSHKeys());
                break;
            case "IdleLocked":
                // this cannot be validated automatically because the user is already idle
                // unlocked before this code runs
                // $this->assertTrue($USER->getFlag(UserFlag::IDLELOCKED));
                break;
            case "Locked":
                $this->assertTrue($USER->getFlag(UserFlag::LOCKED));
                break;
            case "NonExistent":
                $this->assertFalse($USER->exists());
                $this->assertFalse($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertFalse($LDAP->getUserGroupEntry($USER->uid)->exists());
                break;
            case "Normal":
                $this->assertTrue($USER->exists());
                $this->assertFalse($USER->isPI());
                $this->assertGreaterThanOrEqual(1, count($USER->getPIGroupGIDs()));
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $this->assertEqualsCanonicalizing([], $SQL->getRequestsByUser($USER->uid));
                $this->assertFalse($USER->getFlag(UserFlag::DISABLED));
                $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
                $this->assertFalse($USER->getFlag(UserFlag::LOCKED));
                $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
                $this->assertTrue($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getUserGroupEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getOrgGroupEntry($USER->getOrg())->exists());
                break;
            case "NormalPI":
                $this->assertTrue($USER->isPI());
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $this->assertGreaterThanOrEqual(2, count($USER->getPIGroup()->getMemberUIDs()));
                break;
            case "HasOneSshKey":
                $this->assertEquals(1, count($USER->getSSHKeys()));
                break;
            default:
                throw new ArrayKeyException($nickname);
        }
    }

    public function assertMessageExists(
        UnityHTTPDMessageLevel $level,
        string $title_regex,
        string $body_regex,
    ) {
        $messages = UnityHTTPD::getMessages();
        $error_msg = sprintf(
            "message(level='%s' title_regex='%s' body_regex='%s'), not found. found messages: %s",
            $level->value,
            $title_regex,
            $body_regex,
            _json_encode($messages),
        );
        $messages_with_title = array_filter(
            $messages,
            fn($x) => (bool) _preg_match($title_regex, $x[0]),
        );
        $messages_with_title_and_body = array_filter(
            $messages_with_title,
            fn($x) => (bool) _preg_match($body_regex, $x[1]),
        );
        $messages_with_title_and_body_and_level = array_filter(
            $messages_with_title_and_body,
            fn($x) => $x[2] == $level,
        );
        $this->assertNotEmpty($messages_with_title_and_body_and_level, $error_msg);
    }

    public function assertGroupMembers(UnityGroup $group, array $expected_members)
    {
        sort($expected_members);
        $found_members = $group->getMemberUIDs();
        sort($found_members);
        $this->assertEqualsCanonicalizing($expected_members, $found_members);
    }

    public function assertRequestedMembership(bool $expected, string $gid)
    {
        global $USER, $SQL;
        $this->assertEquals($expected, $SQL->requestExists($USER->uid, $gid));
    }

    public function getNumberAccountDeletionRequests()
    {
        global $USER, $SQL;
        $stmt = $SQL->getConn()->prepare("SELECT * FROM account_deletion_requests WHERE uid=:uid");
        $uid = $USER->uid;
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
        return count($stmt->fetchAll());
    }

    public function assertNumberAccountDeletionRequests(int $x)
    {
        global $USER, $SQL;
        if ($x == 0) {
            $this->assertFalse($USER->hasRequestedAccountDeletion());
            $this->assertFalse($SQL->accDeletionRequestExists($USER->uid));
        } elseif ($x > 0) {
            $this->assertTrue($USER->hasRequestedAccountDeletion());
            $this->assertTrue($SQL->accDeletionRequestExists($USER->uid));
        } else {
            throw new RuntimeException("x must not be negative");
        }
        $this->assertEquals($x, $this->getNumberAccountDeletionRequests());
    }

    public function assertRequestedPIGroup(bool $expected)
    {
        global $USER, $SQL;
        $this->assertEquals(
            $expected,
            $SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI),
        );
    }

    public function getNumberPiBecomeRequests()
    {
        global $USER, $SQL;
        // FIXME table name, "admin" are public constants in UnitySQL
        // FIXME "admin" should be something else
        $stmt = $SQL
            ->getConn()
            ->prepare("SELECT * FROM requests WHERE uid=:uid and request_for='admin'");
        $uid = $USER->uid;
        $stmt->bindParam(":uid", $uid);
        $stmt->execute();
        return count($stmt->fetchAll());
    }

    public function assertNumberPiBecomeRequests(int $x)
    {
        global $USER, $SQL;
        if ($x == 0) {
            $this->assertFalse($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
        } elseif ($x > 0) {
            $this->assertTrue($SQL->requestExists($USER->uid, UnitySQL::REQUEST_BECOME_PI));
        } else {
            throw new RuntimeException("x must not be negative");
        }
        $this->assertEquals($x, $this->getNumberPiBecomeRequests());
    }

    public function getNumberRequests()
    {
        global $USER, $SQL;
        return count($SQL->getRequestsByUser($USER->uid));
    }

    public function assertNumberRequests(int $x)
    {
        $this->assertEquals($x, $this->getNumberRequests());
    }

    function switchUser(
        string $nickname,
        bool $reuse_last_session = true,
        bool $validate = true,
    ): void {
        global $LDAP,
            $SQL,
            $MAILER,
            $WEBHOOK,
            $GITHUB,
            $SITE,
            $SSO,
            $USER,
            $LOC_HEADER,
            $LOC_FOOTER;
        if (!array_key_exists($nickname, self::$NICKNAME2UID)) {
            throw new ArrayKeyException($nickname);
        }
        $uid = self::$NICKNAME2UID[$nickname];
        if (!array_key_exists($uid, self::$UID2ATTRIBUTES)) {
            throw new ArrayKeyException($uid);
        }
        [$eppn, $given_name, $sn, $mail] = self::$UID2ATTRIBUTES[$uid];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $previous_session_id = $this->nickname_to_latest_session_id[$nickname] ?? null;
        if (!$reuse_last_session || !$previous_session_id) {
            $session_id = str_replace(["_", "@", "."], "-", uniqid($eppn . "_"));
            $this->nickname_to_latest_session_id[$nickname] = $session_id;
            session_id($session_id);
        } else {
            session_id($previous_session_id);
        }
        $this->last_user_nickname = $this->current_user_nickname;
        $this->current_user_nickname = $nickname;
        // session_start will be called on the first post()
        $_SERVER["REMOTE_USER"] = $eppn;
        $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
        $_SERVER["HTTP_HOST"] = "phpunit"; // used for config override
        $_SERVER["eppn"] = $eppn;
        $_SERVER["givenName"] = $given_name;
        $_SERVER["sn"] = $sn;
        include __DIR__ . "/../resources/autoload.php";
        if ($validate) {
            $this->validateUser($nickname);
        }
        ensure(!is_null($USER));
    }

    function switchBackUser(bool $validate = false)
    {
        assert($this->last_user_nickname !== null);
        $this->switchUser($this->last_user_nickname, validate: $validate);
    }

    function setUp(): void
    {
        $this->assertArrayNotHasKey("REQUEST_METHOD", $_SERVER);
    }
}

function getSomeUIDsOfQualifiedUsersNotRequestedAccountDeletion()
{
    return [
        "user1_org1_test",
        "user3_org1_test",
        "user6_org1_test",
        "user7_org1_test",
        "user8_org1_test",
        "user9_org3_test",
        "user10_org1_test",
        "user11_org1_test",
    ];
}

/**
 * @param resource $x
 * @throws ArrayKeyException
 */
function getPathFromFileHandle(mixed $x): string
{
    $metadata = stream_get_meta_data($x);
    if (!array_key_exists("uri", $metadata)) {
        throw new ArrayKeyException("stream_get_meta_data return value has no key 'uri'!");
    }
    return $metadata["uri"];
}

function writeLinesToTmpFile(array $lines)
{
    $file = tmpfile();
    if (!$file) {
        throw new RuntimeException("failed to make tmpfile");
    }
    $path = getPathFromFileHandle($file);
    $contents = implode("\n", $lines);
    $fwrite = fwrite($file, $contents);
    if ($fwrite === false) {
        throw new RuntimeException("failed to write to tmpfile '$path'");
    }
    return $file;
}
