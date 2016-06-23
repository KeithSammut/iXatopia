<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
?>

<div id="referrals" class="notice">
	Referrals? Note: <small>IN PROGRESS (doesn't work yet)</small><br />
	You can find your referral link <a href="/profile">[HERE]</a><br />
	For every user you refer, you will earn credit, which can be redeemed at your profile.
</div>

<div id="reserve" class="notice">
	What is transfer reserve?<br />
	Due to people registering lots and lots of accounts to get free xats, we have re-introduced<br />
	an old rule stating that you cannot transfer or trade xats that you obtain upon registration!
</div>

<div id="rules" class="notice">
	Rule #1: No discussing other iXats or the creation of another private server.<br />
	Rule #2: Do not disrespect another user or staff member. <br />
	Rule #3: No asking for xats/powers or a rank.<br />
	Rule #4: Try to keep the profanity to a minimum, the ages of the users vary and we'd like to keep a clean enviroment.<br />
	Rule #5: Do not advertise your own chat at the Lobby chat room.<br />
	Rule #6: Refrain from testing smilies at the Lobby chat room.<br />
	Rule #7: Inappropriate pictures are allowed, as long as they are not too vulgar in nature.<br />	
        Rule #8: No linking to xat.com chats is tolerated at all while chatting in lobby.<br />
        Rule #9: YOU'RE NOT PERMITTED TO HAVE MORE THAN ONE X4T ACCOUNT UNLESS GIVEN PERMISSION BY X4T STAFF.<br />
        Rule #9: YOU'RE NOT PERMITTED TO SELL YOUR ACCOUNTS FOR ANY CURRENCY INCLUDING X4TS UNLESS GIVEN PERMISSION BY X4T STAFF.
</div>

<div id="trade" class="notice">
	Where do my xats go when someone buys my power? They will go onto your account, you must relogin to retrieve them.<br />
	How do I know when my power has been sold? At the moment there is no way to tell, please be patient.<br />
	Why can't I do private trades or retract one of my powers? These features are still in development.
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