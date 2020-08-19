<?php
namespace App;

use InteractivePlus\PDK2020Core\Implementions\EmailServiceProviderWithSMTP;
use InteractivePlus\PDK2020Core\Implementions\EmailVericodeSenderWithService;
use InteractivePlus\PDK2020Core\Implementions\Storages\LogStorageMySQLImpl;
use InteractivePlus\PDK2020Core\Interfaces\EmailVericodeSender;
use InteractivePlus\PDK2020Core\Interfaces\SMSVericodeSender;
use InteractivePlus\PDK2020Core\Logs\LogRepository;
use MysqliDb;

class APPGlobal{
    private static $_dbConn;
    private static $_logger;
    private static $_emailVericodeShooter;
    private static $_smsVericodeSender;
    public static function init() : void{
        self::$_dbConn = new MysqliDb(
            APPSettings::DBHost,
            APPSettings::DBUserName,
            APPSettings::DBPassword,
            APPSettings::DBDatabaseName,
            APPSettings::DBPort,
            APPSettings::DBCharset
        );
        self::$_dbConn->autoReconnect = true;
        self::$_logger = new LogRepository(
            new LogStorageMySQLImpl(
                self::$_dbConn
            )
        );
        //TODO: Setup Email shooter and SMS shooter
        self::$_emailVericodeShooter = new EmailVericodeSenderWithService(
            new EmailServiceProviderWithSMTP(
                APPSettings::SMTPHost,
                APPSettings::SMTPUsername,
                APPSettings::SMTPPassword,
                APPSettings::SMTPPort,
                APPSettings::SMTPSecure
            )
        );
    }
    public static function getDatabase() : MysqliDb{
        return self::$_dbConn;
    }
    public static function getLogger() : LogRepository{
        return self::$_logger;
    }
    public static function getVericodeEmailSender() : EmailVericodeSender{
        return self::$_emailVericodeShooter;
    }
    public static function getVericodeSMSSender() : SMSVericodeSender{
        return self::$_smsVericodeSender;
    }
}