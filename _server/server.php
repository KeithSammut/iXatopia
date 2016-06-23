<?php
set_time_limit(0);
date_default_timezone_set("Australia/Melbourne");
libxml_use_internal_errors(true);
ini_set('display_errors', 'on');
error_reporting(E_ALL);

do
{
    $server = new iXatServer();
    unset($server);
} while(true);




class iXatServer
{
    private $info     = array();
    public $socket    = array(null, null);
    public $users     = array();
    public $ipbans    = array();
    public $protected = array();
    public $rfilter   = array();

    public $debug     = false;
    public $hasGroupPowers = array("Lobby","Help");



    
    
    public function __construct()
    {
        require __DIR__ . "/../_class/config.php";
        $this->config = (object) $config;
        $this->mysql  = new Database($config->db[0], $config->db[1], $config->db[2], $config->db[3]);
        
        $this->resetConfig();
        $this->bind();
        
		while(true)
		{
			$this->bind();
			
			while($this->socket[0])
			{
				$this->listen();
			}
			
			array_map('socket_close', $this->socket);
		}
    }
	
    public function resetConfig()
    {
        $this->config = $this->mysql->fetch_array("select * from `server` limit 0, 1;");
        $this->config = (object) $this->config[0];
        
        $this->config->spam_wait  = 800;
        $this->config->staff = (array) json_decode($this->config->staff);
        $this->config->pawns = (array) json_decode($this->config->pawns);
        
        $this->config->pcount = $this->mysql->fetch_array('select count(distinct `section`) as `count` from `powers`;');
        $this->config->pcount = $this->config->pcount[0]['count'];
        
        $this->hash   = $this->mysql->rand(25); /* For API Laterz */
        $this->ipbans = $this->mysql->fetch_array("select `ipbans` from `server`;");
        $this->ipbans = (array) json_decode($this->ipbans[0]['ipbans']);
        $this->mysql->query("update `server` set `pid`='" . getmypid() . "';");
    }
























    
    public function bind()
    {
        try
        {
            global $argv;
            $this->socket = array(
                socket_create(AF_INET, SOCK_STREAM, SOL_TCP),
                socket_create_listen(0)
            );
            
            socket_getsockname(end($this->socket), $ip, $port);
            $this->mysql->query("update `server` set `ipc`={$port};");
            socket_set_option($this->socket[0], SOL_SOCKET, SO_REUSEADDR, true);
            
            if(!isset($argv[1]) || $argv[1] != 'debug')
            {
                socket_bind($this->socket[0], $this->config->server_ip, $this->config->server_pt) or exit('line:' .  __LINE__);
            }
            else
            {
				$this->debug = true;
                print 'binding on debug port' . chr(10);
                socket_bind($this->socket[0], $this->config->server_ip, $this->config->backup_pt) or exit('line:' .  __LINE__);
            }
            
            socket_listen($this->socket[0]);
            socket_set_block($this->socket[0]);
        } catch(Exception $e) {
            print $e->getMessage();
            exit('line:' .  __LINE__);
        }
    }
    
