<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
	if(!$core->auth)
	{
		return include $pages['profile'];
	}
	
	if(isset($_POST['storebuy']) && ctype_alnum($_POST['storebuy']))
	{
		$json = new stdClass();
		$json->status = 'ok';
		
		try
		{
			$power = $mysql->fetch_array('select * from `powers` where `name`=:power;', array('power' => $_POST['storebuy']));
			
			if(empty($power))
			{
				throw new Exception('An error has occured.');
			}
			
			if($core->user['xats'] < $power[0]['cost'])
			{
				throw new Exception('You don\'t have enough xats to buy that power.');
			}
			
			if($power[0]['limited'] == 1)
			{
				if($power[0]['amount'] == 0)
				{
					throw new Exception('The power you are trying to buy is limited.');
				}
				
				$special = $mysql->fetch_array('select `purchased` from `userpowers` where `userid`=' . $core->user['id'] . ' and `purchased`>' . (time() - 180) . ';');
				if(count($special) > 0)
				{
					throw new Exception('You can\'t purchase this power again for another ' . ($special[0]['purchased'] - (time() - 180)) . ' seconds.');
				}
				else
				{
					$mysql->query('update `powers` set `amount`=`amount`-1 where `id`=' . $power[0]['id'] . ';');
				}
			}
			
			$mysql->query('update `users` set `xats`=`xats`-' . $power[0]['cost'] . ' where `id`=' . $core->user['id'] . ';');
			$core->user['xats'] -= $power[0]['cost'];
			setCookie('xats', $core->user['xats']);
			
			$upowers = $mysql->fetch_array('select * from `userpowers` where `userid`=' . $core->user['id'] . ' and `powerid`=' . $power[0]['id'] . ';');
			if(empty($upowers))
			{
				$mysql->insert('userpowers', array('userid' => $core->user['id'], 'powerid' => $power[0]['id'], 'count' => 1, 'purchased' => time()));
			}
			else
			{
				$mysql->query('update `userpowers` set `count`=`count`+1, `purchased`=' . time() . ' where `userid`=' . $core->user['id'] . ' and `powerid`=' . $power[0]['id'] . ';');
			}
			
			$json->relogin = $core->refreshLogin(false);
		}
		catch(Exception $e)
		{ $json->status = $e->getMessage(); }
		
		print json_encode($json);
		exit;
	}
?>

<div class="block c1">
	<div class="tc">
		<i> Hover over the power NAME for any smilies associated with the power (if there are any) </i>
	</div>
	
	<div class="block c3">
		<div class="heading"> Search by name </div>
		<small> Powers will update as you type. </small>
		<input type="text" class="psearchname" style="width: 98%" />
	</div>
	
	<div class="block c3 fr">
		<div class="heading"> Search by price <i>NOT WORKING DUE TO UPDATE</i> </div>
		<small> Powers will update as you type. </small>
		<table style="width: 99%">
			<tr> <td>
				<select class="psearchprice0">
					<option selected="selected"> Below </option>
					<option> Above </option>
					<option> Exactly </option>
				</select>
			</td> <td>
				<input type="text" class="psearchprice" style="width: 100%" />
			</td> </tr>
		</table>
	</div>
</div>

<div class="block c1" id="pages"></div>
<div class="block c1" id="powerBlock"></div>

<script type="text/javascript">
	<?php
		$powers = $mysql->fetch_array('select `id`, `name`, `topsh`, `cost`, `limited`, `amount` from `powers` where `name` not like \'%(Undefined)%\' and name not in(\'allpowers\', \'chrome\', \'everypower\') and `subid`<2147483647;');
		print "var powers = JSON.parse('" . json_encode($powers) . "');\n";
		print "var usepowers = [];";
	?>
	
	window.onload = function()
	{
		usepowers = powers;
		setPages();
		loadPage(1);
	}
	
	function setPages()
	{
		var pwr_max = usepowers.length;
		var pwr_pages = pwr_max / 20 + (pwr_max % 20 == 0 ? 0 : 1);
		var pages = document.getElementById('pages');
		pages.innerHTML = 'Pages: ';
		
		for(var i = 1; i <= pwr_pages; i++)
		{
			pages.innerHTML += '&nbsp;<span class="pnt hus" onclick="loadPage(' + i + ')">' + i.toString() + '</span>&nbsp;';
		}
	}
	
	function loadPage(page)
	{
		var index = (page - 1) * 20;
		var on_page = usepowers.slice(index, index + 20);
		var power = null;
		var element = null;
		var html = null;
		var powerBlock = document.getElementById('powerBlock');
		powerBlock.innerHTML = "";
		
		if(on_page.length > 0)
		{
			
			for(var pwrIndex in on_page)
			{
				power = on_page[pwrIndex];
				
				html  = '<div class="block c5">';
				html += '<div class="heading pnt" title="' + power["topsh"] + '">' + power["name"] + '</div>';
				html += '<table style="width:99%" title="">';
				html += '<input type="hidden" class="xats" value="' + power["cost"] + '" />';
				html += '<tr class="price"> <td> Price </td> <td class="tr price"> ' + power["cost"] + ' xats </td> </tr>';
				status = "unlimited";
				if(parseInt(power['limited']) == 1)
				{
					if(parseInt(power['amount']) > 0)
					{
						status = power['amount'] + " available";
					}
					else
					{
						status = "Limited";
					}
				}
				
				html += '<tr> <td> Status </td> <td class="tr"> ' + status + ' </td> </tr>';
				html += '<tr> <td colspan="2" class="tc preview pnt" onclick="$(this).parent().siblings(\'.pvbox\').toggle(400);"> Click for preview </td> </tr>';
				html += '<tr class="pvbox"> <td colspan="2" class="tc"><embed height="60%" width="60%" type="application/x-shockwave-flash" quality="high" src="/web_gear/flash/sm2/' + power['name'] + '.swf?r=2" wmode="transparent" /> </td> </tr>';
				html += '<tr> <td colspan="2" class="tc"> <input type="submit" onclick="storeBuy(this)" class="storebuy" value="Buy Power" style="width: 100%" name="p' + power['name'] + '" /> </td> </tr>';
				html += '</table></div>';
				
				powerBlock.innerHTML += html;
			}
		}
		else
		{
			powerBlock.innerHTML = '<div class="block c1 tc"> No powers were found </div>';
		}
		
		function escape(text) {
			return text
			  .replace(/&/g, "&amp;")
			  .replace(/</g, "&lt;")
			  .replace(/>/g, "&gt;")
			  .replace(/"/g, "&quot;")
			  .replace(/'/g, "&#039;");
		}
	}
</script>