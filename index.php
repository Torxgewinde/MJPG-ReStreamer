<?php

/* *********************************************************************
 * 
 * MJPG-ReStreamer, MJPG-Reverse-Proxy written in PHP
 * 
 * (c) 2015 Stephen Price / Webmad, http://www.webmad.co.nz
 * (c) 2022 Tom Stöveken
 * 
 * This PHP script connects to a MJPG/MJPEG camera stream, extracts just
 * the pictures and retransmits them as a MJPG-stream
 * 
 * In contrast to common HTTP(s) reverse-proxies this script just passes
 * the MJPEG stream and prevents accessing the upstream camera details
 * and settings.
 * 
 * ********************************************************************/

//define camera, if the camera supports TLS / SSL put "tls://" in front
//of the host or IP
$host = "tls://your-camera.lan";
$port = "443";
//the URL varies a lot for each manufacturer and camera model
$url  = "/cgi-bin/mjpg/video.cgi?channel=0&subtype=1";
//If the camera requires Basic authentication provide credentials as
// username:password if no auth is required, set $auth to value false
$auth = "my_username:my_password_123";

//This script grants access by username and password
//Either by Basic auth or by passing those values as GET parameter
$user = "another_username";
$pass = "another_password123";

/**********************************************************************/
//the boundary can be configured here. The upstream cameras boundary is 
//taken from the headers if $boundaryIn is set to false
$boundaryOut = "MyMultipartBoundaryDoNotStumble";
$boundaryIn = false;
//define after how many seconds of accumulated CPU usage the script exits
set_time_limit(100);

if( isset($_GET["$user"]) and strcmp($_GET["$user"], $pass) == 0 ) {
	//user is authenticated by GET parameter
}
else {
	//trigger browser to show a password dialog
	if( !isset($_SERVER['PHP_AUTH_USER']) or !isset($_SERVER['PHP_AUTH_PW']) ) {
		header('WWW-Authenticate: Basic realm="Webcam"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'No credentials';
		exit;
	}
	if( strcmp($_SERVER['PHP_AUTH_USER'], $user) != 0 or strcmp($_SERVER['PHP_AUTH_PW'], $pass) != 0 ) {
		header('WWW-Authenticate: Basic realm="Webcam"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Wrong credentials';
		exit;
	}
}

//send headers
header('Cache-Control: no-cache');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 01:00:00 GMT');
header('Connection: close');
header('Script-Info: MJPG-ReStreamer');
header("Content-Type: multipart/x-mixed-replace; boundary=$boundaryOut");
echo "--$boundaryOut\r\n";
flush();

/******************************************************************************
Description.: print a string to a frame of the stream
Input Value.: string to write to the stream
Return Value: -
******************************************************************************/
function MessageImage($str) {
	$img = imagecreatetruecolor(640, 480);

	$bgc = imagecolorallocate($img, 255, 255, 255);
	$tc  = imagecolorallocate($img, 0, 0, 0);

	imagefilledrectangle($img, 10, 10, 640-10, 480-10, $bgc);

	imagestring($img, 1, 20, 20, $str, $tc);

	ob_start();
	imagejpeg($img, NULL, -1);
	$imgstr = ob_get_contents();
	ob_end_clean();

	echo "Content-Type: image/jpeg\r\nContent-Length: ".
		strlen($imgstr)."\r\n\r\n".
		$imgstr.
		"\r\n--$boundaryOut\r\n";
	flush();
}

/******************************************************************************
Description.: clean up when the script finishes
              script finished either by client leaving or by reaching
              CPU-time-limit.
              Render one last frame with the last error
Input Value.: -
Return Value: -
******************************************************************************/
function shutdown() {
	global $fp;

	if ($fp !== false)
		fclose($fp);

	$last_error = error_get_last();
	$last_error = $last_error['message'];

	MessageImage($last_error);
}
register_shutdown_function('shutdown');

//Either use fsockopen or stream_sock_client to open $fp
$fp = @fsockopen($host, $port, $errno, $errstr, 10);

/*
 * if the camera cert is self-signed, maybe ignore TLS certificate details
 * WARNING: MITM is possible when setting verify... to false
 * Due to weakening security, this option is commented out.
 */
/*
$context = stream_context_create([
	'ssl' => [
		'verify_peer' => false,
		'verify_peer_name' => false
	]
]);
$fp = stream_socket_client("$host:$port", $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
*/

if ($fp === false) {
	/* Show error as stream picture */
	MessageImage("Input failed (FP: ". json_encode($fp).", $errstr)");
	shutdown();
	die();
}

//send data to the upstream camera
$out = "GET $url HTTP/1.1\r\n";
$out .= "Host: $host\r\n";
if($auth !== false)
	$out .= "Authorization: Basic ". base64_encode($auth) ."\r\n";
$out .= "\r\n";
fwrite($fp, $out);

$buffer='';
$counter = 0;
while (!feof($fp)) {
	$buffer .= fgets($fp);
	$part=$buffer;

	//learn boundary string
	if ($boundaryIn === false) {
		$boundaryStart = strpos($buffer, 'Content-Type: multipart/x-mixed-replace; boundary=');
		if( $boundaryStart === false )
			continue; 
		$boundaryStart = $boundaryStart + strlen('Content-Type: multipart/x-mixed-replace; boundary=');
		$boundaryEnd = strpos($buffer, "\r\n", $boundaryStart);
		if( $boundaryEnd === false )
			continue;
		if ($boundaryStart >= $boundaryEnd)
			continue;
			$boundaryIn = substr($buffer, $boundaryStart, $boundaryEnd-$boundaryStart);
	}

	//extract single JPEG frame, alternatively we could also search EOI, SOI markers
	$part = substr($part, strpos($part, "--$boundaryIn") + strlen("--$boundaryIn"));
	$part = trim(substr($part, strpos($part, "\r\n\r\n")));
	$part = substr($part, 0, strpos($part, "--$boundaryIn"));

	//substr returns an emtpy string if the string could not be extracted
	//an image should not be smaller then this, so skip if too small
	if(strlen($part) <= 100)
		continue;

	//shorten the buffer
	$buffer = substr($buffer, strpos($buffer, $part) + strlen($part));

	//without overlay, this does not stress the server as much as de/encoding JPEGs does
	echo "Content-Type: image/jpeg\r\n".
	     "Content-Length: ".strlen($part)."\r\n".
	     "\r\n".
	     $part.
	     "\r\n--$boundaryOut\r\n";

	flush();
	continue;
}
