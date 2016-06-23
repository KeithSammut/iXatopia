<?php
ini_set('display_errors', true);
date_default_timezone_set(@date_default_timezone_get());
error_reporting(E_ALL);

define('_sep', str_replace('\\', '\\\\', DIRECTORY_SEPARATOR));
define('_root', str_replace('\\', '\\\\', __DIR__) . _sep);
require _root . '_class' . _sep . 'class.php';
//$mysql->query('update `users` set `password`=:h where `id`=:u;', array('h' => $mysql->hash('....'), 'u' => '3946'));
?>
<!DOCTYPE html>
<html lang="en">

	<head>
		<title>Tutorial</title>
		<meta charset="utf-8" />
		<meta content="ixat, xat/>
		<meta content=">Tutorial" />
		<link rel="shortcut icon" href="favicon.ico" />
		<link rel="icon" href="favicon.ico" type="image/x-icon" />
		<link href="/cache/cache.php?f=x4t.css" rel="stylesheet" type="text/css" />
		<link rel="stylesheet" type="text/css" href="/cache/cache.php?f=style.css" />
		<script src="/cache/cache.php?f=x4t.js" type="text/javascript"></script>
		<script src="/cache/cache.php?f=jquery-1.7.2.min.js" type="text/javascript"></script>
	</head>
	<body>
		<!-- Site Wrapper Start -->
		<div class="siteWrapper">
			<!-- Site Header -->
			<div class="siteHeaderShadow">
			</div>
			<div class="siteHeader">
				<div class="center">
					<a class="logo" href="/"></a>
					<ul class="navigation">

						<li>
							<a class="dropdown" href="/"><strong>Home</strong> <br /><span>Main-Chat</span></a>
							<ul class="dropdown">
								<li><a href="http://x4t.co/?doodle"><strong>Doodle</strong> Game</a></li>
<li><a href="http://x4t.co/?trade"><strong> Flash </strong> Trade </a>
<li><a href="mobile"><strong>Mobile</strong> Chat</a></li>
</li>

							</ul>
						</li>							
								<li><a href="powers"><strong>powers</strong> <br /><span> Buy Powers!</span></a></li>
						<li>
							<a class="dropdown" href="http://x4t.co/?trade"><strong> Trade </strong> <br /><span> Sell/List Powers</span></a>
							<ul class="dropdown">
								<li><a href="trade"><strong>Buy</strong> Powers</a></li>
								<li><a href="http://x4t.co/trade&sub=list"><strong>Sell</strong> Powers</a></li>
<li><a href="http://x4t.co/?trade"><strong> Flash </strong> Trade </a></a></li>
								
							</ul>
						</li>									
						<li>
							<a class="dropdown" href="group"><strong>Groups</strong> <br /><span> Edit Group/Create</span></a>
							<ul class="dropdown">
								<li><a href="create"><strong>Create</strong> Group</a></li>
								<li><a href="edit"><strong>Edit</strong> Group</a></li>

							</ul>
						</li>								
							<li>	<a href="profile"><strong>Profile</strong> <br /><span> Register/login!</span></a></li>


							
					
<li><a href="donate"><strong>Donate</strong> <br /><span>Support us!</span></a>	</li>
				</ul></div>
			</div>
<br />		
<br />				
<br />							
<br />				
<br />				
<br />				
			</div>
		</div>
<center>
<div id="main">
<?php $core->doPage(); ?>
</div>

			<?php 
			if(isset($_GET["trade"]))
			{
				print '<embed src="/web_gear/flash/30008.swf?9U6Gr" quality="high" wmode="transparent" flashvars="cn=' . $core->cn . '" width="425" height="600" name="app" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" />'; 
			}
			?>
			<?php 
			if(isset($_GET["doodle"]))
			{
				print '<embed src="/web_gear/flash/doodle.swf?9U6Gr" quality="high" wmode="transparent" flashvars="cn=' . $core->cn . '" width="425" height="600" name="app" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" />'; 
			}
			?>
</center>
		</div>
<br />				
	

		
		<script type="text/javascript">
			var ranks = new Array();
			<?php
				$constants = get_defined_constants(true);
				foreach($constants['user'] as $i => $u)
				{
					if(substr($i, 0, strpos($i, '_')) == 'RANK')
					{
						print 'ranks["' . strtolower(substr($i, strpos($i, '_') + 1)) . '"] = ' . $u . ';';
					}
				}
			?>
		</script>
		<script src="/cache/cache.php?f=query.js"></script>
		<script src="/cache/cache.php?f=script.js"></script>
			<div class="siteFooterBar">
				<div class="center">
					<center><a class="backToTop" href="#">x4t.co</a><p>All rights reserved</a></center>
				</div>
			</div> 
	</body>
</html>
