<?php

namespace UnityWebPortal\lib;

use PHPOpenLDAPer\LDAPEntry;
use Exception;
use UnityWebPortal\lib\exceptions\ArrayKeyException;

class UnityUser
{
    private const HOME_DIR = "/home/";

    public string $uid;
    private LDAPEntry $entry;
    private UnityLDAP $LDAP;
    private UnitySQL $SQL;
    private UnityMailer $MAILER;
    private UnityWebhook $WEBHOOK;

    public function __construct(
        string $uid,
        UnityLDAP $LDAP,
        UnitySQL $SQL,
        UnityMailer $MAILER,
        UnityWebhook $WEBHOOK,
    ) {
        $uid = trim($uid);
        $this->uid = $uid;
        $this->entry = $LDAP->getUserEntry($uid);

        $this->LDAP = $LDAP;
        $this->SQL = $SQL;
        $this->MAILER = $MAILER;
        $this->WEBHOOK = $WEBHOOK;
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
        \ensure(!$ldapGroupEntry->exists());
        $ldapGroupEntry->create([
            "objectclass" => ["posixGroup", "top"],
            "gidnumber" => strval($id),
        ]);
        \ensure(!$this->entry->exists());
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

    public function setFlag(
        UserFlag $flag,
        bool $newValue,
        bool $doSendMail = true,
        bool $doSendMailAdmin = true,
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
                    "org" => $this->getOrg(),
                    "flag" => $flag,
                ]);
            }
            if ($doSendMailAdmin) {
                $this->MAILER->sendMail("admin", "user_flag_added_admin", [
                    "user" => $this->uid,
                    "org" => $this->getOrg(),
                    "flag" => $flag,
                ]);
            }
        } else {
            $this->LDAP->userFlagGroups[$flag->value]->removeMemberUID($this->uid);
            if ($doSendMail) {
                $this->MAILER->sendMail($this->getMail(), "user_flag_removed", [
                    "user" => $this->uid,
                    "org" => $this->getOrg(),
                    "flag" => $flag,
                ]);
            }
            if ($doSendMailAdmin) {
                $this->MAILER->sendMail("admin", "user_flag_removed_admin", [
                    "user" => $this->uid,
                    "org" => $this->getOrg(),
                    "flag" => $flag,
                ]);
            }
        }
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
        $this->entry->setAttribute("o", $org);
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
        $this->entry->setAttribute("givenname", $firstname);
        $this->SQL->addLog("firstname_changed", $this->uid);
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
        $this->entry->setAttribute("sn", $lastname);
        $this->SQL->addLog("lastname_changed", $this->uid);
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
        $this->entry->setAttribute("mail", $email);
        $this->SQL->addLog("email_changed", $this->uid);
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
    }

    /**
     * Sets the SSH keys on the account and the corresponding entry
     * @param string[] $keys
     */
    private function setSSHKeys(array $keys, bool $send_mail = true): void
    {
        \ensure($this->entry->exists());
        $this->entry->setAttribute("sshpublickey", $keys);
        $this->SQL->addLog("sshkey_modify", $this->uid);
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
        $result = $this->entry->getAttribute("sshpublickey");
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
        \ensure($this->entry->exists());
        $this->entry->setAttribute("loginshell", $shell);
        $this->SQL->addLog("loginshell_changed", $this->uid);
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
        \ensure($this->entry->exists());
        $this->entry->setAttribute("homedirectory", $home);
        $this->SQL->addLog("homedir_changed", $this->uid);
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
            $this->WEBHOOK,
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
     * Sends an email to admins about account deletion request
     * and also adds it to a table in the database
     */
    public function requestAccountDeletion(): void
    {
        $this->SQL->deleteRequestsByUser($this->uid);
        $this->SQL->addAccountDeletionRequest($this->uid);
        $this->MAILER->sendMail("admin", "account_deletion_request_admin", [
            "user" => $this->uid,
            "name" => $this->getFullname(),
            "email" => $this->getMail(),
        ]);
    }

    public function cancelRequestAccountDeletion(): void
    {
        $this->SQL->deleteAccountDeletionRequest($this->uid);
        $this->MAILER->sendMail("admin", "account_deletion_request_cancelled_admin", [
            "user" => $this->uid,
            "name" => $this->getFullname(),
            "email" => $this->getMail(),
        ]);
    }

    /**
     * Checks if the user has requested account deletion
     */
    public function hasRequestedAccountDeletion(): bool
    {
        return $this->SQL->accDeletionRequestExists($this->uid);
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

    public function disable(bool $send_mail = true, bool $send_mail_admin = true): void
    {
        foreach ($this->LDAP->getNonDisabledPIGroupGIDsWithMemberUID($this->uid) as $gid) {
            $group = new UnityGroup($gid, $this->LDAP, $this->SQL, $this->MAILER, $this->WEBHOOK);
            $group->removeMemberUID($this->uid);
        }
        $this->entry->removeAttribute("sshPublicKey");
        $this->setFlag(
            UserFlag::DISABLED,
            true,
            doSendMail: $send_mail,
            doSendMailAdmin: $send_mail_admin,
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
