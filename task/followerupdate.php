<?php

// フォロワーの更新を行います。
class FollowerUpdate extends TaskBase
{
	// コンストラクタです。
	function FollowerUpdate() {
		$this->setup();
	}
	
	// フォロワーを更新します。
	function execute() {
		info("Follower Update Start.");
		info("Getting Followers...");
		$followers = $this->st->getFollowers();
		info("Getting Friends...");
		$friends = $this->st->getFriends();
		$fIds = array();
		$fdict = array();
		$dfIds = array();
		$dfdict = array();
		$frIds = array();
		$frdict = array();
		
		// フォロワー取得が成功しているかの確認
		if(!count($followers)) {
			warn("followerの取得に失敗しました。");
			return;
		}
		
		// フレンズ取得が成功しているかの確認
		if(!count($friends)) {
			warn("friendsの取得に失敗しました。");
			return;
		}
		
		info("Getting DBFollowers...");
		$dbFolowers = $this->db->select("follower", 1);
		
		info("Follower Updating...");
		foreach ($friends as $fr) {
			array_push($frIds, $fr["id"]);
			$frdict[$fr["id"]] = $fr;
		}
		
		foreach ($followers as $f) {
			array_push($fIds, $f["id"]);
			$fdict[$f["id"]] = $f;
			$t .= $f["screen_name"]. "\t";
		}
		
		foreach ($dbFolowers as $df) {
			array_push($dfIds, $df["id"]);
			$dfdict[$df["id"]] = $df;
		}
		
		// フォロワーだがDBにまだ登録されていない場合
		foreach ($followers as $f) {
			$id = $f["id"];
			if (!in_array($id, $dfIds)) {
				$data = array(
					"id" => $f["id"],
					"screen_name" => $f["screen_name"],
					"name" => $f["name"],
					"favorite" => 0,
					"niseho" => 0
				);
				$this->db->insert("follower", $data);
				info("$f[screen_name]をDBに登録しました。");
			}else{
				// フォロワー情報が変更されていたら更新
				$updateflag = false;
				if ($f["screen_name"] != $dfdict[$id]["screen_name"]){
					$this->db->update("follower", "id = $id", "screen_name", $f["screen_name"]);
					$updateflag = true;
				}
				if ($f["name"] != $dfdict[$id]["name"]){
					$this->db->update("follower", "id = $id", "name", $f["name"]);
					$updateflag = true;
				}
				if ($updateflag){
					//info("フォロワー情報の更新：\nid->$id\nscreen_name: ".$dfdict[$id]["screen_name"]." -> ".$f["screen_name"]."\nname: ".$dfdict[$id]["name"]." -> ".$f["name"]);
				}
			}
		}
		
		// DBに登録されているが既にフォロワーではない場合
		foreach ($dbFolowers as $df) {
			if (!in_array($df["id"], $fIds)) {
				$this->db->del("follower", "id = ".$df["id"]);
				info("$df[screen_name]をDBから除去しました。");
			}
		}
		
		// 未フォローのフォロワーがいた場合
		foreach ($followers as $f) {
			if (!in_array($f["id"], $frIds)) {
				$this->st->addFriend($f["screen_name"]);
				info("$f[screen_name]をフォローしました");
				//warn("<A href=http://twitter.com/" . $f["screen_name"] . ">" . $f["screen_name"] . "</A> not followed.");
			}
		}
		
		// 片思いを除去
		foreach ($friends as $fr) {
			if (!in_array($fr["id"], $fIds)) {
				$this->st->removeFriend($fr["screen_name"]);
				info("$fr[screen_name]をリムーブしました");
			}
		}
		
		info("||||| Follower Updated.");
	}
}

?>