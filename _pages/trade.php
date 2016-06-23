<?php
	if(!isset($config->complete))
	{
		return include $pages['setup'];
	}
	
	if(!$core->auth) // || !in_array($core->user['id'], array('1', '69', '17')))
	{
		return include $pages['profile'];
	}
	
	switch(isset($_GET['sub']) ? $_GET['sub'] : '')
	{
		case 'buyPower':
			$json = new stdClass();
			if(!isset($_POST['power']))
			{
				$json->status = '...';
			}
			else
			{
				$power = $mysql->fetch_array('select * from `trade` where `id`=:trade and `count`>0;', array('trade' => $_POST['power']));
				if(count($power) < 1)
				{
					$json->status = 'That power has already been sold...';
				}
				else
				{
					$power = $power[0];
					if($power['price'] > $core->user['xats'])
					{
						$json->status = "You don't have enough xats to buy that power.";
					}
					else
					{
						$mysql->query('update `users` set `xats`=`xats`-' . $power['price'] . ' where `id`=' . $core->user['id'] . ';');
						$mysql->query('update `users` set `xats`=`xats`+' . $power['price'] . ' where `id`=' . $power['userid'] . ';');
						$core->user['xats'] -= $power['price'];
						setCookie('xats', $core->user['xats']);
						
						$test = $mysql->fetch_array('select count(*) from `userpowers` where `userid`=' . $core->user['id'] . ' and `powerid`=' . $power['powerid'] . ';');
						if($test[0]['count(*)'] > 0)
						{
							$mysql->query('update `userpowers` set `count`=`count`+1 where `userid`=' . $core->user['id'] . ' and `powerid`=' . $power['powerid'] . ';');
						}
						else
						{
							$mysql->insert('userpowers', array('userid' => $core->user['id'], 'powerid' => $power['powerid'], 'count' => 1));
						}
						
						/*
						if($power['count'] > 1)
						{
							$mysql->query('update `trade` set `count`=`count`-1 where `id`=' . $power['id'] . ';');
						}
						else
						{
							$mysql->query('delete from `trade` where `id`=' . $power['id'] . ';');
						}
						*/
						
						$mysql->query('update `trade` set `count`=`count`-1 where `id`=' . $power['id'] . ';');
						
						$json->status = 'ok';
						$json->relogin = $core->refreshLogin(false);
					}
				}
			}
			
			print json_encode($json);
			break;
		
		case 'listPower':
			$json = new stdClass();
			$json->status = '...';
			
			if(isset($_POST['power']) && isset($_POST['price']))
			{
				$upower = $mysql->fetch_array('select * from `userpowers` where `userid`=:u and `powerid`=:p;', array('u' => $core->user['id'], 'p' => $_POST['power']));
				if(count($upower) == 0)
				{
					$json->status = "You don't have that power...";
				}
				else
				{
					$power = $mysql->fetch_array('select `cost`, `name` from `powers` where `id`=:p;', array('p' => $upower[0]['powerid']));
					if(in_array($power[0]['name'], array('everypower', 'allpowers', 'bot5', 'staff', 'namecolor')))
					{
						$json->status = "You can't trade that power!";
					}
					else
					{
						$trade = $mysql->fetch_array('select `count` from `trade` where `userid`=:u and `powerid`=:p;', array('u' => $core->user['id'], 'p' => $upower[0]['powerid']));
						if($power[0]['cost'] * 0.8 > $_POST['price'])
						{
							$json->status = "Minimum price is " . floor($power[0]['cost'] * 0.8);
						}
						elseif($power[0]['cost'] * 1.2 < $_POST['price'])
						{
							$json->status = "Maximum price is " . floor($power[0]['cost'] * 1.2);
						}
						else
						{
							if($upower[0]['count'] == 1)
							{
								$mysql->query('delete from `userpowers` where `userid`=:u and `powerid`=:p;', array('u' => $core->user['id'], 'p' => $upower[0]['powerid']));
							}
							else
							{
								$mysql->query('update `userpowers` set `count`=`count`-1 where `userid`=:u and `powerid`=:p;', array('u' => $core->user['id'], 'p' => $upower[0]['powerid']));
							}
							
							if(count($trade) == 0)
							{
								$mysql->insert('trade', array('userid' => $core->user['id'], 'powerid' => $upower[0]['powerid'], 'price' => $_POST['price'], 'count' => 1, 'private' => 0));
							}
							else
							{
								$mysql->query('update `trade` set `count`=`count`+1 where `userid`=:u and `powerid`=:p;', array('u' => $core->user['id'], 'p' => $upower[0]['powerid']));
							}
							
							$json->status = 'ok';
							$json->relogin = $core->refreshLogin(false);
						}
					}
				}
			}
			
			print json_encode($json);
			break;
		
		
		
		
		
		
		case 'list':
			print '<script type="text/javascript" src="/cache/cache.php?f=trade.js&v=2"></script>';
			$trade = $mysql->fetch_array('select * from `userpowers` where `userid`=' . $core->user['id'] . ' order by `powerid` asc;');
			$pwers = $mysql->fetch_array('select `id`, `name`, `cost` from `powers`;');
			$sales = array();
			
			foreach($trade as $i => $sale)
			{
				$sale = array_merge($sale, $pwers[$sale['powerid']]);
				if(!in_array($sale['name'], array('allpowers', 'everypower')))
				{
					$sales[] = $sale;
				}
			}
			
			print '<div class="block c1" id="search"><div class="block c3"><div class="heading"> Search by name </div><small> Powers will update as you type. </small><input type="text" class="psearchname" onkeyup="do_tradeSearch()" style="width: 98%" /></div></div>';
			print '<div class="block c1" id="pages"></div>';
			print '<div class="block c1" id="powers"></div>';
			print '<script type="text/javascript">window.onload = writeTrade(' . json_encode($sales) . ', "list");</script>';
			break;
			
		default:
			print '<script type="text/javascript" src="/cache/cache.php?f=trade.js&v=2"></script>';
			$trade = $mysql->fetch_array('select * from `trade` where `private`=0 and `userid`!=' . $core->user['id'] . ' and `count`>0;');
			$pwers = $mysql->fetch_array('select `name`, `cost` from `powers`;');
			$sales = array();
			
			foreach($trade as $i => $sale)
			{
				$sale = array_merge($sale, $pwers[$sale['powerid']]);
				$sales[] = $sale;
			}
			
			print '<div class="message"><i> Only other people can see the powers that you have listed. </i></div>';
			print '<div class="block c1" id="search"><div class="block c3"><div class="heading"> Search by name </div><small> Powers will update as you type. </small><input type="text" class="psearchname" onkeyup="do_tradeSearch()" style="width: 98%" /></div></div>';
			print '<div class="block c1" id="pages"></div>';
			print '<div class="block c1" id="powers"></div>';
			print '<script type="text/javascript">window.onload = writeTrade(' . json_encode($sales) . ', "trades");</script>';
			break;
	}