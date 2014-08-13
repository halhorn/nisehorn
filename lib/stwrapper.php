<?php
require_once("twitteroauth.php");

// TwitterAPIのラッパークラスです。
class STWrapper
{
	var $api;
	var $subapi = array();
	var $nisehoariapi;
	var $db;
	var $friendsTimeline = null;
	var $friendsReplyTimeline = null;
	var $friendsTimelineDone = False;
	var $friends = null;
	var $replies = null;
	var $repliesDone = False;
	var $followers = null;
	var $subapinum;
	var $kiseinum = -1;
	
	function STWrapper() {
		global $contoken, $consecret, $acstoken, $acssecret, $nisehoaritoken, $nisehoarisecret, $subacstoken, $subacssecret, $db;
		
		$this->api = new TwitterOAuth($contoken, $consecret, $acstoken, $acssecret);
		$this->nisehoariapi = new TwitterOAuth($contoken, $consecret, $nisehoaritoken, $nisehoarisecret);
		
		$this->subapinum = count($subacstoken);
		
		for($i=0; $i < $this->subapinum; $i++){
			array_push($this->subapi, new TwitterOAuth($contoken, $consecret, $subacstoken[$i], $subacssecret[$i]));
		}
		$this->db = $db;
	}
	
	// friendsTimelineのシングルトンインスタンスを返します。
	function getFriendsTimeline() {
		
		// もしキャッシュがあればそれを返す。
		if($this->friendsTimelineDone) return $this->friendsTimeline;
		
		$last_id = $this->db->getConfig("last_id_friends", 0);
		$this->db->setConfig("success_prev_last_id_friends", $this->db->getConfig("success_last_id_friends",1));
		
		$this->friendsTimeline = $this->get(
			"https://api.twitter.com/1.1/statuses/home_timeline.json",
			array("since_id" => $last_id, "count"=>200)
		);
		
		$this->friendsTimeline = $this->convertTimeline($this->friendsTimeline, $last_id);
		
		info("getFT end (" . count($this->friendsTimeline) . " records.)");
		if(count($this->friendsTimeline) > 190) warn("STWrapper::getFriendsTimeline : Over 190 post.");
		
		if($this->friendsTimeline){
			$lastid = $this->friendsTimeline[count($this->friendsTimeline) - 1]["id_str"];
			if($lastid){
				$this->db->setConfig("last_id_friends", $lastid);
				$this->db->setConfig("success_last_id_friends",1);
			}else{
				$this->db->setConfig("success_last_id_friends",0);
				warn("stwrapper.getFriendsTimeline: No Last ID!!!");
				debug($this->friendsTimeline, "stwrapper.getFriendsTimeline: When no last id, timeline");
				$this->setUpdate("@halhorn last_id取得できなかったァ！ [暴走の可能性が有るためお眠りします]");
			}
		}
		$this->friendsTimelineDone = True;
		return $this->friendsTimeline;
	}

	// mentionsのシングルトンインスタンスを返します。
	function getReplies() {
		
		// キャッシュがある場合それを返す。
		if($this->repliesDone) return $this->replies;
		
		$last_id = $this->db->getConfig("last_id_reply", 0);
		
		$result = $this->get(
			"https://api.twitter.com/1.1/statuses/mentions_timeline.json",
			array("count" => 200)
		);
		// 臨時
		$this->replies = array();
		foreach($result as $tweet){
			if($tweet["id_str"] > $last_id) $this->replies[] = $tweet;
		}
		
		$this->replies = $this->convertTimeline($this->replies, $last_id);
		info("getReplies end (" . count($this->replies) . " records.)");
		if(count($this->replies) > 190) warn("STWrapper::getReplies : Over 190 post.");

		$lastid = $this->replies[count($this->replies) - 1]["id_str"];
		if($lastid){
			$this->db->setConfig("last_id_reply", $lastid);
		}
		$this->repliesDone = True;
		return $this->replies;
	}
	
