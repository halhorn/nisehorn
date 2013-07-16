<PRE>
<?php
require_once("../config.php");
require_once("./nhdb.php");
require_once("./twittool.php");
$db = new NHDB();
$tool = new TwitTool();
require_once("./stwrapper.php");

$st = new STWrapper();

//$st->setUpdate("投稿テスト [OAuthテスト]");
//$st->addFriend("_haltest");


$st->addFriend("_haltest");



##########################################################
// 警告します。
function warn($mes) {
	global $db;
	$db->addLog($mes, "Warn");
	print "<B>Warn : $mes</B><BR>\n";
}

// エラー表示して終了します。
function error($mes) {
	global $db;
	print "<H3>Error</H3>\n";
	$db->addLog($mes, "Error");
	die($mes);
}

// インフォを表示します。
function info($mes) {
	global $db;
	print "Info : $mes<BR>\n";
	$db->addLog($mes, "Info");
}

// デバグ用のログ出力です。
function debug($var) {
	global $db;
	$mes = "<PRE>" . print_r($var, true) . "</PRE>";
	print "Debug : $mes<BR>\n";
	$db->addLog($mes, "Debug");
}

// ログを表示します。
function showLog($date) {
	global $db;
	$db->showLog($date);
}

?>
</PRE>