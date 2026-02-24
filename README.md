![UNITY](https://user-images.githubusercontent.com/40907639/137608695-2d914da2-1ecc-480b-a47e-a9e33b2b1b45.png)

# Unity HPC Account Portal

An identity management GUI for research computing written in PHP and built on MariaDB and OpenLDAP.

Typical Usage:

- User registers an account
  - LDAP entries are created
- User manages SSH public keys
  - No passwords
  - Github import, upload file, paste, generate and download private key
- User changes login shell
- User requests to join a PI group
  - Requires PI approval
- User requests their own PI group
  - Requires admin approval
- PI approves/denies requests to join their PI group
- PI removes members from their group

Admin Features:

- Responsive Tables ([datatables.net](https://datatables.net)) for filtering, sorting, pagination, etc.
- Branding customization for multiple domains simultaneously
- Custom UIDNumber / GIDNumber mappings for specific users
- Login as another user
- Mailing
- User expiration

## Installation/Deployment

See the Docker Compose environment (`tools/docker-dev/`) for an (unsafe for production) example.

1. OpenLDAP server
   - Structure should be similar to `tools/docker-dev/identity/bootstrap.ldif` <!-- TODO separate OUs from entries -->
   - Also see `tools/docker-dev/identity/{config,ssh}.ldif`
   - recommended openldap modules/overlays:
     - `unique`: prevent UIDNumber, GIDNumber conflicts
     - `pw-sha2`: allow the use of sha2 password hashing algorithms for bind
1. MySQL / MariaDB server
   - Structure should be similar to `tools/docker-dev/sql/bootstrap.sql` <!-- TODO separate structure from data -->
1. SMTP server
1. Web server
   - This repository cloned
   - `deployment/config/config.ini` should be owned by the apache user (`www-data` on Ubuntu), with mode `0600`
   - Submodules checked out (`git submodule update --checkout --init`)
   - Composer (`apt install composer` on Ubuntu)
   - NodeJS
     - this is only needed because we use `npm` to manage frontend JS files like jQuery
     - (`curl -sL "https://deb.nodesource.com/setup_24.x" | sudo bash; sudo apt update; sudo apt install nodejs` on Ubuntu)
   - Dependencies:
     - PHP extensions
       - curl, intl, ldap, mbstring, mysql, pdo, xml (`apt install php-curl php-intl php-ldap php-mbstring php-mysql php-pdo php-xml` on Ubuntu)
     - PHP Libraries
       - `COMPOSER_ALLOW_SUPERUSER=1 composer --no-dev --no-scripts --no-plugins install`
     - JS Libraries
       - `npm install`
       - `npx copy-files-from-to`
   - `httpd` `DocumentRoot` set to `webroot/`
   - `httpd` Authentication
     - Any authentication will do as long as it defines `REMOTE_USER`, `givenName`, `sn`, and `mail`
       - `REMOTE_USER` must take the form `username@org`
       - `givenName` is first name, `sn` is last name
     - Unity uses Shibboleth SP and the Apache Shibboleth module (`apt install shibboleth-sp-utils libapache2-mod-shib` on Ubuntu)
   - `httpd` Authorization
     - Restricted access to `webroot/admin/`
     - Global access (with valid authentication) to `webroot/`
     - No access anywhere else
1. Authorization for your other services based on user flag groups
   - in order to access your services, a user should be in the `qualified` group and should not be in the `locked`, `idlelocked`, or `disabled` groups
   - (what services you offer) and (how you implement this authorization) are out of scope

## Configuration

1. Create `deployment/config/config.ini` using `/deployment/defaults/config.ini` as a reference
   - Make sure this file is not world readable!
1. If using mulitple domains, create `deployment/overrides/<domain>/config/config.ini`
1. If using custom UIDNumber/GIDNumber mappings, create `deployment/custom_user_mappings/*.csv`
   - The 1st column is UID, the 2nd column is both UIDNumber and GIDNumber
1. Add logos to `webroot/assets/footer_logos/`
1. Copy emails from `resources/mail` to `deployment/mail_overrides` and edit them if you wish
1. Copy pages from `resources/templates` to `deployment/templates_overrides` and edit them if you wish

## Integration

The scope of this project ends at being responsible for the LDAP user database. We recommend production deployments to set up scripts which detect changes in LDAP and then perform further actions. For example, Unity uses such scripts to create home directories and add records to the Slurm account database.

## Updating

We recommend a deployment where each version of the portal is its own clone, then just change a symlink to point to the new version. This way a rollback is much easier.

Example folder structure, where `->` indicates a symlink:

```
/srv/www/
    unity-web-portal -> unity-web-portal-1.1.0
    unity-web-portal-1.1.0
    unity-web-portal-1.2.0
```

Update instructions assuming the above structure:

```shell
url="https://..."
prod="/srv/www/unity-web"
old="/srv/www/unity-web-1.1.0"
new="/srv/www/unity-web-1.2.0"

mkdir "$new" && cd "$new"
git clone "$url" .
git submodule update --init --checkout
COMPOSER_ALLOW_SUPERUSER=1 composer --no-dev --no-scripts --no-plugins install
npm install
npx copy-files-from-to
cp --preserve=all "$prod/deployment/config/config.ini" ./deployment/config/config.ini
rsync -a "$prod/deployment/custom_user_mappings/" ./deployment/custom_user_mappings/
rsync -a "$prod/deployment/overrides/" ./deployment/overrides/
rsync -a "$prod/webroot/assets/footer_logos/" ./footer_logos/
rsync -a "$prod/deployment/mail_overrides/" ./deployment/mail_overrides/
rsync -a "$prod/deployment/templates_overrides/" ./deployment/templates_overrides/
rm "$prod" && ln -s "$PWD" "$prod"
```

Rollback:

```shell
rm "$prod" && ln -s "$old" "$prod"
```