	// FriendsTimeline, Mentions をマージしたTLを取得しDBに追加します。
	function getFriendsRepliesTimeline() {
		// キャッシュがある場合それを返す。
		if($this->friendsReplyTimeline) return $this->friendsReplyTimeline;
		
		$ft = $this->getFriendsTimeline();
		$rt = $this->getReplies();
		$maxf = count($ft);
		$maxr = count($rt);
		$f = 0;
		$r = 0;
		$this->friendsReplyTimeline = array();
		while ($f < $maxf || $r < $maxr){
			if($f >= $maxf){
				array_push($this->friendsReplyTimeline, $rt[$r]);
				$r++;
			}elseif($r >= $maxr){
				array_push($this->friendsReplyTimeline, $ft[$f]);
				$f++;
			}elseif($ft[$f] == $rt[$r]){
				$r++;
			}elseif(strtotime($ft[$f]["created_at"]) < strtotime($rt[$r]["created_at"])){
				array_push($this->friendsReplyTimeline, $ft[$f]);
				$f++;
			}else{
				array_push($this->friendsReplyTimeline, $rt[$r]);
				$r++;
			}
		}
		$this->db->addTweets("friends_tweet", $this->friendsReplyTimeline);
		info("getFriendsReplies end (" . count($this->friendsReplyTimeline) . " records.)");
		return $this->friendsReplyTimeline;
	}
	
	// friendsのシングルトンインスタンスを返します。
	function getFriends() {
		if(!$this->friends) {
			$this->friends = array();
	        $cursor = -1;
	        $i = 1;
			while($cursor) {
				$result = $this->get("https://api.twitter.com/1.1/friends/list.json",array("cursor"=>$cursor));
				if($result == array()){
					warn("stwrapper.getFriends: getFriends fault. $i th loop. ". count($this->friends) ." items in prev loops. requested cursor:$cursor");
					$this->friends = array();
					return $this->friends;
				}
				$cursor = $result["next_cursor_str"];
				$this->friends = array_merge($this->friends,$result["users"]);
				$i++;
			}
		}
         
		return $this->friends;
	}
	
	// followersのシングルトンインスタンスを返します。
	function getFollowers() {
		if(!$this->followers) {
			$this->followers = array();
	        $cursor = -1;
	        $i = 1;
			while($cursor) {
				$result = $this->get("https://api.twitter.com/1.1/followers/list.json",array("cursor"=>$cursor));
				if($result == array()){
					warn("stwrapper.getFollowers: getFollowers fault. $i th loop. ". count($this->friends) ." items in prev loops. requested cursor:$cursor");
					$this->followers = array();
					return $this->followers;
				}
				$cursor = $result["next_cursor_str"];
				$this->followers = array_merge($this->followers,$result["users"]);
				$i++;
			}
		}
         
		return $this->followers;
	}
	
	function getStatusShow($id) {
		return $this->get("https://api.twitter.com/1.1/statuses/show.json", array("id" => $id));
	}
	
	function getUserShow($arg) {
		$opt = array("screen_name"=>$arg);
		if(is_array($arg)){
			$opt = $arg;
		}
		
		return $this->get("https://api.twitter.com/1.1/users/show.json", $opt);
	}
	
	function getRateLimitStatus() {
		// remaining_hits, hourly_limit, reset_time, reset_time_in_seconds
		return $this->get("https://api.twitter.com/1.1/application/rate_limit_status.json?statuses");
	}
	
	// setUpdate() を実行します。
	function setUpdate($text, $in_reply_to = false, $type=false) {
		/*$pid = pcntl_fork();
		if ($pid == -1) error("pcntl_fork failed!!");
		elseif ($pid) return; // 親プロセスなら戻る。
		*/
		// 子プロセスでアップデートを行う。
		$arg = "";
		if($in_reply_to){
			$opt = array("status"=>$text,"in_reply_to_status_id"=>$in_reply_to);
		}elseif(!is_array($text)){
			$opt = array("status"=>$text);
		}else{
			$opt = $text;
		}
		if ($type == "nisehoari"){
			$ret = $this->nisehoaripost("https://api.twitter.com/1.1/statuses/update.json", $opt);
			if($ret["error"] != "User is over daily status update limit.") return;
		}
		if($this->kiseinum == -1){
			$ret = $this->post("https://api.twitter.com/1.1/statuses/update.json", $opt);
		}else{
			$ret = $this->subpost("https://api.twitter.com/1.1/statuses/update.json", $opt, $this->kiseinum);
		}
		
		// 失敗したら次の規制用アカを使用。
		while($ret["error"] == "User is over daily status update limit." && $this->kiseinum < $this->subapinum-1){
			$this->kiseinum++;
			$ret = $this->subpost("https://api.twitter.com/1.1/statuses/update.json", $opt, $this->kiseinum);
		}
		
		if($this->kiseinum != -1){
			info("Post from sub account No.".($this->kiseinum));
		}
		//exit(0);
	}
	
	// addFriend($screen_name) を実行します。
	function addFriend($screen_name) {
		/*$pid = pcntl_fork();
		if ($pid == -1) error("pcntl_fork failed!!");
		elseif ($pid) return; // 親プロセスなら戻る。
		*/
		$this->post("https://api.twitter.com/1.1/friendships/create.json", array(screen_name => $screen_name, follow => True));
		//exit(0);
	}
	
