<?php
	if(!isset($config->complete) || !isset($core->user) || !isset($_GET['ajax']))
	{
		return include $pages['home'];
	}
	
	print $core->refreshLogin();