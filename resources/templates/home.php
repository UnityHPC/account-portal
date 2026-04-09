<?php

echo $TWIG->render("home.html.twig", ["user_exists" => $_SESSION["user_exists"] ?? false]);
