<?php
namespace App;

use InteractivePlus\PDK2020Core\Implementions\Storages\LogStorageMySQLImpl;
use InteractivePlus\PDK2020Core\Logs\LogRepository;
use MysqliDb;

class APPGlobal{
    private static $_dbConn;
    private static $_logger;
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
    }
    public static function getDatabase() : MysqliDb{
        return self::$_dbConn;
    }
    public static function getLogger() : LogRepository{
        return self::$_logger;
    }
}