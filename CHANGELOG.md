# Changelog

For details on the changes in each release, see [the Releases page](https://github.com/UnityHPC/account-portal/releases).

## Version-specific update instructions:

### 1.6 -> 1.7

- the `update-qualified-users-group.php` worker should be executed
  - this may remove a large number of users from your qualified users group
- the `pages` SQL table should be droppped
  - the `home` page can be copied over to `deployment/templates_overrides/home.php`
  - the `support` page should be moved over to wherever you host your documentation
- the `notices` SQL table should be droppped
- a new LDAP schema needs to be added:
  ```shell
  scp tools/docker-dev/identity/account-portal-schema.ldif root@your-ldap-server:/root/account-portal-schema.ldif
  ssh root@your-ldap-server ldapadd -Y EXTERNAL -H ldapi:/// -f /root/account-portal-schema.ldif
  ```
- the objectClass `piGroup` needs to be added to all your PI groups
  ```shell
  ./workers/ensure-all-pi-groups-have-objectClass.php
  ```
- the `[ldap]group_ou` option has been renamed to `[ldap]usergroup_ou`
- the `[ldap]user_flag_groups[ghost]` group has been renamed to `[ldap]user_flag_groups[disabled]`
- 3 new colors `danger` `danger_hover` `danger_disabled` should be added to the branding config
- a new config section has been added `[expiry]` which needs 4 options set:
  - `idlelock_warning_days`: list of day numbers when a user will get an email warning that their account will be idlelocked
  - `idlelock_day`: day number when a user will be idlelocked
  - `disable_warning_days`: list of day numbers when a user will get an email warning that their account will be disabled
  - `disable_day`: day number when a user will be disabled
  - a "day number" starts counting from the last day that a user logged in, so on day 5, the user last logged in 5 days ago
- drop the `account_deletion_requests` table
- a new location `/lan` needs to be configured in your webserver
  - authorization: only IP addresses in your local area network should be allowed
  - authentication: none
- a new LDAP posixGroup needs to be created for "immortal" users, who are exempt from automatic account expiration
  - the `[ldap]user_flag_groups[immortal]` open must also be defined
- the `[site]account_policy_url` option has been renamed to `[site]pi_qualification_docs_url`
- the `[site]account_expiration_policy_url` option must be defined

### 1.5 -> 1.6

- the `[site]getting_started_url` option should be defined
- the `[ldap]admin_group` option has been renamed to `[ldap]user_flag_groups[admin]`
- the `[ldap]qualified_user_group` option has been renamed to `[ldap]user_flag_groups[qualified]`
- the `user_qualified`, `user_dequalified` mail templates have been removed
- the `user_flag_added`, `user_flag_removed` mail templates have been added (`qualified` is one of the flags)

### 1.4 -> 1.5

- Redis can be shut down
- the `[redis]` portion of your config file should be removed

### 1.3 -> 1.4

- the `[ldap]user_group` option has been renamed to `[ldap]qualified_user_group`
- the `user_created ` mail template has been renamed to `user_qualified`
- the `user_dequalified` mail template has been added

In v1.2.1, we extended PI group requests and PI group join requests to store user info like name and email.
This was necessary because LDAP entries were not created for users until they became "qualified" (become a PI or joined a PI group).
While a user was unqualified, if the Redis cache was cleared, the user info would be lost.
Now, LDAP entries are created immediately for every user, so this is no longer necessary.

- Shut down the web portal
  ```shell
  systemctl stop apache2
  ```
- Create LDAP entries for all existing requests
  ```php
  use UnityWebPortal\lib\UnityUser;
  $_SERVER["HTTP_HOST"] = "worker"; // see deployment/overrides/worker/
  $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
  require_once __DIR__ . "/../resources/autoload.php";
  foreach ($SQL->getAllRequests() as $request) {
    $user = new UnityUser($request["uid"], $LDAP, $SQL, $MAILER, $WEBHOOK);
    if (!$user->exists()) {
      echo "creating user: " . jsonEncode($request) . "\n";
      $user->init(
        $request["firstname"],
        $request["lastname"],
        $request["email"],
        $request["org"],
      );
    }
  }
  ```
- Remove columns from the `requests` table:
  ```sql
  ALTER TABLE `requests`
  DROP COLUMN `firstname`,
  DROP COLUMN `lastname`,
  DROP COLUMN `email`,
  DROP COLUMN `org`;
  ```
- Update the portal PHP code following the normal procedure
- Start the portal again
  ```shell
  systemctl start apache2
  ```

### 1.2 -> 1.3

- SQL:
  - remove the `sitevars` table
- `defaults/config.ini.default` has some new fields that need to be overriden:
  - `offset_UIDGID`
  - `offset_PIGID`
  - `offset_ORGGID`
- `custom_user_mappings` can no longer match with just the 1st segment of the logged in user's UID, an exact match is required
- LDAP:
  - create the `gecos` attribute for all users by concatenating `givenName` and `sn`

### 1.2.0 -> 1.2.1

- SQL:
  - Add new columns to the `requests` table:
    ```sql
    ALTER TABLE `requests`
    ADD `firstname` VARCHAR(768) NOT NULL AFTER `timestamp`,
    ADD `lastname` VARCHAR(768) NOT NULL AFTER `firstname`,
    ADD `email` VARCHAR(768) NOT NULL AFTER `lastname`,
    ADD `org` VARCHAR(768) NOT NULL AFTER `email`;
    ```

### 1.1 -> 1.2

- SQL:
  - Create the `sitevars` table (see `bootstrap.sql` for details)
  - Create the `audit_log` table (see `bootstrap.sql` for details)
  - Create the `account_deletion_requests` table (see `bootstrap.sql` for details)
  - Create the `user_last_logins` table (see `bootstrap.sql` for details)
  - Drop the `sso_log` table
  - Drop the `events` table
  - Reduce the size of all `varchar(1000)` columns to `varchar(768)`
  - Delete the `priv` row in the `pages` table (if moving site policy to external site)
  - Add the `account_policy` row in the `pages` table (if NOT moving site policy to external site)
- `defaults/config.ini.default` has some new fields that may need to be overriden:
  - `ldap.user_group`
  - `site.terms_of_service_url`
    - example, created account policy page: `https://unity.rc.umass.edu/panel/account_policy.php`
  - `site.account_policy_url`
    - example, using old site policy page: `https://unity.rc.umass.edu/panel/priv.php`
- LDAP:
  - Create a new group defined by `ldap.user_group` in the config

### 1.0 -> 1.1

- SQL:
  - Add the `home` content management row
- `config/branding/config.ini.default` has some new fields that may need to be overriden:
  - `mail.pi_approve*`
  - `page.home`
  - The entire `loginshell` section
