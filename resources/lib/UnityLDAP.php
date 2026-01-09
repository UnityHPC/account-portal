<?php

namespace UnityWebPortal\lib;

use UnityWebPortal\lib\exceptions\EntryNotFoundException;
use PHPOpenLDAPer\LDAPConn;
use PHPOpenLDAPer\LDAPEntry;
use UnityWebPortal\lib\PosixGroup;

enum UserFlag: string
{
    case ADMIN = "admin";
    case GHOST = "ghost";
    case IDLELOCKED = "idlelocked";
    case LOCKED = "locked";
    case QUALIFIED = "qualified";
}

/**
 * An LDAP connection class which extends LDAPConn tailored for the UnityHPC Platform
 */
class UnityLDAP extends LDAPConn
{
    private const string RDN = "cn"; // The defauls RDN for LDAP entries is set to "common name"

    // isDefunct unset or set to "FALSE"
    private static string $NON_DEFUNCT_FILTER = "(|(!(isDefunct=*))(isDefunct=FALSE))";

    public const array POSIX_ACCOUNT_CLASS = [
        "inetorgperson",
        "posixAccount",
        "top",
        "ldapPublicKey",
    ];

    private string $custom_mappings_path =
        __DIR__ . "/../../" . CONFIG["ldap"]["custom_user_mappings_dir"];
    private string $def_user_shell = CONFIG["ldap"]["def_user_shell"];
    private int $offset_UIDGID = CONFIG["ldap"]["offset_UIDGID"];
    private int $offset_PIGID = CONFIG["ldap"]["offset_PIGID"];
    private int $offset_ORGGID = CONFIG["ldap"]["offset_ORGGID"];

    // Instance vars for various ldapEntry objects
    private LDAPEntry $baseOU;
    private LDAPEntry $userOU;
    private LDAPEntry $groupOU; /** @phpstan-ignore property.onlyWritten */
    private LDAPEntry $pi_groupOU;
    private LDAPEntry $org_groupOU;

    public array $userFlagGroups;

    public function __construct()
    {
        parent::__construct(CONFIG["ldap"]["uri"], CONFIG["ldap"]["user"], CONFIG["ldap"]["pass"]);
        $this->baseOU = $this->getEntry(CONFIG["ldap"]["basedn"]);
        $this->userOU = $this->getEntry(CONFIG["ldap"]["user_ou"]);
        $this->groupOU = $this->getEntry(CONFIG["ldap"]["group_ou"]);
        $this->pi_groupOU = $this->getEntry(CONFIG["ldap"]["pigroup_ou"]);
        $this->org_groupOU = $this->getEntry(CONFIG["ldap"]["orggroup_ou"]);
        $this->userFlagGroups = [];
        foreach (UserFlag::cases() as $flag) {
            $dn = CONFIG["ldap"]["user_flag_groups"][$flag->value];
            $this->userFlagGroups[$flag->value] = new PosixGroup(new LDAPEntry($this->conn, $dn));
        }
    }

    public function getDefUserShell(): string
    {
        return $this->def_user_shell;
    }

    public function getNextUIDGIDNumber(string $uid): int
    {
        $IDNumsInUse = array_merge($this->getAllUIDNumbersInUse(), $this->getAllGIDNumbersInUse());
        $customIDMappings = $this->getCustomIDMappings();
        $customMappedID = $customIDMappings[$uid] ?? null;
        if (!is_null($customMappedID) && !in_array($customMappedID, $IDNumsInUse)) {
            return $customMappedID;
        }
        if (!is_null($customMappedID) && in_array($customMappedID, $IDNumsInUse)) {
            UnityHTTPD::errorLog(
                "warning",
                "user '$uid' has a custom mapped IDNumber $customMappedID but it's already in use!",
            );
        }
        return $this->getNextIDNumber(
            $this->offset_UIDGID,
            array_merge($IDNumsInUse, array_values($this->getCustomIDMappings())),
        );
    }

    public function getNextPIGIDNumber(): int
    {
        return $this->getNextIDNumber(
            $this->offset_PIGID,
            array_merge($this->getAllGIDNumbersInUse(), array_values($this->getCustomIDMappings())),
        );
    }

