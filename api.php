<?php
/*******************************************************************************
 * nisebot ver.?
 * 
 * 設定はconfig.php と DB上に。
********************************************************************************/

// 実行ID(ログ用)
$execId = date("is");

// 自作ライブラリ読み込み
require_once("config.php");
require_once("lib/stwrapper.php");
require_once("lib/taskbase.php");
require_once("lib/nhdb.php");
require_once("lib/twittool.php");
require_once("lib/mecab.php");
require_once("task/bot.php");

// インスタンス作成
$db = new NHDB();
$st = new STWrapper();
$tool = new TwitTool();
$bot = new Bot();

$errorflag = false;

$cmd = "";
$text = "";
$name = "";
if ($_SERVER["argv"][1]){
	$cmd = $_SERVER["argv"][1];
	$text = $_SERVER["argv"][2];
}else{
	$cmd = $_REQUEST["cmd"];
	$text = $_REQUEST["text"];
}

// Tweet-Reply PairのDB表示。
switch ($cmd) {
case "anstrp":
	$tweet = array("text"=>$text, "user"=>array("name"=>$_REQUEST["name"]));
	$trp = new TRP();
	print $trp->getTweetReplyPair($tweet, true);
	break;
case "tweetlist":
	$trp = new TRP();
	$trp->printTweetList($text);
	break;
default:
	print "invalid request";
}

exit;

// グローバル関数 //////////////////////////////////////////////////////////////

// 警告します。
function warn($mes) {
	global $db,$execId;
	$db->addLog($mes, "Warn", $execId);
	print "<B>Warn : $mes</B><BR>\n";
}

// エラー表示して終了します。
function error($mes, $doreport = true) {
	global $db,$st,$execId, $errorflag;
	if(!$errorflag) {
		$errorflag = true;
		print "<H3>Error</H3>\n";
		$db->addLog($mes, "Error", $execId);
		$db->setConfig("exec_start_time", 0);
		if($doreport) $st->setUpdate("D halhorn $mes");
		info("Exec End (Error) |||||||||||||||");
	}else{
		print "<B>Error Loop!!!!!!</B>";
	}
	die($mes);
}

// インフォを表示します。
function info($mes) {
	global $db,$execId;
	print "Info : $mes<BR>\n";
	$db->addLog($mes, "Info", $execId);
}

// デバグ用のログ出力です。
function debug($var, $title = "") {
	global $db,$execId;
	if($title) $title .= "<BR>\n";
	$mes = "$title<PRE>" . print_r($var, true) . "</PRE>";
	print "Debug : $mes<BR>\n";
	$db->addLog($mes, "Debug", $execId);
}

// ログを表示します。
function showLog($date) {
	global $db;
	$db->showLog($date);
}
?>