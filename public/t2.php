<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/1/25
 * Time: 12:13
 */

//将时区设置为中国
date_default_timezone_set();
//var_dump (GetTimeZone());
//将时区设置为上海时区
ini_set('date.timezone','Asia/Shanghai');

$dateYmdH = date ( 'Y-m-d H:i:s' );

echo  strtotime ('-1 ');