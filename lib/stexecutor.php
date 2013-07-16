#!/usr/local/bin/php
<?php
/*******************************************************************************
 * マルチスレッドでTwitterへの操作をする為の実行スクリプト
 * 
 * 設定はconfig.php と DB上に。
********************************************************************************/

// 外部ライブラリ読み込み
require_once("Services/Twitter.php");
require_once("DB.php");

// 自作ライブラリ読み込み
require_once("config.php");
require_once("lib/stwrapper.php");
require_once("lib/twittool.php");
require_once("lib/nhdb.php");

// インスタンス作成
$_st =& new Services_Twitter($username, $password);
$db = new NHDB();
$tool = new TwitTool();

$cpass = $_REQUEST["cpass"];
$cmd = $_REQUEST["cmd"];
$text = $_REQUEST["text"];
$screen_name = $_REQUEST["screen_name"];
$in_reply_to = $_REQUEST["in_reply_to"];

// 認証
if(crypt($password) != $cpass) {
	error("STExecutor : Invalid Password!!");
	exit(1);
}

// 実行
switch ($cmd) {
case "setupdate":
	info("SetUpdate start : $text");
	setUpdate($text,$in_reply_to);
	info("SetUpdate end : $text");
	break;

case "addfriend":
	info("addFriend start : $screen_name");
	addFriend($screen_name);
	info("addFriend end : $screen_name");
	break;
}

exit;

// グローバル関数 //////////////////////////////////////////////////////////////
function setUpdate($text, $in_reply_to = false) {
	global $_st;
	if($in_reply_to){
		return $_st->setUpdate(array("status"=>$text,"in_reply_to_status_id"=>$in_reply_to));
	}else{
		return $_st->setUpdate($text);
	}
}

// addFriend($screen_name) を実行します。
function addFriend($screen_name) {
	return $_st->addFriend($screen_name);
}

// 警告します。
function warn($mes) {
	global $db;
	$db->addLog($mes, "Warn");
	print "<B>Warn : $mes</B><BR>\n";
}

// エラー表示して終了します。
function error($mes) {
	global $db;
	$db->addLog($mes, "Error");
	print "<H3>Error</H3>\n$mes";
	die($mes);
}

// インフォを表示します。
function info($mes) {
	global $db;
	$db->addLog($mes, "Info");
	print "Info : $mes<BR>\n";
}

// デバグ用のログ出力です。
function debug($var) {
	global $db;
	$mes = "<PRE>" . print_r($var, true) . "</PRE>";
	$db->addLog($mes, "Debug");
	print "Debug : $mes<BR>\n";
}

// ログを表示します。
function showLog($date) {
	global $db;
	$db->showLog($date);
}
?>