<?php
// Tweet-Reply Pair の処理を行うクラスです。(英語版)
class TRP extends TaskBase
{
	var $except = array("aaaaaaaaaaaaaa");
	var $regists = array();
	
	// コンストラクタです。
	function TRP() {
		$this->setup();
	}
	
	// 実行します。
	function execute()
	{
		if(!$this->db->taskEnabled("trp")) {
			info("TRP::execute : trp disable.");
			return;
		}
		
		//$this->updateTRPTable();
		//return;
		info("TRP start.");
		$this->fTimeline = $this->st->getFriendsTimeline();
		$this->pTimeline = $this->st->getPublicTimeline();
		
		//info("TRP:FriendsLoop");
		$this->friendsLoop();
		//info("TRP:PublicLoop");
		$this->publicLoop();
		
		// DBへTRPを登録。
		info("TRP:Registing to DB...(".count($this->regists)."records)");
		$columns = array("tweet","reply", "tweet_screen_name", "reply_screen_name","created_at");
		$this->db->insert_items("tweet_reply_pair", $columns, $this->regists);
		//info("TRP end.");
	}

	// friendsTimeline からの処理です。
	function friendsLoop() {
		foreach ($this->fTimeline as $tweet) {
			if($this->tool->isReplyToMe($tweet)) { // 自分へのリプライであったとき
				
				// お気に入り度を１追加。
				$this->db->addFavor($tweet["user"]["screen_name"], 1);
				
				$this->tweetReplyPair($tweet, true);
				
			} else if ($this->tool->isReply($tweet)) { // 誰かへのリプライであったとき
				$this->registTweetReplyPair($tweet);
				
			} else {
				$this->tweetReplyPair($tweet);
			}
		}
	}
	
	// publicTimelineからの処理です。
	function publicLoop() {
		foreach($this->pTimeline as $tweet) {
			if ($this->tool->isReply($tweet)) {
				$this->registTweetReplyPair($tweet);
			}
		}
	}
	
	// DBを更新します（メンテナンス用）
	function updateTRPTable() {
		$trp = $this->db->select("tweet_reply_pair");
		$this->db->del("tweet_reply_pair", "1");
		
		$i = 0;
		foreach ($trp as $record) {
			if ($this->registTRPCond($record["tweet"], $record["reply"])) {
				$record["tweet"] = $this->tweetReplyConvert($record["tweet"]);
				$record["id"] = $i + 1;
				$this->db->insert("tweet_reply_pair", $record);
				$i++;
			}
		}
		info("TRP Table Updated. $i Records.");
	}
	
	// DBに登録するTweet-Reply Pairを収集します。
	function registTweetReplyPair ($tweet) {
		$dbf = $this->db->select("follower", "id =" . $tweet["user"]["id_str"]);
		if(!count($dbf)) return;
		
		if($tweet["in_reply_to_status_id"] && 
		   !$this->tool->isMe($tweet["user"]["screen_name"]) &&
		   !$dbf[0]["stop_learning"]) {
			
			// Tweet を求める
			$repliedTweet = $this->tool->getTweet($tweet["in_reply_to_status_id_str"]);
			$repliedText = preg_replace("/(\@[_0-9a-zA-Z]+ +)+/", "", $repliedTweet["text"]);
			$repliedText = $this->repliedConvert($repliedText);
			
			// Replyを求める
			$replyText = $this->tool->getReplyText($tweet);
			$replyText = $this->replyCnovert($replyText, $repliedTweet);
			if ($this->registTRPCond($repliedText, $replyText)) {
				$this->regists[] = array($repliedText, $replyText, $repliedTweet["user"]["screen_name"], $tweet["user"]["screen_name"], $repliedTweet["created_at"]);
				info("RegistTRP : From $repliedTweet[screen_name]：$repliedTweet[text] \nTo "
					. $tweet["user"]["screen_name"] . "：$tweet[text]\nTime:$repliedTweet[created_at]");
			}
		}
	}
	
