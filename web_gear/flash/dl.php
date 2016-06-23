<?php
/*
 * This file is used to download missing smilies dynamically
 * from xat (via the chat.swf) in order to remove the unbearable
 * load of having to do it yourself ^-^
 *   - Removing this file will break its use
 * */

ini_set('display_errors', true);
error_reporting(E_ALL);

if(isset($_GET['smileyname']) && isset($_GET['smileyurl']))
{
	file_put_contents($_GET['smileyname'], file_get_contents($_GET['smileyurl']));
}
else
{
	print __DIR__;
}