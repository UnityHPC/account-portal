<?php

namespace UnityWebPortal\lib;

use PHPOpenLDAPer\LDAPEntry;
use Exception;
use UnityWebPortal\lib\exceptions\ArrayKeyException;

enum UnityUserDisabledReason: string
{
    case DisabledSelf = "DisabledSelf";
    case Expired = "Expired";
}

class UnityUser
{
    private const HOME_DIR = "/home/";

    public string $uid;
    private LDAPEntry $entry;
    private UnityLDAP $LDAP;
    private UnitySQL $SQL;
    private UnityMailer $MAILER;

    public function __construct(string $uid, UnityLDAP $LDAP, UnitySQL $SQL, UnityMailer $MAILER)
    {
        $uid = trim($uid);
        $this->uid = $uid;
        $this->entry = $LDAP->getUserEntry($uid);

        $this->LDAP = $LDAP;
        $this->SQL = $SQL;
        $this->MAILER = $MAILER;
    }

    public function equals(UnityUser $other_user): bool
    {
        return $this->uid == $other_user->uid;
    }

    public function __toString(): string
    {
        return $this->uid;
    }

    /**
     * This is the method that is run when a new account is created
     */
    public function init(
        string $firstname,
        string $lastname,
        string $email,
        string $org,
        bool $send_mail = true,
    ): void {
        $ldapGroupEntry = $this->getUserGroupEntry();
        $id = $this->LDAP->getNextUIDGIDNumber($this->uid);
        assert(!$ldapGroupEntry->exists());
        $ldapGroupEntry->create([
            "objectclass" => ["posixGroup", "top"],
            "gidnumber" => strval($id),
        ]);
        assert(!$this->entry->exists());
        $this->entry->create([
            "objectclass" => UnityLDAP::POSIX_ACCOUNT_CLASS,
            "uid" => $this->uid,
            "givenname" => $firstname,
            "sn" => $lastname,
            "gecos" => \transliterator_transliterate("Latin-ASCII", "$firstname $lastname"),
            "mail" => $email,
            "o" => $org,
            "homedirectory" => self::HOME_DIR . $this->uid,
            "loginshell" => $this->LDAP->getDefUserShell(),
            "uidnumber" => strval($id),
            "gidnumber" => strval($id),
        ]);
        $org = $this->getOrgGroup();
        if (!$org->exists()) {
            $org->init();
        }
        if (!$org->memberUIDExists($this->uid)) {
            $org->addMemberUID($this->uid);
        }

        $this->SQL->addLog("user_added", $this->uid);
    }

    public function getFlag(UserFlag $flag): bool
    {
        return $this->LDAP->userFlagGroups[$flag->value]->memberUIDExists($this->uid);
    }

    /** if you want to set the "disabled" flag, you should probably use disable() or reEnable() */
    public function setFlag(
        UserFlag $flag,
        bool $newValue,
        bool $doSendMail = true,
        bool $doSendMailAdmin = true,
        mixed $why = null,
    ): void {
        $oldValue = $this->getFlag($flag);
        if ($oldValue == $newValue) {
            return;
        }
        $this->SQL->addLog(
            sprintf("set_user_flag_%s_%s", $flag->value, $newValue ? "true" : "false"),
            $this->uid,
        );
        if ($newValue) {
            $this->LDAP->userFlagGroups[$flag->value]->addMemberUID($this->uid);
            if ($doSendMail) {
                $this->MAILER->sendMail($this->getMail(), "user_flag_added", [
                    "user" => $this->uid,
                    "flag" => $flag,
                    "why" => $why,
                ]);
            }
            if ($doSendMailAdmin) {
                $this->MAILER->sendMail("admin", "user_flag_added_admin", [
                    "user" => $this->uid,
                    "flag" => $flag,
                    "why" => $why,
                ]);
            }
        } else {
            $this->LDAP->userFlagGroups[$flag->value]->removeMemberUID($this->uid);
            if ($doSendMail) {
                $this->MAILER->sendMail($this->getMail(), "user_flag_removed", [
                    "user" => $this->uid,
                    "flag" => $flag,
                    "why" => $why,
                ]);
            }
            if ($doSendMailAdmin) {
                $this->MAILER->sendMail("admin", "user_flag_removed_admin", [
                    "user" => $this->uid,
                    "flag" => $flag,
                    "why" => $why,
                ]);
            }
        }
    }

