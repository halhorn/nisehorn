<?php
// にせほタイマーを実装するクラスです。
class Timer extends TaskBase
{
	/*
	 * Type (省略時normal)
	 * normal   : ただしゃべるだけ
	 * exp      : ～～まであと○日○時間 array(年,月,日,時)
	 * expwords : 期限が来た時の言葉
	 * start    : 何時を基準にするか(省略時期限時間)
	 * interval : startを基準に何時間お気に発言するか
	 *
	 * exp : 期限タイマー
	 * wordsに__TIME__を入れておけばそこが期限までの日にちと時間に置換される
	*/
	var $hourWords = array(
		array(
			"type" => "exp",
			"words" => "KU情報学研究科知能専攻院試まであと__TIME__ァ！ ※院試の時間は各自責任持って調べましょう #inshi",
			"expwords" => "KU情報学研究科知能専攻院試開始ァ！",
			"exp" => array(2010,8,9,10),
			"interval" => 1, // 省略時1
		),
		array(
			"type" => "exp",
			"words" => "ぽけもんBW発売まであと__TIME__ァ！",
			"expwords" => "ぽけもんBW発売ァ！",
			"exp" => array(2010,9,18,7),
			"interval" => 6, // 省略時1
		),
		array(
			"type" => "exp",
			"words" => "京大オケ第188回定期演奏会＠京都まで__TIME__ァ！",
			"expwords" => "京大オケ定期演奏会チケット販売ァ！",
			"exp" => array(2011,1,12,19),
			"interval" => 24, // 省略時1
		),
		array(
			"type" => "normal",
			"words" => "規制用アカ！ @nisehorrn @nisehorrrn ... @nisehorrrrrrrn ６匹まで規制されても大丈夫ァ！",
			"interval" => 24, // 省略時1
			"start" => 22
		),
		array(
			"type" => "exp",
			"words" => "KUIS 卒業論文提出締切まで__TIME__ァ！",
			"exp" => array(2011,1,31,16),
			"interval" => 8, // 省略時1
		),
		array(
			"type" => "exp",
			"words" => "京大オーケストラ定期演奏会チケット販売開始まで__TIME__ァ！興味ある方は @halhorn まで！ http://kyodaioke.com/concertInfo.html",
			"interval" => 8, // 省略時1
			"start" => 22,
			"exp" => array(2011,5,14,10)
		),
		array(
			"type" => "exp",
			"words" => "KU情報学研究科知能専攻院試まであと__TIME__ァ！ ※院試の時間は各自責任持って調べましょう #inshi",
			"expwords" => "KU情報学研究科知能専攻院試開始ァ！",
			"exp" => array(2011,8,8,10),
			"interval" => 12, // 省略時1
		),
		
	);
	
	function Timer() {
		$this->setup();
	}
	
	// タイマーを実行します。
	function execute() {
		$this->hourTimer();
	}
	
	// 時間ごとのタイマーを実行します。
	function hourTimer() {
		$t = getdate();
		if(	$t["hours"] != $this->db->getConfig("last_htimer_hour", 0) ) {
			info("nisehoTimer(hour) Start.");
			$this->db->setConfig("last_htimer_hour", $t["hours"]);
			
			foreach($this->hourWords as $hw) {
				if (!$hw["interval"]) $hw["interval"] = 1;
				if (!$hw["type"]) $hw["type"] = "normal";
				if (!$hw["start"]) $hw["start"] = $hw["exp"][3];
				
				if (($t["hours"] - $hw["start"]) % $hw["interval"] != 0) continue;
				
				$text = $hw["words"];
				
				if ($hw["type"] == "exp") {
					$remain = $this->getRemain($hw["exp"]);
					if($remain == -1) continue;
					list($d, $h) = $remain;
					
					if($d == 0 && $h == 0) {
						$text = $hw["expwords"];
					}else{
						$text = str_replace("__TIME__", $d."日と".$h."時間", $text);
					}
				}
				
				$this->st->setUpdate($text);
				info("書き込み：".$text);
			}
		}
	}
	
	// 期限までの日にちと時間を計算します。
	function getRemain($exp) {
		$t = getdate();
		if(count($exp) == 3) $exp[3] = 0;
		
		$now = mktime($t["hours"], 0, 0, $t["mon"], $t["mday"], $t["year"]);
		$exp = mktime($exp[3], 0, 0, $exp[1], $exp[2], $exp[0]);
		
		$sub = $exp - $now;
		
		if($sub < 0) return -1;
		
		$sub = (int)($sub / 3600);
		$h = $sub % 24;
		$d = (int)($sub / 24);
		
		return array($d, $h);
	}
}
?>
