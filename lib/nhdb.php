<?php
// データベースを扱うクラスです。

require_once("DB.php");

class NHDB
{
	var $mysql;
	var $censored;
	var $errCounter; // queryErrorが呼ばれた回数を記録するカウンター。
	
	// コンストラクタです。
	function NHDB() {
		global $dbuser, $dbpass, $dbhost, $dbname;
		$this->errCounter = 0;
		
		$dsn = "mysql://$dbuser:$dbpass@$dbhost/$dbname";
		$this->mysql = DB::connect($dsn);
		
		if(DB::isError($this->mysql)){
			print "DB-Error!!\n$dsn";
			error($this->mysql->getMessage() . "<BR>\n$dsn");
		}
	}
	
	// Tweetリストに登録します。古いTweetを除去します。
	function addTweets($table, $timeline) {
		global $tool;
		$columns = array("id","screen_name","name","text","in_reply_to_status_id","created_at");
		$data = array();
		$last_no = 0;
		
		foreach($timeline as $tweet){
			if(!$tweet["user"]["id_str"]) continue;
			$dbf = $this->select("follower", "id =" . $tweet["user"]["id_str"]);
			
			// フォローされてない場合、自分のつぶやきは登録しない
			if(!count($dbf)) continue;
			if($tool->isMe($tweet["user"]["screen_name"])) continue;
			//if(mb_strlen($tweet["text"],"UTF-8") == strlen($tweet["text"])) continue;
			
			// Tweet追加
			$data[] = array(
				$tweet["id_str"], 
				$tweet["user"]["screen_name"], 
				$tweet["user"]["name"],
				$tweet["text"],
				$tweet["in_reply_to_status_id_str"],
				$tweet["created_at"]
			);
			$last_no = $tweet["id_str"];
		}
		if(count($data) == 0 && count($timeline) > 20){
			warn("NHDB.addTweets: fault. no data added to db.");
		}
		$this->insert_items($table, $columns, $data);
		
		// 古いTweet除去
		if ($last_no){
			$result = $this->select($table, "id = " . $last_no);
			$oldest = $result[0]["no"] - $this->getConfig("save_tweet_num", 100) + 1;
		
			$this->del($table, "no < $oldest");
		}else{
			warn("NHDB.addTweets: can't delete old tweets. last_no is empty. table: " . $table);
		}
	}
	
	// 指定されたIDのTweetをDBから引き出します。
	function getTweet($id) {
		$result = $this->select("friends_tweet", "id = $id");
		if(!$result || !$result[0]) {
			$result = $this->select("public_tweet", "id = $id");
		}
		//if(!$result || !$result[0]) warn("Warn NHDB::getTweet : Tweetが見つかりません。ID:$id");
		return $result[0];
	}
	
	// お気に入り度を増減させます。
	function addFavor($screen_name, $add) {
		$fav = $this->getFavor($screen_name) + $add;
		$where = "screen_name like '$screen_name'";
		$this->update("follower", $where, "favorite", $fav);
	}
	
	// お気に入り度を返します。
	function getFavor($screen_name) {
		$where = "screen_name like '$screen_name'";
		$records = $this->select("follower", $where);
		if($records[0]) {
			return $records[0]["favorite"];
			
		} else {
			warn("NHDB::getFavor : no such follower : $screen_name");
			return 0;
		}
	}
	
	// 指定されたタスクが有効かを返します。
	function taskEnabled($taskname) {
		return $this->getConfig($taskname . "_enabled", 1) || $_REQUEST["executeforce"];
	}

	// 設定を読み込みます。
	function getConfig($confName, $default = null) {
		$where = "conf_name like '$confName'";
		$result = $this->select("config", $where, "conf_value");
		
		if(count($result)) {
			return $result[0]["conf_value"];
			
		} else {
			// 設定がなければ作成する。
			$this->makeConfig($confName, $default);
			warn("NHDB::getConfig : configName '$confName' is not exist. makeConfig executed.");
			return null;
		}
	}
	
	// 設定を保存します。
	function setConfig($confName, $confValue) {
		$where = "conf_name like '$confName'";
		$this->getConfig($confName); // 設定がなかった時のため。
		$this->update("config", $where, "conf_value", $confValue);
	}
	
