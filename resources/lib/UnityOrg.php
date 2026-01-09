<?php

namespace UnityWebPortal\lib;

class UnityOrg extends PosixGroup
{
    public string $gid;
    private UnityLDAP $LDAP;

    public function __construct(string $gid, UnityLDAP $LDAP)
    {
        parent::__construct($LDAP->getOrgGroupEntry(trim($gid)));
        $this->gid = $gid;
        $this->LDAP = $LDAP;
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
            "objectclass" => UnityLDAP::POSIX_GROUP_CLASS,
            "gidnumber" => strval($nextGID),
        ]);
    }
}
