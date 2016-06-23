<?php
	if(isset($config->complete))
	{
		return include $pages['home'];
	}
	
	$messages = array();
	switch(isset($_POST['cmd']) ? $_POST['cmd'] : 'nope')
	{
		case 'admin':
			if($core->allset($_POST, 'user', 'pass') && isset($config->server_ip) && !isset($config->complete))
			{
				if(strlen($_POST['user']) < 3 || !ctype_alnum($_POST['user']))
				{
					$messages[] = 'Admin username must be at least 3 alpha-numeric characters long.';
				}
				if(strlen($_POST['pass']) < 6)
				{
					$messages[] = 'Admin Password has to be larger than 5 characters.';
				}
				if(!empty($messages)) break;
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
					'days' => time() + ($config->xats * 86400),
					'email' => 'pulse@' . $_SERVER['SERVER_ADDR'],
					'powers' => '',
					'enabled' => 1,
					'transferblock' => '',
					'connectedlast' => '',
					'rank' => '2'
				);
				$mysql->insert('users', $vals);
				$mysql->insert('ranks', array(
					'userid' => $mysql->insert_id(),
					'chatid' => 1,
					'f' => 1
				));
				array_push($messages, 'Registration Complete.');
				$config->complete = true;
			}
			break;
		case 'server': // bind, addr, port, domain, xats, days
			if($core->allset($_POST, 'bind', 'addr', 'port', 'domain', 'xats', 'days') && $config && !isset($config->server_ip))
			{
				$mysql->insert('server',
					array(
						'ipbans' => '',
						'pid' => 0,
						'tax' => 0,
						'ipc' => '',
						'connect_ip' => $_POST['addr'],
						'server_ip' => $_POST['bind'],
						'server_pt' => $_POST['port'],
						'backup_pt' => $_POST['bport'],
						'server_domain' => $_POST['domain'],
						'verification' => 0,
						'starting_xats' => $_POST['xats'],
						'starting_days' => $_POST['days'],
						'max_per_ip' => 15,
						'max_total' => 400,
						'staff' => '["1000"]'
					)
				);
				
				$test = $mysql->fetch_array('select count(*) as `count` from `server`;');
				
				if($test[0]['count'] == 0)
				{
					array_push($messages, 'An error has occured, try again.');
				}
				else
				{
					array_push($messages, 'Server information has been imported.');
					$config->server_ip = true;
				}
			}
			break;
		case 'mysql':
			if($core->allset($_POST, 'host', 'user', 'pass', 'name') && !$config)
			{
				$mysql = new Database($_POST['host'], $_POST['user'], $_POST['pass']);
				if(!is_object($mysql))
				{
					array_push($messages, 'We couldn\'t connect to your MySQL Host, please try again.');
					break;
				}
				
				$fh = @fopen(_root . '_class' . _sep . 'config.php', 'w');
				if(!fwrite($fh, '<?php $config = (object) array( \'db\' => array( 0 => \'' . $_POST['host'] . '\',  1 => \'' . $_POST['user'] . '\',  2 => \'' . $_POST['pass'] . '\',  3 => \'' . $_POST['name'] . '\' ) ); ?>'))
				{
					array_push($messages, 'We couldn\'t write the file "' . _root . '_class' . _sep . 'config.php" chmod maybe?');
					break;
				}
				fclose($fh);
				
				if(!$mysql->setDB($_POST['name']))
				{
					if(!$mysql->query('create database `' . $_POST['name'] . '`;') || !$mysql->setDB($_POST['name']))
					{
						array_push($messages, 'We can connect to the mysql server, but the database `' . $_POST['name'] . '` does not exist, and I could not create it.');
					}
				}
				
				$mysql->exec(file_get_contents(_root . '_server' . _sep . 'database.sql'));
				
				$tcount = $mysql->fetch_array('select count(*) from `powers`;');
				
				if(!@$tcount[0]['count(*)'])
				{
					array_push($messages, 'We could connect to the database, but we\'re having trouble creating the tables. Do I have permission?');
					break;
				}
				
				if(file_exists(_root . '_class' . _sep . 'config.php'))
				{
					include _root . '_class' . _sep . 'config.php';
				}
				array_push($messages, $config ? 'MySQL has been configured.' : 'We had trouble writing the file /_class/config.php');
			}
			break;
	}
	
	foreach($messages as $post)
	{
		print '<div class="message">' . $post . '</div>';
	}