	// Tweet-Reply Pairに基づいてReplyします。
	function tweetReplyPair($tweet, $myReplyMode = false) {
		if($this->tool->isMe($tweet["user"]["screen_name"])) return;

		if($this->db->getConfig("success_prev_last_id_friends",1) == 0){
			warn("TRP.tweetReplyPair: setUpdate interrupted because success_prev_last_id_friends == 0. target:".$tweet["text"]);
			//return;
		}
		if (count($this->db->select("follower", "id =" . $tweet["user"]["id_str"]))) {
            if($text = $this->getTweetReplyPair($tweet, $myReplyMode)) {
                $replyText = "@" . $tweet["user"]["screen_name"] . " " . $text;
                
                $this->st->setUpdate($replyText, $tweet["id_str"]);
                
                info("書き込み：" . $replyText);
            }
        }
	}

    // Tweet-Reply Pairに基づいて返信すべきテキストを返します。
	function getTweetReplyPair($tweet, $myReplyMode = false) {
        if(!$tweet["text"]) return false;
        
        $targetText = $this->repliedConvert($tweet["text"]);
		
        if($targetText == "") return;
        if(in_array($targetText, $this->except)) return false;
		if(mb_strlen($targetText,"UTF-8") >= $this->db->getconfig("trp_tweet_len", 25)) return false;
		// DBから取得
		$targetText = addslashes($targetText);
		$where = "tweet like '$targetText'";
		$result = $this->db->select("tweet_reply_pair", $where, "reply");
		$textsTmp = $this->db->convertToArray($result, "reply");
        $texts = array();
		
        // 不適切なものを削る
        foreach($textsTmp as $text) {
            $text = $this->tweetConvert($text, $tweet);
            if((!preg_match("/あり/", $text) || $myReplyMode)
               && $text ) $texts[] = $text;
        }
        if (count($texts) && $texts[0])	{
            // 取得したもののうちランダムなものを返す。
            $rnd = array_rand($texts);
            return $texts[$rnd];
        }
        return false;
    }
	
	// TRPテーブルに登録する条件を返します。
	function registTRPCond($repliedText, $replyText) {
		return $repliedText && 
				$replyText && 
				!preg_match("/\@/", $repliedText) && 
				!preg_match("/\@/", $replyText) &&
				mb_strlen($repliedText, "UTF-8") < $this->db->getconfig("trp_tweet_len", 25) &&
				!$this->containsJapanese($repliedText) &&
				!$this->containsJapanese($replyText);
	}
	
	// Tweet-Reply Pairのキーの変換を行います。
	function repliedConvert($text) {
		$text = preg_replace("/\?\?/", "?", $text);
		$text = preg_replace("/\[.*\]/", "", $text);
		$text = preg_replace("/\#[_0-9a-zA-Z]+/", "", $text);
		$text = preg_replace("/\s*(\:|\;)\-?(\)|\(|D|P|\/)\s*/", "", $text); // 顔文字除去
		$text = preg_replace("/\s+$/", "", $text);
		$text = preg_replace("/\s+lol$/", "", $text);
		$text = preg_replace("/(\.)+$/", "", $text);
		$text = preg_replace("/\!+$/", "", $text);
		
		$text = preg_replace("/^(\@[_0-9a-zA-Z]+ )+/", "", $text);
		
		return $text;
	}
	
	function replyCnovert($text, $tweet) {
		$text = preg_replace("/\[.*\]/", "", $text);
		$text = preg_replace("/\#[_0-9a-zA-Z]+/", "", $text);
		$text = str_replace($tweet["user"]["name"], "Hans", $text);
		
		$text = preg_replace("/ +$/", "", $text);
		
		return $text;
	}
	
	function tweetConvert($text, $tweet) {
		$text = preg_replace("/\@/", "at ", $text);
		$text = str_replace("Hans", $tweet["user"]["name"], $text);
		
		return $text;
	}
	
	function containsJapanese($text) {
		return strlen($text) != mb_strlen($text,"UTF-8");
		/*
		return
			preg_match("/(?:\xA4[\xA1-\xF3])/", $text) ||
			preg_match("/(?:\x82[\x9F-\xF1])/", $text) ||
			preg_match("/(?:\xA5[\xA1-\xF6])/", $text) ||
			preg_match("/(?:\x83[\x40-\x96]|\x81[\x45\x5B\x52\x53])/", $text);
		*/
	}
}
?>
