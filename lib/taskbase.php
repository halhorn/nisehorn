<?php
// ボットのベースになるクラスです。/////////////////////////////////////////////
// ボットを作る際にはこのクラスを継承させます。
class TaskBase
{
	var $st;
	var $db;
	var $tool;
	var $fTimeline;
	var $frTimeline;
	var $replies;
	var $execId;
	
	function setup() {
		global $st, $db, $tool, $execId;
		
		$this->st = &$st;
		$this->db = &$db;
		$this->tool = &$tool;
		$this->execId = $execId;
		
		srand(time());
	}
}
?>
