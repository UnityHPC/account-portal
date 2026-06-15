#!/bin/bash

# unlike the other workers, spoof-user-registration.php needs to initialize a proper user session
# this cannot be done if anything has been printed to stdout
# https://stackoverflow.com/questions/8028957/how-to-fix-headers-already-sent-error-in-php

echo "Please enter the spoofed user's attributes below."
echo "Name and email can be imprecise and they will be updated when the real user logs in."
echo "EPPN must be the exact value from the home institution. This determines their username and cannot be changed later."

read -r -p "first name: " first_name
read -r -p "last name: " last_name
read -r -p "EPPN: " eppn
read -r -p "mail: " mail

printf '%s\n' "$first_name" "$last_name" "$eppn" "$mail" | ./spoof-user-registration.php