    private function setAttribute(string $attribute_name, mixed $attribute_value): void
    {
        assert($this->entry->exists());
        $before = $this->entry->getAttribute($attribute_name);
        $after = (array) $attribute_value;
        if ($before === $after) {
            return;
        }
        $this->entry->setAttribute($attribute_name, $attribute_value);
        $this->SQL->addLog(
            "attribute_changed",
            _json_encode([
                "uid" => $this->uid,
                "attribute" => $attribute_name,
                "before" => $before,
                "after" => $attribute_value,
            ]),
        );
    }

    /**
     * Returns the ldap group entry corresponding to the user
     */
    public function getUserGroupEntry(): LDAPEntry
    {
        return $this->LDAP->getUserGroupEntry($this->uid);
    }

    public function exists(): bool
    {
        return $this->entry->exists() && $this->getUserGroupEntry()->exists();
    }

    public function setOrg(string $org): void
    {
        $this->setAttribute("o", $org);
    }

    public function getOrg(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("o")[0];
    }

    /**
     * Sets the firstname of the account and the corresponding ldap entry if it exists
     */
    public function setFirstname(string $firstname): void
    {
        $this->setAttribute("givenName", $firstname);
    }

    /**
     * Gets the firstname of the account
     */
    public function getFirstname(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("givenname")[0];
    }

    /**
     * Sets the lastname of the account and the corresponding ldap entry if it exists
     */
    public function setLastname(string $lastname): void
    {
        $this->setAttribute("sn", $lastname);
    }

    /**
     * Get method for the lastname on the account
     */
    public function getLastname(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("sn")[0];
    }

    public function getFullname(): string
    {
        $this->entry->ensureExists();
        return $this->getFirstname() . " " . $this->getLastname();
    }

    /**
     * Sets the mail in the account and the ldap entry
     */
    public function setMail(string $email): void
    {
        $this->setAttribute("mail", $email);
    }

    /**
     * Method to get the mail instance var
     */
    public function getMail(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("mail")[0];
    }

    /**
     * @return bool true if key added, false if key not added because it was already there
     * be sure to check that the key is actually valid first!
     */
    public function addSSHKey(string $key, bool $send_mail = true): bool
    {
        if ($this->SSHKeyExists($key)) {
            return false;
        }
        $this->setSSHKeys(array_merge($this->getSSHKeys(), [$key]), $send_mail);
        $key_info = formatSSHKeyInfoInternal($key);
        $this->SQL->addLog("sshkey_added", _json_encode(["uid" => $this->uid, "key" => $key_info]));
        return true;
    }

    /**
     *  @throws ArrayKeyException
     */
    public function removeSSHKey(string $key, bool $send_mail = true): void
    {
        $keys_before = $this->getSSHKeys();
        $keys_after = $keys_before;
        if (($i = array_search($key, $keys_before)) !== false) {
            unset($keys_after[$i]);
        } else {
            throw new ArrayKeyException($key);
        }
        $keys_after = array_values($keys_after); // reindex
        $this->setSSHKeys($keys_after, $send_mail);
        $key_info = formatSSHKeyInfoInternal($key);
        $this->SQL->addLog(
            "sshkey_removed",
            _json_encode(["uid" => $this->uid, "key" => $key_info]),
        );
    }