?>


<div class="block c3 tc">
	<div class="heading tl"> MySQL Information <?php if($config) print '<div class="tick fr">&#x2714;</div>'; ?> </div>
	<?php
		if($config)
		{
			print 'MySQL is setup, please move on to the next step.';
		}
		else
		{
			print '<table style="width: 99%">';
			print '<form method="post">';
			print '<input type="hidden" name="cmd" value="mysql">';
			print '<tr> <td class="tl"> MySQL Host </td> <td> <input type="text" style="width: 100%" name="host" value="127.0.0.1" /> </td> </tr>';
			print '<tr> <td class="tl"> MySQL Username </td> <td> <input type="text" style="width: 100%" name="user" value="root" /> </td> </tr>';
			print '<tr> <td class="tl"> MySQL Password </td> <td> <input type="password" style="width: 100%" name="pass"> </td> </tr>';
			print '<tr> <td class="tl"> Database name </td> <td> <input type="text" style="width: 100%" name="name" value="pulse"> </td> </tr>';
			print '<tr> <td colspan="2" class="f0px"> <input type="submit" value="Setup" class="fr" /> </td> </tr>';
			print '</form>';
			print '</table>';
		}
	?>
</div>

<div class="block c3 tc">
	<div class="heading tl"> Server Information <?php if(isset($config->server_ip)) print '<div class="tick fr">&#x2714;</div>'; ?> </div>
	<?php
		if(!$config)
		{
			print 'You need to setup MySQL before you can do this.';
		}
		elseif(isset($config->server_ip))
		{
			print 'The server settings have been written, next step.';
		}
		else
		{
			print '<small>Hover over a row for its description</small>';
			print '<table style="width: 99%">';
			print '<form method="post">';
			print '<input type="hidden" name="cmd" value="server">';
			print '<tr> <td class="tl pnt" title="The address server.php will bind to"> Bind IP </td> <td> <input type="text" style="width: 100%" name="bind" value="0.0.0.0" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="This is the address that chat.swf will connect to"> Server IP </td> <td> <input type="text" style="width: 100%" name="addr" value="' . $_SERVER['SERVER_ADDR'] . '" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="The port server.php will listen on"> Server Port </td> <td> <input type="text" style="width: 100%" name="port" value="1204" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="The port server.php will listen on for debug testing"> Debug Port </td> <td> <input type="text" style="width: 100%" name="bport" value="1205" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="your_site.com"> Domain </td> <td> <input type="text" style="width: 100%" name="domain" value="' . $_SERVER['SERVER_NAME'] . '" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="If you need a description, you shouldn\'t be doing this."> Start Xats </td> <td> <input type="text" style="width: 100%" name="xats" value="10000" /> </td> </tr>';
			print '<tr> <td class="tl pnt" title="If you need a description, you shouldn\'t be doing this."> Start Days </td> <td> <input type="text" style="width: 100%" name="days" value="1000" /> </td> </tr>';
			print '<tr> <td colspan="2" class="f0px"> <input type="submit" value="Setup" class="fr" /> </td> </tr>';
			print '</form>';
			print '</table>';
		}
	?>
</div>

<div class="block c3 tc">
	<div class="heading tl"> Admin Account <?php if(isset($config->complete)) print '<div class="tick fr">&#x2714;</div>'; ?> </div>
	<?php
		if(isset($config->complete))
		{
			print 'Setup complete. <a href="/">Go Home</a>';
		}
		elseif(!isset($config->server_ip))
		{
			print 'You need to setup your server settings first.';
		}
		else
		{
			print '<table style="width: 99%">';
			print '<form method="post">';
			print '<input type="hidden" name="cmd" value="admin">';
			print '<tr> <td class="tl"> Username </td> <td> <input type="text" style="width: 100%" name="user" value="Pulse" /> </td> </tr>';
			print '<tr> <td class="tl"> Password </td> <td> <input type="password" style="width: 100%" name="pass" /> </td> </tr>';
			print '<tr> <td colspan="2" class="f0px"> <input type="submit" value="Register" class="fr" /> </td> </tr>';
			print '</form>';
			print '</table>';
		}
	?>
</div>