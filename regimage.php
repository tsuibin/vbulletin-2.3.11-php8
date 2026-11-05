<?php
require_once __DIR__ . '/admin/php8compat.php';

	error_reporting(7);

	define('JPG', 2);
	define('PNG', 3);

	if (function_exists('imagejpeg'))
	{
		define('IMAGEJPEG', true);
	}
	else
	{
		define('IMAGEJPEG', false);
	}

	if (function_exists('imagepng'))
	{
		define('IMAGEPNG', true);
	}
	else
	{
		define('IMAGEPNG', false);
	}

	require('./global.php');

	function vbimage(&$image, $type = 2)
	{

		// Try to create a jpg
		if (IMAGEJPEG)
		{
			header('Content-Type: image/jpeg');
			@imagejpeg($image);
			imagedestroy($image);
			return true;
		}

		// Try to create a PNG if we don't have a JPEG
		// Don't use PNG by default since a bug exists with the library on some systems see http://bugs.php.net/bug.php?id=16841
		if (!IMAGEJPEG)
		{
			header('Content-Type: image/png');
			@imagepng($image);
			imagedestroy($image);
			return true;
		}

		return false;
	}

	if (!$ih OR !($imageinfo = $DB_site->query_first("SELECT imagestamp FROM regimage WHERE regimagehash = '" . vb_escape($ih) . "'")))
	{
		exit;
	}

	$string = $imageinfo['imagestamp'];
	for ($x = 0; $x < strlen($string); $x++)
	{
		$newstring .= $string[$x] . ' ';
	}
	$string = '  ' . $newstring . ' ';

	// Temp image that creates string
	$temp_width  = 135;
	$temp_height = 20;
	// Resized image that blows up string.
	$image_width = 201;
	$image_height = 61;

	if ($gdversion == 1)
	{
		$image = imagecreate($image_width, $image_height);
		$temp = imagecreate($temp_width, $temp_height);
	}
	else if ($gdversion == 2)
	{
		$image = imagecreatetruecolor($image_width, $image_height);
		$temp = imagecreatetruecolor($temp_width, $temp_height);
	}
	else
	{
		exit;
	}

	$background_color = imagecolorallocate ($temp, 255, 255, 255); //white background
	imagefill($temp, 0, 0, $background_color); // For GD2+
	$text_color = imagecolorallocate ($temp, 0, 0,0);//black text

	imagestring ($temp, 5, 0, 2,  $string, $text_color);
	imagecopyresized($image, $temp, 0, 0, 0, 0, $image_width, $image_height, $temp_width, $temp_height);
	imagedestroy($temp);

	for ($x = 0; $x <= $image_height; $x += 20)
	{
		imageline($image, 0, $x, $image_width, $x, $text_color);
	}

	for ($x = 0; $x <= $image_width; $x += 20)
	{
		imageline($image, $x, 0, $x, $image_height, $text_color);
	}

	$pixels = $image_width * $image_height / 20;
	for($i = 0; $i < $pixels; $i++){
		imagesetpixel($image, rand(0, $image_width), rand(0, $image_height), $text_color);
	}

	// get multipliers for waves
	$wavenum = 3;
	$wavemultiplier = ($wavenum * 360) / $image_width;

	// cosine wave
	$curX = 0;
	$curY = $image_height;
	for($pt = 0; $pt < $image_width; $pt++)
	{
		$newX = $curX + 1;
		$newY = ($image_height/2) + (cos(deg2rad($newX * $wavemultiplier)) * ($image_height/2));
		ImageLine($image, $curX, $curY, $newX, $newY, $text_color);
		$curX = $newX;
		$curY = $newY;
	}

	// sine wave
	$curX = 0;
	$curY = 0;
	for($pt = 0; $pt < $image_width; $pt++)
	{
		$newX = $curX + 1;
		$newY = ($image_height/2) + (sin(deg2rad($newX * $wavemultiplier - 90)) * ($image_height/2));
		ImageLine($image, $curX, $curY, $newX, $newY, $text_color);
		$curX = $newX;
		$curY = $newY;
	}

	vbimage($image);

?>



