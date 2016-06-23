<?php
if(!isset($config->complete))// || !isset($_SERVER['HTTP_REFERER']))
{
	return include $pages['home'];
}

$json = new stdClass();
$json->sockDomain = $config->info['connect_ip'];
$json->sockPort = isset($_GET['debug']) ? $config->info['backup_pt'] : $config->info['server_pt'];
$json->useDomain = 'http://' . $config->info['server_domain'];

$powers = $mysql->fetch_array('select `id`, `name`, `topsh` from `powers` order by `id` ASC;');

$json->pow2 = array();
	//$json->pow2[0] = array(0 => "last", 1 => new stdClass());
	//$json->pow2[1] = array(0 => "backs", 1 => new stdClass());
	//$json->pow2[2] = array(0 => "actions", 1 => new stdClass());
	$json->pow2[3] = array(0 => "topsh", 1 => new stdClass());
	//$json->pow2[4] = array(0 => "isgrp", 1 => new stdClass());
	$json->pow2[5] = array(0 => "pssa", 1 => new stdClass());
	$json->pow2[6] = array(0 => "pawns", 1 => new stdClass());
	
	$json->pow2[6][1] = json_decode($config->info['special_pawns']);
	
	foreach($powers as $power)
	{
		if(!is_numeric(strpos($power['name'], '(Undefined)')) && $power['topsh'] != '')
		{
			foreach(explode(',', $power['topsh']) as $top)
			{
				$json->pow2[3][1]->{$top} = (int) $power['id'];
			}
		}
		
		$json->pow2[5][1]->{$power['name']} = (int) $power['id'];
	}

$json->staff = json_decode($config->info['staff'] == '' ? '[]' : $config->info['staff']);

print json_encode($json);