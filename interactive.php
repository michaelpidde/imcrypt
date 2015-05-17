#!/usr/bin/php
<?php

echo <<<EOF

********************************************************************************
|                                                                              |
| imcrypt is a utility to (mostly insecurely) encrypt and decrypt images into  |
| a textual format.                                                            |
|                                                                              |
********************************************************************************

EOF;

define("SCRIPT", __dir__ . "/");
define("PROMPT", "imcrypt:/> ");
define("ARG_ERROR", "Argument(s) expected. ");
define("LOAD_DB", "No database loaded. ");
define("HELP", "See 'help' for details.");
define("ENDL", "\n");

$GLOBALS['db'] = null;
$GLOBALS['dbName'] = null;
$GLOBALS['browsePid'] = null;

/*
 * MAIN LOOP
 */
$continue = true;
while($continue) {
	$args = readline(PROMPT);
	if(validCommand(getCmd($args))) {
		$continue = processCommand($args);
	}
}

function processCommand($args) {
	$continue = true;
	switch(getCmd($args)) {
	case "add":
		if(checkDB()) {
			add($args);
		} else {
			errLoadDB();
		}
		break;
	case "browse":
		if(checkDB()) {
			browse();
		} else {
			errLoadDB();
		}
		break;
	case "exit":
		onExit();
		$continue = false;
		break;
	case "help":
		help();
		break;
	case "list":
		if(checkDB()) {
			listImgs($args);
		} else {
			errLoadDB();
		}
		break;
	case "new":
		newDb($args);
		break;
	case "open":
		open($args);
		break;
	case "view":
		echo "View.\n";
		break;
	case "which":
		if(checkDB()) {
			which();
		} else {
			errLoadDB();
		}
		break;
	}

	return $continue;
}

/*
 * HELPER FUNCTIONS
 */
function validCommand($cmd) {
	$commands = [
		"add",
		"browse",
		"exit",
		"help",
		"list",
		"new",
		"open",
		"view",
		"which",
	];
	return in_array($cmd, $commands);
}

function getCmd($cmd) {
	return explode(" ", $cmd)[0];
}

function countArgs($args, $num) {
	/* Don't count first arg which is the name of the command itself. */
	return count(explode(" ", $args)) >= $num + 1;
}

function checkDB() {
	if($GLOBALS['db'] === null) {
		return false;
	} else {
		return true;
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

function isCompatibleType($imgName) {
	return in_array(getExt($imgName), ["GIF", "JPG", "PNG"]);
}

function isLinux() {
	return strpos(php_uname(), "Linux") >= 0;
}

/*
 * IMAGE MANIPULATION
 */

function getImgResource($path) {
	switch(getExt($path)) {
	case "GIF":
		return imagecreatefromgif($path);
		break;
	case "JPG":
		return imagecreatefromjpeg($path);
		break;
	case "PNG":
		return imagecreatefrompng($path);
		break;
	}
}

function generateThumb($path) {
	$img = getImgResource($path);
	$info = getimagesize($path);
	$newWidth = 150;
	$newHeight = 100;
	/* Check if width is less than height. */
	if($info[0] < $info[1]) {
		$newWidth = 100;
		$newHeight = 150;
	}
	$scaled = imagescale($img, $newWidth, $newHeight);

	/* Don't actually write image, just get its content in output buffer. */
	ob_start();
	imagejpeg($scaled);
	$data = ob_get_contents();
	ob_end_clean();
	
	return $data;
}

/*
 * ERROR FUNCTIONS
 */

function errLoadDB() {
	echo LOAD_DB . HELP . ENDL;
}

/*
 * HANDLERS
 */
function add($args) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		if(!isCompatibleType($argv[1])) {
			echo "Non-compatible image type. " . HELP . ENDL;
			return 0;
		}
		$img = fread(fopen($argv[1], "r"), filesize($argv[1]));
		$thumb = generateThumb($argv[1]);
		addImg(
			getImgName($argv[1]), 
			base64_encode($img),
			base64_encode($thumb)
		);
	} else {
		echo ARG_ERROR . HELP . ENDL;
	}
}

function browse() {
	$output = [];
	exec("php -S localhost:8888 "
		. " -t ". SCRIPT . "browse/" 
		. " > /dev/null 2>&1 & echo $!",
		$output
	);
	$GLOBALS['browsePid'] = $output[0];

	if(isLinux()) {
		$cmd = "xdg-open";
	} else {
		$cmd = "open";
	}
	exec($cmd . " http://localhost:8888/index.php?"
		. "db=" . urlencode(SCRIPT . $GLOBALS['dbName'])
		. " > /dev/null &"
	);
}

function onExit() {
	if($GLOBALS['browsePid'] != null) {
		exec("kill " . $GLOBALS['browsePid']);
	}
}

function help() {
echo <<<EOT

  add -- Add new image
  browse -- Open image browser
  exit -- Exit program
  help -- Display help menu
  list -- List entries in database
  new -- Create new image database
  open -- Open existing image database
  view -- View entry details
  which -- Information about currently open database


EOT;
}

function listImgs($args) {
	$imgs = getImgs();
	while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
		echo $img["id"] . " -- " . $img["name"] . ENDL;
	}
}

function newDb($args) {
	open($args, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
	createImgTable();
}

function open($args, $flag=SQLITE3_OPEN_READWRITE) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		$GLOBALS['dbName'] = $argv[1];
		$GLOBALS['db'] = new SQLite3($argv[1], $flag);
	} else {
		echo ARG_ERROR . HELP . ENDL;
	}
}

function which() {
	var_dump($GLOBALS['db']);
}

/*
 * QUERY FUNCTIONS
 */
function createImgTable() {
	$GLOBALS['db']->exec(
		"CREATE TABLE images(" .
		"id INTEGER PRIMARY KEY AUTOINCREMENT, " .
		"name TEXT, " .
		"data TEXT, " .
		"thumb TEXT" .
		")"
	);
}

function getImgs() {
	return $GLOBALS['db']->query("SELECT id, name, thumb FROM images");
}

function addImg($name, $data, $thumb) {
	$GLOBALS['db']->exec(
		"INSERT INTO images (name, data, thumb) " .
		"VALUES('$name', '$data', '$thumb')"
	);
}
