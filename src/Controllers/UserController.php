<?php
namespace App\Controllers;

use App\APPGlobal;
use App\APPSettings;
use App\ResponseUtil;
use InteractivePlus\PDK2020CaptchaCore\CaptchaRepository;
use InteractivePlus\PDK2020CaptchaCore\Implementions\Storage\CaptchaInfoStorageMySQLImpl;
use InteractivePlus\PDK2020Core\Exceptions\PDKException;
use InteractivePlus\PDK2020Core\Formats\UserFormat;
use InteractivePlus\PDK2020Core\Logs\LogLevel;
use InteractivePlus\PDK2020Core\User\Token;
use InteractivePlus\PDK2020Core\User\User;
use InteractivePlus\PDK2020Core\Utils\UserPhoneNumUtil;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class UserController{
    public function getToken(Request $request, Response $response) : Response{
        $currentOperationActionID = 10001;

        $getParams = $request->getQueryParams();
        $REQ_ACCOUNTTYPE = $getParams['accountType'];
        $REQ_ACCOUNT = $getParams['account'];
        $REQ_PASSWORD = $getParams['password'];
        $REQ_CAPTCHAPHRASE = $getParams['captchaPhrase'];
        $REQ_AREA = $getParams['area'];
        $REQ_LOCALE = $getParams['locale'];
        
        $UserObj = NULL;
        switch($REQ_ACCOUNTTYPE){
            case 0:
                //Username mode
                if(UserFormat::verifyUsername($REQ_ACCOUNT)){
                    return ResponseUtil::credentialNotFormattedReponse('account',$response);
                }
                try{
                    $UserObj = User::fromUsername(APPGlobal::getDatabase(),$REQ_ACCOUNT);
                }catch(PDKException $e){
                    //10001 => User non-existant
                    return ResponseUtil::itemNotFoundResponse('account',$response);
                }
            break;
            case 1:
                //Email
                if(UserFormat::verifyEmail($REQ_ACCOUNT)){
                    return ResponseUtil::credentialNotFormattedReponse('account',$response);
                }
                try{
                    $UserObj = User::fromEmail(APPGlobal::getDatabase(),$REQ_ACCOUNT);
                }catch(PDKException $e){
                    return ResponseUtil::itemNotFoundResponse('account',$response);
                }
            break;
            case 2:
                //Phone
                $phoneNumObj = NULL;
                try{
                    $phoneNumObj = UserPhoneNumUtil::parsePhone($REQ_ACCOUNT,$REQ_AREA);
                }catch(PDKException $e){
                    //30002, cannot parse phone
                    return ResponseUtil::credentialNotFormattedReponse('account',$response);
                }
                try{
                    $UserObj = User::fromPhoneObj(APPGlobal::getDatabase(),$phoneNumObj);
                }catch(PDKException $e){
                    return ResponseUtil::itemNotFoundResponse('account',$response);
                }
            break;
            default:
                return ResponseUtil::credentialNotFormattedReponse('accountType',$response);
        }
        
        if($UserObj === NULL){
            APPGlobal::getLogger()->addLogItem(
                $currentOperationActionID,
                0,
                LogLevel::CRITICAL,
                false,
                0,
                $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                'Unexpected NULL User Object after initialization',
                array(
                    'method' => $REQ_ACCOUNTTYPE,
                    'BrowserUA' => $request->getHeader('User-Agent')
                )
            );
            return ResponseUtil::internalErrorResponse($response,new PDKException(51000,'Unexpected Null User Object after initialization'));
        }
        //check password format
        if(UserFormat::verifyPassword($REQ_PASSWORD)){
            return ResponseUtil::credentialNotFormattedReponse('password',$response);
        }
        
        //check captcha match
        $captchaRepo = new CaptchaRepository(new CaptchaInfoStorageMySQLImpl(APPGlobal::getDatabase()));
        if(!$captchaRepo->checkCaptchaPhrase($REQ_CAPTCHAPHRASE,$currentOperationActionID,$request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME))){
            return ResponseUtil::credentialIncorrectResponse('captchaPhrase',$response);
        }

        //check password match
        if(!$UserObj->checkPassword($REQ_PASSWORD)){
            return ResponseUtil::credentialIncorrectResponse('password',$response);
        }

        //check if the user is an active user
        if(!$UserObj->isValid()){
            if(!$UserObj->isFormalUser()){
                $notVerifiedContact = '';
                if(!empty($UserObj->getEmail())){
                    $notVerifiedContact = 'email';
                }else{
                    $notVerifiedContact = 'phone';
                }
                return ResponseUtil::accountNotVerifiedResponse($notVerifiedContact,$response);
            }else{
                return ResponseUtil::accountFrozenResponse($response);
            }
        }

        //create new token
        $tokenObj = Token::createToken(
            APPGlobal::getDatabase(),
            $UserObj,
            $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME)
        );

        //save token OBJ to DB
        $tokenObj->saveToDatabase();

        //retrun token info
        $returnArr = array(
            'errCode' => 0,
            'errMessage' => 'Token Successfully Allocated',
            'tokenInfo' => array(
                'token' => $tokenObj->getTokenString(),
                'refresh_token' => $tokenObj->getRefreshTokenString(),
                'expires' => $tokenObj->expireTime,
                'refresh_expires' => $tokenObj->refresh_expire_time
            )
        );

        $logContext = array(
            'uid' => $UserObj->getUID(),
            'method' => $REQ_ACCOUNTTYPE,
            'token' => $tokenObj->getTokenString(),
            'BrowserUA' => $request->getHeader('User-Agent')
        );
        APPGlobal::getLogger()->addLogItem(
            $currentOperationActionID,
            0,
            LogLevel::INFO,
            true,
            0,
            $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
            'Token Allocated',
            $logContext
        );
        
        $response->getBody()->write(json_encode($returnArr));
        return $response->withStatus(201);
    }
}