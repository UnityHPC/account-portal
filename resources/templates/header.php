<?php

echo $TWIG->render("header.html.twig", [
    "user_exists" => $_SESSION["user_exists"] ?? false,
    "is_pi" => $_SESSION["is_pi"] ?? false,
    "is_admin" => $_SESSION["is_admin"] ?? false
]);
