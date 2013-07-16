#!/usr/local/bin/php
<?php
/*******************************************************************************
 * words.php
 * 
 * DB上にあるデータをJuliusに与えるためのテキストデータに変換するプログラム。
********************************************************************************/

// 外部ライブラリ読み込み
require_once("DB.php");

// 自作ライブラリ読み込み
require_once("config.php");
require_once("lib/nhdb.php");

$db = new NHDB();

if(!$db->taskEnabled("all") && !preg_match("/^show.*/", $_REQUEST["cmd"])) {
	info("root : all disable.");
	exit;
}

$tweets = $db->select("friends_tweet", "", "text");
$trps = $db->select("tweet_reply_pair", "", "tweet");

foreach($tweets as $tweet) {
	$text = $tweet["text"];
	print $text . "\n";
}

foreach($trps as $trp) {
	$tweet = $trp["tweet"];
	print $tweet . "\n";
}

exit;
?>
