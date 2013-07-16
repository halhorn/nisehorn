<?php
class DateMessenger extends TaskBase
{
	var $messages;
	
	function DateMessenger(){
		$this->setup();
		
		$this->messages = array(
			"01_01" => "新年明けたー。おめでとうおめでとう。",
		);
	}
	
	function execute(){
		return; // これ専用のテーブルとか作るべき。
		
		foreach($this->messages as $date => $message){
			if(date("Y") != $this->db->getConfig("date_message_" . $date) && $date == date("m_d")){
				$this->st->setUpdate($message);
				info("DateMessanger write. $message");
				$this->db->setConfig("date_message_" . $date, date("Y"));
			}
		}
	}
}

?>
