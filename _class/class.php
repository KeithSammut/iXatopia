<?php

define('RANK_GUEST', 0);
define('RANK_MEMBER', 1);
define('RANK_ADMIN', 2);

class core
{
	private $page = 'home';
	public  $user = array();
	public  $auth = false;
	const version = '160909.1'; // yymmdd.v

	
	public function __construct()
	{
		global $core, $mysql, $config;
		
		$this->cn = time() . rand(1, 10000);
		
		if(isset($_SERVER['HTTP_CF_CONNECTING_IP']))
		{
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		
		if(!class_exists('Database'))
		{
			require _root . '_class' . _sep . 'pdo.php';
			
			if(file_exists(_root . '_class' . _sep . 'config.php'))
			{
				@include _root . '_class' . _sep . 'config.php';
				if(isset($config))
				{
					$mysql = new Database($config->db[0], $config->db[1], $config->db[2], $config->db[3]);
					if(!isset($mysql->conn) || !$mysql->conn)
					{
						$config = $mysql = false;
					}
					else
					{
						$server = $mysql->fetch_array('select * from `server` limit 0, 1;');
						if(count($server) > 0)
						{
							$config->bind_ip = $server[0]['server_ip'];
							$config->server_ip = $server[0]['connect_ip'];
							$config->server_pt = $server[0]['server_pt'];
							$config->debug_pt = $server[0]['backup_pt'];
							$config->server_dm = $server[0]['server_domain'];
							$config->xats = $server[0]['starting_xats'];
							$config->days = $server[0]['starting_days'];
							$config->info = $server[0];
							$admin = $mysql->fetch_array('select count(*) from `users` where `username`!=\'Unregistered\';');
							if($admin[0]['count(*)'] > 0)
							{
								$config->complete = true;
								try
								{
									if(isset($_COOKIE['loginKey']) && strlen($_COOKIE['loginKey']) == 32)
									{
										$user = $mysql->fetch_array('select * from `users` where `loginKey`=:key;', array('key' => $_COOKIE['loginKey']));
										if(empty($user))
										{
											throw new Exception;
										}
										if(!isset($_COOKIE['xats']) || $_COOKIE['xats'] != $user[0]['xats'])
										{
											setCookie('xats', $user[0]['xats'], strtotime('+ 1 year'));
										}
										if(!isset($_COOKIE['days']) || $_COOKIE['days'] != floor($user[0]['days'] / 86400))
										{
											setCookie('days', floor($user[0]['days'] / 86400), strtotime('+ 1 year'));
										}
										if(!isset($_COOKIE['rank']) || $_COOKIE['rank'] != $user[0]['rank'])
										{
											setCookie('rank', $user[0]['rank'], strtotime('+ 1 year'));
										}
										$this->user = $user[0];
										$this->auth = true;
									}
								}
								catch(Exception $e)
								{
									/* Do Nothing */
								}
							}
						}
					}
				}
			}
		}
	}
	
	public function doPage()
	{
		global $mysql, $config, $core;
		
		$pages = array();
		foreach(glob(_root . '_pages' . _sep . '*.php') as $i => $u)
		{
			$pages[substr(basename($u), 0, -4)] = $u;
		}
		
		if(isset($_GET['page']) && array_key_exists($_GET['page'], $pages))
		{
			$this->page = $_GET['page'];
		}
		elseif(isset($_GET['page']))
		{
			if(is_string($_GET['page']))
			{
				$chat = $mysql->fetch_array('select * from `chats` where `name`=:chat;', array('chat' => $_GET['page']));
				if(!empty($chat))
				{
					$this->page = 'chat';
				}
			}
		}
		
		require $pages[$this->page];
	}
	
	public function allset()
	{ /* So I can cheat */
		$args  = func_get_args();
		$array = array_shift($args);
		foreach($args as $index)
		{
			if(!isset($array[$index]) || !is_string($array[$index]))
			{
				return false;
			}
		}
		return true;
	}
	
	public function getEmbed($chat, $pass = false, $w = 730, $h = 490)
	{
		global $mysql, $config;
		$chat = $mysql->fetch_array('select * from `chats` where `name`=:a or `id`=:b;', array('a' => $chat, 'b' => $chat));
		if($pass !== false)
		{
			$pass = '&pass=' . urlencode($pass);
		}
		$debug = isset($_GET['debug']) ? '&debug' : '';
		$uChat = (strlen($debug) > 0 ? 'chat.swf' : 'chat.swf') . '&v=' . core::version;
		return empty($chat) ? false : "<embed id=\"XenoBox\" width=\"{$w}\" height=\"{$h}\" type=\"application/x-shockwave-flash\" quality=\"high\" src=\"http://{$config->info['server_domain']}/cache/cache.php?f={$uChat}&d=flash{$debug}\" flashvars=\"id={$chat[0]["id"]}{$pass}{$debug}&xc=2336&cn={$this->cn}&gb=9U6Gr\" wmode=\"transparent\">";
	}
	
	public function refreshLogin($redirect = true)
	{
		global $mysql, $config;
		if(!isset($_COOKIE['loginKey']) || strlen($_COOKIE['loginKey']) != 32)
		{
			return false;
		}
		
		$user = $mysql->fetch_array('select * from `users` where `loginKey`=:key;', array('key' => $_COOKIE['loginKey']));
		if(empty($user))
		{
			return false;
		}
		
		$upowers = $mysql->fetch_array("select * from `userpowers` where `userid`={$user[0]['id']};");
		$spowers = $mysql->fetch_array("select * from `powers` where `name` not like '%(Undefined)%';");
		list($vals, $p, $dO, $powerO, $pp) = array(array(), array(), '', '', '');
		foreach($spowers as $i => $u)
		{
			$vals[$u["id"]] = array($u["section"], $u["subid"]);
			if(!isset($p[$u["section"]]))
			{
				$p[$u["section"]] = 0;
			}
		}
		
		foreach($upowers as $i => $u)
		{
			if($u["count"] >= 1 && isset($vals[$u["powerid"]]) && isset($p[$vals[$u["powerid"]][0]]))
			{
				$str = $u['powerid'] . '=' . ($u['count'] > 1 ? ($u['count'] -1) : 1) . '|';
				$dO .= $str;
				if($u['count'] > 1)
				{
					$powerO .= $str;
				}
				$p[$vals[$u["powerid"]][0]] += $vals[$u["powerid"]][1];
			}
		}
		
		foreach($p as $i => $u)
		{
			$pp .= "w_d" . (substr($i, 1) + 4) . "={$u}&";
		}
	/* Nickname / Status */
		$nickname = explode('##', $user[0]['nickname'], 2);
		if(count($nickname) != 2)
		{
			$nickname[1] = "";
		}
		$user[0] = array_map('urlencode', $user[0]);
		$vars = "";
		$vars .= "w_userno={$user[0]["id"]}&";
		$vars .= "w_avatar={$user[0]["avatar"]}&";
		$vars .= "w_k1={$user[0]["k"]}&";
		$vars .= "w_d0={$user[0]['d0']}&";
		$vars .= "w_d1={$user[0]['days']}&";
		$vars .= "w_d2={$user[0]['d2']}&";
		$vars .= "w_d3=&";
		$vars .= $pp;
		$vars .= "w_dt=0&";
		$vars .= "w_homepage={$user[0]['url']}&";
		$vars .= "w_Powers=" . implode(",", $p) . "&";
		$vars .= "w_PowerO=" . urlencode($powerO) . "&";
		$vars .= "w_status=" . urlencode($nickname[1]) . "&";
		$vars .= "w_dO=" . urlencode($dO) . "&";
		$vars .= "w_dx={$user[0]["xats"]}&";
		$vars .= "w_registered={$user[0]["username"]}&";
		$vars .= "w_k2={$user[0]["k2"]}&";
		$vars .= "w_k3={$user[0]["k3"]}&";
		$vars .= "w_name=" . urlencode($nickname[0]);
	/* Finish Login */
		return "<embed height=\"1\" width=\"1\" type=\"application/x-shockwave-flash\" flashvars=\"{$vars}" . ($redirect ? '&redirect=1' : '') . "\" src=\"http://{$config->info['server_domain']}/cache/cache.php?f=login.swf&d=flash\">";
	}
	
}

$core = new core();

if(isset($_GET['ajax']) || isset($_GET['page']) && ($_GET['page'] == 'config' || $_GET['page'] == 'mobile'))
{
	if(isset($_GET['ajax']))
	{
		header('Content-Type: text/plain');
	}
	$core->doPage();
	exit;
}