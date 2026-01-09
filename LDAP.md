Terminology:

- **qualified user**: a user who is currently a PI or a member of at least one PI group
- **unqualified user**: inverse of qualified
- **native user**: a user created by this account portal
- **non-native user**: inverse of native
  - users created for administrative purposes should not be mixed with native users in the LDAP OUs given in `config.ini` or else this account portal may get confused
- **ghost user**: a user who is effectively deleted
- **defunct group**: a PI group that was disbanded or that lost its owner
  - memberuid attribute should be empty
