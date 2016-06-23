<?php
	if(!isset($config->complete))
	{
		return include $pages['home'];
	}
?>
<!DOCTYPE>
<html>
	<head>
		<?php
			print '<link rel="stylesheet" type="text/css" href="/cache/cache.php?f=mobile.css" />' . "\r\n";
			
			if(isset($_POST['username']) && isset($_POST['password']))
			{
				$user = $mysql->fetch_array('select * from `users` where `username`=:user;', array('user' => $_POST['username']));
				if(count($user) != 1 || !$mysql->validate($_POST['password'], $user[0]['password']))
				{
					print '<div class="message"> That username and password combination doesn\'t exist. </div>';
					return include $pages['mobile-login'];
				}
				else
				{
					setCookie('loginKey', $user[0]['loginKey'], strtotime("+ 1 year"));
				}
			}
			elseif(isset($_COOKIE['loginKey']))
			{
				$user = $mysql->fetch_array('select * from `users` where `loginKey`=:key;', array('key' => $_COOKIE['loginKey']));
				if(count($user) < 1)
				{
					return include $pages['mobile-login'];
				}
			}
			else
			{
				return include $pages['mobile-login'];
			}
			
			if(!isset($_POST['room']))
			{
				if(!isset($_GET['room']))
				{
					$core->mobileNoLogin = true;
					return include $pages['mobile-login'];
				}
				else
				{
					$_POST['room'] = $_GET['room'];
				}
			}
			
			$chat = $mysql->fetch_array('select `id`, `name` from `chats` where `name`=:chat;', array('chat' => $_POST['room']));
			if(count($chat) == 0)
			{
				print '<div class="message"> The chat name you entered does not exist. </div>';
				$core->mobileNoLogin = true;
				$core->mobileForceRoom = true;
				return include $pages['mobile-login'];
			}
			
			$user   = $user[0];
			unset($user['xavi']);
			$powers = $mysql->fetch_array('select * from `userpowers` where `userid`=' . $user['id'] . ';');
			$powdet = $mysql->fetch_array('select * from `powers`;');
			$powids = array();
			$p      = array();
			foreach($powdet as $i => $pow)
			{
				$section = 'p' . (substr($pow['section'], 1) + 4);
				if(!isset($p[$section]))
				{
					$p[$section] = 0;
				}
				
				$powids[$pow['id']] = $pow;
				unset($powdet[$i]);
			}
			
			foreach($powers as $pow)
			{
				$p['p' . (substr($powids[$pow['powerid']]['section'], 1) + 4)] += $powids[$pow['powerid']]['subid'];
			}
		?>

		<script type="text/javascript">
			<?php print 'var userinfo = JSON.parse(\'' . json_encode($user) . '\');'; ?>
			<?php print 'var san_info = JSON.parse(\'' . json_encode(array_map(function($x) { return htmlspecialchars(htmlspecialchars_decode($x)); }, $user)) . '\');'; ?>
			<?php print 'var mypowers = JSON.parse(\'' . json_encode($p) . '\');'; ?>
			<?php print 'var tabs = {0: ["' . htmlspecialchars($chat[0]['name']) . '", false]};'; ?>
			var sock = null
			var ready = false;
			var messages = [];
			var pmessages = {};
			var users = {};
			var currenttab = 0;
			function connect()
			{
				<?php print "sock = new WebSocket('ws://" . $config->server_ip . ':' . (isset($_GET['debug']) ? $config->debug_pt : $config->server_pt) . "/');\r\n"; ?>
				
				sock.onopen = function()
				{
					sock.send('<y r="1" />\0');
				}
				
				sock.onclose = function()
				{
					ready = false;
					alert('You have been logged out.');
				}
				
				sock.onerror = function(e)
				{
					socket.onclose();
				}
				
				sock.onmessage = function(e)
				{
					packet = JSON.parse(e.data);
					
					switch(packet.tag)
					{
						case 'y':
							joinRoom(packet);
							break;
						
						case 'm':
							if(packet.u != '0' && packet.t[0] != '/')
							{
								if(packet.N == undefined)
								{
									if(users[packet.u] == undefined)
									{
										break;
									}
									
									packet.N = users[packet.u].N;
									packet.n = users[packet.u].n;
								}
								
								if(packet.N == undefined || packet.N == '')
								{
									packet.N = 'Guest (' + packet.n.substring(0, 32) + ')';
								}
								else
								{
									packet.N += ' (' + packet.n.substring(0, 32) + ')';
								}
								
								if(currenttab != 0)
								{
									tabs[0][1] = true;
									resetTabs();
								}
								
								messages.push({'u': packet.u, 'm': htmlspecialchars(packet.t), 'n': packet.N});
								resetMessages();
							}
							break;
						
						case 'p':
							if(users[packet.u] != undefined)
							{
								if(pmessages[packet.u] == undefined)
								{
									pmessages[packet.u] = [];
								}
								
								pm = {'n': users[packet.u].N, 'm': htmlspecialchars(packet.t), 'u': packet.u};
								if(pm.n == '')
								{
									pm.n = 'Guest (' + users[packet.u].n + ')';
								}
								
								pmessages[packet.u].push(pm);
								tabs[packet.u] = [pm.n, true];
								if(currenttab == packet.u)
								{
									tabs[packet.u][1] = false;
									resetPM();
								}
								
								resetTabs();
							}
							break;
						
						case 'u':
							//{"tag":"u","flag":"2","s":"1","f":"1","u":"1003","q":"3","N":"","n":"nick","a":"1099","h":"","d0":"0","d2":"","bride":"","p0":"","p1":"","p2":"","p3":"","v":"1"}
							if(packet.u != 2)
							{
								users[packet.u] = packet;
								users[packet.u].rank = relrank(users[packet.u].f);
								resetUsers();
							}
							break;
						
						case 'l':
							if(users[packet.u] != undefined)
							{
								delete users[packet.u];
								resetUsers();
							}
							break;
						
						case 'i':
							userinfo.f = packet.f;
							userinfo.rank = relrank(userinfo.f);
							resetUsers();
							resetTabs();
							break;
						
						case 'done':
							ready = true;
							break;
					}
				}
			}
			
			function relrank(f)
			{
				/*  Binary          Modified
					1: main owner :    5
					2: moderator  :    3
					3: member     :    2
					4: owner      :    4
					0,5: guest    :    1
					banned (f&16) :    0
				*/
				if(f & 16)
				{
					return 0;
				}
				
				ranks = [1, 5, 3, 2, 4, 1];
				return ranks[f & 7];
			}
			
			function joinRoom(packet)
			{
				build = ["<j2"];
				build.push('banned="0"');
				build.push('v="0"');
				build.push('f="0"');
				build.push('u="' + userinfo.id + '"');
				build.push('h="' + san_info.url + '"');
				build.push('a="' + san_info.avatar + '"');
				build.push('n="' + san_info.username + '"');
				build.push('N="' + san_info.username + '"');
				build.push('k="' + userinfo.k + '"');
				build.push('c="<?php print $chat[0]['id'] ?>"');
				for(var i in mypowers)
				{
					build.push('d' + i.substr(1) + '="' + mypowers[i] + '"');
				}
				
				build.push('/>');
				sock.send(build.join(' '));
			}
			
			function sendMessage(message)
			{
				if(ready)
				{
					if(message == '')
					{
						alert('You can\'t send an empty message :s');
					}
					else if(currenttab != 0)
					{
						sendPM(message);
						resetPM();
					}
					else
					{
						document.getElementById('message_body').value = '';
						message = htmlspecialchars(message);
						build = ["<m"];
						build.push('t="' + message + '"');
						build.push('/>');
						sock.send(build.join(' ') + '\0');
						
						uMessage = {'u': userinfo.id, 'm': message, 'n': userinfo.username};
						messages.push(uMessage);
						resetMessages();
					}
				}
				else
				{
					alert('You\'re not chatting yet.');
				}
			}
			
			function sendPM(message)
			{
				if(users[currenttab] == undefined)
				{
					alert('This user is currently offline.');
				}
				else
				{
					document.getElementById('message_body').value = '';
					message = htmlspecialchars(message);
					build = ['<p'];
					build.push('t="' + message + '"');
					build.push('u="' + currenttab + '"');
					build.push('s="2"');
					build.push('/>');
					sock.send(build.join(' ') + '\0');
					
					uMessage = {'u': userinfo.id, 'm': message, 'n': userinfo.username};
					pmessages[currenttab].push(uMessage);
				}
			}
			
			function resetMessages()
			{
				messagebox = document.getElementById('_messagebox');
				messagebox.innerHTML = '';
				while(messages.length > 25)
				{
					messages.splice(0, 1);
				}
				
				for(var i in messages)
				{
					var element = document.createElement('div');
					element.innerHTML = '<div class="mob_message"><div class="nick" onclick="openUser(' + messages[i].u + ')"><b>' + messages[i].n + '</b></div><div class="text">' + messages[i].m + '</div></div>';
					messagebox.appendChild(element.firstChild);
				}
				
				messagebox.scrollTop = messagebox.scrollHeight;
			}
			
			function resetPM()
			{
				pmbox = document.getElementById('_privatebox');
				pmbox.innerHTML = '';
				while(pmessages[currenttab].length > 25)
				{
					pmessages[currenttab].slice(0, 1);
				}
				
				for(var i in pmessages[currenttab])
				{
					var message = pmessages[currenttab][i];
					var element = document.createElement('div');
					
					element.innerHTML = '<div class="mob_message"><div class="nick" uid="' + message.u + '"><b>' + message.n + '</b></div><div class="text">' + message.m + '</div></div>';
					pmbox.appendChild(element.firstChild);
				}
				
				pmbox.scrollTop = pmbox.scrollHeight;
			}
			
			function resetTabs()
			{
				tabbox = document.getElementById('_tabbox');
				tabbox.innerHTML = '';
				
				if(Object.keys(tabs).length == 1)
				{
					tabbox.style.display = 'none';
				}
				else
				{
					tabbox.style.display = 'inline-block';
					for(var i in tabs)
					{
						var element = document.createElement('div');
						element.innerHTML = '<div class="tab" id="tab_' + i + '" onclick="openTab(' + i + ')">' + tabs[i][0] + '</div>';
						if(tabs[i][1] == true)
						{
							element.firstChild.style.backgroundColor = '#00ff00';
						}
						else if(currenttab == i)
						{
							element.firstChild.style.backgroundColor = '#cccccc';
						}
						
						tabbox.appendChild(element.firstChild);
					}
				}
			}
			
			function closeTab(i)
			{
				if(tabs[i] != undefined)
				{
					delete tabs[i];
				}
				
				openTab(0);
				resetTabs();
				resetUsers();
			}
			
			function openTab(i)
			{
				if(tabs[i] != undefined)
				{
					tabs[i][1] = false;
					currenttab = i;
					resetTabs();
					resetUsers();
					
					if(i == 0)
					{
						document.getElementById('_privatebox').style.display = 'none';
						e = document.getElementById('_messagebox');
						e.style.display = 'inline-block';
						e.scrollTop = e.scrollHeight;
					}
					else
					{
						document.getElementById('_messagebox').style.display = 'none';
						e = document.getElementById('_privatebox');
						e.style.display = 'inline-block';
						e.scrollTop = e.scrollHeight;
						resetPM();
					}
					
				}
			}
			
			function resetUsers()
			{
				var userbox = document.getElementById('_userbox');
				var usrpush = [[], [], [], [], []];
				var ranks = [4, 0, 2, 3, 1, 0];
				var colors = ['#00ff00', 'orange', '#ffffff', '#6666ff', 'orange', '#00ff00'];
				
				userbox.innerHTML = '';
				
				usrpush[0].push({'u':userinfo.id, 'N':userinfo.username, 'n':userinfo.username, 'f':userinfo.f});
				
				if(currenttab == 0)
				{
					for(var i in users)
					{
						var index = users[i].f & 7;
						if(usrpush[index] == undefined)
						{
							index = 0;
						}
						
						usrpush[ranks[index]].push(users[i]);
					}
				}
				else if(users[currenttab] != undefined)
				{
					usrpush[0].push(users[currenttab]);
				}
				
				for(var i in usrpush)
				{
					for(var o in usrpush[i])
					{
						var element = document.createElement('div');
						var name = usrpush[i][o].N;
						if(name == '' || name == undefined)
						{
							name = 'Guest (' + usrpush[i][o].n + ')';
						}
						
						element.innerHTML = '<div class="user" onclick="openUser(' + usrpush[i][o].u + ')">' + name + '</div>';
						if(usrpush[i][o].f & 16)
						{
							element.firstChild.style.background = '#49311c';
							element.firstChild.style.color = '#ffffff';
							element.firstChild.innerHTML = 'Banned';
						}
						else
						{
							element.firstChild.style.background = colors[usrpush[i][o].f & 7];
						}
						
						userbox.appendChild(element.firstChild);
					}
				}
				
				if(currenttab != 0)
				{
					if(users[currenttab] != undefined && userinfo.rank > users[currenttab].rank)
					{
						if(userinfo.rank > 2)
							userbox.innerHTML += '<div onclick="setRank(\'/r\')" class="action">Make Guest</div>';
						if(userinfo.rank > 2)
							userbox.innerHTML += '<div onclick="setRank(\'/e\')" class="action">Make Member</div>';
						if(userinfo.rank > 3)
							userbox.innerHTML += '<div onclick="setRank(\'/m\')" class="action">Make Moderator</div>';
						if(userinfo.rank > 4)
							userbox.innerHTML += '<div onclick="setRank(\'/M\')" class="action">Make Owner</div>';
					}
					
					userbox.innerHTML += '<div onclick="closeTab(' + currenttab + ')" class="action">Close</div>';
				}
				
				delete usrpush;
			}
			
			function setRank(rank)
			{
				build = ['<c'];
				build.push('t="' + rank + '"');
				build.push('u="' + currenttab + '"');
				build.push('/>');
				sock.send(build.join(' ') + '\0');
			}
			
			function openUser(u)
			{
				if(u != userinfo.id)
				{
					if(tabs[u] == undefined && users[u] != undefined)
					{
						pmessages[u] = [];
						tabs[u] = [users[u].N == '' ? 'Guest (' + users[u].n + ')' : users[u].N, false];
						openTab(u);
					}
					else
					{
						openTab(u);
					}
				}
			}
			
			function htmlspecialchars(str) {
				if(typeof(str) == "string") {
					str = str.replace(/&/g, "&amp;"); /* must do &amp; first */
					str = str.replace(/"/g, "&quot;");
					str = str.replace(/'/g, "&#039;");
					str = str.replace(/</g, "&lt;");
					str = str.replace(/>/g, "&gt;");
				}
				return str;
			}
			
			connect();
		</script>
		
		<meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
	</head>
	
	<body>
		<div class="mob_container">
			<div class="chatContainer">
				<div class="mob_messages" id="_messagebox"></div>
				<div class="mob_private" id="_privatebox"></div>
				<div class="users" id="_userbox"></div>
			</div>
		</div>
		<div class="mob_tabs" id="_tabbox"></div>
		<div class="sendMessage">
			<input type="text" id="message_body" placeholder="message" onkeydown="if (event.keyCode == 13) { sendMessage(document.getElementById('message_body').value); }" />
			<input type="submit" value="send" id="sendPress" onclick="sendMessage(document.getElementById('message_body').value);" />
		</div>
	</body>
</html>