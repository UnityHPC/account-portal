# Deployment

## `config.base.ini`

This is the base configuration. Do not modify it!

## `config.ini`

You should copy the base config to a new file called `config.ini` and make your changes there.

## `custom_user_mappings/`

Any file ending with `.csv` in this directory is assumed to contain custom user mappings.
These files must have 2 columns: `uid`, `idNumber`.
When a user registers an account, the account portal must allocate an ID number for them.
This ID number is used for their uidNumber *and* their gidNumber.
If the user's UID is found in any of the custom user mapping files, that number is used if available.
If not available, the normal allocation procedure is used.

## `mail/`

In this directory, you can copy any file from `resources/mail/` and modify it. **The filename must remain the same**

## `templates/`

In this directory, you can copy any file from `resources/templates/` and modify it. **The filename must remain the same**

## `domain_overrides/`

If your account portal is hosted using multiple DNS domain names, you can setup domain-specific deployment overrides.
In this directory, each subdirectory is a domain name.
Each subdirectory can contain `config.ini`, `mail/` and `templates/`.
This is particularly useful for changing branding colors.
Domain overrides are also used to change the portal's behavior when executing command line tools.

Example:

```
config.base.ini
config.ini
custom_user_mappings/
    example.csv
mail/
    example.php
templates/
    example.php
domain_overrides/
    foobar/
        config.ini
        custom_user_mappings/
            example.csv
        mail/
            example.php
        templates/
            example.php
```