    public function getNextOrgGIDNumber(): int
    {
        return $this->getNextIDNumber(
            $this->offset_ORGGID,
            array_merge($this->getAllGIDNumbersInUse(), array_values($this->getCustomIDMappings())),
        );
    }

    private function isIDNumberForbidden($id): bool
    {
        // 0-99 are probably going to be used for local system accounts instead of LDAP accounts
        // 100-999, 60000-64999 are reserved for debian packages
        return $id <= 999 || ($id >= 60000 && $id <= 64999);
    }

    private function getNextIDNumber($start, $IDsToSkip): int
    {
        $new_id = $start;
        while ($this->isIDNumberForbidden($new_id) || in_array($new_id, $IDsToSkip)) {
            $new_id++;
        }
        return $new_id;
    }

    private function getCustomIDMappings(): array
    {
        $output = [];
        $dir = new \DirectoryIterator($this->custom_mappings_path);
        foreach ($dir as $fileinfo) {
            $filename = $fileinfo->getFilename();
            if ($fileinfo->isDot() || $filename == "README.md") {
                continue;
            }
            if ($fileinfo->getExtension() == "csv") {
                $handle = fopen($fileinfo->getPathname(), "r");
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
        foreach ($output as [$uid, $uidNumber_str]) {
            $output_map[$uid] = digits2int($uidNumber_str);
        }
        return $output_map;
    }

    private function getAllUIDNumbersInUse(): array
    {
        // use baseOU for awareness of externally managed entries
        return array_map(
            fn($x) => $x["uidnumber"][0],
            $this->baseOU->getChildrenArrayStrict(
                ["uidNumber"],
                true,
                "(objectClass=posixAccount)",
            ),
        );
    }

    private function getAllGIDNumbersInUse(): array
    {
        // use baseOU for awareness of externally managed entries
        return array_map(
            fn($x) => $x["gidnumber"][0],
            $this->baseOU->getChildrenArrayStrict(["gidNumber"], true, "(objectClass=posixGroup)"),
        );
    }

    public function getAllNativeUsersAttributes(
        array $attributes,
        array $default_values = [],
    ): array {
        return $this->userOU->getChildrenArrayStrict(
            $attributes,
            true, // recursive
            "(objectClass=posixAccount)",
            $default_values,
        );
    }

    public function getAllNonDefunctPIGroups(
        UnitySQL $UnitySQL,
        UnityMailer $UnityMailer,
        UnityWebhook $UnityWebhook,
    ) {
        $out = [];
        $pi_groups_attributes = $this->pi_groupOU->getChildrenArrayStrict(
            attributes: ["cn"],
            recursive: false,
            filter: self::$NON_DEFUNCT_FILTER,
        );
        foreach ($pi_groups_attributes as $attributes) {
            array_push(
                $out,
                new UnityGroup($attributes["cn"][0], $this, $UnitySQL, $UnityMailer, $UnityWebhook),
            );
        }

        return $out;
    }

    public function getAllNonDefunctPIGroupsAttributes(
        array $attributes,
        array $default_values = [],
    ): array {
        return $this->pi_groupOU->getChildrenArrayStrict(
            $attributes,
            false, // non-recursive
            self::$NON_DEFUNCT_FILTER,
            $default_values,
        );
    }

    public function getNonDefunctPIGroupGIDsWithMemberUID(string $uid): array
    {
        return array_map(
            fn($x) => $x["cn"][0],
            $this->pi_groupOU->getChildrenArrayStrict(
                ["cn"],
                false,
                sprintf(
                    "(&(memberuid=%s)%s)",
                    ldap_escape($uid, "", LDAP_ESCAPE_FILTER),
                    self::$NON_DEFUNCT_FILTER,
                ),
            ),
        );
    }

    public function getAllNonDefunctPIGroupOwnerUIDs(): array
    {
        return array_map(
            fn($x) => UnityGroup::GID2OwnerUID($x["cn"][0]),
            $this->getAllNonDefunctPIGroupsAttributes(["cn"]),
        );
    }

    /**
     * Returns an associative array where keys are UIDs and values are arrays of PI GIDs
     */
    public function getUID2PIGIDs(): array
    {
        $uid2pigids = [];
        // for each PI group, append that GID to the member list for each of its member UIDs
        $pi_groups_attributes = $this->getAllNonDefunctPIGroupsAttributes(
            ["cn", "memberuid"],
            default_values: ["memberuid" => []],
        );
        foreach ($pi_groups_attributes as $array) {
            $gid = $array["cn"][0];
            foreach ($array["memberuid"] as $uid) {
                if (!array_key_exists($uid, $uid2pigids)) {
                    $uid2pigids[$uid] = [];
                }
                array_push($uid2pigids[$uid], $gid);
            }
        }
        return $uid2pigids;
    }

    public function getAllOrgGroups($UnitySQL, $UnityMailer, $UnityWebhook): array
    {
        $out = [];

        $org_groups = $this->org_groupOU->getChildren(true);

        foreach ($org_groups as $org_group) {
            array_push(
                $out,
                new UnityOrg(
                    $org_group->getAttribute("cn")[0],
                    $this,
                    $UnitySQL,
                    $UnityMailer,
                    $UnityWebhook,
                ),
            );
        }

        return $out;
    }

    public function getAllOrgGroupsAttributes(array $attributes, array $default_values = []): array
    {
        return $this->org_groupOU->getChildrenArrayStrict(
            $attributes,
            default_values: $default_values,
        );
    }

    public function getUserEntry(string $uid): LDAPEntry
    {
        $uid = ldap_escape($uid, "", LDAP_ESCAPE_DN);
        return $this->getEntry(UnityLDAP::RDN . "=$uid," . CONFIG["ldap"]["user_ou"]);
    }

    public function getGroupEntry(string $gid): LDAPEntry
    {
        $gid = ldap_escape($gid, "", LDAP_ESCAPE_DN);
        return $this->getEntry(UnityLDAP::RDN . "=$gid," . CONFIG["ldap"]["group_ou"]);
    }

    public function getPIGroupEntry(string $gid): LDAPEntry
    {
        $gid = ldap_escape($gid, "", LDAP_ESCAPE_DN);
        return $this->getEntry(UnityLDAP::RDN . "=$gid," . CONFIG["ldap"]["pigroup_ou"]);
    }

    public function getOrgGroupEntry(string $gid): LDAPEntry
    {
        $gid = ldap_escape($gid, "", LDAP_ESCAPE_DN);
        return $this->getEntry(UnityLDAP::RDN . "=$gid," . CONFIG["ldap"]["orggroup_ou"]);
    }

    /**
     * @throws \UnityWebPortal\lib\exceptions\EntryNotFoundException
     */
    public function getUidFromEmail(string $email): LDAPEntry
    {
        $email = ldap_escape($email, "", LDAP_ESCAPE_FILTER);
        $cn = $this->search("mail=$email", CONFIG["ldap"]["user_ou"], ["cn"]);
        if ($cn && count($cn) == 1) {
            return $cn[0];
        }
        throw new exceptions\EntryNotFoundException($email);
    }

    /**
     * returns an array with each UID as an array key
     * @throws \UnityWebPortal\lib\exceptions\EntryNotFoundException
     */
    public function getUsersAttributes(
        array $uids,
        array $attributes,
        array $default_values = [],
    ): array {
        if (count($uids) === 0) {
            return [];
        }
        $attributes = array_map("strtolower", $attributes);
        if (in_array("uid", $attributes)) {
            $asked_for_uid_attribute = true;
        } else {
            $asked_for_uid_attribute = false;
            array_push($attributes, "uid");
        }
        $uids_escaped = array_map(fn($x) => ldap_escape($x, "", LDAP_ESCAPE_FILTER), $uids);
        $filter =
            "(&(objectClass=posixAccount)(|" .
            implode("", array_map(fn($x) => "(uid=$x)", $uids_escaped)) .
            "))";
        $entries = $this->baseOU->getChildrenArrayStrict(
            $attributes,
            true,
            $filter,
            $default_values,
        );
        $output = [];
        foreach ($entries as $entry) {
            $uid = $entry["uid"][0];
            if (!$asked_for_uid_attribute) {
                unset($entry["uid"]);
            }
            $output[$uid] = $entry;
        }
        $uids_not_found = array_diff($uids, array_keys($output));
        if (count($uids_not_found) > 0) {
            throw new EntryNotFoundException(jsonEncode($uids_not_found));
        }
        ksort($output);
        return $output;
    }
}