	// 設定の項目を追加します。
	function makeConfig($confName, $value = null) {
		$data = array("conf_name" => $confName, "conf_value" => $value);
		$this->insert("config", $data);
	}
	
	// ログを取ります。
	function addLog($message, $level, $execId) {
		$date = date("Y/m/d H:i:s");
		
		$logExpr = $this->getConfig("log_expr", 7);
		$delDate = date("Y/m/d H:i:s", time() - $logExpr * 24*60*60); // ログを設定した日付の分保存
		$delWhere = "date < '$delDate'";
		
		$data = array(
			"execId" => $execId,
			"date" => $date,
			"level" => $level,
			"message" => addslashes($message)
		);
		
		$this->del("execute_log", $delWhere);
		$this->insert("execute_log", $data);
	}
	
	// ログを表示します。
	// errorMode = trueにするとWarn,Erorrしか表示しない。
	function showLog($date = "", $errorMode = false) {
		global $php;
		$cmd = $errorMode ? "showerror" : "showlog";
		if(!$date) $date = date("Y-m-d");
		$where = "date > '$date 00:00:00' and date < '$date 23:59:59'";
		$records = array_reverse($this->select("execute_log", $where));
		
		preg_match("/^([0-9]+\-[0-9]+\-)([0-9]+)/", $date, $match);
		$prev = $match[1] . ($match[2] - 1);
		$next = $match[1] . ($match[2] + 1);
		
		print "<HTML><HEAD><META http-equiv='Content-Type' content='text/html; charset=UTF-8'><TITLE>nisehorn LOG</TITLE></HEAD><BODY>";
		print "<CENTER><A href='$php?cmd=$cmd&date=$prev'>＜＜</A>&nbsp;&nbsp;&nbsp;<A href='$php?cmd=$cmd&date=$next'>＞＞</A>";
		print "<TABLE border=0 cellspacing=0 cellpadding=5>\n";
		print "<TR><TH>ID</TH><TH>Date</TH><TH>Level</TH><TH>Message</TH></TR>\n";
		
		foreach($records as $record) {
			$level = $record["level"];
			if($level != "Warn" && $level != "Error" && $errorMode) continue;
			if($level == "Error") $level = "<B><FONT color=red>$level</FONT></B>";
			if($level == "Warn") $level = "<B>$level</B>";
			print "<TR><TD>$record[id]</TD><TD nowrap>$record[date]</TD><TD>$level</TD><TD>$record[message]</TD></TR>\n";
		}
		
		print "</TABLE>\n</CENTER></BODY></HTML>\n";
	}
	
	// Tweet-Reply Pairのテーブルを表示します。
	function showTweetReplyPair() {
		$result = array_reverse($this->select("tweet_reply_pair"));
		
		print "<HTML><HEAD><META http-equiv='Content-Type' content='text/html; charset=UTF-8'><TITLE>TRPテーブル</TITLE></HEAD><BODY><CENTER>";
		print "<A href='http://halmidi.com/log/phpmyadmin/index.php?db=halmidi_nise&table=config&lang=ja-euc&token=c84562e111395d5cc46168f38680211f' target=_blank>編集</A>";
		print "<TABLE border=1 cellspacing=0 cellpadding=3>\n";
		print "<TR><TH>ID</TH><TH>TweetDate</TH><TH>Tweet</TH><TH>Reply</TH></TR>\n";
		
		foreach ($result as $record) {
			print "<TR><TD>$record[id]</TD><TD nowrap>$record[created_at]</TD><TD>$record[tweet]</TD><TD>$record[reply]</TD></TR>\n";
		}
		print "</TABLE>\n";
		print "</CENTER></BODY></HTML>\n";
	}
	
	// censoredリストに入っている単語を<censored>に変換します。
	function filter($text) {
		if (!$this->censored) {
			$this->censored = explode(",", $this->getConfig("censored_list",""));
			$this->censored = array_map("trim",$this->censored);
			
		}
		
		foreach ($this->censored as $c) {
			if($c){
				$text = str_replace("$c","<censored>",$text);
			}
		}
		return $text;
	}
	
