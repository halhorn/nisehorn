<?php
class MeCabTest extends TaskBase
{
	var $mecab;
	
	function MeCabTest() {
		$this->setup();
		$this->mecab = new MeCab();
	}
	
	function execute() {
		if(!$this->db->taskEnabled("mecab_test")) return;
		
		$this->replies = $this->st->getReplies();
		$newLastId = $lastId = $this->db->getConfig("mecab_test_lastid");
		
		foreach ($this->replies as $tweet) {
			global $username;
			
			if ($tweet["id"] > $lastId) {
				if (preg_match("/^@$username( |　)+mecab( |　)+(.+)$/i", $tweet["text"], $match)) {
					$data = $this->mecab->parse($match[3]);
					
					$text = "@" . $tweet["user"]["screen_name"] . " 分けたよっ！「";
					foreach ($data as $record) {
						$text .= "$record[word]($record[kind])　";
					}
					$text .= "」";
					$len = mb_strlen($text,"UTF-8");
					if($len > 140) $text = "@" . $tweet["user"]["screen_name"] . " 分けたけど長すぎて投稿できないァ！ (".$len."文字..)";
					info("MeCabTest:" . $text);
					$this->st->setUpdate($text, $tweet["id"]);
				}
				$newLastId = $tweet["id_str"];
			}
		}
		$this->db->setConfig("mecab_test_lastid", $newLastId);
	}
}
?>
