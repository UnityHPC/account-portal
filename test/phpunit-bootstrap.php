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

use PHPStan\DependencyInjection\ValidateExcludePathsExtension;
use UnityWebPortal\lib\CSRFToken;
use UnityWebPortal\lib\exceptions\ArrayKeyException;
use UnityWebPortal\lib\UnityGroup;
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
    mbConvertEncoding("Hello, World!", "UTF-16"),
];

function http_post(
    string $phpfile,
    array $post_data,
    bool $enforce_PRG = true,
    bool $do_generate_csrf_token = true,
): void {
    global $LDAP, $SQL, $MAILER, $WEBHOOK, $GITHUB, $SITE, $SSO, $USER, $LOC_HEADER, $LOC_FOOTER;
    $_PREVIOUS_SERVER = $_SERVER;
    $_SERVER["REQUEST_METHOD"] = "POST";
    $_SERVER["PHP_SELF"] = preg_replace("/.*webroot\//", "/", $phpfile);
    $_SERVER["REQUEST_URI"] = preg_replace("/.*webroot\//", "/", $phpfile); // Slightly imprecise because it doesn't include get parameters
    if (!array_key_exists("csrf_token", $post_data) && $do_generate_csrf_token) {
        $post_data["csrf_token"] = CSRFToken::generate();
    }
    $_POST = $post_data;
    ob_start();
    $post_did_redirect_or_die = false;
    try {
        include $phpfile;
    } catch (UnityWebPortal\lib\exceptions\NoDieException $e) {
        $post_did_redirect_or_die = true;
    } finally {
        ob_get_clean(); // discard output
        unset($_POST);
        $_SERVER = $_PREVIOUS_SERVER;
    }
    if ($enforce_PRG) {
        // https://en.wikipedia.org/wiki/Post/Redirect/Get
        ensure($post_did_redirect_or_die, "post did not redirect or die!");
    }
}

function http_get(string $phpfile, array $get_data = []): string
{
    global $LDAP, $SQL, $MAILER, $WEBHOOK, $GITHUB, $SITE, $SSO, $USER, $LOC_HEADER, $LOC_FOOTER;
    $_PREVIOUS_SERVER = $_SERVER;
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["PHP_SELF"] = preg_replace("/.*webroot\//", "/", $phpfile);
    $_SERVER["REQUEST_URI"] = preg_replace("/.*webroot\//", "/", $phpfile); // Slightly imprecise because it doesn't include get parameters
    $_GET = $get_data;
    ob_start();
    try {
        include $phpfile;
    } finally {
        unset($_GET);
        $_SERVER = $_PREVIOUS_SERVER;
        return ob_get_clean();
    }
}

// delete requests made by that user
// delete user entry
// delete user group entry
// remove user from org group
// remove user from "all users" group
// does not remove user from PI groups
function ensureUserDoesNotExist()
{
    global $USER, $SQL, $LDAP;
    $SQL->deleteRequestsByUser($USER->uid);
    if ($USER->exists()) {
        $org = $USER->getOrgGroup();
        if ($org->exists() and $org->memberUIDExists($USER->uid)) {
            $org->removeMemberUID($USER->uid);
            ensure(!$org->memberUIDExists($USER->uid));
        }
        $LDAP->getUserEntry($USER->uid)->delete();
        ensure(!$USER->exists());
    }
    if ($USER->getGroupEntry()->exists()) {
        $USER->getGroupEntry()->delete();
        ensure(!$USER->getGroupEntry()->exists());
    }
    $USER->setFlag(UserFlag::QUALIFIED, false);
    ensure(!$LDAP->userFlagGroups[UserFlag::QUALIFIED->value]->memberUIDExists($USER->uid));
}

