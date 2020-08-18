<?php
namespace App;

use InteractivePlus\PDK2020Core\Exceptions\PDKException;
use Slim\Psr7\Response;

class ResponseUtil{
    public static function credentialIncorrectResponse(string $paramName, Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 10001,
            'errMessage' => 'Credential Incorrect',
            'errContext' => array(
                'credential' => $paramName
            )
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(401);
    }
    public static function credentialNotFormattedReponse(string $paramName, Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 10002,
            'errMessage' => 'Credential Format Incorrect',
            'errContext' => array(
                'credential' => $paramName
            )
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(400);
    }
    public static function credentialExpiredReponse(string $paramName, Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 10003,
            'errMessage' => 'Credential Expired',
            'errContext' => array(
                'credential' => $paramName
            )
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(401);
    }
    public static function itemNotFoundResponse(string $paramName, Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 20001,
            'errMessage' => 'Item Not Found',
            'errContext' => array(
                'item' => $paramName
            )
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(404);
    }
    public static function itemAlreadyExistResponse(string $paramName, Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 20002,
            'errMessage' => 'Item Already Exist',
            'errContext' => array(
                'item' => $paramName
            )
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(409);
    }
    public static function permissionDeniedResponse(Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 30001,
            'errMessage' => 'Permission Denied'
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(403);
    }
    public static function operationTooFrequentResponse(Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 90001,
            'errMessage' => 'Operation Too Frequent'
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(403);
    }
    public static function systemBusyResponse(Response &$response) : Response{
        $bodyArr = array(
            'errCode' => 90002,
            'errMessage' => 'System Busy'
        );
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(503);
    }
    public static function internalErrorResponse(Response &$response, ?PDKException $exception = NULL) : Response{
        $bodyArr = array(
            'errCode' => 90003,
            'errMessage' => 'Internal Error'
        );
        if($exception !== NULL){
            $bodyArr['pdkErrCode'] = $exception->getCode();
            $bodyArr['pdkErrDescription'] = $exception->getMessage();
            $bodyArr['pdkErrParam'] = $exception->getErrorParams();
        }
        $response->getBody()->write(json_encode($bodyArr));
        return $response->withStatus(500);
    }
}