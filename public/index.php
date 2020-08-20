<?php
use App\APPGlobal;
use App\APPSettings;
use App\Controllers\CommonController;
use App\Controllers\UserController;
use App\Middlewares\InternationalAPIMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

//Initiate AppGlobal static class
APPGlobal::init();

//Create Slim APP
$app = AppFactory::create();

//Set up Error Logger
$app->addErrorMiddleware(true,true,true,APPGlobal::getLogger());

//Set up IP Address Middleware
$app->add(new \RKA\Middleware\IpAddress(
    APPSettings::PROXY_ENABLE,
    APPSettings::PROXY_TRUSTED_LIST,
    APPSettings::IP_ATTRIBUTE_NAME,
    APPSettings::PROXY_IP_HEADERS
));

$IntlAPIMiddleware = new InternationalAPIMiddleware;

//Register /Common/captcha request solver
$app->get('/common/captcha', CommonController::class . ':getCaptcha');

//Register /interactiveLiveID/token request solver
$app->get('/interactiveLiveID/token',UserController::class . ':getToken')->add($IntlAPIMiddleware);
//Register /interactiveLiveID/user request solver
$app->post('/interactiveLiveID/user', UserController::class . ':createUser')->add($IntlAPIMiddleware);
//Register /interactiveLiveID/validationResult/token request solver
$app->get('/interactiveLiveID/validationResult/token',UserController::class . ':validateToken');
//Register refresh token request solver
$app->post('/interactiveLiveID/token',UserController::class . ':refreshToken');

$app->run();