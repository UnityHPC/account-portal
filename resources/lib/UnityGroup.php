<?php

namespace UnityWebPortal\lib;

use Exception;
use UnityWebPortal\lib\exceptions\EntryNotFoundException;

/**
 * Class that represents a single PI group in the Unity HPC Platform.
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
        if ($this->exists() && !$this->getIsDisabled()) {
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
     * email all members that group is now disabled, remove all members, log, set attribute
     * the owner must manually remove all members first, but admins don't have this requirement
     */
    public function disable(bool $send_mail = true): void
    {
        if ($this->getIsDisabled()) {
            throw new Exception("cannot disable an already disabled group");
        }
        $this->SQL->addLog("disable_pi_group", $this->gid);
        $memberuids = $this->getMemberUIDs();
        if ($send_mail) {
            $member_attributes = $this->LDAP->getUsersAttributes($memberuids, ["mail"]);
            $member_mails = array_map(fn($x) => (string) $x["mail"][0], $member_attributes);
            if (count($member_mails) > 0) {
                $this->MAILER->sendMail($member_mails, "group_disabled", [
                    "group_name" => $this->gid,
                ]);
            }
        }
        $this->setIsDisabled(true);
        if (count($memberuids) > 0) {
            $this->entry->setAttribute("memberuid", []);
        }
        // TODO optimize
        // UnityUser::__construct() makes one LDAP query for each user
        // updateIsQualified() makes one LDAP query for each member
        // if user is no longer in any PI group, disqualify them
        foreach ($memberuids as $uid) {
            $user = new UnityUser($uid, $this->LDAP, $this->SQL, $this->MAILER, $this->WEBHOOK);
            $user->updateIsQualified($send_mail);
        }
    }

    private function reenable(bool $send_mail = true): void
    {
        if (!$this->getIsDisabled()) {
            throw new Exception("cannot re-enable a group that is not disabled");
        }
        $this->SQL->addLog("reenabled_pi_group", $this->gid);
        if ($send_mail) {
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_reenabled",
                ["group_name" => $this->gid],
            );
        }
        $this->setIsDisabled(false);
        $owner_uid = $this->getOwner()->uid;
        if (!$this->memberUIDExists($owner_uid)) {
            $this->addMemberUID($owner_uid);
        }
        $this->getOwner()->updateIsQualified($send_mail);
    }

    /**
     * This method will create the group (this is what is executed when an admin approved the group)
     */
    public function approveGroup(bool $send_mail = true): void
    {
        $uid = $this->getOwner()->uid;
        $request = $this->SQL->getRequest($uid, UnitySQL::REQUEST_BECOME_PI);
        \ensure($this->getOwner()->exists());
        if (!$this->entry->exists()) {
            $this->init();
        } elseif ($this->getIsDisabled()) {
            $this->reenable();
        } else {
            throw new Exception("cannot approve group that already exists and is not disabled");
        }
        $this->SQL->removeRequest($this->getOwner()->uid, UnitySQL::REQUEST_BECOME_PI);
        $this->SQL->addLog("approved_group", $this->getOwner()->uid);
        if ($send_mail) {
            $this->MAILER->sendMail($this->getOwner()->getMail(), "group_created");
        }
        // having your own group makes you qualified
        $this->getOwner()->updateIsQualified($send_mail);
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
            $this->MAILER->sendMail("pi_approve", "group_request_cancelled", [
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
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_join_request_cancelled",
                ["uid" => $user->uid],
            );
        }
    }

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
            _json_encode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_added", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_user_added_owner",
                [
                    "group" => $this->gid,
                    "user" => $new_user->uid,
                    "name" => $new_user->getFullname(),
                    "email" => $new_user->getMail(),
                    "org" => $new_user->getOrg(),
                ],
            );
        }
        $new_user->updateIsQualified($send_mail); // being in a group makes you qualified
    }

    public function denyUser(UnityUser $new_user, bool $send_mail = true): void
    {
        $request = $this->SQL->getRequest($new_user->uid, $this->gid);
        // remove request, this will fail silently if the request doesn't exist
        $this->SQL->removeRequest($new_user->uid, $this->gid);
        $this->SQL->addLog(
            "denied_user",
            _json_encode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_denied", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_user_denied_owner",
                [
                    "group" => $this->gid,
                    "user" => $new_user->uid,
                    "name" => $new_user->getFullName(),
                    "email" => $new_user->getMail(),
                    "org" => $new_user->getOrg(),
                ],
            );
        }
    }

    public function removeUser(UnityUser $new_user, bool $send_mail = true): void
    {
        if (!$this->memberUIDExists($new_user->uid)) {
            return;
        }
        if ($new_user->uid == $this->getOwner()->uid) {
            throw new Exception("Cannot delete group owner from group. Disable group instead");
        }
        $this->removeMemberUID($new_user->uid);
        $this->SQL->addLog(
            "removed_user",
            _json_encode(["user" => $new_user->uid, "group" => $this->gid]),
        );
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_removed", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_user_removed_owner",
                [
                    "group" => $this->gid,
                    "user" => $new_user->uid,
                    "name" => $new_user->getFullName(),
                    "email" => $new_user->getMail(),
                    "org" => $new_user->getOrg(),
                ],
            );
        }
        // if user is no longer in any PI group, disqualify them
        $new_user->updateIsQualified($send_mail);
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
        $this->addRequest($new_user->uid);
        if ($send_mail) {
            $this->MAILER->sendMail($new_user->getMail(), "group_user_request", [
                "group" => $this->gid,
            ]);
            $this->MAILER->sendMail(
                $this->getOwnerMailAndPlusAddressedManagerMails(),
                "group_user_request_owner",
                [
                    "group" => $this->gid,
                    "user" => $new_user->uid,
                    "name" => $new_user->getFullname(),
                    "email" => $new_user->getMail(),
                    "org" => $new_user->getOrg(),
                ],
            );
        }
    }

    /** @return array{0: UnityUser, 1: string}[] */
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

    /** @return UnityUser[] */
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
            "objectclass" => ["piGroup", "posixGroup", "top"],
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
     * @param string[] $attributes
     * @param array<string, mixed[]> $default_values
     * @return array<string, mixed[]>
     */
    public function getGroupMembersAttributes(array $attributes, array $default_values = []): array
    {
        return $this->LDAP->getUsersAttributes(
            $this->getMemberUIDs(),
            $attributes,
            $default_values,
        );
    }

    public function getIsDisabled(): bool
    {
        $value = $this->entry->getAttribute("isDisabled");
        switch (count($value)) {
            case 0:
                return false;
            case 1:
                switch ($value[0]) {
                    case "TRUE":
                        return true;
                    case "FALSE":
                        return false;
                    default:
                        throw new \RuntimeException(
                            sprintf(
                                "unexpected value for isDisabled: '%s'. expected 'TRUE' or 'FALSE'",
                                $value[0],
                            ),
                        );
                }
            default:
                throw new \RuntimeException(
                    sprintf(
                        "expected value of length 0 or 1, found value %s of length %s",
                        _json_encode($value),
                        count($value),
                    ),
                );
        }
    }

    private function setIsDisabled(bool $new_value): void
    {
        $this->entry->setAttribute("isDisabled", $new_value ? "TRUE" : "FALSE");
    }

    public function addManagerUID(string $uid): void
    {
        $new_manager = new UnityUser($uid, $this->LDAP, $this->SQL, $this->MAILER, $this->WEBHOOK);
        if (!$new_manager->exists()) {
            throw new EntryNotFoundException("user '$uid' does not exist!");
        }
        $member_uids = $this->getMemberUIDs();
        if (!in_array($uid, $member_uids)) {
            throw new Exception("user '$uid' is not a group member!");
        }
        $this->entry->appendAttribute("managerUid", $uid);
    }

    public function removeManagerUID(string $uid): void
    {
        $this->entry->removeAttributeEntryByValue("managerUid", $uid);
    }

    public function managerUIDExists(string $uid): bool
    {
        return in_array($uid, $this->entry->getAttribute("managerUid"));
    }

    /** @return string[] */
    public function getManagerUIDs(): array
    {
        return $this->entry->getAttribute("managerUid");
    }

    public function removeMemberUID(string $uid): void
    {
        if ($this->managerUIDExists($uid)) {
            $this->removeManagerUID($uid);
        }
        parent::removeMemberUID($uid);
    }

    public function addPlusAddressToMail(string $mail): string
    {
        $owner = $this->getOwner();
        $suffix = "_" . $owner->getOrg();
        ensure(str_ends_with($owner->uid, $suffix));
        $short_name = substr($owner->uid, 0, -1 * strlen($suffix));
        $parts = explode("@", $mail, 2);
        return sprintf("%s+%s@%s", $parts[0], $short_name, $parts[1]);
    }

    /** @return string[] */
    public function getOwnerMailAndPlusAddressedManagerMails(): array
    {
        $mails = [$this->getOwner()->getMail()];
        foreach ($this->getManagerUIDs() as $manager_uid) {
            $manager = new UnityUser(
                $manager_uid,
                $this->LDAP,
                $this->SQL,
                $this->MAILER,
                $this->WEBHOOK,
            );
            array_push($mails, $this->addPlusAddressToMail($manager->getMail()));
        }
        $mails = array_unique($mails);
        sort($mails);
        return $mails;
    }
}
