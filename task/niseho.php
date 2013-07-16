<?php
// にせほーを実装するクラスです。
class Niseho extends TaskBase
{
	var $startHour;
	var $termMinute;
	
	var $kinen = array(
		5 => array("","", ""),
		10 => array("","","",""),
		20 => array("","",""),
		30 => array("","",""),
		40 => array("", "",""),
		50 => array("", ""),
		100 => array(""),
		65536 => array("")
	);
	
	function Niseho() {
		$this->setup();
		
		// にせほーする時間を決める。
		$this->setNisehoTime();
	}
	
	// にせほーを実行します。
	function execute() {
		if(!$this->db->taskEnabled("niseho")) {
			info("Niseho::execute : niseho disable.");
			return;
		}
		
		$this->nisehoStart();
		$this->nisehoEnd();
	}
	
	// にせほーする時間を決めます。
	function setNisehoTime() {
		global $nisehoseed;
		$t = getdate();
		srand($t["year"]*360 + $t["mon"]*30 + $t["mday"] + $nisehoseed);
		$this->startHour = rand(0, 23);
		//$this->termMinute = rand(3,10);
		//$this->startHour = 0;
		$this->termMinute = 3.0;
		
		// srandを元に戻す。
		srand(time());
	}
	
	// にせほーとつぶやきます。
	function nisehoStart() {
		$t = getdate();
		if(	$t["hours"] >= $this->startHour && 
			$this->db->getConfig("last_niseho_date", 0) != $t["mday"]
			
		) {
			$time = time();
			$this->db->setConfig("last_niseho_date", $t["mday"]);
			$this->db->setConfig("niseho_start_time", $time);
			
			$this->st->setUpdate("にせほー");
			info("にせほスタート：\nHour:$this->startHour:00\nTerm:$this->termMinute minute.\nniseho_start_time:$time");
		}
	}
	
	// 間に合った人を集計します。
	function nisehoEnd() {
		$t = getdate();
		$startTime = $this->db->getConfig("niseho_start_time");
		$endTime = $this->termMinute * 60 + $startTime;
		
		if(	$this->db->getConfig("last_niseho_date") == $t["mday"] &&
			$startTime &&
			time() >=  $endTime-15 // マージンを取っておく
			
		) {
			$this->db->setConfig("niseho_start_time", 0);
			info("nisehoEnd.");
			$where = "created_at >= \"" . $this->tool->timeTimeToDB($startTime) . "\" and created_at <= \"" . $this->tool->timeTimeToDB($endTime) . "\"";
			$records = $this->db->select("friends_tweet", $where);
			$completeList = array();
			$lastuser = "";
			$count = 0;
			
			info("niseho: records length:" . count($records));
			foreach($records as $tweet) {
				if(preg_match("/にせほ/", $tweet["text"]) && !$completeList[$tweet["screen_name"]]) {
					$where = "screen_name like '".$tweet["screen_name"]."'";
					$user = $this->db->select("follower", $where);
					if ($user){
						$this->db->update("follower", "id = ".$user[0]["id"], "niseho", ++$user[0]["niseho"]);
						$completeList[$tweet["screen_name"]] = 1;
						$this->sayThanks($tweet["screen_name"], $user[0]["niseho"], $tweet["id"]);
						$lastuser = $tweet["screen_name"];
						$count++;
					}else{
						warn("niseho: get user info fault: " . $tweet["screen_name"]);
						$this->st->setUpdate("@halhorn ".$tweet["screen_name"]." のにせほー回数思い出せなかったァ！", false, "nisehoari");
						$this->sayThanks($tweet["screen_name"], 0, $tweet["id"]);
					}
				}
			}
			$this->st->setUpdate(" $count 人のにせほーありがとァ！", false, "nisehoari");
			$this->st->setUpdate("今日のにせほーチキンレース勝者は @$lastuser ァ！！！おめァ！", false, "nisehoari");
		}
	}
	
	function sayThanks($name, $n, $id) {
		$words = "@" . $name . " ";
		foreach($this->kinen as $kinenNum => $kinenWords) {
			if ($n == $kinenNum) {
				$words .= $n ."回目にせほおめー！ " . $kinenWords[rand(0,count($kinenWords)-1)];
				$this->st->setUpdate($words, $id, "nisehoari");
				
				$pwords = ".@" . $name . " が" . $n . "回目にせほ達成ァ！";
				$this->st->setUpdate($pwords, false, "nisehoari");
				info("書き込み：" . $words);
				info("書き込み：" . $pwords);
				return;
			}
		}
		$words .= $n . "回目にせほありー";
		$this->st->setUpdate($words, $id, "nisehoari");
		info("書き込み：" . $words);
	}
}
?>
