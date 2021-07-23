<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once 'lib/phpqrcode/qrlib.php';
$res = 0;
if (! $res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
// Security check
$result=restrictedArea($user,'billing',0,'','','','');

$actual_host= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// $actual_host= "http://192.168.200.140";
QRcode::png($actual_host . DOL_URL_ROOT . "/custom/swisspayments/mobilescan.php", false, 8, 8);

