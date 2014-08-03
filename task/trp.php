<?php
// Tweet-Reply Pair の処理を行うクラスです。
class TRP extends TaskBase
{
	var $except = array("にせほ", "にせほー", "よるほ", "よるほー");
	var $regists = array();
	var $mecab;
	
	// コンストラクタです。
	function TRP() {
		$this->setup();
		$this->mecab = new MeCab();
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
		$this->frTimeline = $this->st->getFriendsRepliesTimeline();
		
		//info("TRP:FriendsLoop");
		$this->friendsLoop();
		
		// DBへTRPを登録。
		info("TRP:Registing to DB...(".count($this->regists)."records)");
		$columns = array("tweet","reply", "tweet_screen_name", "reply_screen_name", "tweet_to", "created_at");
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
			$repliedText = $repliedTweet["text"];
			$tweetTo = "";
			$matches = array();
			if(preg_match("/\@([_0-9a-zA-Z]+) /", $repliedText, $matches)){
				$tweetTo = $matches[1];
			}
			$repliedText = preg_replace("/(\@[_0-9a-zA-Z]+ +)+/", "", $repliedTweet["text"]);
			$repliedText = $this->repliedConvert($repliedText);
			
			// Replyを求める
			$replyText = $this->tool->getReplyText($tweet);
			$replyText = $this->replyCnovert($replyText, $repliedTweet);
			if ($this->registTRPCond($repliedText, $replyText)) {
				$this->regists[] = array($repliedText, $replyText, $repliedTweet["user"]["screen_name"], $tweet["user"]["screen_name"], $tweetTo, $repliedTweet["created_at"]);
				info("RegistTRP : From $repliedTweet[screen_name]：$repliedTweet[text] \nTo "
					. $tweet["user"]["screen_name"] . "：$tweet[text]");
			}
		}
	}
	
	// Tweet-Reply Pairに基づいてReplyします。
	function tweetReplyPair($tweet, $myReplyMode = false) {
		// 自分のつぶやきには返信しない。
		if($this->tool->isMe($tweet["user"]["screen_name"])) return;
		
		if($this->db->getConfig("success_prev_last_id_friends",1) == 0){
			warn("TRP.tweetReplyPair: setUpdate interrupted because success_prev_last_id_friends == 0. target:".$tweet["text"]);
			return;
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
		
		// 「えっ」などの情報を含まない言葉への返信対処
		// 会話の流れのコンテキストを読む
		$where = "";
		if($myReplyMode && $tweet["in_reply_to_status_id_str"] && !$this->containNV($tweet["text"])){
			info("TRP.getTweetReplyPair: Conversation Context Mode. ($tweet[text])");
			$repliedTweet = $this->tool->getTweet($tweet["in_reply_to_status_id_str"]);
			$repliedText = preg_replace("/(\@[_0-9a-zA-Z]+ +)+/", "", $repliedTweet["text"]);
			$mecabdata = $this->mecab->parse($repliedText);
			foreach($mecabdata as $record){
				if($record["kind"] == "名詞" || $record["kind"] == "動詞"){
					$where = "tweet like '%".$record["word"]."%'";
					info("TRP:getTweetReplyPair: Context word：$record[word]");
					$texts = $this->getTRPsFromDB($tweet["text"], $tweet["user"]["name"], $myReplyMode, false, $where);
					break;
				}
			}
			if(!$texts){
				info("TRP:getTweetReplyPair: Context reading fault.");
			}
		}
		if(!$texts || $texts == array()){
			$texts = $this->getTRPsFromDB($tweet["text"], $tweet["user"]["name"], $myReplyMode);
		}
		
		if($myReplyMode && !$texts){
			$texts = $this->getTweetReplyMyReplyMode($tweet);
		}
        if ($texts)	{
            // 取得したもののうちランダムなものを返す。
            $rnd = array_rand($texts);
            return $texts[$rnd];
        }
        
        return false;
    }
    
    // 自分へのリプライの場合にがんばって返し言葉を見つけます。
    function getTweetReplyMyReplyMode($tweet){
		$text = preg_replace("/\[.*\]/", "", $tweet["text"]);
		$texts = $this->getTRPsFromDB($text, $tweet["user"]["name"], true, true);
        if($texts) return $texts;

	return false;
        
        // まだ見つからなければtweetを品詞分解
		$data = $this->mecab->parse($text);
        $words = array();
		foreach ($data as $record) {
			$word = $record["word"];
			if( $record["kind"] == "名詞" // 名詞
				&& (   $record["detail1"] == "一般"
					|| $record["detail1"] == "固有名詞"
					|| $record["detail1"] == "サ変接続"
					|| $record["detail1"] == "特殊"
				)
				&& mb_strlen($word,"UTF-8") != strlen($word) // 日本語
			) {
				$words[] = $word;
			}
		}
		usort($words, array($this, "lencmp"));
		//info($words, "getTRPMyReplyMode: 選ばれた名詞");
		foreach($words as $word){
			$texts = $this->getTRPsFromDB($word, $tweet["user"]["name"], true, true);
			if ($texts){
				//info("getTRPMyReplyMode: $word に返信します。(original: $tweet[text])");
				return $texts;
			}
		}
        return false;
    }
    
    // 文字が長い順にソートするための比較関数です。
    function lencmp($a, $b){
    	$alen = mb_strlen($a,"UTF-8");
    	$blen = mb_strlen($b,"UTF-8");
    	if($alen == $blen) return 0;
    	return ($alen > $blen) ? -1 : 1;
    }
    
    // DBからある単語に対するTRPの返しを取得します。
    function getTRPsFromDB($text, $tweetuser, $myReplyMode = false, $pmode = false, $addWhere = ""){
        if(!$text) return false;
        
        $targetText = $this->repliedConvert($text);
		
        if($targetText == "") return;
        if(in_array($targetText, $this->except)) return false;
		
		// DBから取得
		$targetText = addslashes($targetText);
		$where = "tweet like '$targetText'";
		if ($pmode) $where = "tweet like '%$targetText%'";
		if (!$myReplyMode) $where .= " and tweet_to like ''";
		if ($addWhere) $where .= " and " . $addWhere;
		$result = $this->db->select("tweet_reply_pair", $where, "reply");
		$textsTmp = $this->db->convertToArray($result, "reply");
		
        $texts = array();
		
        // 不適切なものを削る
        foreach($textsTmp as $text) {
            $text = $this->tweetConvert($text, $tweetuser);
            if((!preg_match("/あり/", $text) || $myReplyMode)
               && !$this->db->is_censored($replyText)
               && $text ) $texts[] = $text;
        }
        if(count($texts) && $texts[0]) return $texts;
        return false;
    }
	
	function printTweetList($text){
		$text = addslashes($text);
		$where = "tweet like '$text%'";
		//$result = $this->db->select("tweet_reply_pair", $where, "DISTINCT `tweet` ,CHAR_LENGTH(`tweet`) as `C_LEN`", "C_LEN");
		$result = $this->db->query_with_return("SELECT DISTINCT `tweet`, CHAR_LENGTH(`tweet`) as `C_LEN` FROM `tweet_reply_pair` WHERE `tweet` LIKE '$text%' ORDER BY `C_LEN` ASC LIMIT 100");
		$textsTmp = $this->db->convertToArray($result, "tweet");
		print implode(",",$textsTmp);
	}
	
	// TRPテーブルに登録する条件を返します。
	function registTRPCond($repliedText, $replyText) {
		return $repliedText && 
				$replyText && 
				!preg_match("/\@/", $repliedText) && 
				!preg_match("/\@/", $replyText) &&
				mb_strlen($repliedText, "UTF-8") < $this->db->getconfig("trp_tweet_len", 25) &&
				!$this->db->is_censored($replyText) &&
				($this->containsJapanese($repliedText) || $this->containsJapanese($replyText));
	}
	
	// Tweet-Reply Pairのキーの変換を行います。
	function repliedConvert($text) {
		$text = mb_convert_kana($text, "KVas", "UTF-8");
		$text = preg_replace("/…+/", "", $text);
		$text = preg_replace("/‥+/", "", $text);
		$text = preg_replace("/[0-9]+/", "*", $text);
		$text = preg_replace("/\?/", "？", $text);
		$text = preg_replace("/？？/", "？", $text);
		$text = preg_replace("/\[.*\]/", "", $text);
		$text = preg_replace("/\#[_0-9a-zA-Z]+/", "", $text);
		
		$text = preg_replace("/ +$/", "", $text);
		$text = preg_replace("/w+$/", "", $text);
		$text = preg_replace("/(。)+$/", "", $text);
		$text = preg_replace("/(☆)+$/", "", $text);
		$text = preg_replace("/(♪)+$/", "", $text);
		$text = preg_replace("/(！)+$/", "", $text);
		$text = preg_replace("/\!+$/", "", $text);
		$text = preg_replace("/(・)+$/", "", $text);
		$text = preg_replace("/(っ)+$/", "", $text);
		$text = preg_replace("/(ー)+$/", "", $text);
		$text = preg_replace("/(〜)+$/", "", $text);
		$text = preg_replace("/(ぁ)+$/", "", $text);
		
		$text = preg_replace("/^(\@[_0-9a-zA-Z]+ )+/", "", $text);
		$text = preg_replace("/^.(っ)?、/", "", $text);
		
		return $text;
	}
	
	function replyCnovert($text, $tweet) {
		$text = preg_replace("/\[.*\]/", "", $text);
		$text = preg_replace("/\#[_0-9a-zA-Z]+/", "", $text);
		$text = str_replace($tweet["user"]["name"], "太郎", $text);
		
		$text = preg_replace("/ +$/", "", $text);
		
		return $text;
	}
	
	function tweetConvert($text, $tweetuser) {
		$text = preg_replace("/\@/", "at ", $text);
		$text = str_replace("太郎", $tweetuser, $text);
		
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
	
	// 名詞か動詞を文章が含んでいるかを返します。
	function containNV($text){
		$data = $this->mecab->parse($tweet["text"]);
		$flag = false;
		foreach($data as $record){
			if($record["kind"] == "名詞" || $record["kind"] == "動詞"){
				$flag = true;
				break;
			}
		}
		return $flag;
	}
}
?>
