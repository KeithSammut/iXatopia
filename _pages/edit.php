<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
	
	/*
	if(!$core->auth || $core->user['id'] != 1000)
	{
		return include $pages['profile'];
	}
	*/
	
	if(isset($_POST['gn']) && isset($_POST['gp']) && count($_POST) == 2)
	{
		$json = new stdClass();
		try
		{
			if(count(array_filter($_POST, 'is_string')) != count($_POST))
			{
				throw new Exception('All inputs must be string variables.');
			}
			$chat = $mysql->fetch_array('select * from `chats` where `name`=:name', array('name' => $_POST['gn']));
			if(count($chat) == 0 || !$mysql->validate($_POST['gp'], $chat[0]['pass']))
			{
				throw new Exception('Incorrect group information');
			}
			$json->html = $core->getEmbed($_POST['gn'], $_POST['gp']);
			$json->chatInnr = htmlspecialchars($chat[0]['bg']);
			$json->chatScrl = htmlspecialchars($chat[0]['sc']);
			$json->chatRdio = htmlspecialchars($chat[0]['radio']);
			$json->chatBttn = htmlspecialchars($chat[0]['button']);
			$json->chatAtch = htmlspecialchars($chat[0]['attached']);
			$json->response = 'success';
		}
		catch(Exception $e)
		{
			$json->response = $e->getMessage();
		}
		print json_encode($json);
		exit;
	}
	elseif(count($_POST) > 0)
	{
		$json = new stdClass();
		$json->response = 'success';
		$json->message = '';
		
		do
		{
			if(count(array_filter($_POST, 'is_string')) != count($_POST))
			{
				$json->message .= 'All inputs must be string variables.';
			}
			
			foreach(array('gn', 'gp', 'innr', 'scrl', 'rdio', 'bttn', 'atch') as $u)
			{
				if(!isset($_POST[$u]))
				{
					$json->message .= $u . ' is not set' . chr(10);
				}
			}
			
			if($json->message != '')
			{
				$json->response = 'failure';
				break;
			}
			
			$chat = $mysql->fetch_array('select * from `chats` where `name`=:name', array('name' => $_POST['gn']));
			if(count($chat) == 0 || !$mysql->validate($_POST['gp'], $chat[0]['pass']))
			{
				throw new Exception('Incorrect group information');
			}
			unset($_POST['gn']);
			unset($_POST['gp']);
			if(count($_POST) != 5)
			{
				$json->message = 'nice try';
			}
			else
			{
				$update  = 'update `chats` set ';
				$update .= '`bg`=:innr, ';
				$update .= '`sc`=:scrl, ';
				$update .= '`radio`=:rdio, ';
				$update .= '`button`=:bttn, ';
				$update .= '`attached`=:atch ';
				$update .= 'where `id`=' . $chat[0]['id'] . ';';
				
				$mysql->query($update, $_POST);
				$json->message = 'Updated';
			}
		} while(false);
		
		print json_encode($json);
		exit;
	}
?>
<div class="block c4">
	<div class="edit_group_login block c1">
		<div class="heading"> <font color="white">Edit Group </div></font>
		<table style="width: 99%">
			<tr> <td> <input type="text" class="egroupname" placeholder="Group Name" style="width: 100%"> </td> </tr>
			<tr> <td> <input type="password" class="egrouppass" placeholder="Password" style="width: 100%"> </td> </tr>
			<tr> <td class="fr"> <input type="submit" class="egroupsub" value="Group Name" /></td> </td>
		</table>
	</div>
	
	<div class="edit_group_conf block c1" style="display: none">
		<div class="heading"> Configuration[<small>extremely basic c:</small>] </div>
		<table style="width: 99%">
			<tr> <td> <input type="text" class="eg_innr" placeholder="Inner BG" style="width: 100%"> </td> </tr>
			<tr> <td> <input type="text" class="eg_scrl" placeholder="Scroller" style="width: 100%"> </td> </tr>
			<tr> <td> <input type="text" class="eg_rdio" placeholder="Radio" style="width: 100%"> </td> </tr>
			<tr> <td> <input type="text" class="eg_bttn" placeholder="Button Color" style="width: 100%"> </td> </tr>
			<tr> <td> <input type="text" class="eg_atch" placeholder="Attached Chat" style="width: 100%"> </td> </tr>
			<tr> <td class="fr"> <input type="submit" class="eg_submit" value="Update Chat" /></td> </td>
		</table>
	</div>
</div>
<div class="block c4-5">
	<div class="heading"> <font color="white">Your group will show up here. </div></font>
	<div class="edit_group_box">
		
	</div>
</div></font>