    public function listen($null = null, $ipc = 0)
    {
        /* Create Read Array */
            $read = $this->socket;
            foreach($this->users as $user)
            {
                $read[] = $user->sock;
            }
            $except = $read;
        /* Accept / Filter New Connections */
            if(@socket_select($read, $null, $except, null) < 1)
            {
                continue;
            }
            
            foreach($this->socket as $i => $psock)
            {
                if(in_array($psock, $read))
                {
                    switch((int) $i)
                    {
                        case 0:
                            $socket = socket_accept($psock);
							socket_set_nonblock($socket);
							
                            if(!is_resource($socket) || count($this->users) >= $this->config->max_total)
                            {
                                @socket_close($socket);
                                break;
                            }
                            
							socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 3, 'usec' => 0));
							
                            @socket_getpeername($socket, $ip);
                            foreach($this->users as $user)
                            {
                                if($user->ipaddr == $ip)
                                {
                                    $ipc++;
                                }
                            }
                            
                            if($ipc > $this->config->max_per_ip || in_array($ip, $this->ipbans))
                            {
                                foreach($this->users as $user)
                                {
                                    if($user->ipaddr == $ip)
                                    {
                                        $this->disconnect($user->index);
                                    }
                                }
                                break;
                            }
                            
                            do
                            {
                                $index = $this->mysql->rand();
                            } while (isset($this->users[$index]));
                            
                            $this->users[$index] = new client($socket, $this, $index, $ip);
                            break;
                        
                        case 1:
                            $this->socket[] = socket_accept($psock);
                            break;
                            
                        default: /* For API if I feel like making it later */
                            $data = trim(socket_read($psock, 1205));
                            
                            if(strlen($data) <= 1)
                            {
                                socket_close($psock);
                                unset($this->socket[$i]);
                                break;
                            }
                            
                            $packet = simplexml_load_string($data);
                            $data   = $this->GetMultiAttr($packet);
                            
                            if(!method_exists($packet, 'getName'))
                            {
                                break;
                            }
                            
                            switch($packet->getName())
                            {
                                case 'usercount':
                                    socket_write($psock, count($this->users));
                                    break;
                                case 'globalMessage':
									foreach($this->users as $i => $user)
									{
										if($user->online == true)
										{
											$user->sendPacket('<fuckoff/>');
										}
									}
									break;
                            }
                            break;
                    }
                }
            }
        /* Read From Waiting Sockets, kill exceptions */
            if(!is_array($except))
            {
                $except = array(); /* To avoid a possibility of an error below */
            }
            
            foreach($this->users as $index => $user)
            {
                if(in_array($user->sock, $except) || !$user->sock)
                {
                    unset($this->users[$index]);
                }
                elseif(in_array($user->sock, $read))
                {
                    $input = @socket_read($user->sock, 1205);
                    if(trim($input) == '' || ord(substr($input, 0, 1)) == 136)
                    {
                        unset($this->users[$index]);
                        continue;
                    }
					elseif(substr_count($input, chr(0)) <= 1)
					{
					    $this->handle($input, $user);
					}
                }
            }
    }
    
    
    private function handle($packet, &$user)
    {
		$packet = str_replace('', '', $packet);//RIP Chrome
		
        try
        {
			if($this->debug)
			{
				var_dump($packet);
			}
			
			
            if($user->mobile == false && substr($packet, 0, 1) !== '<')
            {
                $user->mobile = true;
            }
			
			if(substr($packet, 0, 2) == '<x')
            {
                $user->sendRoom($packet);
            }
            
            if($user->mobile == true)
            {
                if($user->mobready == false)
                {
                    $user->buffer .= $packet;
                    if(strlen($user->buffer) >= 4096)
                    {
                        throw new Exception();
                    }
                    
                    if(is_numeric(strpos($user->buffer, "\r\n\r\n")))
                    {
                        $headers = array();
                        $lines = explode("\r\n", $user->buffer);
                        foreach($lines as $line)
                        {
                            $line = explode(': ',  $line, 2);
                            if(count($line) < 2) continue;
                            $headers[strtolower($line[0])] = $line[1];
                        }
                        
                        if(!isset($headers['sec-websocket-key']))
                        {
                            throw new Exception();
                        }
                        
                        $secAccept = base64_encode(pack('H*', sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                        $response = array();
                        array_push($response, "HTTP/1.1 101 Pulse");
                        array_push($response, "Upgrade: websocket");
                        array_push($response, "Connection: Upgrade");
                        array_push($response, "Sec-WebSocket-Accept: " . $secAccept);
                        @socket_write($user->sock, implode("\r\n", $response) . "\r\n\r\n");
                        
                        $user->mobready = true;
                    }
                    
                    return;
                }
                else
                {
                    $packet = $this->unmask($packet);
                    if($packet == false)
                    {
                        throw new Exception(1);
                    }
                }
            }
            
            if(strpos($packet, '<', 1) !== false)
            {
                throw new Exception(2);
            }
			$packet2 = $packet;
            $packet = simplexml_load_string(trim($packet));
            
            if(!method_exists($packet, 'getName'))
            {
                libxml_clear_errors(true);
                throw new Exception(3);
            }
            
            $tag = strtolower($packet->getName());
			$lPackets = array('policy-file-request', 'j2', 'y', 'login');
            
            if(strlen($tag) > 25 || $tag == '')
            {
                throw new Exception(4);
            }
            
            if(!isset($user->loginKey) || $user->loginKey == null)
            {
                if(!in_array($tag, $lPackets))
                {
                    throw new Exception(5);
                }
            }
            elseif($user->authenticated == null && $tag != 'j2')
            {
                throw new Exception(6);
            }
            elseif(isset($user->id) && in_array($user->id, array(0, 2)))
            {
                throw new Exception(7);
            }
			elseif($user->hidden == true && $user->online)
			{
				$user->hidden = false;
				$user->joinRoom($user->chat, false, true, $user->pool);
			};
        } catch(Exception $e) {
            //print $e->getMessage() . "\n";
            return $this->disconnect($user->index);
        }
		
		
		if(!$user->authenticated && !in_array($tag, $lPackets))
        {
            return $this->disconnect($user->index, true);
      	}
        
        switch($tag)
        {    
            //For bots
			case 'login':
				//$key = $this->getAttribute($packet, 'key');//lol later
				$user2 = $this->getAttribute($packet, 'user');
				$password = $this->getAttribute($packet, 'pass');
				$userLogin = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($user2) . '\';');
				if(!$this->mysql->validate($password, $userLogin[0]['password']) || empty($userLogin))
				{
					$user->sendPacket('<login t="Bad Username/Password." e="1" />');
				} else {
					$loginKey = md5(json_encode(array(time(),$userLogin[0]['username'],$userLogin[0]['password'])));
					$this->mysql->query('update `users` set `loginKey`=\''.$loginKey.'\' where `username`=\''.$this->mysql->sanatize($userLogin[0]['username']).'\';');
					//$user->sendPacket('<login t="'.$loginKey.'" e="0" />');
					$upowers = $this->mysql->fetch_array("select * from `userpowers` where `userid`={$userLogin[0]['id']};");
					$spowers = $this->mysql->fetch_array("select * from `powers` where `name` not like '%(Undefined)%';");
					list($vals, $p, $dO, $powerO, $pp) = array(array(), array(), '', '', '');
					foreach($spowers as $i => $u)
					{
							$vals[$u["id"]] = array($u["section"], $u["subid"]);
							if(!isset($p[$u["section"]]))
							{
									$p[$u["section"]] = 0;
							}
					}
					foreach($upowers as $i => $u)
					{
							if($u["count"] >= 1 && isset($vals[$u["powerid"]]) && isset($p[$vals[$u["powerid"]][0]]))
							{
									$str = $u['powerid'] . '=' . ($u['count'] > 1 ? ($u['count'] -1) : 1) . '|';
									$dO .= $str;
									if($u['count'] > 1)
									{
											$powerO .= $str;
									}
									$p[$vals[$u["powerid"]][0]] += $vals[$u["powerid"]][1];
							}
					}
					$nickname = explode('##', $userLogin[0]['nickname'], 2);
					if(count($nickname) != 2)
					{
							$nickname[1] = "";
					}
					$vars = "";
					$vars .= 'userno="'.$userLogin[0]["id"].'" ';
					$vars .= 'avatar="'.$userLogin[0]["avatar"].'" ';
					$vars .= 'k1="'.$userLogin[0]["k"].'" ';
					$vars .= 'd0="'.$userLogin[0]["d0"].'" ';
					$vars .= 'd1="'.$userLogin[0]["days"].'" ';
					$vars .= 'd2="'.$userLogin[0]["d2"].'" ';
					$vars .= 'd3="" ';
				   
					foreach($p as $i => $u)
							$vars .= 'd'.(substr($i, 1) + 4).'="'.$u.'" '; 
						   
					$vars .= 'dt=0" ';
					$vars .= 'homepage="'.$userLogin[0]["url"].'" ';
					$vars .= 'Powers="'.implode(",", $p).'" ';
					$vars .= 'PowerO="'.$powerO.'" ';
					$vars .= 'status="'.$nickname[1].'" ';
					//$vars .= 'dO="'.$dO.'" ';
					$vars .= 'dx="'.$userLogin[0]["xats"].'" ';
					$vars .= 'registered="'.$userLogin[0]["username"].'" ';
					$vars .= 'k2="'.$userLogin[0]["k2"].'" ';
					$vars .= 'k3="'.$userLogin[0]["k3"].'" ';
					$vars .= 'name="'.$nickname[0].'" ';
					$vars .= 'loginKey="'.$loginKey.'"';
					$user->sendPacket('<v '.$vars.' e="0" />');
				}
			break;
			
            case substr($tag, 0, 1) == 'w': /* Pools, leave it here nigga, maybe later just use joinRoom() for faster change :] */
				$pool = substr($tag, 1, 2);
				$user->sendRoom("<l u=\"{$user->id}\" />");
				$user->switchingPools = true;
				$user->joinRoom($user->chat, true, true, $pool);
            break;
                
            case 'f':
                $users = $this->getAttribute($packet, 'o');
                if($users === false || $this->spamfilter($tag, $user, 200))
                {
                    $this->disconnect($user->index);
                }
                else
                {
                    $friends = (array) explode(' ', $users);
                    $online  = array();
                    foreach($this->users as $i => $_user)
                    {
                        if($_user->id != $user->id && in_array($_user->id, $friends) && $_user->hidden === false && !in_array($_user->id, $online))
                        {
                            array_push($online, $_user->id);
                        }
                    }
                    $user->sendPacket('<f v="' . implode(',', $online) . '" />');
                }
                break;
        
            case 'policy-file-request':
                if(isset($user->policy))
                {
                    return $this->ipban($user->ipaddr);
                }
                
                $user->sendPacket('<cross-domain-policy><allow-access-from domain="*" to-ports="*" /></cross-domain-policy>');
                $user->policy = 1;
                break;
            
            case 'y':
                if(isset($user->loginKey) && $user->loginKey != null)
                {
                    return $this->ipban($user->ipaddr);
                }
                
                $user->loginKey   = rand(10000000, 99999999);
                $user->loginShift = rand(2, 5);
				$user->loginTime  = time();
				
				$user->sendPacket('<y yi="' . $user->loginKey . '" yc="' . $user->loginTime . '" ys="' . $user->loginShift . '" />');
                break;
    
            case 'j2':

                if($user->authenticated == true)
                {
                    $user->sendPacket('<logout />');
                    return $this->disconnect($user->index);
                }
                
                if($user->authenticate($packet) == false)
                {
                    $user->sendPacket('<n t="You must re-login to be able to chat further." />');
                    $user->sendPacket('<logout />');
                    $this->disconnect($user->index);
                }



                break;
            
            case 'l':
                $this->disconnect($user->index);
                break;

    
            case 'm':
                if($user->banned > time())
                {
                    return false;
                }
                
                if(isset($this->protected[$user->chat]))
                {
                    if($this->protected[$user->chat]['end'] < time())
                    {
                        unset($this->protected[$user->chat]);
                        $user->sendRoom("<m t=\"Chat protection has exceeded 60 minutes and has been automatically disabled.\" u=\"0\" />");
                    }
                    elseif($this->protected[$user->chat]['type'] == 'noguest')
                    {
                        if($user->rank == 5 || $user->rank == 40)
                        {
                            return false;
                        }
                    }
                    elseif($this->protected[$user->chat]['type'] == 'unreg')
                    {
                        if($user->guest == true && in_array($user->rank, array(5, 40)))
                        {
                            return false;
                        }
                    }
                }
                
                if(in_array($user->rank, array(5, 40)) && $user->guest == true)
                {
                    if(!isset($this->rfilter[$user->chat]))
                    {
                        $this->rfilter[$user->chat] = array();
                    }
                    
                    $ctime = time() - 5;
                    $count = 1;
                    foreach($this->rfilter[$user->chat] as $i => $time)
                    {
                        if($ctime > $time)
                        {
                            unset($this->rfilter[$user->chat][$i]);
                            continue;
                        }
                        
                        $count++;
                    }
                    
                    array_push($this->rfilter[$user->chat], time());
                    if($count >= 12)
                    {
                        $this->protected[$user->chat] = array('end' => time() + 3600, 'type' => 'unreg');
                        $user->sendRoom("<m u=\"0\" t=\"Protection has been enabled for the next 60 minutes!(Raid Detected)\" />");
                        foreach($this->users as $i => $u)
                        {
                            if($u->chat == $user->chat && in_array($u->rank, array(5, 40)) && $u->guest == true)
                            {
                                $u->sendPacket('<n t="Protection enabled, kicking unregistered guests." />');
                                $this->disconnect($u->index);
                            }
                        }
                        
                        unset($this->rfilter[$user->chat]);
                    }
                }
                
                $message = $this->getAttribute($packet, 't');

                if(empty($message))
                {
                    return false;
                }
                elseif(substr($message, 0, 1) == '~')
                { // commands <-- That's there so I can ctrl+f to here quickly <:

                    $owner = in_array($user->id, $this->config->staff) ? true : false;

                    $args  = explode(' ', substr($message, 1));
                    switch(strtolower($args[0]))
                    {
                        case 'resetconfig':
                            if($owner)
                            {
                                $this->resetConfig();
                                $user->sendPacket('<m u="0" t="Configuration has been reloaded" />');
                            }
                            break;
                        
                        case 'users':
                            if (!$owner) {
                                break;
                            }
                            $user->sendPacket('<m u="0" t="' . count($this->users) . ' currently online" />');
                            break;





























						
                        case 'setxats':
                            if(count($args) != 3 || !$owner)
                            {
                                break;
                            }
                            $uRow = $this->mysql->fetch_array('select `id`, `username`, `password` from `users` where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                            if(count($uRow) == 1 && is_numeric($args[2]))
                            {
                                $this->mysql->query('update `users` set `xats`=' . $args[2] . ' where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                                $_user = $this->getuserbyid($uRow[0]['id'], $user->chat);
                                if($_user != false)
                                {
                                    $_user->sendPacket($this->doLogin($uRow[0]['username'], $uRow[0]['password']));
                                }
                            }
                            break;
                        
                        case 'clear':
                            $this->mysql->query('update `messages` set `visible`=0 where `id`=' . $user->chat . ';');
                            $user->joinRoom($user->chat, 1, true);
                            return;

				case 'roulette':
		  $num = floor(36 * (rand(0, 36)*rand(0, 36)));
              $user->sendAll("<n t=\"$num IS Your Number!\" />");
                            return;

													case 'release':
								if(!$owner) { break; }
								$power = $args[1];
								$amount = $args[2];
								$this->mysql->query("UPDATE `powers` SET `amount`='".$amount."' WHERE `name`='".$power."'");
								$sOrNah = $amount == 1 ? "" : "s";
								$haveOrHas = $amount == 1 ? "has" : "have";
								$user->sendAll("<n t=\"{$amount} {$power}{$sOrNah} {$haveOrHas} been released!\" />");
								return;
							break;

						case "global":
							if(!$owner){
								break;
							}
							$args  = explode(' ', substr($message, 1),2);
							$sum = "<n t=\"{$args[1]}\" />";
							$user->sendAll($sum);
							return;
						break;	

						case 'relog':
                            if(count($args) == 2 && $owner)
                            { 
                                $_user = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                                if(empty($_user))
                                {
                                    break;
                                }
                                $online = $this->getuserbyid($_user[0]['id']);
                                if(is_object($online))












                                {
                                    $online->sendPacket($this->doLogin($_user[0]['username'], $_user[0]['password']));
                                }
								return;
                            }

						break;































                        case 'everypower':
                        case 'nopowers':
                            if(count($args) != 2 || !$owner)
                            {
                                break;
                            }
                            $uRow = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                            if(count($uRow) == 1)
                            {
                                $this->mysql->query('delete from `userpowers` where `userid`=' . $uRow[0]['id'] . ';');
                                if(strtolower($args[0]) == 'everypower')
                                {
                                    $powers = $this->mysql->fetch_array('select `id`, `name` from `powers` where `name` not like \'%(Undefined)%\' and `subid`<2147483647;');
                                    $inputs = '';
                                    foreach($powers as $power)
                                    {
                                        if(!is_numeric($power['name']))
                                        {
                                            $inputs .= '(' . $uRow[0]['id'] . ', ' . $power['id'] . ', 1),';
                                        }
                                    }
                                    $this->mysql->query('insert into `userpowers` (`userid`, `powerid`, `count`) values ' . substr($inputs, 0, -1) . ';');
                                }
                                
                                $_user = $this->getuserbyid($uRow[0]['id'], $user->chat);
                                if($_user != false)
                                {
                                    $_user->sendPacket($this->doLogin($uRow[0]['username'], $uRow[0]['password']));
                                }
                            }
                            break;
                        case 'gback':
                            if (!$owner) {
                                break;
                            }
                            $arg1 = $args[1];
                            $this->mysql->query("UPDATE `chats` SET `gback`='" . $arg1 . "' WHERE `id`='" . $user->chat . "'");
                            $user->sendPacket('<m u="0" t="gback has been updated [' . $arg1 . ']" i="0" />');
                              break;							
                        case 'addpower':
                        case 'delpower':
                            if(count($args) == 3 && $owner)
                            { /* Just cause I felt like doing it this way this time */
                                $_user = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                                $power = $this->mysql->fetch_array('select * from `powers` where `name`=\'' . $this->mysql->sanatize($args[2]) . '\';');
                                if(empty($_user) || empty($power))
                                {
                                    break;
                                }
                                $this->mysql->query('delete from `userpowers` where `userid`=' . $_user[0]['id'] . ' and `powerid`=' . $power[0]['id'] . ';');
                                if(strtolower($args[0]) == 'addpower')
                                {
                                    $this->mysql->query('insert into `userpowers`(`userid`, `powerid`, `count`) values(' . $_user[0]['id'] . ', ' . $power[0]['id'] . ', 1);');
                                }
                                
                                $online = $this->getuserbyid($_user[0]['id']);
                                if(is_object($online))
                                {
                                    $online->sendPacket($this->doLogin($_user[0]['username'], $_user[0]['password']));
                                }
                            }
                            break;
							




























































































                        case 'setid':
                            if(count($args) == 3 && is_numeric($args[2]) && $owner)
                            {
                                $_user = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($args[1]) . '\';');
                                $_test = $this->mysql->fetch_array('select * from `users` where `id`=\'' . $this->mysql->sanatize($args[2]) . '\';');
                                
                                if(!empty($_test))
                                {
                                    $user->sendPacket('<m t="Dude that ID is taken by ' . $_test[0]['username'] . '" u="0" />');
                                    break;
                                }
                                
                                if(empty($_user))
                                {
                                    $user->sendPacket('<m t="That username doesn\'t exist" u="0" />');
                                    break;
                                }
                                
                                $this->mysql->query('update `users` set `id`=' . $this->mysql->sanatize($args[2]) . ' where `id`=' . $_user[0]['id'] . ';');
                                $this->mysql->query('update `ranks` set `userid`=' . $this->mysql->sanatize($args[2]) . ' where `userid`=' . $_user[0]['id'] . ';');
                                $this->mysql->query('update `userpowers` set `userid`=' . $this->mysql->sanatize($args[2]) . ' where `userid`=' . $_user[0]['id'] . ';');
                                
                                $online = $this->getuserbyid($_user[0]['id']);
                                if(is_object($online))
                                {
                                    $online->sendPacket($this->doLogin($_user[0]['username'], $_user[0]['password']));
                                }
                            }
                            break;
							
                        case 'getmain':








































































                        case 'delrank':
                            if($owner)
                            {
                                $this->mysql->query('delete from `ranks` where `chatid`=' . $user->chat . ' and `userid`=' . $user->id . ';');
                                if(strtolower($args[0]) == 'getmain')
                                {









                                    $this->mysql->query('insert into `ranks`(`userid`, `chatid`, `f`) values(' . $user->id . ', ' . $user->chat . ', 1);');
                                }
                                $this->disconnect($user->index);
                            }
                            break;
						
                    }
                }
                elseif(substr($message, 0, 1) == "/")
                {
					if($message == '/away' && $user->hasPower(144))
					{
						$user->f |= 0x4000;
						$user->joinRoom($user->chat, false, true, $user->pool);
						return;
					}
					elseif($message == '/back')
					{
						if($user->f & 0x4000 && $user->hasPower(144))
						{
							$user->f -= 0x4000;
							$user->joinRoom($user->chat, false, true, $user->pool);
						}
						
						return;
					}
					else
					{
						switch(strtolower(substr($message, 1, 1)))
						{
							case 'd':
								if(in_array($user->rank, array(1, 2, 4)))
								{
									$mid = substr($message, 2);
									
									if(is_numeric($mid))
									{
										$res = $this->mysql->query('update `messages` set `visible`=0 where `id`=' . $user->chat . ' and `mid`=' . $mid . ';');
										if($res)
										{
											$user->sendRoom('<m t="/' . $mid . '" u="0" />');
											unset($user->last['m']);
										}
									}
									elseif($mid == 'clear')
									{
										$res = $this->mysql->query('update `messages` set `visible`=0 where `id`=' . $user->chat . ';');
									}
								}
								return;
							case 'p':
								if($user->rank == 1 || $user->rank == 4)
								{
									if(!isset($this->protected[$user->chat]))
									{
										$user->sendRoom("<m u=\"0\" t=\"Protection has been enabled for the next 60 minutes!({$user->id})\" />");
										$this->protected[$user->chat] = array("end"=>(time()+3600), "type"=>'noguest');
										return false;
									}
									else
									{
										unset($this->protected[$user->chat]);
										$user->sendRoom("<m u=\"0\" t=\"Protection has been disabled!({$user->id})\" />");
										return false;
									}
								}
							break;
							case 's':
								if($user->rank!=1)
								{
									return false;
								}
								$scroll = $this->mysql->sanatize(htmlspecialchars(substr($message, 2), ENT_QUOTES));
								$this->mysql->query("update `chats` set `sc` = '{$scroll}' where `name` = '{$user->group}';");
								$user->sendRoom("<m u=\"{$user->id}\" t=\"/s".str_replace('"','',htmlspecialchars_decode(stripslashes($scroll)))."\" />");
							break;								
							case 'g':
								if($user->hasPower(32))
								{
									$this->mysql->query('delete from `ranks` where `chatid`=' . $user->chat . ' and `userid`=' . $user->id . ';');
									$user->joinRoom($user->chat, 0, true);
								}
								break;
							default:
								$user->message($message);
								return false;
						}
					}
                }
                
                if($this->spamfilter($tag, $user, 700)) break;
                $this->mysql->query("insert into `messages` (`id`, `uid`, `message`, `name`, `registered`, `avatar`, `time`, `pool`) values ('{$this->mysql->sanatize($user->chat)}', '{$this->mysql->sanatize($user->id)}', '{$this->mysql->sanatize($message)}', '{$this->mysql->sanatize($user->nickname)}', '{$this->mysql->sanatize($user->username)}', '{$this->mysql->sanatize($user->avatar)}', '".time()."', '{$this->mysql->sanatize($user->pool)}');");
                $user->message($message);
                $user->last = array();
                break;

            case "ap": // assign/un-assign group powers
            $attributes = array("p", "a");
            $attributes = $this->getMultiAttr($packet, $attributes);
            $p = $attributes["p"];
            $a = $attributes["a"];
            $power = $this->mysql->fetch_array("SELECT * FROM `powers` WHERE `id`='{$p}';");
            $name = $power[0]['name'];
            switch($a)
            {
                case "1":
                $t = $this->mysql->fetch_array("SELECT * FROM `gorup_powers` WHERE `power`='{$p}' AND `assignedBy`='{$user->id}';");
                if(!empty($t))
                { // Power is already assigned
                    $user->sendPacket("<ap p=\"{$p}\" r=\"3\" />");
                    break;
                }
                $s = $this->mysql->fetch_array("SELECT * FROM `group_powers` WHERE `group`='{$user->group}' AND `power`='{$p}';");
                if(!empty($s))
                { // The group already has that power 
                    $user->sendPacket("<ap p=\"{$p}\" r=\"4\" />");
                    break;
                }
                $this->mysql->query("INSERT INTO group_powers(`group`,`power`,`assignedBy`) VALUES ('{$user->group}', '{$p}', '{$user->id}');");
                $user->sendPacket("<ap p=\"{$p}\" r=\"1\" />");
                break;
                
                case "0":
                $i = $this->mysql->fetch_array("SELECT * FROM `group_powers` WHERE `assignedBy`='{$user->id}' AND `group`='{$user->group}';");
                if(empty($i))
                {
                    $user->sendPacket("<ap p=\"{$p}\" r=\"2\" />");
                    break;
                }
                $this->mysql->query("DELETE FROM `group_powers` WHERE `assignedBy`='{$user->id}' AND `group`='{$user->group}';");
                $user->sendPacket("<ap p=\"{$p}\" r=\"0\" />");
                break;
            }
            break;
                
            case 'a':
                if($this->spamfilter($tag, $user, $this->config->spam_wait) || $user->banned > time()) break;
                if($user->guest == true)
                {
                    return false;
                }
                
                $attributes = array('x', 's', 'b', 'm', 'p', 'k', 'f');
                $attributes = $this->getMultiAttr($packet, $attributes);
                $x = $attributes['x'];
                $s = $attributes['s'];
                $b = $attributes['b'];
                $m = $attributes['m'];
                $p = $attributes['p'];
                $k = $attributes['k'];
                $f = $attributes['f'];
                
                if(!$b && !$f)
                {
                    if($user->xats < 25)
                    {
                        return $user->sendPacket('<m t="/wYou don\'t have enough xats!" u="0" />');
                    }
                    
                    $usr = $this->mysql->fetch_array("select * from `users` where `id`='{$user->id}';"); $usr = $usr[0];
                    if(!$this->mysql->checkPass($p, $usr['password']))
                    {
                        return $user->sendPacket('<v e="8" />');
                    }
                    
                    $user->xats = ($usr['xats']-25);
                    $this->mysql->query("update `users` set `xats` = '{$user->xats}', `reserve`=`reserve`-25 where `id` = '{$user->id}';");
                    $user->sendRoom("<a u=\"{$user->id}\" k=\"{$k}\" t=\"{$m}\" />", true);
                    $user->sendPacket("<a u=\"{$user->id}\" k=\"{$k}\" t=\"{$m}\" c=\"{$user->xats}\" />");
                }
                else
                {
                    switch($k)
                    {
                        case 'Confetti':
                        case 'Hearts':
                            if($user->d2 != 0)
                            {
                                $user->sendPacket('<n t="/wYou already have a BFF or are married." u="0" />');
                                break;
                            }
                            if($user->id==$b)
                            {
                                $user->sendPacket('<n t="/wYou can\'t marry yourself" u="0" />');
                                break;
                            }
                            $usr = $this->mysql->fetch_array("select * from `users` where `id`='{$user->id}';"); $usr = $usr[0];
                            if(!$this->mysql->checkPass($p, $usr['password']))
                            {
                                return $user->sendPacket('<v e="8" />');
                            }
                            if($user->xats < 200)
                            {
                                $user->sendPacket('<v e="11" />');
                                break;
                            }
                            $u = $this->getUserByID($b, $user->chat);
                            if(!is_object($u))
                            {
                                break;
                            }
                            if($u->hasPower(99))
                            {
                                return $user->sendPacket('<n t="' . $u->id . ' has single power." />');
                            }
                            $user->xats = ($usr['xats']-200);
                            if($u->d2!=0)
                            {
                                $user->sendPacket('<m t="/wThat has a BFF or is already married." u="0" />');
                                break;
                            }
                            $this->mysql->query("update `users` set `bride` = '{$u->id}', `d2` = '{$u->id}', `xats` = '{$user->xats}', `reserve`=`reserve`-200 where `id` = '{$user->id}';");
                            $this->mysql->query("update `users` set `bride` = '{$user->id}', `d2` = '{$user->id}' where `id` = '{$u->id}';");
                            $data1 = $this->doLogin($user->username, $user->password);
                            $data2 = $this->doLogin($u->username, $u->password);
                            $user->sendPacket('<n t="You\'re now married to ' . $u->id . '" />');
                            $user->sendPacket($data1);
                            $u->sendPacket('<n t="You\'re now married to ' . $user->id . '" />');
                            $u->sendPacket($data2);
                            break;
                        
                        case 'Argue':
                            $this->mysql->query("update `users` set `d0` = '0', `d2` = '0', `bride` = '' where `id` = '{$user->id}';");
                            $user->sendPacket('<n t="You\'re now divorced" />');
                            $data1 = $this->doLogin($user->username, $user->password);
                            $user->sendPacket($data1);
                            break;
                        
                        case 'Champagne':
                            if($user->d2!=0)
                            {
                                $user->sendPacket('<m t="/wYou\'re already BFF\'d | Married" u="0" />');
                                break;
                            }
                            if($user->id==$b)
                            {
                                $user->sendPacket('<m t="/wYou can\'t BFF yourself" u="0" />');
                                break;
                            }
                            $usr = $this->mysql->fetch_array("select * from `users` where `id`='{$user->id}';"); $usr = $usr[0];
                            if(!$this->mysql->checkPass($p, $usr['password']))
                            {
                                return $user->sendPacket('<v e="8" />');
                            }
                            if($user->xats < 200)
                            {
                                $user->sendPacket('<v e="11" />');
                                break;
                            }
                            $u = $this->getUserByID($f, $user->chat);
                            if(!is_object($u)) 
                            {
                                break;
                            }
                            if($u->hasPower(99))
                            {
                                return $user->sendPacket('<n t="' . $u->id . ' has single power." />');
                            }
                            $user->xats = ($usr['xats']-25);
                            if($u->d2!=0)
                            {
                                $user->sendPacket('<m t="/wThat user is already BFF\'d/Married" u="0" />');
                                break;
                            }

                            $this->mysql->query("update `users` set `d0` = '1', `d2` = '{$u->id}', `xats` = '{$user->xats}', `reserve`=`reserve`-25 where `id` = '{$user->id}';");
                            $this->mysql->query("update `users` set `d0` = '1', `d2` = '{$user->id}' where `id` = '{$u->id}';");
                            $data1 = $this->doLogin($user->username, $user->password);
                            $data2 = $this->doLogin($u->username, $u->password);
                            $user->sendPacket('<n t="You\'re now best friends with ' . $u->id . '" />');
                            $user->sendPacket($data1);
                            $u->sendPacket('<n t="You\'re now best friends with ' . $user->id . '" />');
                            $u->sendPacket($data2);
                            break;
                        
                        case 'T':
                            if($x < 0 || !is_numeric($x))
                            {
                                return $this->disconnect($user->index);
                            }
                            $usr = $this->mysql->fetch_array("select * from `users` where `id`='{$user->id}';"); $usr = $usr[0];
                            if($usr['transferblock']>time())
                            {
                                return $user->sendPacket('<v e="10" />'); //Transfer block



                            }
                            if(!$this->mysql->checkPass($p, $usr['password']))
                            {
                                return $user->sendPacket('<v e="8" />');


                            }
                            if($x > $usr['xats'])
                            {
                                return $user->sendPacket('<v e="11" />'); //not enough xats

                            }
                            if($x > $usr['xats'] - $usr['reserve'])
                            {
                                return $user->sendPacket("<n t=\"You cannot cut into your reserved xats (You can send ".($usr['xats'] - $usr['reserve'])." xats).\" />");
                            }
                            if(strtotime("+ $s days") > $usr['days'])
                            {
                                return $user->sendPacket('<v e="18" />'); // not enough days
                            }
                            $u = $this->getUserByID($b, $user->chat);
                            if(!is_object($u))
                            {
                                return $user->sendPacket('<v e="0" m="a" t="" />');
                            }
                            
                            if($user->ipaddr == $u->ipaddr)
                            {
                                return $user->sendPacket('<n t="You can\'t trade with yourself D:" />');
                            }
                            
                            $u->xats += $x;
                            if($u->days <= 0)
                            {
                                $u->days = $s;
                            }
                            else
                            {
                                $u->days += $s;
                            }
                            $user->xats -= $x;
                            $user->days -= $s;
                            $uDAYS = strtotime("+ ".$u->days." days");
                            $UDAYS = strtotime("+ ".$user->days." days");
                            $this->mysql->query("update `users` set `xats`='{$u->xats}', `days`='{$uDAYS}' where `id` = '{$u->id}';");
                            $this->mysql->query("update `users` set `xats`='{$user->xats}', `days`='{$UDAYS}' where `id` = '{$user->id}';");
                            $this->mysql->query("insert into `transfers` (`to`, `from`, `xats`, `days`, `timestamp`) values ('{$u->id}', '{$user->id}', '{$x}', '{$s}', '".time()."');");
                            
                            $user->sendPacket("<a c=\"{$user->xats}\" u=\"{$user->id}\" b=\"{$b}\" s=\"{$s}\" x=\"{$x}\" k=\"T\" t=\"{$m}\" />");
                            $u->sendPacket("<a c=\"{$u->xats}\" u=\"{$user->id}\" b=\"{$b}\" s=\"{$s}\" x=\"{$x}\" k=\"T\" t=\"{$m}\" />");
                            
                            $user->joinRoom($user->chat, 1, false, $user->pool);
                            $u->joinRoom($user->chat, 1, false, $u->pool);
                            break;
                    }
                }
            break;
            
            case 'p':
                $u = $this->getuserbyid($this->getAttribute($packet, 'u', true), $user->chat);
                if(!is_object($u))
                {
                    break;
                }
                
                $attr = $this->getMultiAttr($packet, array('t', 's'));
                
                if(substr($attr['t'], 0, 1) == "/")
                {
                    switch(1)
                    {
                        case substr($attr['t'], 1, 2) == 'mo':
                            if(!in_array($user->rank, array(1)) || !$this->higherRank($user->rank,$u->rank,true))
                            {
                                break;
                            }
                            $time = round(substr($attr['t'], 3), 1);
                            if(!is_numeric($time) || $time > 24 || $time < 1)
                            {
                                return $user->sendPacket("<n t=\"Please use the following format\n/mo2.5 for 2.5 hours.\nMax:24\nMin:1\" />");
                            }
                            $this->mysql->query("delete from `ranks` where `userid`='{$u->id}' and `chatid`='{$user->chat}';");
                            $this->mysql->query("insert into `ranks`(`userid`, `chatid`, `f`, `tempend`) values('{$u->id}', '{$u->chatid}', 4, " . (time() + ($time*60*60)) . ");");
                            $x = "<i>";
                            $x = htmlspecialchars($x);
                            $user->sendRoom("<m u=\"{$user->id}\" t=\"{$x} I have made {$u->username} an owner for {$time} hours!\" />");
                            $u->joinRoom($user->chat, 0, true);
                        break;
                        



















                        case substr($attr['t'], 1, 1) == 'm':
                            if(!in_array($user->rank, array(1, 4)) || !$this->higherRank($user->rank,$u->rank,true))
                            {
                                break;
                            }
                            $time = round(substr($attr['t'], 2), 1);
                            if(!is_numeric($time) || $time > 24 || $time < 1)
                            {
                                return $user->sendPacket("<n t=\"Please use the following format\n/m2.5 for 2.5 hours.\nMax:24\nMin:1\" />");
                            }
                            $this->mysql->query("delete from `ranks` where `userid`='{$u->id}' and `chatid`='{$user->chat}';");
                            $this->mysql->query("insert into `ranks`(`userid`, `chatid`, `f`, `tempend`) values('{$u->id}', '{$u->chatid}', 2, " . (time() + ($time*60*60)) . ");");
                            $user->sendRoom("<m u=\"{$user->id}\" t=\"&lt;i&gt; I have made {$u->username} a moderator for {$time} hours!\" />");
                            $u->joinRoom($user->chat, 0, true);
                            break;
                        default:
                            $attr['t'] = htmlspecialchars($attr['t']);
                            $attr['s'] = htmlspecialchars($attr['s']);
                            $u->sendPacket("<p u=\"{$user->id}\" t=\"{$attr['t']}\" s=\"{$attr['s']}\" />");
                            return;
                    }
                }
                else
                {
                    $attr['t'] = htmlspecialchars($attr['t']);
                    $attr['s'] = htmlspecialchars($attr['s']);
                    $u->sendPacket("<p u=\"{$user->id}\" t=\"{$attr['t']}\" s=\"{$attr['s']}\" />");
                    if($this->spamfilter($tag, $user, 700)) break;
                }
                break;
            
           case 'z':
				if($user->switchingPools == true){$user->switchingPools = false; break; }
                if($this->spamfilter($tag, $user, 1)) break;
                $d = $this->getAttribute($packet, 'd');
                $u = $this->getUserByID($d);
                if(!is_object($u))
                {
                    break;
                }
				if(!is_object($user))
                {
                    break;
                }
				$t2 = $this->getAttribute($packet, 't');
				$t = substr($t2, 0, 2);
				$t3 = substr($t2, 0, 3);
                $param = substr($t2, 2);
                switch($t)
                {
                    case '/l':
                        if($u->hidden == true)
                        {
                            return false;
                        }
                        $str = ((($u->p0 & 32) && ($u->chat != $user->chat)) || !isset($u->group)) ? " t=\"/a_Nofollow\"" : " t=\"/a_on {$u->group}\"";//Nofollow
                        
                        $user->sendPacket('<z b="1" d="' . $user->id . '" u="' . $u->id . '"' . ( $str ) . ' po="' . $u->dO . '" ' . $u->pStr . 'x="' . $u->xats .
                            '" y="' . $u->days . '" q="3"' . ($u->username == '' ? '' : ' N="' . $u->username . '"') . ' n="' . html_entity_decode (htmlspecialchars_decode(($u->nickname))) . '" a="' . $this->mysql->sanatize($u->avatar) . '" h="' . $this->mysql->sanatize($u->url) . '" v="2" />');
                            
                        $u->sendPacket('<z b="1" d="' . $u->id . '" u="' . $user->id . '" t="/l" po="' . $user->dO . '" ' . $user->pStr . 'x="' . $user->xats .
                            '" y="' . $user->days . '" q="3"' . ($user->username == '' ? '' : ' N="' . $user->username . '"') . ' n="' . html_entity_decode (htmlspecialchars_decode(($user->nickname))) . '" a="' . $this->mysql->sanatize($user->avatar) . '" h="' . $this->mysql->sanatize($user->url) . '" v="2" />');
                        break;
                    case '/a':
                        break;

                    default:
                        $t = $this->getAttribute($packet, 't');
                        $s = $this->getAttribute($packet, 's');
                        $u->sendPacket("<z u=\"".$user->id."\" t=\"".$t."\" s=\"".$s."\" d=\"".$u->id."\" />");
                        break;
                }
            break;
            
            case 'c':
                if($this->spamfilter($tag, $user, 800)) break;
                if($user->banned > time())
                {
                    return false;
                }
                
                if($user->rExpire != 0 && $user->rExpire < time())
                {
                    $this->mysql->query("delete from `ranks` where `userid`={$user->id} and `chatid`='{$user->chat}';");
                    $this->mysql->query("insert into `ranks`(`userid`, `chatid`, `f`) values({$user->id}, {$user->chat}, 3);");
                    return $user->joinRoom($user->chat, 0, true);
                }
                
                $attr     = $this->getAttribute($packet, 'u', true);
                $t2       = $this->getAttribute($packet, 't');
                $uid      = $this->getAttribute($packet, 'u');
				$game     = $this->getAttribute($packet, 'w');
				$p        = $this->getAttribute($packet, 'p');
                $u        = $this->getUserByID($attr, $user->chat);
                $bchat      = $this->mysql->fetch_array("select * from `chats` where `id`='{$user->chat}';");
                $blastban   = $bchat[0]["blastban"];
                $blastkick  = $bchat[0]["blastkick"];
                $blastpro   = $bchat[0]["blastpro"];
                $blastde    = $bchat[0]["blastde"];
                $param3   = substr($t2, 3);
                $param    = substr($t2, 2);
                
                if(!is_object($u))
                {
                    break;
                }
                







                switch(substr($t2, 0, 3))
                {
                    case "/gm":
                        if($this->higherRank($user->rank,$u->rank,true) && in_array($user->rank, array(1, 4)))
                        { // Mute
                            $time = $param3 == 0 ? strtotime("+ 20 years") : strtotime("+ {$param3} seconds");
                            $this->mysql->query("insert into `bans` (`chatid`, `userid`, `unbandate`, `ip`) values ('{$user->chat}', '{$u->id}', '{$time}', '{$u->ipaddr}');");
                            $u->joinRoom($user->chat, 0, true);
                            $user->sendRoom('<m p="'.$this->getAttribute($packet, 'p').'" t="/gm'.$param3.'" u="'.$user->id.'" d="'.$u->id.'" />',false,$u->id);
                            if(in_array($user->group, $this->hasGroupPowers))
                            {
                                $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastban" v="' . $blastban . '" r="'.$this->BlastCor($u->rank).'" o="'.$this->BlastCargo($u->rank).'" />   ', false);
                            }
                            $u->banned = $time;
                        }
                        return;
					
					case '/gg':
                        if($this->higherRank($user->rank,$u->rank,true) && in_array($user->rank, array(1, 2, 4)))
                        { // Gag
							if($u->f & 0xff)
							{
								$this->mysql->query("delete from `bans` where `chatid`='{$user->chat}' and `userid`='{$u->id}' or `chatid`='{$user->chat}' and `ip`='{$u->ipaddr}';");
								$user->sendRoom('<m t="/u" u="' . $user->id . '" d="' . $u->id . '" />');
								$u->sendPacket('<c u="0" d="' . $u->id . '" t="/u" />');
								$u->f -= 0xff;
								$u->joinRoom($user->chat, false, true, 1);
							}
							else
							{
								$time = $param3 == 0 ? strtotime("+ 20 years") : strtotime("+ {$param3} seconds");
								$this->mysql->query("insert into `bans` (`chatid`, `userid`, `unbandate`, `ip`, `type`) values ('{$user->chat}', '{$u->id}', '{$time}', '{$u->ipaddr}', 'f256');");
								$u->joinRoom($user->chat, false, true, 1);
								$user->sendRoom('<m p="'.$this->getAttribute($packet, 'p').'" t="/gg'.$param3.'" u="'.$user->id.'" d="'.$u->id.'" />',false,$u->id);
								$u->banned = $time;
							}
						}
						return;





















						
					case '/gd':
                        if($this->higherRank($user->rank,$u->rank,true) && in_array($user->rank, array(1, 2, 4)))
                        { // Dunce
							if($u->f & 0x8000)
							{
								$this->mysql->query("delete from `bans` where `chatid`='{$user->chat}' and `userid`='{$u->id}' or `chatid`='{$user->chat}' and `ip`='{$u->ipaddr}';");
								$user->sendRoom('<m t="/u" u="' . $user->id . '" d="' . $u->id . '" />');
								$u->sendPacket('<c u="0" d="' . $u->id . '" t="/u" />');
								$u->f -= 0x8000;
								$u->joinRoom($user->chat, false, true, 1);
							}
							else
							{
								$time = $param3 == 0 ? strtotime("+ 20 years") : strtotime("+ {$param3} seconds");
								$this->mysql->query("insert into `bans` (`chatid`, `userid`, `unbandate`, `ip`, `type`) values ('{$user->chat}', '{$u->id}', '{$time}', '{$u->ipaddr}', 'f32768');");
   			 	                                if(in_array($user->group, $this->hasGroupPowers))
				                                {
                                    			        $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastban" v="2" r="'.$this->BlastCor($u->rank).'" o="'.$this->BlastCargo($u->rank).'" />', false);
				                                }
								$u->joinRoom($user->chat, false, true, 1);
								$user->sendRoom('<m p="'.$this->getAttribute($packet, 'p').'" t="/gd'.$param3.'" u="'.$user->id.'" d="'.$u->id.'" w="158" />', false, $u->id);
							}
						}
						return;
                }
                
                switch(substr($t2, 0, 2))
                {
                    case '/r': // Guest
                    case '/e': // Member
                    case '/m': // Mod
                    case '/M': // Owner
                        $ranks = array(
                            'r' => array(array(1, 2, 4), 5),
                            'e' => array(array(1, 2, 4), 3),
                            'm' => array(array(1, 4), 2),
                            'M' => array(array(1), 4)
                        );
                        
                        $rank = $ranks[substr($t2, 1, 1)];
                        
                        if(in_array($user->rank, $rank[0]) && $this->higherRank($user->rank, $u->rank, true))
                        {
                            $this->mysql->query('delete from `ranks` where `userid`=' . $u->id . ' and `chatid`=' . $user->chat . ';');
                            $this->mysql->query('insert into `ranks`(`userid`, `chatid`, `f`) values(' . $u->id . ', ' . $user->chat . ', ' . $rank[1] . ');');
                            $p = $this->getAttribute($packet, 'p');
                            $silent = 'm'; //$user->hasPower(72) && in_array($user->rank, array(1, 4)) && $rank == $ranks['e'] ? 'c' : 'm';
                            $u->sendPacket('<c p="' . $p . '" t="' . substr($t2, 0, 2) . '" u="' . $user->id . '" d="' . $u->id . '" />');
                            //$user->sendRoom('<' . $silent . ' p="' . $p . '" t="' . substr($t2, 0, 2) . '" u="' . $user->id . '" d="' . $u->id . '" />');
                            $user->sendRoom('<m u="' . $user->id . '" d="' . $u->id . '" t="/m" p="' . substr($t2, 1, 1) . '" />');
                                                        /*
                            * Guest: 0x009900
                            * Member: 0x3366FF
                            * Moderator: 0xFFFFFF
                            * Owner: 0xFF9900
                            */
                            $cols = array(
                                "/r" => "0x009900",
                                "/e" => "0x3366FF",
                                "/m" => "0xFFFFFF",
                                "/M" => "0xFF9900"
                            );
                            $colIndex = substr($t2, 0, 2);
                            $blaster = $cols[$colIndex];
                            $oAttr = array(
                                "/r" => "r",
                                "/e" => "e",
                                "/m" => "m",
                                "/M" => "M"
                            );
                            $oIndex = substr($t2, 0, 2);
                            $useO = $oAttr[$oIndex];
                            if(in_array($user->group, $this->hasGroupPowers))
                            {
                                $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastpro" v="' . $blastpro . '" r="'.$blaster.'" o="'.$useO.'" />', false); 
                            }
                            $u->joinRoom($user->chat, 0, true);
                        }
                        break;
                        
                    case '/g': // Ban
                        if(in_array($user->rank, array(1, 2, 4)) && $this->higherRank($user->rank, $u->rank, true))
                        {
                            if($user->rank == 2)
                            { // Mod8
                                $hours = round((($param3 / 60) / 60), 1);
                                $mod8  = $user->haspower(3);
                                if($hours > 6 && !$mod8 || $mod8 && $hours > 8)
                                {
                                    return;
                                }
                            }
							
							$time = $param3 == 0 ? strtotime("+ 20 years") : strtotime("+ {$param3} seconds");
							
							if($game !== false && is_numeric($game) && $game > 0)
							{
								if($user->hasPower($game))
								{
									$this->mysql->query("insert into `bans` (`chatid`, `userid`, `unbandate`, `ip`, `type`) values ('{$user->chat}', '{$u->id}', '{$time}', '{$u->ipaddr}', 'w{$game}');");
									$user->sendRoom('<m p="' . $p . '" t="/g' . $param . '" w="' . $game . '" u="' . $user->id . '" d="' . $u->id . '" />');
									$u->sendPacket('<c p="' . $p . '" w="' . $game . '" t="/g' . $time . '"  u="' . $user->id . '" d="' . $u->id . '" />');
                                    if(in_array($user->group, $this->hasGroupPowers))
                                    {
                                        $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastban" v="2" r="'.$this->BlastCor($u->rank).'" o="'.$this->BlastCargo($u->rank).'" />', false);
                                    }									
									$u->joinRoom($user->chat, false, true, 2);
								}
								else
								{
									$user->sendPacket('<n t="You don\'t have that power!" />');
								}
							}
                            else
							{
								$this->mysql->query("insert into `bans` (`chatid`, `userid`, `unbandate`, `ip`) values ('{$user->chat}', '{$u->id}', '{$time}', '{$u->ipaddr}');");
								$user->sendRoom('<m p="'.$this->getAttribute($packet, 'p').'" t="/g'.$param.'"  u="'.$user->id.'" d="'.$u->id.'" />');
								$u->sendPacket('<c p="'.$this->getAttribute($packet, 'p').'" t="/g'.$time.'" u="'.$this->getAttribute($packet, 'u').'" d="'.$this->getAttribute($packet, 'd').'" />');
								$u->sendRoom("<l u=\"{$u->id}\" />");
                                if(in_array($user->group, $this->hasGroupPowers))
                                {
                                    $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastban" v="2" r="'.$this->BlastCor($u->rank).'" o="'.$this->BlastCargo($u->rank).'" />', false);
                                }
                                $u->sendRoom("<l u=\"{$u->id}\" />"); // Left off here [Blasts]								
									$u->joinRoom($user->chat, false, true, 2);
							}
                        }
                        break;        
						
                    case "/k": // Kick/Boot
                        if(in_array($user->rank, array(1, 2, 4)) && $this->higherRank($user->rank, $u->rank, true))
                        {
                            $args = explode("#", $pee = $this->getAttribute($packet, 'p'));
                            if(count($args) == 2)
                            {
                                $chat = $this->mysql->fetch_array("select * from `chats` where `id`='{$this->mysql->sanatize($args[1])}' or `name`='{$this->mysql->sanatize($args[1])}';");
                                if(empty($chat))
                                {
                                    $user->sendPacket("<n t=\"That chat doesn't exist 3:\" />");
                                }
                                else
                                {
                                    $user->sendRoom("<m p=\"{$pee}\" t=\"/k\" u=\"{$user->id}\" d=\"{$u->id}\" />", false);
                                    $u->sendPacket("<q p2=\"{$pee}\" u=\"{$u->id}\" d2=\"{$user->id}\" r=\"{$chat[0]['id']}\" />");
                                    $u->joinRoom($chat[0]['id'], true);
                                    $user->sendRoom("<l u=\"{$u->id}\" />");
                                }
                            }
                            else
                            {
								if(count($args) == 3 && !$user->hasPower(121))
								{
									$user->sendPacket("<n t=\"You don't have Zap power :c\" />");
								}
								else
								{
									$user->sendRoom("<m p=\"{$pee}\" t=\"/k\" u=\"{$user->id}\" d=\"{$u->id}\" />", false);
									$u->sendPacket("<c p=\"{$pee}\" t=\"/k\" u=\"{$user->id}\" d=\"{$u->id}\" />");
                                    if(in_array($user->group, $this->hasGroupPowers))
                                    {
                                        $user->sendRoom('<bl u="'.$user->id.'" d="'.$u->id.'" t="blastkick" v="' . $blastkick . '" r="'.$this->BlastCor($u->rank).'" o="'.$this->BlastCargo($u->rank).'" />', false);
                                    }								
									$this->disconnect($u->index);
									$user->sendRoom("<l u=\"{$u->id}\" />");
								}
                            }
                        }
                        else
                        {
                            $this->disconnect($user->index);
                        }
                        break;
                        
                    case '/u':
                        if(in_array($user->rank, array(1, 2, 4)) && ($u->rank == 16 && $this->higherRank($user->rank, $u->rank, true)))
                        {
                            $this->mysql->query("delete from `bans` where `chatid`='{$user->chat}' and `userid`='{$u->id}' or `chatid`='{$user->chat}' and `ip`='{$u->ipaddr}';");
                            $user->sendRoom('<m t="/u" u="' . $user->id . '" d="' . $u->id . '" />');
							$u->sendPacket('<c u="0" d="' . $u->id . '" t="/u" />');
							$u->joinRoom($user->chat, 0, true);
                        }
                        break;
                }
            break;
            default:
                $this->disconnect($user->index);
                break;
        }
    }
    
    public function BlastCor($rank) {
        $ranks = array(1,2,3,4,5);
        $cor = "0x009900";
        if($rank == 5) $cor = "0x009900";
        if($rank == 4) $cor = "0xFF9900";
        if($rank == 3) $cor = "0x3366FF";
        if($rank == 2) $cor = "0xFFFFFF";
        if($rank == 1) $cor = "X";
        return $cor;
    }
    
    public function BlastCargo($rank) {
        $ranks = array(1,2,3,4,5);
        $cargo = "0x009900";
        if($rank == 5) $cargo = "r"; // Guest
        if($rank == 4) $cargo = "M"; // Owner
        if($rank == 3) $cargo = "e"; // Member
        if($rank == 2) $cargo = "m"; // Mod
        if($rank == 1) $cargo = "X"; // Main Owner
        return $cargo;
    }
       
    public function mask($packet)
    {
        $length = strlen($packet);
        
        if($length < 126)
        {
            return pack('CC', 0x80 | (0x1 & 0x0f), $length) . $packet;
        }
        elseif($length < 65536)
        {
            return pack('CCn', 0x80 | (0x1 & 0x0f), 126, $length) . $packet;
        }
        else
        {
            return pack('CCNN', 0x80 | (0x1 & 0x0f), 127, $length) . $packet;
        }
    }
    
    public function unmask($packet)
    {
        try
        {
            $length = ord($packet[1]) & 127;
            if($length == 126)
            {
                $masks = substr($packet, 4, 4);
                $data = substr($packet, 8);
            }
            elseif($length == 127)
            {
                $masks = substr($packet, 10, 4);
                $data = substr($packet, 14);
            }
            else
            {
                $masks = substr($packet, 2, 4);
                $data = substr($packet, 6);
            }
            
            $response = '';
            $dlength  = strlen($data);
            for($i = 0; $i < $dlength; ++$i)
            {
                $response .= $data[$i] ^ $masks[$i % 4];
            }
            
            return $response == '' ? false : $response;
        } catch(Exception $e) {
            return false;
        }
    }
    
    public function doLogin($user, $pass)
    {
        /* Variables */
            $vals = array();
            $p = array();
            $pp = '';
            $dO = '';
            $powerO = '';
        
        $user = $this->mysql->fetch_array('select * from `users` where `username`=\'' . $this->mysql->sanatize($user) . '\';');
        if(isset($user[0]))
        {
            $bride = $user[0]['d2'] == 0 ? false : $user[0]['bride'];
            
            if($user[0]['days'] > time())
            {
                $upowers = $this->mysql->fetch_array('select * from `userpowers` where `userid`=' . $user[0]['id'] . ';');
                $spowers = $this->mysql->fetch_array('select * from `powers` where `name` not like \'%(Undefined)%\';');
                
                foreach($spowers as $power)
                {
                    $vals[$power['id']] = array($power['section'], $power['subid']);
                    $p[$power['section']] = 0;
                }
                
                foreach($upowers as $power)
                {
                    if($power['count'] >= 1 && isset($vals[$power['powerid']]) && isset($p[$vals[$power['powerid']][0]]))
                    {
                        $str = $power['powerid'] . '=' . ($power['count'] > 1 ? ($power['count'] - 1) : 1) . '|';
                        $p[$vals[$power['powerid']][0]] += $vals[$power['powerid']][1];
                        $dO .= $str;
                        if($power['count'] > 1)
                        {
                            $powerO .= $str;
                        }
                    }
                }
                
                foreach($p as $i => $u)
                {
                    $pp .= " d" . (substr($i, 1) + 4) . "=\"{$u}\"";
                }
            }
            
            $this->mysql->query("update `users` set `dO`='{$this->mysql->sanatize($powerO)}' where `username`='{$this->mysql->sanatize($user[0]['username'])}';");
            
            return "<v RL=\"1\" i=\"{$user[0]['id']}\" c=\"{$user[0]['xats']}\" dt=\"0\" n=\"{$user[0]['username']}\" k1=\"{$user[0]['k']}\" k2=\"{$user[0]['k2']}\" k3=\"{$user[0]['k3']}\" bride=\"{$bride}\" d0=\"{$user[0]['d0']}\" d1=\"{$user[0]['days']}\" d2=\"{$user[0]['d2']}\" d3=\"\"{$pp} dx=\"{$user[0]['xats']}\" dO=\"{$powerO}\" PowerO=\"{$powerO}\" />";
        }
        return false;
    }

    public function getUserByID($id, $chat=null)
    {
        if($id == 2 || $id == 0)
        {
            return false;
        }
        foreach($this->users as $user)
        {
            if($user->id == $id && ($chat == null || $user->chat == $chat))
            {
                return $user->online ? $user : false;
            }
        }
        return false;
    }
    
    function higherRank($rank1, $rank2, $minMod = false)
    {
        if($rank1 == $rank2)
        {
            return false;
        }
        $order = array(1, 2, 3, 4);
        if(in_array($rank1, $order) && !in_array($rank2, $order))
        {
            return true;
        }
        if($rank1 == 1)
        {
            return true;
        }
        if($rank1 == 4 && $rank2 != 1)
        {
            return true;
        }
        if($rank1 == 2 && $rank2 != 1 && $rank2 != 4)
        {
            return true;
        }
        if($minMod == true)
        {
            return false;
        }
        if($rank1 == 3 && $rank2 != 1 && $rank2 != 4 && $rank2 != 2)
        {
            return true;
        }
        return false;
    }
    
    function objectToArray($object)
    {
        $array = array();
        foreach($object as $member => $data)
        {
            $array[$member] = $data;
        }
        return $array;
    }

    public function getAttribute($xml, $attName, $reverse = false)
    {
        $att = $this->objectToArray($xml->attributes());
        if($reverse == true)
        {
            array_reverse($att);
        }
        
        foreach($att as $a=>$b)
        {
            if($a == $attName)
            {
                $b = htmlspecialchars ($b);
                return $b;
            }
        }
        return false;
    }
    
    public function getMultiAttr($xml, $names=array(), $values=array())
    {
        setType($names, 'array');
        if(!method_exists($xml, 'attributes'))
        {
            return array();
        }
        
        foreach($names as $u)
        {
            $values[$u] = false;
        }
        
        foreach($xml->attributes() as $i=>$u)
        {
            if(in_array($i, $names) || empty($names))
            {
                $values[$i] = ((string)((string)$u));
            }
        }
        
        return $values;
    }
    
    public function disconnect($userID, $logout=null, $num=null, $chatid=null)
    {
        if(isset($this->users[$userID]) && $user = $this->users[$userID])
        {
            if(!is_null($logout) && $user->online)
            {
                $user->sendPacket("<logout />");
            }
            
			if(is_resource($user->sock))
			{
				socket_close($user->sock);
				$user->sock = null;
			}
            $user->online = false;

            return true;
        }
        return false;
    }
    
    public function ipban($ip, $dcall=true)
    {
        if(!filter_var($ip, FILTER_VALIDATE_IP))
        {
            return false;
        }
        
        $this->ipbans[] = $ip;
        if($dcall == true)
        {
            foreach($this->users as $u)
            {
                if($u->ipaddr == $ip)
                {
                    $this->disconnect($u->index);
                }
            }
        }
        $bans = json_encode($this->ipbans);
        $this->mysql->query("update `server` set `ipbans`='{$this->mysql->sanatize($bans)}';");
        return true;
    }
    

    public function ipUnban($ip)
    {
        if(!filter_var($ip, FILTER_VALIDATE_IP))
        {
            return false;
        }
        foreach($this->ipbans as $index => $addr)
        {
            if($ip == $addr)
            {
                unset($this->ipbans[$index]);
                $bans = json_encode($this->ipbans);
                $this->mysql->query("update `server` set `ipbans`='{$this->mysql->sanatize($bans)}';");
                return true;
            }
            else
            {
                continue;
            }
        }
        return false;
    }
    
    public function spamfilter($element, $user, $ms=800, $time=null, $dc=true)
    {
        if(is_null($time))
        {
            $time = round(microtime(true) * 1000);
        }
        if(isset($user->last[$element]) && ($user->last[$element] + $ms) >= $time)
        {
            return (is_null($dc) ? true : $this->disconnect($user->index));
        }
        $user->last[$element] = $time;
        return false;
    }
    
}



class client
{
    public $sock, $parent;
    public $bride, $rank, $id, $username, $nickname, $k, $k2, $k3, $password, $avatar, $url, $powers, $room, $xats, $days, $chat, $banned, $hidden = false, $pool = 0, $switchingPools = false;
    public $d0, $d1, $d2, $d3, $d4, $d5, $d6, $dt, $dx, $dO, $p0, $p1, $p2, $p4, $PowerO, $d7, $p3, $homepage, $h, $group, $away = false, $pStr;
    public $loginKey = null, $last = array(), $authenticated = null, $online = false, $disconnect = false, $rExpire = 0, $chatPass = false, $pawn = '';
    public $mobready = false, $buffer = '';
    

    public function __construct(&$socket, &$parent, $index, $ipaddr, $mobile = false)
    {
        list($this->index, $this->sock, $this->parent, $this->ipaddr, $this->mobile) = array(
            $index, $socket, $parent, $ipaddr, $mobile
        );
    }
    
    public function resetDetails($id, $bans = null)
    {
        $user = $this->parent->mysql->fetch_array("select * from `users` where `id`='{$this->parent->mysql->sanatize($id)}' and `id` not in(0, 2);");
        if(empty($user))
        {
            $this->guest = true;
        }
        else
        {
            if($user[0]['username'] == '')
            {
                list($this->guest, $this->k, $this->k2, $this->k3) = array(
                    true, $user[0]['k'], $user[0]['k2'], $user[0]['k3']
                );
            }
            else
            {
                $this->xats     = $user[0]['xats'];
                $this->days     = floor(($user[0]['days'] - time()) / 86400);
                $this->username = $user[0]['username'];
                $this->password = $user[0]['password'];
                $this->enabled  = $user[0]['enabled'];
                $this->k        = $user[0]['k'];
                $this->k2       = $user[0]['k2'];
                $this->k3       = $user[0]['k3'];
                $this->PowerO   = $user[0]['dO'];
                $this->powers   = $user[0]['powers'];
                $this->avatar   = $user[0]['avatar'];
                $this->url      = $user[0]['url'];
                $this->d1       = 0;
                $this->d2       = $user[0]['d2'];
                $this->bride    = $user[0]['bride'];
                $this->d3       = null;
				$this->pawn     = $user[0]['custpawn'] == 'off' ? '' : $user[0]['custpawn'];
                
                if($this->mobile)
                {
                    $this->nickname = $this->username == '' ? 'Unregistered' : $this->username;
                }
                else
                {
                    $this->nickname    = explode("##", $user[0]['nickname'], 2);
                    $this->nickname[0] = htmlspecialchars_decode($this->nickname[0]);
                    $this->nickname    = count($this->nickname)>1?implode("##", $this->nickname):$this->nickname[0];
                }
				
                if(true || $user[0]['torched']!=1)
                { // Torching - Add Later
                    if(!$this->getPowers())
                    {
                        return false;
                    }
                    
                    $this->dO = $user[0]['dO'];
                }
                $this->dt  = null;
                $this->guest = false;
            }
		
			$trolls = json_decode($user[0]['trolls'], true);
			if(is_array($trolls))
			{
				foreach($trolls as $i => $u)
				{
					$this->{$i} = $u;
				}
			}
        }
		
        if($this->guest === true)
        {
            $this->username = '';
        }
        return true;
    }

    public function getPowers($pV = array())
    {
        if($this->days < 1)
        {
            for($i = 0; $i <= $this->parent->config->pcount; $this->{'p' . $i++} = 0);
            return true; /* Obvious much? */
        }
        
        $powers = $this->parent->mysql->fetch_array('select * from `userpowers` where `userid`=' . $this->id . ';');
        $powerv = $this->parent->mysql->fetch_array('select `id`, `section`, `subid` from `powers` where `name` not like \'%(Undefined)%\';');
        $pv = $test = $final = array();
        foreach($powerv as $power)
        {
            $pv[$power['id']] = array('sect' => $power['section'], 'sub' => (int) $power['subid']);
            $test[$power['section']] = 0;
            $last[$power['section']] = 0;

        }
        
        foreach($powers as $power)
        {
            $test[$pv[$power['powerid']]['sect']] += $pv[$power['powerid']]['sub'];
        }
        
        foreach($test as $sect => $val)
        {
            if((int) $val != (int) $this->{$sect . 'v'})
            {
                return false;
            }
        }
        
        foreach($powers as $power)
        {
            if(isset($pv[$power['powerid']]))
            {
                $power = $pv[$power['powerid']];
                if((int) $this->{$power['sect'] . 'v'} & $test[$power['sect']])
                {
                    if(!((int) $power['sub'] & $test[$power['sect']]))
                    {
                        return false;
                    }
                    
                    if(!($this->{'m' . substr($power['sect'], 1)} & (int) $power['sub']))
                    {
                        $last[$power['sect']] += (int) $power['sub'];
                    }







                }
            }
        }
        
        $this->pStr = '';
        foreach($test as $sect => $u)
        {
            $this->{$sect} = $last[$sect];
            $this->pStr .= $sect . '="' . $this->{$sect} . '" ';
        }







        
        return true;
    }
    
    public function updateDetails()
    {
        if($this->id != 0 && $this->id != 2 && $this->mobile == false)
        {
            $this->parent->mysql->query(
                "update `users` set
                    `nickname`='{$this->parent->mysql->sanatize($this->nickname)}',
                    `avatar`='{$this->parent->mysql->sanatize($this->avatar)}',
                    `url`='{$this->parent->mysql->sanatize($this->url)}',
                    `connectedlast`='{$this->ipaddr}'
                where `id`='{$this->parent->mysql->sanatize($this->id)}';"
            );
        }
        return ($this->id != 0 && $this->id != 2) ? true : false;
    }
    
    public function hasPower($power)
    {
        list($subid, $section) = array(
            pow(2, $power % 32),
            $power >> 5
        );
        
        return $this->{'p' . $section} & $subid ? true : false;;
    }

    public function authenticate($packet)
    {
        //print_r($packet->Attributes());
        /* Load Packet Information */
            /* Load Packet / Values */
                $attributes = array('u', 'N', 'k', 'pool', 'f', 'ym1', 'ym2', 'h', 'd0', 'a', 'c', 'banned', 'r');
                for($i = 0; $i <= $this->parent->config->pcount; $i++)
                {
                    array_push($attributes, 'd' . ($i + 4));
                    array_push($attributes, 'm' . $i);
                }
                
                $info = $this->getMultiAttr($packet, $attributes);
                
                for($i = 0; $i <= $this->parent->config->pcount; $i++)
                {
                    $this->{'p' . $i . 'v'} = (int) $info['d' . ($i + 4)];
                    $this->{'m' . $i} = (int) $info['m' . $i];
                }
            /* End */
            $this->id =  (string)  $info['u'];
            $this->d0 =  (integer) $info['d0'];
            $this->f =  (integer) $info['f'];
            $n    =  (string)  $info['N'];
            $k    =  (integer) $info['k'];
            $pool = $this->pool;
            

            if($this->mobile)
            {
                $this->f |= 0x0200;
            }
			





            $this->b  = $this->f & 8 ? true : false;
            $chat     = (int) $info['c'];
            
            for($i = 0; $i <= $this->parent->config->pcount; $i++)
            {
                $this->{'p' . $i . 'v'} = isset($info['d' . ($i + 4)]) ? $info['d' . ($i + 4)] : 0;
                $this->{'m' . $i} = isset($info['m' . $i]) ? $info['m' . $i] : 0;
                $this->pStr .= 'p' . $i . '="' . $this->{'p' . $i . 'v'} . '" ';
            }
        /* End */
        /* Reset details, Check powers */
            if(!$this->resetDetails($this->id))
            {
                return false;
            }
            $this->url    = (string) $info['h'];
            $this->avatar = (string) $info['a'];
        /* End */
        /* Bot Protection */
            if(!$this->mobile)
            {
                $this->bot1 = (int) $info['ym1'];
                $this->bot2 = (int) $info['ym2'];
                





                $bot2 = floor(pow(2, $this->loginShift % 32));
                $bot1 = floor(2 << ($this->loginKey % 30)) % $this->loginTime + $this->loginKey;
				
                if($bot1 != $this->bot1 || $bot2 != $this->bot2)
                {
                    return false;
                }
            }
        /* End */
        /* Chat Password [get main] */
            if($info['r'] !== false)
            {
                $this->chatPass = $info['r'];
            }
        /* Sanatize Name / Explode Status */
            $this->nickname = $this->getAttribute($packet, 'n');
            $this->nickname = explode('##', $this->nickname, 2);
            if(count($this->nickname) > 1)
            {
                $this->nickname[1] = htmlentities(str_replace("", "", $this->nickname[1]));
                $this->nickname = implode('##', $this->nickname);
            }
            else
            {
                $this->nickname = $this->nickname[0];
            }
            if(strlen($this->nickname) > 255)
            {
                //return false;
            }
        /* End */
        /* Just some information checking for guest system, + user exists */
            if($this->guest == true && isset($this->enabled) && $this->id != 2)
            {
                return false;
            }
            elseif($this->id != 2 && is_numeric($k))
            {
                $user = $this->parent->mysql->fetch_array("select * from `users` where `id`='{$this->parent->mysql->sanatize($this->id)}' and `k`='{$this->parent->mysql->sanatize($k)}' and `id`!='' and `k`!='';");
                if(empty($user))
                {
                    return false;
                }
                elseif($user[0]['username'] == 'Unregistered')
                {
                    $this->guest = true;
                }
                else
                {
                    $this->guest = false;
                }
            }
            else
            {
                $this->guest = true;
            }
        /* End */
        $this->updateDetails();
        $this->authenticated = true;
        return $this->joinRoom($chat, 1, false, $pool);
    }

    public function getAttribute($xml, $name)
    {
        if(method_exists($xml, 'attributes'))
        {
            foreach($xml->attributes() as $a=>$b)
            {
                if($a==$name) return (string) $b;
            }
        }
        return false;
    }
    
    public function getMultiAttr($xml, $names=array(), $values=array())
    {
        setType($names, 'array');
        if(!method_exists($xml, 'attributes'))
        {
            return array();
        }
        foreach($names as $u)
        {
            $values[$u] = false;
        }
        foreach($xml->attributes() as $i => $u)
        {
            if(in_array($i, $names))
            {
                $values[$i] = mb_convert_encoding((string) $u, "utf-8");
            }
        }
        return $values;
    }

    public function message($t, $ex = true)
    {
        $this->sendMessage($t,$this->id,0,false,$ex);
    }
    
    public function sendMessage($t, $u='[C]', $i=0, $s=false, $ex=false)
    {
        if($u=='[C]') $u = $this->id;
        $packet = "<m t=\"{$t}\" u=\"{$u}\" i=\"{$i}\" />";
        $ex!=false?$this->sendRoom($packet, $ex):$this->sendPacket($packet);
    }
    
    public function sendPacket($packet)
    {
        if($this->sock)
        {
            if($this->mobile == true)
            {
                $packet = simplexml_load_string($packet);
                if(!method_exists($packet, 'getName'))
                {
                    $this->parent->disconnect($this->sock);
                    return false;
                }
                
                $json = new stdClass();
                $json->tag = $packet->getName();
                foreach($packet->Attributes() as $i => $u)
                {
                    $json->{$i} = (string)$u;
                }
                
                $packet = json_encode($json);
                $packet = $this->parent->mask($packet);
            }
            elseif(substr($packet, -1) != chr(0))
            {
                $packet .= chr(0);
            }
            
			// socket_set_nonblock($this->sock);
            if(!@socket_write($this->sock, $packet, strlen($packet)))
            {
                $this->parent->disconnect($this->sock);
                return false;
            }
			
			// socket_set_block($this->sock);
            return true;
        }
    }
    
    public function sendAll($packet)
    {
        if(stristr($packet, strlen($packet) - 1, 1) != chr(0))
        {
            $packet = $packet.chr(0);
        }
        foreach($this->parent->users as &$user)
        {
            if(!@socket_write($user->sock, $packet, strlen($packet)))
            {
                $this->parent->disconnect($user->index);
            }
        }
        return true;
    }

    public function parseRank($rank)
    {
        $ranks = array(1, 2, 3, 4, 5);
        if(!is_numeric($rank))
        {
            switch(strtolower($rank))
            {
                case 'guest':     return 5;
                case 'owner':     return 4;
                case 'member':    return 3;
                case 'moderator': return 2;
                case 'mainowner': return 1;
                default:          return 0;
            }
        }
        elseif(!in_array($rank,$ranks))
        {
            return 0;
        }
        return $rank;
    }
    
    public function rank($numrank, $word=null, $compare=null)
    { // Made this for the hell of it
        $ranks = array(
            5 => array(5, 'guest'),
            3 => array(4, 'member'),
            2 => array(3, 'moderator'),
            4 => array(2, 'owner'),
            1 => array(1, 'mainOwner')
        );
        if(!in_array($numrank, $ranks))
        {
            $rank = $ranks[5];
        }
        else
        {
            $rank = $ranks[$numrank];
        }
        return is_null($compare) ? (is_null($word) ? $rank[0] : $rank[1]) : ($rank[0] < $ranks[$compare][0] ? true : false);
    }

    public function __destruct()
    {
        /* It's done like this to avoid a bitch of a memory leak */
        if(isset($this->id) && !isset($this->noLogout))
        {
            $this->sendRoom('<l u="' . $this->id . '" />', true);
        }
    }
    
    public function joinRoom($chat, $reload = true, $nodup = false, $pool = 0, $banTick = 0)
    {
        /* Initial Information */
		
			list($this->pool, $this->hidden) = array($pool, false);

            if(!$this->authenticated || !is_numeric($chat) || $chat < 1)
            {
                return false;
            }
            			
            $chat = $this->parent->mysql->fetch_array("select * from `chats` where `id`='{$this->parent->mysql->sanatize($chat)}';");
            if(empty($chat))
            {
                return false;
            }
            

            list($this->chatid, $this->group) = array($chat[0]['id'], $chat[0]['name']);
        /* Do Ranks */
            $ranks = $this->parent->mysql->fetch_array("select * from `ranks` where `chatid`='{$chat[0]['id']}' and `userid`='{$this->parent->mysql->sanatize($this->id)}';");
            if($this->chatPass !== false)
            {
                if($this->parent->mysql->validate($this->chatPass, $chat[0]['pass']) === true)
                {
                    if(empty($ranks))
                    {
                        $this->parent->mysql->query("insert into `ranks`(`userid`, `chatid`, `f`) values({$this->id}, {$this->chatid}, 1);");
                    }
                    else
                    {
                        $this->parent->mysql->query("update `ranks` set `f`=1 where `userid`={$this->id} and `chatid`={$this->chatid};");
                    }
                    $ranks[0] = array(
                        'userid'  => $this->id,
                        'chatid'  => $this->chatid,
                        'f'       => 1,
                        'tempend' => 0
                    );
                }
            }
            if(!isset($ranks[0]['f']))
            {
                $ranks[0] = array('f' => 5);
                $this->parent->mysql->query("insert into `ranks` (`userid`, `chatid`, `f`) values ('{$this->parent->mysql->sanatize($this->id)}', '{$chat[0]['id']}', '5');");
            }
            elseif($ranks[0]['tempend'] > 0 && $ranks[0]['tempend'] < time())
            {
                $ranks[0] = array("f" => 3);
                $this->parent->mysql->query("update `ranks` set `f`=3, `tempend`=0 where `userid`={$this->id} and `chatid`={$this->chatid};");
            }
            else
            {
                $userRank = $ranks[0]['f'];
                $this->rExpire = $ranks[0]['tempend'] > time() ? $ranks[0]['tempend'] : 0;
            }
            
            $this->rank = $ranks[0]['f'];
			
			if($this->hasPower(29) && !$this->online && in_array($this->rank & 7, array(1, 4)))
			{
				$this->hidden = true;
				
				if(!($this->f & 0x0400))
				{
					$this->f += 0x0400;
				}
			}
			elseif($this->f & 0x0400)
			{
				$this->f -= 0x0400;
			}
			
            $this->updateDetails();
            $this->resetDetails($this->id, true);
        /* End */
        /* Update / Check Bans */
			$game = '';
            $this->banned = 0;
            $this->unban = false;
            $ban = $this->parent->mysql->fetch_array("select * from `bans` where `userid`='{$this->parent->mysql->sanatize($this->id)}' and `chatid`='{$this->parent->mysql->sanatize($chat[0]['id'])}' or `ip`='{$this->ipaddr}' and `chatid`='{$this->parent->mysql->sanatize($this->chatid)}' order by `unbandate` desc limit 0,1;");
            if(!empty($ban) && ($this->id == $ban[0]['userid'] || $this->ipaddr == $ban[0]['ip']))
            {
                $ban = $ban[0];
                if($ban['unbandate'] >= $this->loginTime)
                {
					if(substr($ban['type'], 0, 1) == 'w')
					{
						$this->rank = 16;
						$game = ' w="' . substr($ban['type'], 1) . '"';
					}
					elseif(substr($ban['type'], 0, 1) == 'r')
					{
						$this->rank |= (int) substr($ban['type'], 1);
					}
					elseif(substr($ban['type'], 0, 1) == 'f')
					{
						$this->f |= (int) substr($ban['type'], 1);
					}
					else
					{
						$this->rank = 16;
					}
					
					if(!($this->f & 0x8000))
					{
						$this->banned = $ban['unbandate'];
					}


                }
                elseif($this->id == $ban['userid'])
                {
                    $this->unban = true;
                    $this->parent->mysql->query("delete from `bans` where `userid`='{$this->parent->mysql->sanatize($this->id)}' and `chatid`='{$this->parent->mysql->sanatize($chat[0]['id'])}' and `unbandate`<={$this->loginTime};");
                }
            }
            elseif(empty($ban) && $this->b == true)
            {
                $this->unban = true;
            }
            elseif(isset($ban['unbandate']))
            {
                $this->sendPacket("<n t=\"You are banned for " . round(($ban['unbandate'] - time())/ 60, 1) . " more minutes.\" />");
            }
        /* End */
        /* Chat Information */
            if(empty($chat[0]['attached'])) 
            {
                $chat[0]['attached'] = array('Lobby', '1');
            }
            else
            {
                $info = $this->parent->mysql->fetch_array("select * from `chats` where `name`='{$this->parent->mysql->sanatize($chat[0]['attached'])}';");
                if(empty($info) || $info[0]['id'] == $chat[0]['id'])
                {
                    $chat[0]['attached'] = array('Lobby', '1');
                }
                else
                {
                    $chat[0]['attached'] = array(
                        0 => $info[0]['name'],
                        1 => $info[0]['id']
                    );
                }
            }
            if($chat[0]['attached'][1] == $this->chatid)
            {
                $chat[0]['attached'] = array('0', '0');
            }
            
            
            if($this->unban == true)
            {
                $this->sendPacket('<c u="0" d="' . $this->id . '" t="/u" />');
                $this->unban = false;
            }
            $pawn = strlen($this->pawn) == 6 ? ' pawn="' . $this->pawn . '"' : '';
			
            $this->sendPacket("<i{$pawn}{$game} b=\"{$chat[0]['bg']};={$chat[0]['attached'][0]};={$chat[0]['attached'][1]};=;={$chat[0]['radio']};={$chat[0]['button']}\" f=\"{$this->f}\" v=\"3\" r=\"{$this->rank}\" cb=\"10\" />");
            /* Pools */
            if(in_array($this->group, $this->parent->hasGroupPowers))
            { // Group Powers, done this way until I get the packet to assign.
                $this->sendPacket('<w v="'.$pool.' ' . $chat[0]['pool'] . '" />');
                $this->sendPacket($this->buildGp());
            }
           // $this->sendPacket('<gp p="0|0|1431372864|1074025493|273678340|268435456|16384|1|0|0|0|0|0|" g80="{\'mg\':\'0\',\'mb\':\'11\',\'kk\':\'0\',\'bn\':\'0\',\'ubn\':\'0\',\'prm\':\'0\',\'bge\':\'0\',\'mxt\':50,\'sme\':\'11\',\'dnc\':\'8\'}" g114="{\'m\':\'' . $chat[0]['chat'] . '\',\'t\':\'' . $chat[0]['mods'] . '\',\'rnk\':\'7\',\'b\':\'' . $chat[0]['banned'] . '\',\'v\':1}" g90="' . $chat[0]['badword'] . '" g74="' . $chat[0]['smiles'] . '" g106="' . $chat[0]['gback'] . '" g188="a91" g100="' . $chat[0]['link'] . '" u="1" />');

	//@$this->sendPacket('<gp g80="{\'mg\':\'0\',\'mb\':\'11\',\'kk\':\'0\',\'bn\':\'0\',\'ubn\':\'0\',\'prm\':\'0\',\'bge\':\'0\',\'mxt\':50,\'sme\':\'11\',\'dnc\':\'8\'}" g114="{\'m\':\'' . $chat[0]['chat'] . '\',\'t\':\'' . $chat[0]['mods'] . '\',\'rnk\':\'7\',\'b\':\'' . $chat[0]['banned'] . '\',\'v\':1}" g90="' . $chat[0]['badword'] . '" g74="' . $chat[0]['gline'] . '" g106="' . $chat[0]['gback'] . '" g188="a91" g100="' . $chat[0]['link'] . '" p="0|0|1431372864|1074025493|273678340|268435456|16384|1|0|0|0|0|0|" />');        /* End */
        /* Check if user is already on chat */
            if($nodup == false)
            {
                while($r = $this->parent->getUserByID((int)$this->id, (int)$chat[0]['id']))
                {
                    if(is_object($r) && $r->online === true)
                    {
                        $r->sendPacket("<dup />");
                        $r->noLogout = true;
                        $this->parent->disconnect($r->index, true);
                    }
                }
            }
        /* End */
        /* Compile, and send user list */
            $this->chat = $chat[0]['id'];
            $myNick = explode("##", $this->nickname, 2);
            $myNick[0] = htmlspecialchars(html_entity_decode(htmlspecialchars_decode($myNick[0])));
            $myNick = count($myNick) > 1 ? implode("##", $myNick) : $myNick[0];
			
            $myPack = "<u{$pawn} so=\"1\" f=\"{$this->f}\" flag=\"{$this->f}\" rank=\"{$this->rank}\" u=\"{$this->id}\" q=\"3\"" . ($this->username == '' ? '' : " N=\"{$this->username}\"") . " n=\"{$myNick}\" a=\"{$this->avatar}\" h=\"{$this->url}\" d0=\"{$this->d0}\" d2=\"{$this->d2}\" bride=\"{$this->bride}\" {$this->pStr}v=\"1\" />";
            $valid  = simplexml_load_string($myPack);
            if(!method_exists($valid, 'getName'))
            {
                return false;
            }
            else
            {
                foreach($this->parent->users as $user)
                {
                    if($this->mobile == true && $user->mobile == true && $user->ipaddr == $this->ipaddr && $user->username != $this->username)
                    {
                        $this->parent->disconnect($user->index);
                    }
                    
                    if($user->chat == $chat[0]['id'] && $user->id != $this->id && $user->pool == $this->pool)
                    {
                        if(!in_array($user->id, array(0, 2)) && $user->hidden == false)
                        {
                            $user->bride = $user->d2 == 0 ? null : $user->d2;
                            $nick = explode('##', $user->nickname, 2);
                            $nick[0] = htmlspecialchars(html_entity_decode(htmlspecialchars_decode($nick[0])));
                            $nick = count($nick) > 1 ? implode('##', $nick) : $nick[0];
                            $pawn = strlen($user->pawn) == 6 ? ' pawn="' . $user->pawn . '"' : '';
							
                            $packet = "<u{$pawn} flag=\"{$user->f}\" s=\"1\" f=\"{$user->f}\" rank=\"{$user->rank}\" u=\"{$user->id}\" q=\"3\"" . ($user->username == '' ? '' : " N=\"{$user->username}\"") . " n=\"{$nick}\" a=\"{$user->avatar}\" h=\"{$user->url}\" d0=\"{$user->d0}\" d2=\"{$user->d2}\" bride=\"{$user->bride}\" {$user->pStr}v=\"1\" />";
                            $valid  = simplexml_load_string($packet);

                            if(method_exists($valid, 'getName'))
                            {
                                $this->sendPacket($packet);
                            }
                            else
                            {
                                $this->parent->disconnect($user->index);
                                continue;
                            }
                        }
                        
                        if(!in_array($this->id, array(0, 2)) && $this->hidden == false)
                        {
                            $user->sendPacket($myPack);
                        }
                    }
                }
            }
        /* End */
        /* Send Previous Messages (15) */
            if($reload == true)
            {
                $messages = $this->parent->mysql->fetch_array("select * from `messages` where `id`='{$chat[0]['id']}' and `pool`={$this->pool} order by time desc limit 0,15;");
                for($i = 0; $i < count($messages); $i++)
                {
                    $message = $messages[count($messages) - $i - 1];
                    if($message['visible'] == '1')
                    {
                        $this->sendPacket("<m u=\"{$message['uid']}\" n=\"{$message['name']}\" N=\"{$message['registered']}\" a=\"{$message['avatar']}\" i=\"{$message['mid']}\" t=\"{$message['message']}\" s=\"1\" />");
                    }
                }
                unset($messages); unset($message);
            }
        /* End */
        $this->sendPacket("<done />");
        /* Other info, scrollies, protection meh */
            $this->sendPacket("<m u=\"{$chat[0]['ch']}\" t=\"/s{$chat[0]['sc']}\" />");
            
            if(isset($this->parent->protected[$this->chat]))
            {
                $time = floor(($this->parent->protected[$this->chat]['end']-time())/60);
                switch($this->parent->protected[$this->chat]['type'])
                {
                    case 'noguest':
                        $this->sendPacket("<z d=\"0\" u=\"0\" t=\"This chat is protected for another {$time} minutes. Guests cannot chat until given a higher rank.\" />");
                        break;
                    case 'unreg':
                        $this->sendPacket("<z d=\"0\" u=\"0\" t=\"This chat is protected for another {$time} minutes. Unregistered users cannot chat until given a higher rank.\" />");
                        break;
                }
            }
            
            elseif($this->f & 1 && 1==2)
            {
                $this->sendPacket("<logout e=\"E12\" />");
            }
			
			$this->online = true;
        /* End */
        return true;
    }

    public function buildGp()
    {
        $gdata = $this->parent->mysql->fetch_array("SELECT * FROM `chats` WHERE `name`='".$this->group."';");
        $gp  = "<gp ";
        $gp .= "p=\"0|0|1431655744|1079334229|290459972|269549572|16645|272646145|4194305|0|0|0|0|\" "; // wut is this lel
        $gp .= "g80=\"{'mm':'14','mbt':48,'ss':'14','prm':'14','dnc':'14','bdg':'8'}\" ";
        $gp .= "g90=\"{$gdata[0]['bad']}\" ";
        $gp .= "g112=\"{$gdata[0]['announce']}\" ";
        $gp .= "g246=\"{'dt':70,'v':1}\" ";
        $gp .= "g256=\"{'rnk':'2','dt':65,'rt':15,'rc':'1','tg':200,'v':1}\" ";
        if($gdata[0]['pools'] != null)
        { // Rektion Protection Bruh
            $gp .= "g114=\"{$gdata[0]['pools']}\" ";
        }
        $gp .= "g100=\"{$gdata[0]['link']}\" ";
        $gp .= "g74=\"{$gdata[0]['gline']}\" "; 
        $gp .= "g106=\"{$gdata[0]['gback']}\" ";
        $gp .= "/>";
        return $gp;
    }
	
    public function sendRoom($packet, $passme=false, $exclude=0)
    {
        foreach($this->parent->users as $user)
        {
            if(
                $user->chat == $this->chat &&
                $user->id != $exclude      &&
                (
                    isset($user->pool) &&
                    isset($this->pool) &&
                    $user->pool == $this->pool
                )
            ) {
                if($user->id != $this->id || $passme == false) {
                    $user->sendPacket($packet);
                }
            }
        }
    }

}



class database
{
    public $link, $host, $user, $pass, $name;
    public $doe = true;
    
    public function __construct($host=null, $user=null, $pass=null, $name=null)
    {
        if($name != null)
        {
            $this->host = $host;
            $this->user = $user;
            $this->pass = $pass;
            $this->name = $name;
        }
        
        if(!$this->connected())
        {
            $this->link = @mysqli_connect($this->host, $this->user, $this->pass, $this->name);
            if(!$this->connected())
            {
                $this->error("Failed to connect to `{$this->host}`.`{$this->name}` using password [" . (empty($this->pass) ? "NO" : 'YES') . "]");
            }
        } return true; // Cause I can put it there if I want to
    }
    
    public function connected()
    {
        return @mysqli_ping($this->link) ? true : false;
    }
    
    public function error($error)
    {
        print $error . chr(10);
        if($this->doe == true)
        {
            exit('line:' .  __LINE__);
        }
    }
    
    public function query($query = "")
    {
        if(!is_string($query))
		
        {
            return false;
        }
        $this->__construct();
        $return = mysqli_query($this->link, $query);
        return $return?$return:false;
    }
    
    public function fetch_array($query, $return = array())
    {
        $this->__construct();
        if(!is_string($query) || !($res = $this->query($query)))
        {
            return array();
        }
        while($data = mysqli_fetch_assoc($res))
        {
            $return[] = $data;
        }
        return !empty($return) ? $return : array();
    }
    
    public function sanatize($data) {
        if(is_array($data))
        {
            return array_map(array($this, 'sanatize'), $data);
        }
        if(function_exists("mb_convert_encoding"))
        {
            $data = mb_convert_encoding($data, "UTF-8", 'auto');
        }
        return $this->link->real_escape_string($data);
    }
    

    public function rand($length = 32, $low = true, $upp = true, $num = true, $indent = false)
    {
        $chars = array_merge(
            $low ? range('a', 'z') : array(),
            $upp ? range('A', 'Z') : array(),
            $num ? range('0', '9') : array()
        );
        for($rand = ""; strlen($rand) < $length; $rand .= $chars[ array_rand($chars) ]);
        if($indent != false)
        {
            $rand = implode('-', str_split($rand, $indent));
        }
        return $rand;
    }
    
    static function urs($x, $y)
    {
        return ($x >> $y) & (2147483647 >> ($y - 1));
    }
    
    public function hash($str, $rawsalt = '', $hash = 'sha512')
    {
        if($rawsalt == '')
        {
            $rawsalt = $this->rand(((strlen($str) % 3) + 1) * 5);
        }
        
        $loc = array(hash('sha1', $rawsalt), hash('sha1', $str), '');
        foreach(str_split($loc[0], 1) as $index => $character)
        {
            $loc[2] .= $character . $loc[1][$index];
        }
        
        $hash = hash($hash, $loc[2]);
        return substr_replace($hash, $rawsalt, (strlen($str) << 2) % strlen($hash), 0);
    }
    
    public function validate($str, $hash, $engine = 'sha512')
    {
        $salt = substr($hash, (strlen($str) << 2) % strlen(hash($engine, 1)), ((strlen($str) % 3) + 1) * 5);
        return $this->hash($str, $salt, $engine) === $hash ? true : false;
    }
    
    public function hashPass($pass, $salt=null, $hashtype='sha512', $hash="")
    {
        return $this->hash($pass, $salt, $hashtype);
    }
    
    public function checkPass($input, $real, $hash='sha512')
    {
        return $this->validate($input, $real, $hash);
    }
    
}