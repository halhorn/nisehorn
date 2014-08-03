<?php
// 最近のツイートのトレンドから勝手につぶやく機能です。
class RecentTweet extends TaskBase
{
	var $mecab;
	
	// コンストラクタです。
	function RecentTweet() {
		$this->setup();
		$this->mecab = new MeCab();
	}
	
	// 実行します。
	function execute()
	{
		if(!$this->db->taskEnabled("recenttweet")) {
			info("RecentTweet::execute : recenttweet disable.");
			return;
		}
		info("RecentTweet start.");
		
		$this->fTimeline = $this->st->getFriendsTimeline();
		
		$theme = $this->getTheme();
		if($theme) $this->tweetFromDB($theme);
	}
	
	// friendsTimeline から対象にする話題を取ってきます。
	function getTheme() {
		global $username;
		
		info("RecentTweet:getTheme start");
		$last = $this->db->getConfig("recenttweet_last","ボット");
		$contRate = $this->db->getConfig("recenttweet_continuerate",3);
		$max = 0;
		$timeline = $this->fTimeline;
		
		// 初期傾斜
		$words = array(); 
		
		// 前回の会話を混ぜることで前回と同じテーマや単語が出やすくする。
		for($i=0; $i < $contRate; $i++) {
			$timeline[] = array("text" => $last);
		}
		
		foreach ($timeline as $tweet) {
			$text = $this->tweetConvert($tweet["text"]);
			$data = $this->mecab->parse($text);
			
			foreach ($data as $record) {
				$word = $record["word"];
				if( $record["kind"] == "名詞" // 名詞
					&& (   $record["detail1"] == "一般"
						|| $record["detail1"] == "固有名詞"
						|| $record["detail1"] == "サ変接続"
						|| $record["detail1"] == "特殊"
					)
					&& mb_strlen($word,"UTF-8") >= $this->db->getConfig("recenttweet_theme_minlen", 3) // 最低長
					&& mb_strlen($word,"UTF-8") != strlen($word) // 日本語
					&& !$this->db->is_censored($word) // 禁止ワードでない
					&& $tweet["user"]["screen_name"] != $username // 自分でない
				) {
					if(!$words[$word]) $words[$word] = 0;
					$words[$word]++;
					$max = max($max, $words[$word]);
				}
			}
		}
		
		// 出現頻度配列の生成
		$tmp = array();
		$log = "";
		for ($i=0;$i < $this->db->getConfig("recenttweet_nonrep",15); $i++) $tmp[] = false;
		foreach ($words as $word => $num) {
			for ($i=0;$i < $num-1; $i++) $tmp[] = $word;
			
			// ログ
			if ($num > 1) {
				$log .= $word . "(" . $num . ")\t";
			}
		}
		info("RecentTweet: Popular Words : ".$log."  (last:$last)");
		
		return $tmp[array_rand($tmp)];
	}
	
	// 指定されたテーマに関することをつぶやきます。
	function tweetFromDB($theme) {
		global $username;
		
		info("RecentTweet:Theme->$theme");
		$result = $this->db->select("friends_tweet", "text like '%$theme%'");
		$texts = array();
		
		foreach ($result as $tweet) {
			$tweet["text"] = preg_replace("/ *\[.*\]/","",$tweet["text"]);
			$tweet["text"] = preg_replace("/ *\#[a-zA-Z0-9_]+/","",$tweet["text"]);
			
			if (strpos($tweet["text"], $theme) !== false
				&& mb_strlen($tweet["text"],"UTF-8") < $this->db->getConfig("recenttweet_maxtweetlen",30)
				&& !preg_match("/\@[0-9a-zA-Z_]+/",$tweet["text"]) 
				&& !preg_match("/(https?|ftp)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/" ,$tweet["text"])
				&& !$this->db->is_censored($tweet["text"])
				&& $tweet["screen_name"] != $username
			) {
				
				$texts[] = $tweet["text"];
			}
		}
		
		if (count($texts)) {
			$text = $texts[array_rand($texts)];
			$this->db->setConfig("recenttweet_last",$text);
			
			info("RecentTweet:書き込み $text");
			$this->st->setUpdate($text);
		} else {
			info("No tweet matches the theme '$theme'");
		}
	}
	
	// テーマとして抽出する際のつぶやきの変換です。
	function tweetConvert($text) {
		$text = preg_replace("/\@[0-9a-zA-Z_]+/", "", $text);
		$text = preg_replace("/(https?|ftp)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)/", "", $text);
		$text = preg_replace("/w(w+)/", "", $text);
		return $text;
	}
}
?>