	// removeFriend($screen_name) を実行します。
	function removeFriend($screen_name) {
		/*$pid = pcntl_fork();
		if ($pid == -1) error("pcntl_fork failed!!");
		elseif ($pid) return; // 親プロセスなら戻る。
		*/
		$opt = array("screen_name"=>$screen_name);
		$this->post("https://api.twitter.com/1.1/friendships/destroy.json", $opt);
		//exit(0);
	}
	
	// ツール関数群
	
	// getメソッドです。
	function get($url, $parameters = array()) {
		$jsonret = $this->api->get($url,$parameters);
		$ret = $this->obj2array($jsonret);
		if(!is_array($ret) or !count($ret)) {
			warn("STWrapper::get : fault (return is not array.)\n".$url."\nreturn:$jsonret");
			return array();
		}elseif($ret["error"]) {
			warn("STWrapper::get : fault (return notice an error.)\n[request] ".$ret["request"]."\n[error] ".$ret["error"]);
			return array();
		}elseif($ret["errors"]){
			var_dump($jsonret);
			warn("STWrapper::get : fault (return notice an error.)\n[url] ".$url."\n[return] ".$ret["errors"][0]["message"]."\n");
			return array();
		}
		info("strwrapper.get: $url");
		return $ret;
	}
	
	// postメソッドです。
	function post($url, $parameters = array()) {
		if($this->db->getConfig("niseho_dead","1")) $this->db->setConfig("niseho_dead","0");
		$ret = $this->obj2array($this->api->post($url, $parameters));
		if(!is_array($ret)) {
			warn("STWrapper::post : fault.\n".$url);
			$ret = array();
			$ret["error"] = "unknown error.";
		}elseif($ret["error"]) {
			warn("STWrapper::post : fault.\n".$ret["request"]."\n".$ret["error"]);
		}
		return $ret;
	}
	
	// にせほあり専用postメソッドです。
	function nisehoaripost($url, $parameters = array()) {
		$ret = $this->obj2array($this->nisehoariapi->post($url, $parameters));
		if(!is_array($ret)) {
			warn("STWrapper::nisehoaripost : fault.\n".$url);
			$ret = array();
			$ret["error"] = "unknown error.";
		}elseif($ret["error"]) {
			warn("STWrapper::nisehoaripost : fault.\n".$ret["request"]."\n".$ret["error"]);
		}
		return $ret;
	}
	
	// 規制用postメソッドです。
	function subpost($url, $parameters = array(), $subnum = 0) {
		// 死亡してる時
		if($subnum == $this->subapinum-1){
			if($this->db->getConfig("niseho_dead", 0) == "0"){
				info("niseho kisei dead.");
				$parameters = "にせほは死にました";
				$this->db->setConfig("niseho_dead", "1");
			}else{
				// 既に死亡通告してあれば強制終了
				info("niseho is dead, so can't post.");
				return;
			}
		}elseif($subnum < $this->subapinum-1 && $this->db->getConfig("niseho_dead",0) == "1"){
			$this->db->setConfig("niseho_dead", "0");
		}
		
		$ret = $this->obj2array($this->subapi[$subnum]->post($url, $parameters));
		if(!is_array($ret)) {
			warn("STWrapper::post : fault.\n".$url);
			$ret = array();
			$ret["error"] = "unknown error.";
		}elseif($ret["error"]) {
			warn("STWrapper::post : fault.\n".$ret["request"]."\n".$ret["error"]);
		}
		return $ret;
	}
	
	// StdObjectを配列に変換します。
	function obj2array($obj) {
		if(!is_object($obj) && !is_array($obj)) return $obj;
		
		$ret = array();
		foreach($obj as $key => $val) {
			$ret[$key] = $this->obj2array($val);
		}
		return $ret;
	}
	
	// タイムラインを変換します。
	function convertTimeline($timeline, $lastId) {
		global $tool;
		if ($timeline) {
			$ret = array();
			foreach($timeline as $tweet) {
				if(!$lastId || $tweet["id_str"] > $lastId) { 
					$ret[] = $this->convertTweet($tweet);
				}
			}
			return array_reverse($ret);
			
		} else {
			return null;
		}
	}
	
	// Tweetを変換します。
	function convertTweet($tweet) {
		global $tool;
		$tweet["created_at"] = $tool->timeTwitToDB($tweet["created_at"]);
		$tweet["user"]["created_at"] = $tool->timeTwitToDB($tweet["user"]["created_at"]);
		return $tweet;
	}
	
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
}
?>