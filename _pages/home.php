<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
	
	if($core->auth == false && isset($_GET['ref']) && is_numeric($_GET['ref']))
	{
		setCookie('referral', $_GET['ref'], strtotime("+ 1 year"));
	}
	
	
	if($core->auth && $core->user['credit'] > 0)
	{
		print '<div class="message">You have ' . $core->user['credit'] . ' xats waiting to be claimed as credits (Claimable at profile)</div>';
	}
?>
<br />
<center>
<div id="news" class="notice2">
	X4T.co is under new ownership. Also No-Longer collaboration with EBRadio & Welzy Is Demoted. <br />Happy Halloween >;]<br/>
	The <b>Latest</b> power is <b>ksun</b> released in store for 3500 xats(limited)<br />
	ksun Limited Pawns are (hat#hg)(hat#h8)(hat#hj)(hat#h9) & other pawns are (hat#h7) (hat#h6)(hat#h5)<br />
	To use <b>PCBACK</b> Put #http://i.imgur.com/aeSovfh.jpg after your avatar (replace the imgur link with the image url you want)<br />
	If you cannot sign into the chat, please visit x4t.co/profile and press re-login to access the chat.<br />
</div>
</center>
<div class="block c3-4">
	<?php print $core->getEmbed('lobby', false, 728, 486); ?>
</div>


<script type="text/javascript">
	var hours;
	var minutes;
	var seconds;
	var timer;
	
	window.onload = function()
	{
		hours = document.getElementById("hours");
		minutes = document.getElementById("minutes");
		seconds = document.getElementById("seconds");
		
		timer = setInterval(
			function()
			{
				var secs = parseInt(seconds.innerHTML);
				var mins = parseInt(minutes.innerHTML);
				var hrs = parseInt(hours.innerHTML);
				
				if(secs == 0 && mins == 0 && hrs == 0)
				{
					clearInterval(timer);
				}
				else
				{
					secs = secs - 1;
					
					if(secs < 0)
					{
						secs = 59;
						mins = mins - 1;
						
						if(mins < 0)
						{
							mins = 59;
							hrs = hrs - 1;
							
							if(hrs >= 0)
							{
								hours.innerHTML = hrs;
							}
						}
						
						minutes.innerHTML = mins;
					}
					
					seconds.innerHTML = secs;
				}
			}, 1000
		);
	}
</script>

