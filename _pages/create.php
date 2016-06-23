<?php
	if(!isset($config->complete))
	{
		return include $pages['home'];
	}
	if(!$core->auth)
	{
		return include $pages['profile'];
	}
	if($core->allset($_POST, 'name', 'pass', 'radio', 'inner'))
	{
		$messages = array();
		
		do
		{
			if(!ctype_alnum($_POST['name']) || strlen($_POST['name']) < 5 || strlen($_POST['name']) > 15)
			{
				$messages[] = 'Your chats name must consist of 5-15 alphanumeric characters.';
			}
			if(strlen($_POST['pass']) < 6)
			{
				$messages[] = 'Your chat needs a password of at least 6 characters';
			}
			if(!empty($messages)) break;
			
			$test = $mysql->fetch_array('select count(*) as `count` from `chats` where `name`=:a;', array('a' => $_POST['name']));
			if($test[0]['count'] > 0)
			{
				$messages[] = 'That chat name has already been taken';
				break;
			}
			
			$mysql->insert('chats',
				array(
					'id' => 'NULL',
					'name' => $_POST['name'],
					'bg' => $_POST['inner'],
					'outter' => '',
					'sc' => '',
					'ch' => '',
					'email' => $core->user['email'],
					'radio' => $_POST['radio'],
					'pass' => $mysql->hash($_POST['pass']),
					'button' => '#000000'
				)
			);
			$mysql->insert('ranks',
				array(
					'userid' => $core->user['id'],
					'chatid' => $mysql->insert_id(),
					'f' => 1
				)
			);
			
			$messages[] = 'Your chat has been created, view it <a href="/' . htmlentities($_POST['name']) . '">HERE</a>';
		} while(false);
		
		foreach($messages as $message)
		{
			print '<div class="message"> ' . $message . ' </div>';
		}
	}
?>

			<font color="white"><div class="heading"> Create a Group </div>
	<small> Fields marked with an (*) are required. </small>
	<table style="width: 99%">
		<form method="post">
			<input type="hidden" name="cmd" value="create" />
			<tr> <td> *Name </td> <td> <input type="text" name="name" style="width: 100%" /> </td> </tr>
			<tr> <td> *Password </td> <td> <input type="password" name="pass" style="width: 100%" /> </td> </tr>
			<tr> <td> Radio </td> <td> <input type="text" name="radio" style="width: 100%" value="http://relay.181.fm:8128" /> </td> </tr>
			<tr> <td> Inner BG </td> <td> <input type="text" name="inner" style="width: 100%" value="<?php print"http://{$config->info["server_domain"]}/web_gear/chat_bgs/" . rand(1, 7) . ".jpg"; ?>" /> </td> </tr>
			<tr> <td colspan="2"> <input type="submit" value="Create Group" class="fr" /> </td> </tr>
		</form>
	</table>
</div>
                  
				<center>  <div class="block c2-3">
	<div class="heading"> Recent Chats </div>
	<?php
		$recent = $mysql->fetch_array('select * from `chats` order by `id` desc limit 0, 4;');
		foreach($recent as $chat)
		{
			print '<div class="block c4">';
			print '<a target="_blank" href="/' . htmlentities($chat['name']) . '">';
			print '<img src="' . htmlentities($chat['bg']) . '" width="158" height="107" />';
			print '<div class="heading nb">' . htmlentities($chat['name']) . '</div>';
			print '</a>';
			print '</div>';
		}
	?>
</div>