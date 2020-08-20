<?php
namespace App\Controllers;

use App\APPGlobal;
use App\APPSettings;
use App\ResponseUtil;
use InteractivePlus\PDK2020CaptchaCore\CaptchaRepository;
use InteractivePlus\PDK2020CaptchaCore\Implementions\Storages\CaptchaInfoStorageMySQLImpl;
use InteractivePlus\PDK2020Core\Apps\AppEntity;
use InteractivePlus\PDK2020Core\Exceptions\PDKException;
use InteractivePlus\PDK2020Core\Formats\APPFormat;
use InteractivePlus\PDK2020Core\Formats\OAuthFormat;
use InteractivePlus\PDK2020Core\Logs\LogLevel;
use InteractivePlus\PDK2020Core\OAuth\OAuthTokenPair;
use InteractivePlus\PDK2020Core\User\Token;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class CommonController{
    public function getCaptcha(Request $request, Response $response) : Response{
        $currentOperationActionID = 80001;
        $getParams = $request->getQueryParams();
        
        $REQ_ACTIONID = $getParams['actionID'];
        if($REQ_ACTIONID === NULL){
            return ResponseUtil::credentialNotFormattedReponse('actionID',$response);
        }
        $REQ_CLIENTID = $getParams['clientID'];
        $REQ_TOKEN = $getParams['token'];
        $REQ_ACCESS_TOKEN = $getParams['access_token'];
        $REQ_WIDTH = $getParams['width'];
        $REQ_HEIGHT = $getParams['height'];

        if(empty($REQ_WIDTH)){
            $REQ_WIDTH = 150;
        }
        if(empty($REQ_HEIGHT)){
            $REQ_HEIGHT = 40;
        }

        $needTokenAuth = false;
        if(empty($REQ_CLIENTID) || $REQ_CLIENTID === 0){
            //TODO: Check if the actionID needs tokenID

        }

        if($needTokenAuth){
            if(!Token::verifyToken($REQ_TOKEN)){
                return ResponseUtil::credentialNotFormattedReponse('token',$response);
            }
        }else if(!empty($REQ_CLIENTID) && $REQ_CLIENTID !== 0){
            if(OAuthFormat::verifyAccessToken($REQ_ACCESS_TOKEN)){
                return ResponseUtil::credentialNotFormattedReponse('access_token',$response);
            }
        }

        $OAuthApp = NULL;
        if(!empty($REQ_CLIENTID) && $REQ_CLIENTID !== 0){
            if(APPFormat::checkClientID($REQ_CLIENTID)){
                return ResponseUtil::credentialNotFormattedReponse('clientID',$response);
            }
            try{
                $OAuthApp = AppEntity::fromClientID(APPGlobal::getDatabase(),$REQ_CLIENTID);
            }catch(PDKException $e){
                //errCode must be 20001 => APP NON-existant
                return ResponseUtil::itemNotFoundResponse('clientID',$response);
            }
        }

        $User = NULL;
        if($needTokenAuth){
            //check if token is fine
            $TokenObj = NULL;
            try{
                $TokenObj = Token::fromTokenID(APPGlobal::getDatabase(),$REQ_TOKEN);
            }catch(PDKException $e){
                //errCode must be 70002 => Token non-existant
                return ResponseUtil::credentialIncorrectResponse('token',$response);
            }
            try{
                $User = $TokenObj->getUser();
            }catch(PDKException $e){
                //errCode must be 10001 => User non-existant
                APPGlobal::getLogger()->addLogItem(
                    $currentOperationActionID,
                    0,
                    LogLevel::WARNING,
                    false,
                    0,
                    $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                    'User UID search failed after a valid token is verified',
                    array(
                        'BrowserUA' => $request->getHeader('User-Agent')
                    )
                );
                return ResponseUtil::systemBusyResponse($response);
            }
        }else if($OAuthApp !== NULL){
            //check if 
            $OAuthAccessTokenObj = NULL;
            try{
                $OAuthAccessTokenObj = OAuthTokenPair::fromTokenID(APPGlobal::getDatabase(),$REQ_ACCESS_TOKEN);
            }catch(PDKException $e){
                //errCode must be 90004 => Access Token non-existant
                return ResponseUtil::credentialIncorrectResponse('access_token',$response);
            }
            try{
                $User = $OAuthAccessTokenObj->getUser();
            }catch(PDKException $e){
                //errCode must be 10001 => User non-existant
                APPGlobal::getLogger()->addLogItem(
                    $currentOperationActionID,
                    0,
                    LogLevel::WARNING,
                    false,
                    0,
                    $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                    'User UID search failed after a valid OAuth access token is verified',
                    array(
                        'BrowserUA' => $request->getHeader('User-Agent')
                    )
                );
                return ResponseUtil::systemBusyResponse($response);
            }
        }

        //we've checked everything, let's generate captcha!
        $captchaRepo = new CaptchaRepository(new CaptchaInfoStorageMySQLImpl(APPGlobal::getDatabase()));
        try{
            $generatedCaptcha = $captchaRepo->generateAndSaveCaptcha(
                APPSettings::CAPTCHA_DURATION,
                $REQ_ACTIONID,
                $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                5,
                NULL,
                $REQ_WIDTH,
                $REQ_HEIGHT
            );
        }catch(PDKException $exception){
            return ResponseUtil::internalErrorResponse($response,$exception);
        }

        $responseArr = array(
            'errCode' => 0,
            'errMessage' => 'Captcha Successfully Generated',
            'captchaInfo' => array(
                'captchaWidth' => $generatedCaptcha->width,
                'captchaHeight' => $generatedCaptcha->height,
                'captchaData' => base64_encode($generatedCaptcha->jpegData),
                'expires' => $generatedCaptcha->expires
            )
        );

        APPGlobal::getLogger()->addLogItem(
            $currentOperationActionID,
            $OAuthApp === NULL ? 0 : $OAuthApp->getAppUID(),
            LogLevel::INFO,
            true,
            0,
            $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
            'Captcha Allocated',
            array(
                'uid' => $User === NULL ? 0 : $User->getUID(),
                'appuid' => $OAuthApp === NULL ? 0 : $OAuthApp->getAppUID(),
                'captchaPhrase' => $generatedCaptcha->phrase,
                'BrowserUA' => $request->getHeader('User-Agent')
            )
        );
        
        $response->getBody()->write(json_encode($responseArr));
        return $response->withStatus(201);
    }
}