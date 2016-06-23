<?php
	if(!isset($config->complete))
	{
		return include $pages['home'];
	}
?>
	<form method="post">
		<table align="center" class="logintable" width="100%">
			<?php
				if(!isset($core->mobileNoLogin))
				{
					print '<tr> <td style="text-align:center"> <input autocomplete="off" type="text" name="username" placeholder="username" /> </td> </tr>';
					print '<tr> <td style="text-align:center"> <input autocomplete="off" type="password" name="password" placeholder="password" /> </td> </tr>';
				}
				
				if(!isset($_GET['room']) || isset($core->mobileForceRoom))
				{
					print '<tr> <td style="text-align:center"> <input autocomplete="off" type="text" name="room" placeholder="room" /> </td> </tr>';
				}
			?>
			
			<tr> <td style="text-align:center"> <input type="submit" value="Login" /> </td> </tr>
		</table>
	</form>