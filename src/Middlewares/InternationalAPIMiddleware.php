<?php
namespace App\Middlewares;

use InteractivePlus\PDK2020Core\Utils\IntlUtil;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class InternationalAPIMiddleware{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next) {
        $requestParams = $request->getQueryParams();
        $requestParams['area'] = IntlUtil::fixArea($requestParams['area']);
        $requestParams['locale'] = IntlUtil::fixLocale($requestParams['locale']);
        $processedResponse = $next($request->withQueryParams($requestParams),$response);
        return $processedResponse;
    }
}