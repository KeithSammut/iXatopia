

<?php
	$chats = $mysql->fetch_array('select * from `chats` order by rand() limit 0, 15;');
	foreach($chats as $chat)
	{
		print '<div class="block c5">';
		print '<a target="_blank" href="/' . htmlentities($chat['name']) . '">';
		print '<img src="' . htmlentities($chat['bg']) . '" width="195" height="130" />';
		print '<div class="heading nb">' . htmlentities($chat['name']) . '</div>';
		print '</a>';
		print '</div>';
	}
?>