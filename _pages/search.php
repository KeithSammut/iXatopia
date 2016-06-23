<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
	
	if(isset($_POST['search']) && is_string($_POST['search']))
	{
		$json = new stdClass();
		
		$json->messages = $mysql->fetch_array('select `message`, `name`, `mid` from `messages` where `message` like :a order by `id` desc limit 0, 25;', array('a' => '%' . $_POST['search'] . '%'));
		
		print json_encode($json);
		exit;
	}
	
	if(isset($_POST['del']) && is_numeric($_POST['del']) && $_POST['del'] != '' && $core->user['rank'] == RANK_ADMIN)
	{
		$json = new stdClass();
		
		$delete = $mysql->query('delete from `messages` where `mid`=:message;', array('message' => $_POST['del']));
		
		$json->status = $delete ? 'SUCCESS' : 'FAILURE';
		
		print(json_encode($json));
		exit;
	}
?>

<div class="block c4">
	<div class="heading"> Search Message </div>
	<table style="width: 99%">
		<tr> <td> <input type="text" class="msearchinput" placeholder="Search" style="width: 100%"> </td> <td class="fr"> <input type="submit" class="msearchsubmit" value="Search" /></td> </td>
	</table>
</div>

<div class="block c4-5">
	<div class="heading"> Messages </div>
	<div class="block c1 showmessages">
	
	</div>
</div>