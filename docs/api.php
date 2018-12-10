<?php

	mysql_connect("localhost", $_ENV['DATABASE_USER'], $_ENV['DATABASE_PASSWORD']) or die(mysql_error());
	mysql_select_db("bnetdb") or die(mysql_error());
	
	$allowed_domains = array
	(
		"bnetdb.com"
	);
	
	$banned_ips = array
	(
		"70.69.81.142",
		"24.17.105.163",
		"76.95.14.28",
		"80.145.190.113"
	);
	
	$message = "";

	$referer = parse_url($_SERVER['HTTP_REFERER']);
	
	if(!$_GET['action'])
	{
		$result = json_encode(array("error" => "Access to the Battle.net Game Database denied"));
	}
	else if(!isset($_SERVER['HTTP_USER_AGENT']) || strlen($_SERVER['HTTP_USER_AGENT']) <= 3)
	{
		$result = json_encode(array("error" => "Access to the Battle.net Game Database denied"));
	}
	else if(in_array($_SERVER['REMOTE_ADDR'], $banned_ips))
	{
		$result = json_encode(array("error" => "Access to the Battle.net Game Database denied"));
	}
	else if(!in_array($referer['host'], $allowed_domains))
	{
		$result = json_encode(array("error" => "Access to the Battle.net Game Database denied"));
	}
	else
	{
		if($_GET['action'] == "get_status")
		{

		$filename = 
			$_GET['action'];
		
		$filename = "cache/" . md5($filename);
		
		$exists = file_exists($filename);
		
		if($exists)
		{
			$mtime = filemtime($filename);
			
			$updated_since = time() - $mtime;
			
			if($updated_since <= 5)
				die(file_get_contents($filename));
		}

		$json = json_encode(array("total_games" => 0));
		
		$query = "SELECT count(game_id) as total_games 
		WHERE 
			game_created > (NOW() - 00-00-00 01:00:00) 
		LIMIT 1";
		
		if($r1 = mysql_query($query) or die(mysql_error()))
			if($row1 = mysql_fetch_array($r1, MYSQL_ASSOC))
				$json['total_games'] = $row1['total_games'];
		
		$result = json_encode($json);
		
		$fp = fopen($filename, 'w');
		
		fwrite($fp, $result);
		
		fclose($fp);
	}
	
	die($result);

?>
