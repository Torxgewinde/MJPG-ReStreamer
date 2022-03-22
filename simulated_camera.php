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

/**********************************************************************/
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);

//the boundary can be configured here.
$boundaryOut = "MyMultipartBoundaryDoNotStumble";

set_time_limit(10);

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
Description.: Output image to stream
Input Value.: $img is JPEG encoded image
Return Value: -
******************************************************************************/
function OutputImage($img) {
	global $boundaryOut;

	echo "Content-Type: image/jpeg\r\n".
		"Content-Length: ".strlen($img)."\r\n".
		"X-Timestamp: ".number_format(microtime(true), 6, '.', '')."\r\n".
		"\r\n".
		$img.
		"\r\n--$boundaryOut\r\n";

	flush();
	ob_flush();
	while (@ob_end_flush());
}

/******************************************************************************
Description.: Convert string to Image
Input Value.: $str is the message to write, linebreaks are not supported
              $background is JPEG encoded background image
Return Value: JPEG encoded image data
******************************************************************************/
function TextToImage($str, $background=NULL) {
	if( !is_null($background) ) {
		$img = imagecreatefromstring($background);
		$bgc = imagecolorallocate($img, 255, 255, 255);
		imagefilledrectangle($img, 10, 10, 640-10, 50, $bgc);
	} else {
		$img = imagecreatetruecolor(640, 480);
		$bgc = imagecolorallocate($img, 255, 255, 255);
		imagefilledrectangle($img, 10, 10, 640-10, 480-10, $bgc);
	}
	$tc  = imagecolorallocate($img, 0, 0, 0);
	imagestring($img, 1, 20, 20, $str, $tc);

	ob_start();
	imagejpeg($img, NULL, -1);
	$imgstr = ob_get_contents();
	ob_end_clean();

	return $imgstr;
}

//Generate MJPEG stream as test pattern
$img = TextToImage("MJPG-ReStreamer");
$img = imagecreatefromstring($img);
while(true) {
	imageline($img,
		rand(0,640),
		rand(0,480),
		rand(0,640),
		rand(0,480),
		imagecolorallocate($img, rand(0,255), rand(0,255), rand(0,255)));

	ob_start();
	imagejpeg($img, NULL, -1);
	$imgstr = ob_get_contents();
	ob_end_clean();

	OutputImage($imgstr);
	usleep(100*1000);
}
?>
