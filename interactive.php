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

define("PROMPT", "imcrypt:/> ");
define("ARG_ERROR", "Argument(s) expected. ");
define("LOAD_DB", "No database loaded. ");
define("HELP", "See 'help' for details.");
define("ENDL", "\n");
$GLOBALS['db'] = null;

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
			echo LOAD_DB . HELP . ENDL;
		}
		break;
	case "new":
		newDb($args);
		break;
	case "exit":
		$continue = false;
		break;
	case "help":
		help();
		break;
	case "list":
		if(checkDB()) {
			listImgs($args);
		} else {
			echo LOAD_DB . HELP . ENDL;
		}
		break;
	case "open":
		open($args);
		break;
	case "view":
		echo "View.\n";
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
		"exit",
		"help",
		"list",
		"new",
		"open",
		"view",
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
		addImg(getImgName($argv[1]), base64_encode($img));
	} else {
		echo ARG_ERROR . HELP . ENDL;
	}
}

function help() {
echo <<<EOT

  add -- Add new image
  exit -- Exit program
  help -- Display help menu
  list -- List entries in database
  new -- Create new image database
  open -- Open existing image database
  view -- View entry details


EOT;
}

function listImgs($args) {
	$imgs = getImgs();
	while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
		echo $img["id"] . " -- " . $img["name"] . ENDL;
	}
}

function newDb($args) {
	open($args);
	createImgTable();
}

function open($args) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		$GLOBALS['db'] = new SQLite3($argv[1]);
	} else {
		echo ARG_ERROR . HELP . ENDL;
	}
}

/*
 * QUERY FUNCTIONS
 */
function createImgTable() {
	$GLOBALS['db']->exec(
		"CREATE TABLE images(" .
		"id INTEGER PRIMARY KEY AUTOINCREMENT, " .
		"name TEXT, " .
		"data TEXT" .
		")"
	);
}

function getImgs() {
	return $GLOBALS['db']->query("SELECT id, name FROM images");
}

function addImg($name, $data) {
	$GLOBALS['db']->exec(
		"INSERT INTO images (name, data) " .
		"VALUES('$name', '$data')"
	);
}