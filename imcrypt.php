#!/usr/bin/php
<?php

define("HELP", "Please see imcrypt.php -h for help.\n");

/*
 * Set up expected command line arguments.
 */
$options = getopt("a:i:h::");

if(count($options) == 0) {
	echo "No arguments supplied. " . HELP;
	return 0;
}

/*
 * Display help if -h flag was passed.
 */
if(array_key_exists("h", $options)) {
echo <<<EOF

  imcrypt is a utility to (mostly insecurely) encrypt and decrypt images into a
  textual format.

  Required arguments:
    -a    Action to perform [enc|dec].
    -i    Path to image file if action is "enc", else name of encrypted version.
          Supported image types are JPG, PNG, and GIF.

    Optional arguments:
    -h    Display help menu.


EOF;
return 0;
}

/*
 * Parse remaining arguments.
 */
if(!array_key_exists("a", $options)) {
	echo "No action supplied. " . HELP;
	return 0;
} elseif($options["a"] != "enc" && $options["a"] != "dec") {
	echo "Invalid value for action. " . HELP;
	return 0;
} else {
	$action = $options["a"];
}

if(!array_key_exists("i", $options)) {
	echo "No image name supplied. " . HELP;
	return 0;
} else {
	$imgName = $options["i"];
}

if(!file_exists($imgName)) {
	echo "Image '" . $imgName . "' not found.\n";
	return 0;
}

/*
 * Process image.
 */
switch($action) {
case "enc":
	writeFlatFile($imgName, encImg($imgName));
	break;
case "dec":
	decImg($imgName);
	break;
}

/*
 * FUNCTIONS
 */
function encImg($imgName) {
	if(!isCompatibleType($imgName)) {
		echo "Non-compatible image type. " . HELP;
		return 0;
	}
	$img = fread(fopen($imgName, "r"), filesize($imgName));
	return base64_encode($img);
}

function decImg($imgName) {
	//exec("php -S localhost:8888");
	//exec("sensible-browser localhost:8888");
}

function writeFlatFile($imgName, $string) {
	$name = getImgName($imgName);
	if(!file_exists("/home/michael/Documents/code/imcrypt/" . $name)) {
		touch("/home/michael/Documents/code/imcrypt/" . $name);
	}
	file_put_contents("/home/michael/Documents/code/imcrypt/" . $name, $string);
}

function getExt($imgName) {
	/*
	 * Get last 4 chars. We'll check if there's a period so we know we're
	 * actually dealing with a file extension.
	 */
	$type = strtoupper(substr($imgName, -4));
	if(!substr($type, 1) == ".") {
		/*
		 * File extension is longer than 3 chars, or it's just not a
		 * real extension. We don't handle those in this script. Just
		 * return it.
		 */
		return $type;
	} else {
		return substr($type, -3);
	}
}

/*
 * Returns just image name, no path.
 * For the sake of simplicity we're assuming that the $imgName being passed in
 * already passes isCompatibleType so we can just lop off the end.
 */
function getImgName($imgName) {
	$parts = explode("/", $imgName);
	$name = $parts[count($parts) - 1];
	return substr($name, 0, strlen($name) - 4);
}

function isCompatibleType($imgName) {
	return in_array(getExt($imgName), ["GIF", "JPG", "PNG"]);
}
