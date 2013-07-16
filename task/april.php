<?php
// サブタスク読み込み
require_once("trp.php");
require_once("mecabtest.php");
require_once("datemessenger.php");
require_once("niseho.php");
require_once("timer.php");
require_once("recenttweet.php");
require_once("followerupdate.php");

// ボットのクラスです。/////////////////////////////////////////////////////////
class April extends TaskBase
{
	function April() {
		$this->setup();
	}

	function post($status, $in_reply_to){
		if(preg_match("/http/", $status) || $this->db->is_censored($status)) return;
		if(preg_match("/^\@([_0-9a-zA-Z]+) /", $status, $matches)){
			$tweetTo = $matches[1];
			if (count($this->db->select("follower", "screen_name LIKE '" . $tweetTo . "'"))) {
				$this->st->setUpdate($status, $in_reply_to);
				print "1";
			}
		}
	}

	function get_timeline($tabName){
		if ($tabName == "HOME"){
			$this->fTimeline = $this->st->getFriendsTimeline();
			print json_encode($this->fTimeline);
		}elseif ($tabName == "REPLY"){
			$this->replies = $this->st->getReplies();
			print json_encode($this->replies);
		}
	}

	function get_status($id){
		print json_encode($this->st->getStatusShow($id));
	}
}
?>
