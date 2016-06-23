<?php
	if(!isset($config->complete) || !isset($core->user) || !isset($_GET['ajax']))
	{
		return include $pages['home'];
	}
	
	$mysql->query('update `users` set `xats`=`xats`+`credit`, `credit`=0 where `id`=' . $core->user['id'] . ';');
	
	print $core->refreshLogin();