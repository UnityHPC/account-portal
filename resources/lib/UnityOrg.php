<?php

namespace UnityWebPortal\lib;

use PHPOpenLDAPer\LDAPEntry;

class UnityOrg extends PosixGroup
{
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
        parent::__construct($LDAP->getOrgGroupEntry(trim($gid)));
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

    public function init(): void
    {
        \ensure(!$this->entry->exists());
        $nextGID = $this->LDAP->getNextOrgGIDNumber();
        $this->entry->create([
            "objectclass" => ["posixGroup", "top"],
            "gidnumber" => strval($nextGID),
        ]);
    }

    public function getOrgMembers(): array
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
}
