<?php

// Chasenのパーサーです。
class ChaSen
{
	var $path = "/usr/local/bin/chasen";
	var $resultText;
	var $data;
	
	function ChaSen($str = false) {
		if($str) $this->parse($str);
	}
	
	// 形態素解析を行います。(オプション-cつき)
	function parsec($str) {
		
		// MeCab実行
		$this->resultText = $this->execChaSen($str, "-c");
		$this->data = array();
		/*
			ホルン  ホルン  ホルン  2 0 0
			を      ヲ      を      61 0 0
			吹く    フク    吹く    47 8 1
		 */
		
		// 帰ってきた文字列を変換
		$lines = explode("\n", $this->resultText);
		foreach ($lines as $line) {
			
			$tmp = explode(" ", $line);
			$this->data[] = array(
				"word" => $tmp[0],		// 元の単語
				"kana" => $tmp[1],		// よみ
				"original" => $tmp[2],	// 原型
				"pc1" => $tmp[3],		// 品詞
				"pc2" => $tmp[4],		// 品詞
				"pc3" => $tmp[5],		// 品詞
			);
		}
		return $this->data;
	}
	
	// MeCabを実行します。
	function execChaSen($str, $param = "") {
		
		if (!function_exists('stream_get_contents')) {
		    function stream_get_contents($handle) {
		        $contents = '';
		        while (!feof($handle)) {
		            $contents .= fread($handle, 8192);
		        }
		        return $contents;
		    }
		}
		
		$descriptorspec = array(
		      0 => array("pipe", "r")
		    , 1 => array("pipe", "w")
		);
		$result = "";
		$process = proc_open($this->path . " " . $param, $descriptorspec, $pipes);
		if (is_resource($process)) {
		    fwrite($pipes[0], $str);
		    fclose($pipes[0]);
		    $result = stream_get_contents($pipes[1]);
		    fclose($pipes[1]);
		    proc_close($process);
		}
		return $result;
	}
	
}

?>