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
use InteractivePlus\PDK2020Core\VerificationCodes\VeriCode;
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
                'refresh_expires' => $tokenObj->refresh_expire_time,
                'uid' => $UserObj->getUID()
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
    public function createUser(Request $request, Response $response) : Response{
        $currentOperationActionID = 10002;

        $getParams = $request->getQueryParams();
        $postParams = $request->getParsedBody();//json_decode($request->getBody(),true);

        $REQ_ACCOUNTTYPE = $postParams['accountType'];
        $REQ_ACCOUNT = $postParams['account'];
        $REQ_USERNAME = $postParams['username'];
        $REQ_DISPLAYNAME = $postParams['displayName'];
        $REQ_PASSWORD = $postParams['password'];
        $REQ_CAPTCHAPHRASE = $postParams['captchaPhrase'];

        $REQ_AREA = $getParams['area'];
        $REQ_LOCALE = $getParams['locale'];

        $email = NULL;
        $phoneObj = NULL;
        if($REQ_ACCOUNTTYPE === 1){
            $email = $REQ_ACCOUNT;
            if(!UserFormat::verifyEmail($email)){
                return ResponseUtil::credentialNotFormattedReponse('account',$response);
            }
        }else if($REQ_ACCOUNTTYPE === 2){
            try{
                $phoneObj = UserPhoneNumUtil::parsePhone($REQ_ACCOUNT,$REQ_AREA);
            }catch(PDKException $e){
                return ResponseUtil::credentialNotFormattedReponse('account',$response);
            }
        }else{
            return ResponseUtil::credentialNotFormattedReponse('accountType',$response);
        }
        if(!UserFormat::verifyUsername($REQ_USERNAME)){
            return ResponseUtil::credentialNotFormattedReponse('username',$response);
        }
        if(!UserFormat::verifyDisplayName($REQ_DISPLAYNAME)){
            return ResponseUtil::credentialNotFormattedReponse('displayName',$response);
        }
        if(!UserFormat::verifyPassword($REQ_PASSWORD)){
            return ResponseUtil::credentialNotFormattedReponse('password',$response);
        }
        $CaptchaRepo = new CaptchaRepository(new CaptchaInfoStorageMySQLImpl(APPGlobal::getDatabase()));
        if(!$CaptchaRepo->checkCaptchaPhrase($REQ_CAPTCHAPHRASE,$currentOperationActionID,$request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME))){
            return ResponseUtil::credentialIncorrectResponse('captchaPhrase',$response);
        }
        //Finish validating, let's register!
        try{
            $RegisteredUser = User::createUser(
                APPGlobal::getDatabase(),
                $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                $REQ_USERNAME,
                $REQ_PASSWORD,
                $REQ_DISPLAYNAME,
                $email,
                $phoneObj,
                $REQ_LOCALE,
                $REQ_AREA,
                false
            );
        }catch(PDKException $e){
            switch($e->getCode()){
                case 10004:
                    return ResponseUtil::itemAlreadyExistResponse('username',$response);
                break;
                case 10007:
                    return ResponseUtil::itemAlreadyExistResponse('displayName',$response);
                break;
                case 10005:
                    //Email already exist
                    return ResponseUtil::itemAlreadyExistResponse('account',$response);
                break;
                case 10006:
                    //Phone already exist
                    return ResponseUtil::itemAlreadyExistResponse('account',$response);
                break;
            }
        }
        $RegisteredUser->saveToDatabase();
        //user successfully registered, let's send out verification email / sms
        $VeriCode = NULL;
        if($email !== NULL){
            $VeriCode = VeriCode::createNewCode(
                APPGlobal::getDatabase(),
                $RegisteredUser,
                10001,
                array(),
                $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME)
            );
            $sender = APPGlobal::getVericodeEmailSender();
            $sender->sendVerificationCode($VeriCode,$REQ_LOCALE);
        }else{
            $VeriCode = VeriCode::createNewCode(
                APPGlobal::getDatabase(),
                $RegisteredUser,
                10002,
                array(),
                $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME)
            );
            $sender = APPGlobal::getVericodeSMSSender();
            if($phoneObj === NULL){
                APPGlobal::getLogger()->addLogItem(
                    $currentOperationActionID,
                    0,
                    LogLevel::WARNING,
                    true,
                    0,
                    $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
                    'Unexpected NULL phoneOBJ after registering and trying to send vericode'
                );
                return ResponseUtil::internalErrorResponse($response,new PDKException(51000,'Unexpected NULL phoneOBJ after registering and trying to send vericode'));
            }
            $sender->sendVerificationCode($VeriCode,$phoneObj,$REQ_DISPLAYNAME);
        }
        $returnArr = array(
            'errCode' => 0,
            'errMessage' => 'User successfully created',
            'uid' => $RegisteredUser->getUID()
        );

        $logContext = array(
            'username' => $REQ_USERNAME,
            'uid' => $RegisteredUser->getUID(),
            'displayName' => $REQ_DISPLAYNAME,
            'BrowserUA' => $request->getHeader('User-Agent')
        );
        if(!empty($email)){
            $logContext['email'] = $email;
        }else{
            $logContext['phoneNum'] = $phoneObj;
        }

        APPGlobal::getLogger()->addLogItem(
            $currentOperationActionID,
            0,
            LogLevel::INFO,
            true,
            0,
            $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
            'User Successfully Created',
            $logContext
        );

        $response->getBody()->write(json_encode($returnArr));
        return $response->withStatus(401);
    }
    public function validateToken(Request $request, Response $response) : Response{
        $currentOperationActionID = 10003;

        $getParams = $request->getQueryParams();
        $REQ_TOKEN = $getParams['token'];
        
        $TokenObj = NULL;
        try{
            $TokenObj = Token::fromTokenID(APPGlobal::getDatabase(),$REQ_TOKEN);
        }catch(PDKException $e){
            return ResponseUtil::credentialIncorrectResponse('token',$response);
        }
        $ctime = time();
        if($TokenObj->expireTime <= $ctime){
            return ResponseUtil::credentialExpiredReponse('token',$response);
        }
        $returnArr = array(
            'errCode' => 0,
            'errMessage' => 'Token is valid',
            'expires' => $TokenObj->expireTime,
            'uid' => $TokenObj->getUID()
        );

        APPGlobal::getLogger()->addLogItem(
            $currentOperationActionID,
            0,
            LogLevel::INFO,
            true,
            0,
            $request->getAttribute(APPSettings::IP_ATTRIBUTE_NAME),
            'Token validation successful',
            array(
                'tokenID' => $REQ_TOKEN,
                'uid' => $TokenObj->getUID()
            )
        );

        $response->getBody()->write(json_encode($returnArr));
        return $response->withStatus(200);
    }
}