    /**
     * Sets the SSH keys on the account and the corresponding entry
     * @param string[] $keys
     */
    private function setSSHKeys(array $keys, bool $send_mail = true): void
    {
        assert($this->entry->exists());
        // do not use $this->setAttribute because it writes too much data to audit log
        // TODO use fingerprints?
        $this->entry->setAttribute("sshpublickey", $keys);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getMail(), "user_sshkey", [
                "keys" => $this->getSSHKeys(),
            ]);
        }
    }

    /**
     * Returns the SSH keys attached to the account
     * @return string[]
     */
    public function getSSHKeys(): array
    {
        $this->entry->ensureExists();
        $result = $this->entry->getAttribute("sshPublicKey");
        return $result;
    }

    /* checks if key exists, ignoring the optional comment suffix */
    public function SSHKeyExists(string $key): bool
    {
        $keyNoSuffix = removeSSHKeyOptionalCommentSuffix($key);
        foreach ($this->getSSHKeys() as $foundKey) {
            $foundKeyNoSuffix = removeSSHKeyOptionalCommentSuffix($foundKey);
            if ($key === $foundKey || $keyNoSuffix === $foundKeyNoSuffix) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sets the login shell for the account
     */
    public function setLoginShell(string $shell, bool $send_mail = true): void
    {
        // ldap schema syntax is "IA5 String (1.3.6.1.4.1.1466.115.121.1.26)"
        if (!mb_check_encoding($shell, "ASCII")) {
            throw new Exception("non ascii characters are not allowed in a login shell!");
        }
        if ($shell != trim($shell)) {
            throw new Exception("leading/trailing whitespace is not allowed in a login shell!");
        }
        if (empty($shell)) {
            throw new Exception("login shell must not be empty!");
        }
        $this->setAttribute("loginShell", $shell);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getMail(), "user_loginshell", [
                "new_shell" => $this->getLoginShell(),
            ]);
        }
    }

    /**
     * Gets the login shell of the account
     */
    public function getLoginShell(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("loginshell")[0];
    }

    public function setHomeDir(string $home): void
    {
        $this->setAttribute("homeDirectory", $home);
    }

    /**
     * Gets the home directory of the user
     */
    public function getHomeDir(): string
    {
        $this->entry->ensureExists();
        return $this->entry->getAttribute("homedirectory")[0];
    }

    /**
     * Checks if current user is a PI
     */
    public function isPI(): bool
    {
        return $this->getPIGroup()->exists() && !$this->getPIGroup()->getIsDisabled();
    }

    public function getPIGroup(): UnityGroup
    {
        return new UnityGroup(
            UnityGroup::ownerUID2GID($this->uid),
            $this->LDAP,
            $this->SQL,
            $this->MAILER,
        );
    }

    public function getOrgGroup(): UnityOrg
    {
        return new UnityOrg($this->getOrg(), $this->LDAP);
    }

    /**
     * Gets the groups this user is assigned to, can be more than one
     * @return string[]
     */
    public function getPIGroupGIDs(): array
    {
        return $this->LDAP->getNonDisabledPIGroupGIDsWithMemberUID($this->uid);
    }

    /**
     * Checks whether a user is in a group or not
     */
    public function isInGroup(string $uid, UnityGroup $group): bool
    {
        return in_array($uid, $group->getMemberUIDs());
    }

    public function updateIsQualified(bool $send_mail = true): void
    {
        $this->setFlag(
            UserFlag::QUALIFIED,
            count($this->getPIGroupGIDs()) !== 0,
            doSendMail: $send_mail,
            doSendMailAdmin: false,
        );
    }

    public function disable(
        UnityUserDisabledReason $why,
        bool $send_mail = true,
        bool $send_mail_pi_group_owner = true,
        bool $send_mail_admin = true,
    ): void {
        $pi_group = $this->getPIGroup();
        if ($pi_group->exists() && !$pi_group->getIsDisabled()) {
            $pi_group->disable($send_mail);
        }
        foreach ($this->LDAP->getNonDisabledPIGroupGIDsWithMemberUID($this->uid) as $gid) {
            $group = new UnityGroup($gid, $this->LDAP, $this->SQL, $this->MAILER);
            $group->removeUser($this, $why, send_mail: $send_mail_pi_group_owner);
        }
        $this->entry->removeAttribute("sshPublicKey");
        $this->setFlag(
            UserFlag::DISABLED,
            true,
            doSendMail: $send_mail,
            doSendMailAdmin: $send_mail_admin,
            why: $why,
        );
    }

    public function reEnable(bool $send_mail = true, bool $send_mail_admin = true): void
    {
        $this->setFlag(
            UserFlag::DISABLED,
            false,
            doSendMail: $send_mail,
            doSendMailAdmin: $send_mail_admin,
        );
    }
}
