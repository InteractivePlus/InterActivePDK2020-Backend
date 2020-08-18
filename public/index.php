<?php
use App\APPGlobal;
use App\APPSettings;
use App\Controllers\CommonController;
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

//Register /Common/captcha request solver
$app->get('/Common/captcha', CommonController::class . ':getCaptcha');

$app->run();