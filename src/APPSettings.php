<?php
namespace App;
class APPSettings{
    const DBHost = 'localhost';
    const DBPort = 3306;
    const DBUserName = '';
    const DBPassword = '';
    const DBDatabaseName = '';
    const DBCharset = 'utf8';
    const PROXY_ENABLE = false;
    const PROXY_TRUSTED_LIST = NULL;
    const IP_ATTRIBUTE_NAME = 'ip_address';
    const PROXY_IP_HEADERS = array(
        'X-FORWARDED-FOR',
        'CF-Connecting-IP'
    );
    const CAPTCHA_DURATION = 60 * 3;
    const SMTPHost = '';
    const SMTPPort = 465;
    const SMTPSecure = 'ssl';
    const SMTPUsername = '';
    const SMTPPassword = '';
}