<?php
set_time_limit(5);
$mimes = array(
	'png' => 'image/png',
	'jpg' => 'image/jpeg',
	'gif' => 'image/gif',
	'css' => 'text/css',
	'js'  => 'text/javascript',
	'woff' => 'font/woff',
	'html' => 'text/html',
	'swf'  => 'application/x-shockwave-flash',
	'eot'  => 'font/eot',
	'ttf'  => 'font/ttf',
	'svg'  => 'font/svg',
	'ico'  => 'image/x-icon'
);

try
{
	$cd = getcwd() . '/';
	
	if(!isset($_GET['f']) || !isset($_SERVER['HTTP_REFERER']))
	{
		throw new Exception('File Not Found');
	}
	
	$extLocation = strrpos($_GET['f'], '.');
	if($extLocation === false)
	{
		throw new Exception('File Not Found');
	}
	
	list($name, $ext) = array(substr($_GET['f'], 0, $extLocation), substr($_GET['f'], $extLocation + 1));
	if(strlen($name) == 0 || strlen($ext) == 0 || !ctype_alnum($ext))
	{
		throw new Exception('File Not Found');
	}
	
	$dir = isset($_GET['d']) ? $_GET['d'] : $ext;
	
	$dirs = array_filter(glob('*'), 'is_dir');
	if(!in_array($dir, $dirs))
	{
		throw new Exception('File Not Found');
	}
	
	chdir($dir);
	
	$files = glob('*');
	if(!in_array($_GET['f'], $files))
	{
		throw new Exception('File Not Found');
	}
	
	header('Content-Type: ' . (isset($mimes[$ext]) ? $mimes[$ext] : 'Application/octet-stream'));
	header('Content-Length: ' . filesize($_GET['f']));
	header('Content-disposition: inline; filename="' . urlencode($_GET['f']) . '"');
	// header("Content-Disposition:attachment;filename='" . $_GET['f'] . "'");
	header('ETag: ' . md5($_GET['f']));
	header('Expires: ' . date('r', time() + 864000));
	readfile($_GET['f']);
}
catch(Exception $e)
{
	print $e->getMessage();
}

exit;