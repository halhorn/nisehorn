<?php
// サブタスク読み込み
require_once("trp_en.php");
require_once("followerupdate.php");

// ボットのクラスです。/////////////////////////////////////////////////////////
class Bot extends TaskBase
{
	function Bot() {
		$this->setup();
	}
	
	// ボットを走らせます。
	function execute() {
		
		// 前回実行が完了していなければ新しい処理を中断する。
		$last_exec = $this->db->getConfig("exec_start_time", time());
		if($last_exec != 0 && time() - $last_exec < $this->db->getConfig("exec_wait_timeout",10)*60){
			info("### Last exec not completed. This execute stopped. ###");
			exit;
		}elseif($last_exec != 0){
			warn("*** Waiting timeout. Force Exec (last exec not completed) ***");
			$this->st->setUpdate("@halhorn 前の処理終わってないけど強制実行開始したァ！");
		}
		
		// 二重実行防止ロック
		info("Execute Start ------------------------------------------------------");
		$this->db->setConfig("exec_start_time",time());
		
		// 残りAPI取得
		$remainapi = $this->st->getRateLimitStatus();
		$remainapinum = $remainapi["remaining_hits"];
		info("RemainAPI: $remainapinum ResetTime:".$remainapi["reset_time"]);
		if($remainapinum == 0){
			error("API Exhausted.", false);
		}

		// TLを取得してキャッシュ
		$this->fTimeline = $this->st->getFriendsTimeline();
		$this->replies = $this->st->getReplies();
		$this->frTimeline = $this->st->getFriendsRepliesTimeline();
		
		
		// 実行開始
		$trp = new TRP();
		$trp->execute();
		
		// ロック解除
		$this->db->setConfig("exec_start_time", 0);
		
		// 使用API数計算
		$remainapi = $this->st->getRateLimitStatus();
		info("UsedApiNum: ".($remainapinum-$remainapi["remaining_hits"]));
		
		info("Execute End ||||||||||");
	}
	
	function dateExecute(){
		info("DateExecute Start.");
		
		$followerUpdate = new FollowerUpdate();
		$followerUpdate->execute();
	}
}
?>