function ensureOrgGroupDoesNotExist()
{
    global $USER, $SSO, $LDAP, $SQL, $MAILER, $WEBHOOK;
    $org_group = $LDAP->getOrgGroupEntry($SSO["org"]);
    if ($org_group->exists()) {
        $org_group->delete();
        ensure(!$org_group->exists());
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

function ensurePIGroupDoesNotExist()
{
    global $USER, $LDAP;
    $gid = $USER->getPIGroup()->gid;
    if ($USER->getPIGroup()->exists()) {
        $LDAP->getPIGroupEntry($gid)->delete();
        ensure(!$USER->getPIGroup()->exists());
    }
    if (count($USER->getPIGroupGIDs()) === 0) {
        $USER->setFlag(UserFlag::QUALIFIED, false);
    }
}

function callPrivateMethod($obj, $name, ...$args)
{
    $class = new \ReflectionClass($obj);
    $method = $class->getMethod($name);
    return $method->invokeArgs($obj, $args);
}

class UnityWebPortalTestCase extends TestCase
{
    private ?string $last_user_nickname = null;
    private ?string $current_user_nickname = null;
    private array $uid_to_latest_session_id = [];
    // FIXME these names are wrong
    private static array $UID2ATTRIBUTES = [
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
        "user2001_org998_test" => ["user2001@org998.test", "foo", "bar", "user2001@org998.test"],
        "user2002_org998_test" => ["user2002@org998.test", "foo", "bar", "user2002@org998.test"],
        "user2003_org998_test" => ["user2003@org1.test", "foo", "bar", "user2001@org1.test"],
        "user2004_org998_test" => ["user2004@org1.test", "foo", "bar", "user2001@org1.test"],
        "user2005_org1_test" => ["user2005@org1.test", "foo", "bar", "user2005@org1.test"],
    ];
    public static array $NICKNAME2UID = [
        "Admin" => "user1_org1_test",
        "Blank" => "user2_org1_test",
        "EmptyPIGroupOwner" => "user5_org2_test",
        "CustomMapped555" => "user2002_org998_test",
        "Ghost" => "user7_org1_test",
        "GhostNotPI" => "user7_org1_test",
        "GhostPI" => "user9_org3_test",
        "PreviousOwnerOfDefunctPIGroup" => "user10_org1_test",
        "HasNoSshKeys" => "user3_org1_test",
        "HasOneSshKey" => "user5_org2_test",
        "IdleLocked" => "user6_org1_test",
        "Locked" => "user8_org1_test",
        "NonExistent" => "user2001_org998_test",
        "Normal" => "user4_org1_test",
        "NormalPI" => "user1_org1_test",
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
                $this->assertFalse($USER->getFlag(UserFlag::GHOST));
                $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
                $this->assertFalse($USER->getFlag(UserFlag::LOCKED));
                // FIXME uncomment this after https://github.com/UnityHPC/account-portal/pull/473
                // $this->assertFalse($USER->getFlag(UserFlag::QUALIFIED));
                $this->assertTrue($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getGroupEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getOrgGroupEntry($USER->getOrg())->exists());
                break;
            case "CustomMapped555":
                $this->assertFalse($USER->exists());
                $this->assertFalse($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertFalse($LDAP->getGroupEntry($USER->uid)->exists());
                break;
            case "EmptyPIGroupOwner":
                $this->assertTrue($USER->isPI());
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $pi_group = $USER->getPIGroup();
                $this->assertEqualsCanonicalizing([$USER->uid], $pi_group->getMemberUIDs());
                $this->assertEqualsCanonicalizing([], $pi_group->getRequests());
                break;
            case "Ghost":
                $this->assertTrue($USER->getFlag(UserFlag::GHOST));
                break;
            case "GhostPI":
                $this->assertTrue($USER->getFlag(UserFlag::GHOST));
                $this->assertTrue($USER->getPIGroup()->exists());
                $this->assertTrue($USER->getPIGroup()->getIsDefunct());
                break;
            case "GhostNotPI":
                $this->assertTrue($USER->getFlag(UserFlag::GHOST));
                $this->assertFalse($USER->getPIGroup()->exists());
                break;
            case "PreviousOwnerOfDefunctPIGroup":
                $this->assertTrue($USER->exists());
                $this->assertFalse($USER->getFlag(UserFlag::GHOST));
                $this->assertFalse($USER->isPI());
                $this->assertTrue($USER->getPIGroup()->exists());
                $this->assertTrue($USER->getPIGroup()->getIsDefunct());
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
                $this->assertFalse($LDAP->getGroupEntry($USER->uid)->exists());
                break;
            case "Normal":
                $this->assertTrue($USER->exists());
                $this->assertFalse($USER->isPI());
                $this->assertGreaterThanOrEqual(1, count($USER->getPIGroupGIDs()));
                $this->assertFalse($USER->hasRequestedAccountDeletion());
                $this->assertEqualsCanonicalizing([], $SQL->getRequestsByUser($USER->uid));
                $this->assertFalse($USER->getFlag(UserFlag::GHOST));
                $this->assertFalse($USER->getFlag(UserFlag::IDLELOCKED));
                $this->assertFalse($USER->getFlag(UserFlag::LOCKED));
                $this->assertTrue($USER->getFlag(UserFlag::QUALIFIED));
                $this->assertTrue($LDAP->getUserEntry($USER->uid)->exists());
                $this->assertTrue($LDAP->getGroupEntry($USER->uid)->exists());
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
            jsonEncode($messages),
        );
        $messages_with_title = array_filter($messages, fn($x) => preg_match($title_regex, $x[0]));
        $messages_with_title_and_body = array_filter(
            $messages_with_title,
            fn($x) => preg_match($body_regex, $x[1]),
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
        if (!$reuse_last_session || !array_key_exists($nickname, $this->uid_to_latest_session_id)) {
            $session_id = str_replace(["_", "@", "."], "-", uniqid($eppn . "_"));
            $this->uid_to_latest_session_id[$uid] = $session_id;
            session_id($session_id);
        } else {
            session_id($this->uid_to_latest_session_id[$uid]);
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
        $this->switchUser($this->last_user_nickname, validate: $validate);
    }
}