	// censoredリストに入っている単語が使われているかを返します。
	function is_censored($text) {
		if (!$this->censored) {
			$this->censored = explode(",", $this->getConfig("censored_list",""));
			$this->censored = array_map("trim",$this->censored);
		}
		
		foreach ($this->censored as $c) {
			if ($c && strpos($text, $c) !== false) return true;
		}
		return false;
	}
	
	// 基本的な関数群 /////////////////////////////////
	
	// where句に当てはまるものがあるかを返します。
	function exists($table, $where) {
		$result = select($table, $where);
		return count($result);
	}
	
	// データ挿入します。
	// string $table : テーブル名
	// dict $data : カラム名 => データ の集合
	function insert($table, $data) {
		$query = "INSERT INTO $table(\n";
		$query .= implode(",\n", array_keys($data));
		$query .= "\n)VALUES(\n";
		$values = array_map(array($this, "quote"), array_values($data)); // クオートする
		$query .= implode(",\n", $values);
		$query .= "\n)\n";
		
		$this->query($query);
	}
	
	// 複数のデータを挿入します。
	// string $table : テーブル名
	// array $columns : カラム名リスト array("id","name","age")
	// array $values  : 値の集合 array(array(1,"hal",23), array(...))
	function insert_items($table, $columns, $values) {
		if (!$values) return;
		$query = "INSERT INTO $table (";
		$query .= implode(",", $columns);
		$query .= ") VALUES\n";
		$f = False;
		foreach($values as $value){
			if(!$f) $f = True;
			else $query .= ",\n";
			$query .= "(";
			$tmp = array_map(array($this, "quote"), $value); // クオートする
			$query .= implode(",", $tmp);
			$query .= ")";
		}
		$this->query($query);
	}
	
	// データを更新します。
	// string $column : カラム名
	function update($table, $where, $column, $newData) {
		if (!$where) error("NHDB::update : 'where' is not exist.");
		$newData = addslashes($newData);
		$query = "UPDATE $table\n";
		$query .= "SET $column = '$newData'\n";
		$query .= "WHERE $where\n";
		
		$this->query($query);
	}
	
	// データを抜き出します。
	// array(dict) return : ret[番号][カラム名] = データ
	function select($table, $where = "", $target = "*", $order = "id") {
		$query = "SELECT $target FROM $table\n";
		if ($where) $query .= "WHERE\n$where\n";
		$query .= "ORDER BY $order ASC\n";
		return $this->query_with_return($query);
	}

	// データを除去します。
	function del($table, $where) {
		if(!$where) error("NHDB::del : 'where' is Empty.");
		$this->query("DELETE FROM $table WHERE $where");
	}
	
	// クエリーをDBに渡します。
	function query($query) {
		global $logger;
		$query = $this->utfEuc($query);
		if(DB::isError($this->mysql->query("set names ujis"))) print "set names ujis : Error";
		$result = $this->mysql->query($query);
		
		if(DB::isError($result)) {
			$this->errCounter++;
			if ($this->errCounter < 50) error($result->getMessage() . "<BR>\n<Pre>$query</Pre>");
			exit(1);
		}
		return $result;
	}

	function query_with_return($query) {
		$result = $this->query($query);
		if(!is_object($result)){
			error("NHDB.return_with_query: result is null! <PRE>\n$query\n</PRE>");
		}
		$ret = array();
		while ($tmp = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
			//$ret[] = $tmp;
			$ret[] = array_map(array($this, "eucUtf"), $tmp);
		}
		
		// 文字コードをUTF-8に変換して返す。
		return $ret;
	}
	
	// 文字列をクオートする。
	function quote($str) {
		$str = addslashes($str);
		return "'$str'";
	}
	
	// EUC-JPをUTF-8に変換する
	function eucUtf($str) {
		return mb_convert_encoding($str, "UTF-8", "EUC-JP");
	}
	
	// UTF-8をEUC-JPに変換する
	function utfEuc($str) {
		return mb_convert_encoding($str, "EUC-JP", "UTF-8");
	}
	
	// 特定のキーの値のみからなる配列に変換する
	// $array[0]["abc"] → $array[0]
	function convertToArray($arrayDict, $key) {
		$ret = array();
		foreach($arrayDict as $dict) {
			$ret[] = $dict[$key];
		}
		return $ret;
	}
}
?>
