<?php
	if(!isset($config->complete))
	{
		return include $pages['home'];
	}
	
	if(!$core->auth)
	{
		return include $pages['profile'];
	}
?>


<script type="text/javascript">
	var usd = null;
	var xats = null;
	var days = null;
	
	function doCalc()
	{
		if(usd != null)
		{
			var amount = parseInt(usd.value);
			if(!isNaN(amount))
			{
				xats.innerHTML = Math.floor(amount * 6000);
				days.innerHTML = Math.floor(amount * 300);
			}
			else
			{
				xats.innerHTML = 0;
				days.innerHTML = 0;
			}
		}
	}
	
	window.onload = function()
	{
		usd = document.getElementById("usd");
		xats = document.getElementById("xats");
		days = document.getElementById("days");
	}
</script>

<div class="tc block c2">
	<div class="heading"> How much will you get? </div><br /><br />
	I would like to spend $<input onkeyup="doCalc()" type="text" id="usd" value="0" /> (USD)<br /><br />
	For $<span id="price">0</span> <i>(USD)</i>, you will get <span id="xats">0</span> xats and <span id="days">0</span> days!
</div>

<div class="tc block c2">
	<div class="heading"> Donate Here </div><br /><br />
<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_donations">
<input type="hidden" name="business" value="x4tpmt@gmail.com">
<input type="hidden" name="lc" value="US">
<input type="hidden" name="item_name" value="X4ts">
<input type="hidden" name="no_note" value="0">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>

</div>