<?php

use UnityWebPortal\lib\UnityHTTPD;

echo $TWIG->render("footer.html.twig", ["messages" => UnityHTTPD::getMessages()]);
