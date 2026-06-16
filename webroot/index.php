<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . "/../resources/autoload.php";

$app = AppFactory::create();

$render = function (Response $response, string $scriptPath): Response {
    ob_start();
    include $scriptPath;
    $output = _ob_get_clean();
    $response->getBody()->write($output);
    return $response;
};

$app->any("/", function (Request $_request, Response $response): Response {
    ob_start();
    require getTemplatePath("header.php");
    require getTemplatePath("home.php");
    require getTemplatePath("footer.php");
    $output = _ob_get_clean();
    $response->getBody()->write($output);
    return $response;
});

$legacyRoutes = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $absolute = $fileInfo->getPathname();
    if (pathinfo($absolute, PATHINFO_EXTENSION) !== "php") {
        continue;
    }
    if (realpath($absolute) === __FILE__) {
        continue;
    }
    $relative = str_replace(__DIR__ . DIRECTORY_SEPARATOR, "", $absolute);
    $relative = str_replace(DIRECTORY_SEPARATOR, "/", $relative);
    $withExtension = "/" . ltrim($relative, "/");
    $legacyRoutes[$withExtension] = $absolute;
    $withoutExtension = _preg_replace('/\.php$/', "", $withExtension);
    if (is_string($withoutExtension) && $withoutExtension !== $withExtension) {
        $legacyRoutes[$withoutExtension] = $absolute;
    }
}

ksort($legacyRoutes);

foreach ($legacyRoutes as $route => $scriptPath) {
    $app->any($route, function (Request $_request, Response $response) use ($render, $scriptPath): Response {
        return $render($response, $scriptPath);
    });
}

$app->map(["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], "/{routes:.+}", function (
    Request $_request,
    Response $response,
): Response {
    $response->getBody()->write("Not Found");
    return $response->withStatus(404);
});

$app->run();
