<?php
	if(!isset($_GET['a'])) exit;
	
	$actions = array();
	$dirh = opendir('./actions/');
	
	for($dirh = opendir('./actions/'); $file !== false; $file = readdir($dirh))
	{
		if(substr($file, -3, 3) == 'txt')
		{
			$pages[substr($file, 0, -4)] = './actions/' . $file;
		}
	}
	
	closedir($dirh);
	
	if(isset($pages[$_GET['a']])) require $pages[$_GET['a']];