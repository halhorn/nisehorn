<?php
// ツイッターの便利関数群です。
class TwitTool
{
	// Replyか
	function isReply($text) {
		if(is_array($text))
			$text = $text["text"];
		return preg_match("/^\@[_a-zA-Z0-9]+/", $text);
	}
	
	// 自分へのReplyか
	function isReplyToMe($text) {
		global $username, $subusername;
		if(is_array($text))
			$text = $text["text"];
		
		if (preg_match("/^\@" . $username . "/", $text)){
			return true;
		}
		foreach($subusername as $sub){
			if(preg_match("/^\@" . $sub . "/", $text)){
				return true;
			}
		}
		return false;
	}
	
	function isMe($screen_name){
		global $username, $subusername;
		if (preg_match("/\@?" . $username . "/", $screen_name)){
			return true;
		}
		foreach($subusername as $sub){
			if(preg_match("/^\@?" . $sub . "/", $screen_name)){
				return true;
			}
		}
		return false;
	}
	
	// Tweetから "@hoge 本文" の本文部を返します。
	// Tweetが@で始まっていなければtext全体を返します。
	function getReplyText($text) {
		if(is_array($text))
			$text = $text["text"];
		if($this->isReply($text)) {
			$text = preg_replace("/^(\@[_a-zA-Z0-9]+ +)+/", "", $text);
		}
		return $text;
	}
	
	// Tweetから "@hoge 本文" のhogeの部分を返します。
	function getReplyTo($text) {
		if(is_array($text))
			$text = $text["text"];
		$replyTo = "";
		if(preg_match("/\@([_a-zA-Z0-9]+)/", $text, $matches))
		{
			$replyTo = $matches[1];
		}
		return $replyTo;
	}
	
	// 指定されたIDのTweetを返します。
	// 最初にDBからの取得を試み、失敗すればWEBから取得します。
	function getTweet($id) {
		global $db, $st;
		$ret = $db->getTweet($id);
		if(!$ret) {
			$s = $st->getStatusShow($id);
			$ret = array(
				"id" => $s["id"], 
				"screen_name" => $s["user"]["screen_name"],
				"name" => $s["user"]["name"],
				"text" => $s["text"],
				"in_reply_to_status_id" => $s["in_reply_to_status_id"],
				"created_at" => $s["created_at"]
			);
		}
		$ret["user"] = array("name" => $ret["name"], "screen_name" => $ret["screen_name"]);
		if(!$ret || !$ret["id"]) warn("TwitTool::getTweet : Tweet not exist. id=$id");
		return $ret;
	}
	
	// Twitterの時間表示をハッシュに変換します。UTCで返します。
	function timeTwitToHash($twitTime) {
		//Tue Dec 15 07:42:12 +0000 2009
		$mon = array("Jan" => "01", "Feb" => "02", "Mar" => "03", "Apr" => "04",
			"May" => "05", "Jun" => "06", "Jul" => "07", "Aug" => "08", 
			"Sep" => "09", "Oct" => "10", "Nov" => "11", "Dec" => "12");
		preg_match("/[a-zA-Z]+ ([a-zA-Z]+) ([0-9]+) ([0-9]+)\:([0-9]+)\:([0-9]+) .[0-9]+ ([0-9]+)/", $twitTime, $m);
		
		$ret = array(
			"year" => $m[6],
			"mon" => $mon[$m[1]],
			"day" => $m[2],
			"hour" => (int)$m[3],
			"min" => $m[4],
			"sec" => $m[5]
		);
		return $ret;
	}
	
	// Twitterの時間表示をUnixTimeに変換します。UTCで返します。
	function timeTwitToTime($twitTime) {
		$t = $this->timeTwitToHash($twitTime);
		
		return strtotime("$t[year]-$t[mon]-$t[day] $t[hour]:$t[min]:$t[sec]");
	}
	
	// Twitterの時間表示をDateTimeに渡せるものに変換します。JSTに変換して返します。
	function timeTwitToDB($twitTime) {
		$time = $this->timeTwitToTime($twitTime) + 9*60*60;
		
		return date("Y/m/d H:i:s", $time);
	}
	
	// UnixTimeをDB用の時間に変換します。
	function timeTimeToDB($time) {
		return date("Y/m/d H:i:s", $time);
	}
}
?>
