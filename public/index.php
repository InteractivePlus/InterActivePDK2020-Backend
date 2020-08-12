<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->addErrorMiddleware(true,true,true);

require __DIR__ . '/../vendor/autoload.php';

$app->get('/', function (Request $request, Response $response, $args) {
    
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->run();