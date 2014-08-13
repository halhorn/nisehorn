<?php
// Tweet-Reply Pair の処理を行うクラスです。
class FollowByReply extends TaskBase
{
	// コンストラクタです。
	function FollowByReply() {
		$this->setup();
	}
	
	// 実行します。
	function execute()
	{
		if(!$this->db->taskEnabled("trp")) {
			info("TRP::execute : trp disable.");
			return;
		}
		
		info("FollowByReply start.");
		$this->replies = $this->st->getReplies();
		$this->repliesLoop();
	}

	function repliesLoop() {
		foreach($this->replies as $tweet) {
			$follower_array = $this->db->select("follower", "id = ".$tweet["user"]["id_str"]);
			if (count($follower_array)) return;

			$user = $tweet["user"];
			$this->st->addFriend($user["screen_name"]);
			$data = array(
				"id" => $user["id_str"],
				"screen_name" => $user["screen_name"],
				"name" => $user["name"],
				"favorite" => 0,
				"niseho" => 0
			);
			$this->db->insert("follower", $data);
			info("$user[screen_name]をDBに登録しました。");
		}
	}
}
?>