<?php

namespace UnityWebPortal\lib;

use PHPOpenLDAPer\LDAPEntry;
use Exception;

/**
 * Class that represents a single PI group in the UnityHPC Platform.
 */
class UnityGroup extends PosixGroup
{
    public const string PI_PREFIX = "pi_";
    public string $gid;
    private UnityLDAP $LDAP;
    private UnitySQL $SQL;
    private UnityMailer $MAILER;
    private UnityWebhook $WEBHOOK;

    public function __construct(
        string $gid,
        UnityLDAP $LDAP,
        UnitySQL $SQL,
        UnityMailer $MAILER,
        UnityWebhook $WEBHOOK,
    ) {
        parent::__construct($LDAP->getPIGroupEntry(trim($gid)));
        $this->gid = $gid;
        $this->LDAP = $LDAP;
        $this->SQL = $SQL;
        $this->MAILER = $MAILER;
        $this->WEBHOOK = $WEBHOOK;
    }

    public function __toString(): string
    {
        return $this->gid;
    }

    public function requestGroup(?bool $send_mail_to_admins = null, bool $send_mail = true): void
    {
        $send_mail_to_admins ??= CONFIG["mail"]["send_pimesg_to_admins"];
        if ($this->exists()) {
            return;
        }
        if ($this->SQL->accDeletionRequestExists($this->getOwner()->uid)) {
            return;
        }
        $context = [
            "user" => $this->getOwner()->uid,
            "org" => $this->getOwner()->getOrg(),
            "name" => $this->getOwner()->getFullName(),
            "email" => $this->getOwner()->getMail(),
        ];
        $this->SQL->addRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_request");
            $this->WEBHOOK->sendWebhook("group_request_admin", $context);
            if ($send_mail_to_admins) {
                $this->MAILER->sendMail("admin", "group_request_admin", $context);
            }
            $this->MAILER->sendMail("pi_approve", "group_request_admin", $context);
        }
    }

    /**
     * This method will create the group (this is what is executed when an admin approved the group)
     */
    public function approveGroup(bool $send_mail = true): void
    {
        $uid = $this->getOwner()->uid;
        $request = $this->SQL->getRequest($uid, UnitySQL::REQUEST_BECOME_PI);
        \ensure($this->getOwner()->exists());
        // if haunted group is re-approved, group is no longer haunted
        if ($this->entry->exists()) {
            if ($this->getIsHaunted()) {
                $this->setIsHaunted(false);
            } else {
                throw new Exception("cannot approve group that already exists and is not haunted");
            }
        } else {
            $this->init();
        }
        $this->SQL->removeRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        $this->SQL->addLog("approved_group", $this->getOwner()->uid);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_created");
        }
        // having your own group makes you qualified
        $this->getOwner()->setFlag(
            UserFlag::QUALIFIED,
            true,
            doSendMail: $send_mail,
            doSendMailAdmin: false,
        );
    }

    /**
     * This method is executed when an admin denys the PI group request
     */
    public function denyGroup(bool $send_mail = true): void
    {
        $request = $this->SQL->getRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        $this->SQL->removeRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        if ($this->exists()) {
            return;
        }
        $this->SQL->addLog("denied_group", $this->getOwner()->uid);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_denied");
        }
    }

    public function cancelGroupRequest(bool $send_mail = true): void
    {
        if (!$this->SQL->requestExists($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI)) {
            return;
        }
        $this->SQL->removeRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        if ($send_mail) {
            $this->MAILER->sendMail("admin", "group_request_cancelled", [
                "uid" => $this->getOwner()->uid,
            ]);
        }
    }

    public function cancelGroupJoinRequest(UnityUser $user, bool $send_mail = true): void
    {
        if (!$this->requestExists($user)) {
            return;
        }
        $this->SQL->removeRequest($user->uid, $this->gid);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_join_request_cancelled", [
                "uid" => $user->uid,
            ]);
        }
    }

    // /**
    //  * This method will delete the group, either by admin action or PI action
    //  */
    // public function removeGroup($send_mail = true)
    // {
    //     // remove any pending requests
    //     // this will silently fail if the request doesn't exist (which is what we want)
    //     $this->SQL->removeRequests($this->gid);

    //     // we don't need to do anything extra if the group is already deleted
    //     if (!$this->exists()) {
    //         return;
    //     }

    //     // first, we must record the users in the group currently
    //     $users = $this->getGroupMembers();

    //     // now we delete the ldap entry
    //     $this->entry->ensureExists();
    //     $this->entry->delete();

    //     // Logs the change
    //     $this->SQL->addLog("removed_group", $this->gid);

    //     // send email to every user of the now deleted PI group
    //     if ($send_mail) {
    //         foreach ($users as $user) {
    //             $this->MAILER->sendMail(
    //                 $user->getMail(),
    //                 "group_disband",
    //                 array("group_name" => $this->gid)
    //             );
    //         }
    //     }
    // }

    /**
     * This method is executed when a user is approved to join the group
     * (either by admin or the group owner)
     */
    public function approveUser(UnityUser $new_user, bool $send_mail = true): void
    {
        $request = $this->SQL->getRequest($new_user->uid, $this->gid);
        \ensure($new_user->exists());
        $this->addMemberUID($new_user->uid);
        $this->SQL->removeRequest($new_user->uid, $this->gid);
        $this->SQL->addLog(
            "approved_user",
            jsonEncode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_added", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_user_added_owner", [
                "group" => $this->gid,
                "user" => $new_user->uid,
                "name" => $new_user->getFullname(),
                "email" => $new_user->getMail(),
                "org" => $new_user->getOrg(),
            ]);
        }
        // being in a group makes you qualified
        $new_user->setFlag(
            UserFlag::QUALIFIED,
            true,
            doSendMail: $send_mail,
            doSendMailAdmin: false,
        );
    }

    public function denyUser(UnityUser $new_user, bool $send_mail = true): void
    {
        $request = $this->SQL->getRequest($new_user->uid, $this->gid);
        // remove request, this will fail silently if the request doesn't exist
        $this->SQL->removeRequest($new_user->uid, $this->gid);
        $this->SQL->addLog(
            "denied_user",
            jsonEncode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_denied", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_user_denied_owner", [
                "group" => $this->gid,
                "user" => $new_user->uid,
                "name" => $new_user->getFullName(),
                "email" => $new_user->getMail(),
                "org" => $new_user->getOrg(),
            ]);
        }
    }

    public function removeUser(UnityUser $new_user, bool $send_mail = true): void
    {
        if (!$this->memberUIDExists($new_user->uid)) {
            return;
        }
        if ($new_user->uid == $this->getOwner()->uid) {
            throw new Exception("Cannot delete group owner from group. Disband group instead");
        }
        // remove request, this will fail silently if the request doesn't exist
        $this->removeMemberUID($new_user->uid);
        $this->SQL->addLog(
            "removed_user",
            jsonEncode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_removed", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_user_removed_owner", [
                "group" => $this->gid,
                "user" => $new_user->uid,
                "name" => $new_user->getFullName(),
                "email" => $new_user->getMail(),
                "org" => $new_user->getOrg(),
            ]);
        }
        // if user is no longer in any PI group, dequalify them
        if (count($this->LDAP->getPIGroupGIDsWithMemberUID($new_user->uid)) === 0) {
            $new_user->setFlag(
                UserFlag::QUALIFIED,
                false,
                doSendMail: $send_mail,
                doSendMailAdmin: false,
            );
        }
    }

    public function newUserRequest(UnityUser $new_user, bool $send_mail = true): void
    {
        if ($this->memberUIDExists($new_user->uid)) {
            UnityHTTPD::errorLog("warning", "user '$new_user' already in group");
            return;
        }
        if ($this->requestExists($new_user)) {
            UnityHTTPD::errorLog("warning", "user '$new_user' already requested group membership");
            return;
        }
        if ($this->SQL->accDeletionRequestExists($new_user->uid)) {
            throw new Exception("user '$new_user' requested account deletion");
        }
        $this->addRequest($new_user->uid);
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_request", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_user_request_owner", [
                "group" => $this->gid,
                "user" => $new_user->uid,
                "name" => $new_user->getFullname(),
                "email" => $new_user->getMail(),
                "org" => $new_user->getOrg(),
            ]);
        }
    }

    public function getRequests(): array
    {
        $requests = $this->SQL->getRequests($this->gid);
        $out = [];
        foreach ($requests as $request) {
            $user = new UnityUser(
                $request["uid"],
                $this->LDAP,
                $this->SQL,
                $this->MAILER,
                $this->WEBHOOK,
            );
            array_push($out, [$user, $request["timestamp"]]);
        }
        return $out;
    }

    public function getGroupMembers(): array
    {
        $members = $this->getMemberUIDs();
        $out = [];
        foreach ($members as $member) {
            $user_obj = new UnityUser(
                $member,
                $this->LDAP,
                $this->SQL,
                $this->MAILER,
                $this->WEBHOOK,
            );
            array_push($out, $user_obj);
        }
        return $out;
    }

    public function requestExists(UnityUser $user): bool
    {
        $requesters = $this->getRequests();
        if (count($requesters) > 0) {
            foreach ($requesters as $requester) {
                if ($requester[0]->uid == $user->uid) {
                    return true;
                }
            }
        }
        return false;
    }

    private function init(): void
    {
        $owner = $this->getOwner();
        \ensure(!$this->entry->exists());
        $nextGID = $this->LDAP->getNextPIGIDNumber();
        $this->entry->create([
            "objectclass" => ["unityClusterPIGroup", "posixGroup", "top"],
            "gidnumber" => strval($nextGID),
            "memberuid" => [$owner->uid],
        ]);
        // TODO if we ever make this project based,
        // we need to update the cache here with the memberuid
    }

    private function addRequest(string $uid): void
    {
        $this->SQL->addRequest($uid, $this->gid);
    }

    public function getOwner(): UnityUser
    {
        return new UnityUser(
            self::GID2OwnerUID($this->gid),
            $this->LDAP,
            $this->SQL,
            $this->MAILER,
            $this->WEBHOOK,
        );
    }

    public static function ownerUID2GID(string $uid): string
    {
        return self::PI_PREFIX . $uid;
    }

    public static function GID2OwnerUID(string $gid): string
    {
        if (substr($gid, 0, strlen(self::PI_PREFIX)) != self::PI_PREFIX) {
            throw new Exception("PI group GID doesn't have the correct prefix.");
        }
        return substr($gid, strlen(self::PI_PREFIX));
    }

    /**
     * @throws \UnityWebPortal\lib\exceptions\EntryNotFoundException
     */
    public static function ownerMail2GID(string $email): string
    {
        global $LDAP;
        $entry = $LDAP->getUidFromEmail($email); // throws EntryNotFoundException
        $ownerUid = $entry->getAttribute("cn")[0];
        return self::PI_PREFIX . $ownerUid;
    }

    public function getGroupMembersAttributes(array $attributes, array $default_values = []): array
    {
        return $this->LDAP->getUsersAttributes(
            $this->getMemberUIDs(),
            $attributes,
            $default_values,
        );
    }

    public function getIsHaunted(): bool
    {
        $value = $this->entry->getAttribute("isHaunted");
        if (count($value) === 0) {
            return false;
        } else {
            return $value[0];
        }
    }

    public function setIsHaunted(bool $new_value): void
    {
        $this->entry->setAttribute("isHaunted", $new_value);
    }
}
