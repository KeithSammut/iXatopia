<?php

if(!isset($config->complete))
{
	return include $pages['setup'];
}

if(isset($_POST['cmd']))
{
	$messages = array();
	switch($_POST['cmd'])
	{
		case 'login':
			if(!$core->allset($_POST, 'user', 'pass'))
			{
				break;
			}
			if(strlen($_POST['user']) == 0)
			{
				$messages[] = 'Please enter your username';
			}
			if(strlen($_POST['pass']) == 0)
			{
				$messages[] = 'Please enter your password';
			}
			if(!empty($messages)) break;
			
			$user = $mysql->fetch_array('select * from `users` where `username`=:a;', array('a' => $_POST['user']));
			if(empty($user) || !$mysql->validate($_POST['pass'], $user[0]['password']))
			{
				$messages[] = 'Bad username / password';
				break;
			}
			
			$loginKey = md5(time() . json_encode($_POST));
			setCookie('loginKey', $loginKey, strtotime('+ 1 year'));
			$_COOKIE['loginKey'] = $loginKey;
			$mysql->query('update `users` set `loginKey`=:a where `username`=:b;', array('a' => $loginKey, 'b' => $user[0]['username']));
			$messages[] = 'You will be redirected momentarily' . $core->refreshLogin();
			$core->auth = true;
			break;
		case 'register':
			if(!$core->allset($_POST, 'user', 'pass', 'mail'))
			{
				break;
			}
			if(strlen($_POST['user']) < 5 || strlen($_POST['user']) > 32 || !ctype_alnum($_POST['user']))
			{
				$messages[] = 'Your username requires 5-15 alpha-numeric characters (a-z/0-9)';
			}
			if(strtolower($_POST['user']) == 'unregistered')
			{
				$messages[] = 'That username is reserved.';
			}
			if(strlen($_POST['pass']) < 6)
			{
				$messages[] = 'You are required to choose a password with at least 6 characters.';
			}
			if(!filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL))
			{
				$messages[] = 'Please enter a valid email address.';
			}
			if(!empty($messages)) break;
			
			$count = $mysql->fetch_array('select count(*) as `count` from `users` where `username`=:a or `email`=:b or (`connectedlast`=:c and `username`!=:d);', array('a' => $_POST['user'], 'b' => $_POST['mail'], 'c' => $_SERVER['REMOTE_ADDR'], 'd' => ''));
			if($count[0]['count'] > 0)
			{
				$messages[] = 'Someone already registered with that username, or you already have an account.';
				break;
			}
			
			/* Insert Pre-Registration-ID Here (Unregistered) */
			$vals = array(
				'id' => 'NULL',
				'username' => $_POST['user'],
				'nickname' => $_POST['user'],
				'password' => $mysql->hash($_POST['pass']),
				'avatar' => rand(0, 1759),
				'url' => '',
				'k' => rand(-1000000, 1000000),
				'k2' => rand(-1000000, 1000000),
				'k3' => rand(-1000000, 1000000),
				'xats' => $config->xats,
				'reserve' => $config->xats,
				'days' => time() + ($config->days * 86400),
				'email' => $_POST['mail'],
				'powers' => '',
				'enabled' => '1',
				'transferblock' => '',
				'connectedlast' => $_SERVER['REMOTE_ADDR'],
				'rank' => 1
			);
			$result = $mysql->insert('users', $vals);
			
			if(isset($_COOKIE['referral']) && is_numeric($_COOKIE['referral']))
			{
				//$mysql->query('update `users` set `credit`=`credit`+125 where `id`=:uid;', array('uid' => $_COOKIE['referral']));
			}
			
			$messages[] = "Registration successful, you may now login";
			break;
		case 'update_bio':
			if($core->auth)
			{
				$mysql->query('update `users` set `desc`=:desc where `id`=' . $core->user['id'] . ';', array('desc' => $_POST['bio']));
			}
			break;
		
		case 'update_pawn':
			if(isset($core->auth))
			{
				if($core->user['custpawn'] != '')
				{
					if(substr($_POST['update_pawn'], 0, 1) == '#')
					{
						$_POST['update_pawn'] = substr($_POST['update_pawn'], 1);
					}
					
					if(!isset($_POST['update_pawn']) || strlen($_POST['update_pawn']) != 6 || !ctype_xdigit($_POST['update_pawn']))
					{
						$_POST['update_pawn'] = 'off';
					}
					
					$mysql->query('update `users` set `custpawn`=:pawn where `id`=' . $core->user['id'] . ';', array('pawn' => $_POST['update_pawn']));
				}
			}
			break;
	}
	
	foreach($messages as $message)
	{
		print '<div class="message"> ' . $message . ' </div>';
	}
}

	if(!isset($_GET['u']) && isset($core->user['username']))
	{
		$_GET['u'] = $core->user['username'];
	}
	
	if(isset($_GET['u']) && ctype_alnum($_GET['u']))
	{
		$user = $mysql->fetch_array('select * from `users` where `username`=:uname or `id`=:uid;', array('uname' => $_GET['u'], 'uid' => $_GET['u']));
		if(count($user) == 1)
		{
			$nickname = htmlspecialchars(substr($user[0]['nickname'], 0, strpos($user[0]['nickname'] . '##', '##')));
			$nickname = preg_replace('/\([^)]*\)+/', '', $nickname);
			$pcount   = $mysql->fetch_array('select count(*) from `userpowers` where `userid`=:userid;', array('userid' => $user[0]['id']));
			
			print '<div class="block c5">';
			print '<div class="heading">' . substr($nickname, 0, 50) . '</div>';
			print '<table style="width: 99%">';
			print '<tr> <td> Xats </td> <td class="tr"> ' . $user[0]['xats'] . ' </td> </tr>';
			print '<tr> <td> Days </td> <td class="tr"> ' . floor($user[0]['days'] / 86400) . ' </td> </tr>';
			print '<tr> <td> Powers </td> <td class="tr"> ' . $pcount[0]['count(*)'] . ' </td> </tr>';
			print '<tr> <td> Credit </td> <td class="tr"> ' . $user[0]['credit'] . ' </td> </tr>';
			print '</table>';
			if($core->auth && $core->user['id'] == $user[0]['id'])
			{
				print '<div style="width: 100%" class="tc"> <input type="submit" class="claimCredit" value="Claim Credit" />&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" class="relogin" value="Relogin" /> </div>';
			}
			print '</div>';
			
			
			
			print '<div class="block c4-5 fr">';
			if(isset($core->user['id']) && $core->user['id'] == $user[0]['id'] && !isset($_GET['preview']))
			{
				if($user[0]['custpawn'] != '')
				{
					print
						'<div class="heading"> Custom Pawn <small style="font-size: 10px">[Hex 6 characters example: #000000, just type "off" to turn your custom pawn off]</small> </div>
						<form method="post">
							<input style="width: 99%;text-align: center;" type="text" autocomplete="off" name="update_pawn" value="' . ($user[0]['custpawn'] == 'off' ? 'off' : '#' . $user[0]['custpawn']) . '" />
							<input type="hidden" name="cmd" value="update_pawn" />
							<div style="width: 99%;text-align: center"> <input type="submit" value="Update" /> </div>
						</form>';
				}
				
				print '<div class="heading"> Referral Link <a href="/faq#referrals">(click for explanation)</a> </div>';
				print '<input type="text" style="width: 99%;text-align: center;" value="http://' . $config->server_dm . '/home?ref=' . $core->user['id'] . '" /><br />';
				print '<br />';
				print '<div class="heading"> Bio [ <a href="/profile?u=' . htmlspecialchars($_GET['u']) . '&preview">Preview</a> ]</div>';
			}
			else
			{
				print '<div class="heading"> Bio </div>';
			}
			
			if($user[0]['desc'] == '' && (!$core->auth || $core->user['id'] != $user[0]['id']))
			{
				print '<div class="tc" style="width: 100%"> ' . $user[0]['username'] . ' does not have a biography. </div>';
			}
			elseif($core->auth && $core->user['id'] == $user[0]['id'] && !isset($_GET['preview']))
			{
				print '<small style="cursor:pointer" title="[br], [center], [b], [h1], [h2], [h3]"> BB CODES (hover) </small>';
				print '<form method="post">';
				print '<input type="hidden" name="cmd" value="update_bio" />';
				print '<textarea name="bio" style="width: 99%;resize: none" rows="15">' . htmlspecialchars($user[0]['desc']) . '</textarea>';
				print '<div style="width: 99%;text-align: center"> <input type="submit" value="Update" /> </div>';
				print '</form>';
			}
			else
			{
				$bb = array(
					'[br]' => '<br />',
					"\n"   => '<br />',
					'[center]' => '<span class="tc" style="width: 100%;display: inline-block;">',
					'[/center]' => '</span>',
					'[b]' => '<b>',
					'[/b]' => '</b>',
					'[h1]' => '<h1>',
					'[/h1]' => '</h1>',
					'[h2]' => '<h2>',
					'[/h2]' => '</h2>',
					'[h3]' => '<h3>',
					'[/h3]' => '</h3>',
					'[center]' => '<center>',
					'[/center]' => '</center>',
				);
				
				print str_replace(array_keys($bb), $bb, htmlspecialchars($user[0]['desc']));
			}
			print '</div>';
		}
		else
		{
			print '<div class="block c1 tc"> User Not Found </div>';
		}
	}
	else
	{
		print '
		<div style="width: 100%;text-align: center;">
			<div class="block c3 tl">
				<div class="heading"> Login to your account </div>
				<table style="width: 99%">
					<form method="post">
						<input type="hidden" name="cmd" value="login" />
						<tr> <td class="tl"> Username </td> <td> <input style="width: 100%" type="text" name="user" /> </td> </tr>
						<tr> <td class="tl"> Password </td> <td> <input style="width: 100%" type="password" name="pass" /> </td> </tr>
						<tr> <td colspan="2"> <input type="submit" value="Login" class="fr" /> </td> </tr>
					</form>
				</table>
			</div>
			
			<div style="width: 10%; display: inline-block;"> <!-- Spacer --> </div>
			
			<div class="block c3 tl">
				<div class="heading"> Register for an account </div>
				<table style="width: 99%">
					<form method="post">
						<input type="hidden" name="cmd" value="register" />
						<tr> <td class="tl"> Username </td> <td> <input style="width: 100%" type="text" name="user" /> </td> </tr>
						<tr> <td class="tl"> Password </td> <td> <input style="width: 100%" type="password" name="pass" /> </td> </tr>
						<tr> <td class="tl"> Email </td> <td> <input style="width: 100%" type="text" name="mail" /> </td> </tr>
						<tr> <td colspan="2"> <input type="submit" value="Register" class="fr" /> </td> </tr>
					</form>
				</table>
			</div>
		</div>
		';
	}
?>