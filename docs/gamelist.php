<?php

	mysql_connect("localhost", "user", "boom7441") or die(mysql_error());
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
	
	if($_POST['updated_since'] > 24 * 60 * 60)
	{
		$result = json_encode(array("error" => "Access to the Battle.net Game Database denied"));
	}
	else if(strlen($_POST['search']) < 1 || (strlen($_POST['search']) === 1 && $_POST['search'] !== "%"))
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
		if(!$_POST['updated_since'])
			$_POST['updated_since'] = 24 * 60 * 60; //24 hours x 60 mins x 60 sec
		
		if(!$_POST['search'])
			$_POST['search'] = '%';
		
		$filename = 
			$_POST['search'] . 
			$_POST['type_id'] . 
			$_POST['realm_id'] . 
			$_POST['game_expansion'] . 
			$_POST['game_ladder'] . 
			$_POST['game_hardcore'] . 
			$_POST['game_difficulty'] . 
			$_POST['updated_since'];
		
		$filename = "cache/" . md5($filename);
		
		$exists = file_exists($filename);
		
		if($exists)
		{
			$mtime = filemtime($filename);
			
			$updated_since = time() - $mtime;
			
			// entire query
			if($updated_since > $_POST['updated_since'])
			{
				$cached_games = array();
				
				$_POST['updated_since'] = $_POST['updated_since'];
			}
			// shorten the query
			else if($updated_since >= 5)
			{
				$cached_games = json_decode(file_get_contents($filename), true);
				
				$_POST['updated_since'] = $updated_since;
			}
			else
			{
				die(file_get_contents($filename));
			}
		}
		// entire query
		else
		{
			$cached_games = array();
		}
		
		$game_updated = date("Y-m-d H:i:s", time() - $_POST['updated_since']);
		
		$query = "SELECT `game_id`, `game_title`, `game_description`, `game_players`, game_characters 
		FROM `d2_games` 
		WHERE
		(
			`game_title` LIKE '" . mysql_real_escape_string($_POST['search']) . "'
			OR 
			`game_description` LIKE '" . mysql_real_escape_string($_POST['search']) . "'
			OR 
			`game_characters` LIKE '" . mysql_real_escape_string($_POST['search']) . "'
		)
		AND `type_id` = " . mysql_real_escape_string($_POST['type_id']) . "
		AND `realm_id` = " . mysql_real_escape_string($_POST['realm_id']) . "
		AND `game_expansion` = " . mysql_real_escape_string($_POST['game_expansion']) . " 
		AND `game_ladder` = " . mysql_real_escape_string($_POST['game_ladder']) . "
		AND `game_hardcore` = " . mysql_real_escape_string($_POST['game_hardcore']) . "
		AND `game_difficulty` = " . mysql_real_escape_string($_POST['game_difficulty']) . " 
		AND `game_updated` > '" . mysql_real_escape_string($game_updated) . "'
		ORDER BY `game_created` DESC, `game_updated` DESC 
		LIMIT 100";
		
		$games = array();
		
		if($result = mysql_query($query) or die(mysql_error()))
		{
			while($row = mysql_fetch_array($result, MYSQL_ASSOC))
			{
				if($row['game_characters'] != "null_t")
					$row['game_characters'] = explode(',', $row['game_characters']);
				else
					$row['game_characters'] = array();

				$games[] = $row;
			}
		}
		
		$games = array("games" => array_reverse($games));
		
		$games = array_merge($cached_games, $games);

		if(count($games["games"]) == 0)
		{
			$games["message"] = "NO NEW GAMES FOUND...";

			$q1 = "SELECT count(login_id) as login_count FROM d2_logins 
				WHERE expansion_id = " . $_POST['game_expansion']. " 
				AND realm_id = " . $_POST['realm_id'] . " 
				AND is_ladder = " . $_POST['game_ladder'] . " 
				AND is_hardcore = " . $_POST['game_hardcore'] . " 
				AND difficulty_id = " . $_POST['game_difficulty'] . " 
				LIMIT 1";

			if($r1 = mysql_query($q1) or die(mysql_error()))
				if($row1 = mysql_fetch_array($r1, MYSQL_ASSOC))
					if($row1['login_count'] == 0)
						$games["message"] = "GAME LIST NOT ENABLED...";
		}
		
		$result = json_encode($games);
		
		$fp = fopen($filename, 'w');
		
		fwrite($fp, $result);
		
		fclose($fp);
	}
	
	die($result);

?>
