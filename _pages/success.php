<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
?>

<div id="success" class="notice">
	Please submit a ticket to support.x4t.co if you do not get credited your purchase within 2 hours of payment.<br />
	If you open a dispute on your donation, you'll be banned from using X4T and your information will be reported. <br />
</div>

<script type="text/javascript">
	function sliceFAQ()
	{
		var hash = location.hash.slice(1);
		
		if(hash.length > 0)
		{
			var doco = document.getElementById(hash);
			var notices = document.getElementsByClassName('notice');
			
			if(doco)
			{
				for(var i in notices)
				{
					if(notices[i].style != undefined)
					{
						notices[i].style.display = 'none';
					}
				}
				
				doco.style.display = 'inline-block';
			}
		}
	}
	
	window.onload = sliceFAQ;
</script>