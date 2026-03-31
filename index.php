<?php
//----------------------------------
// hoshibbs by sakots
// 2ch風掲示板 https://hoshibbs.sakots.net/
//----------------------------------

//スクリプトのバージョン
const HOSHI_VER = 'v0.0.0'; //lot.260331.0

//言語判定
$lang = ($http_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
  ? explode( ',', $http_langs )[0] : '';
$en = (stripos($lang,'ja')!== 0);

// phpのバージョンが古い場合動かさせない
if (($php_ver = phpversion()) < "7.3.0") {
	die($en ? "PHP version 7.3 or higher is required for this program to work. <br>\n(Current PHP version:{$php_ver})" : "PHPバージョン7.3以上が必要です。 <br>\n(現在のPHPバージョン:{$php_ver})");
}

// 設定ファイルの読み込み
require_once('./config.php');
// コンフィグのバージョンが古くて互換性がない場合動かさせない
if (CONF_VER < 20251112 || !defined('CONF_VER')) {
	die($en ? "The configuration file is incompatible. Please reconfigure it." : "コンフィグファイルに互換性がないようです。再設定をお願いします。<br>\n The configuration file is incompatible. Please reconfigure it.");
}

// BladeOneの読み込み v4.19.1
require_once __DIR__."/vendor/autoload.php";
use eftec\bladeone\BladeOne;

// 読み込みを行いたいテンプレートパス記述
$views = __DIR__.'/views';
$cache = __DIR__.'/cache';
$blade = new BladeOne($views,$cache,BladeOne::MODE_AUTO);
