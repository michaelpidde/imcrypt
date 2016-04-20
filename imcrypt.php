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
$GLOBALS['password'] = null;
$GLOBALS['loggedIn'] = false;



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
		databaseRequiredWrapper("add", $args);
		break;
	case "browse":
		databaseRequiredWrapper("browse", $args);
		break;
	case "delete":
		databaseRequiredWrapper("delete", $args);
		break;
	case "exit":
		onExit();
		$continue = false;
		break;
	case "export":
		databaseRequiredWrapper("export", $args);
		break;
	case "help":
		help();
		break;
	case "import":
		databaseRequiredWrapper("import", $args);
		break;
	case "list":
		databaseRequiredWrapper("listImgs", $args);
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
		which();
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
        "delete",
		"exit",
		"export",
		"help",
        "import",
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

function databaseRequiredWrapper($callback, $args) {
	if(is_callable($callback)) {
		if(checkDB()) {
			$callback($args);
		} else {
			errLoadDB();
		}
	}
}

/*
 * Returns just image name, no path.
 */
function getImgName($imgName) {
	$parts = explode("/", $imgName);
	$name = $parts[count($parts) - 1];
	$ext = getExt($name);
	return str_replace($ext, "", $name);
}

function getExt($imgName) {
	/*
	 * Get last 5 chars. We'll check if there's a period so we know we're
	 * actually dealing with a file extension. This will work for file
	 * extensions with 4 chars (JPEG) as well as the normal 3.
	 */
	$type = strtoupper(substr($imgName, -5));
	if(substr($type, 0, 1) == ".") {
		return substr($type, -4);
	} elseif(substr($type, 1, 1) == ".") {
		return substr($type, -3);
	} else {
		return $type;
	}
}

function getValidImgsInDir($dir) {
	if(is_dir($dir)) {
		$scan = scandir($dir);
		$filtered = [];
		foreach($scan as $img) {
			if(isCompatibleType($img)) {
				array_push($filtered, $dir . "/" . $img);
			}
		}
		return $filtered;
	} else {
		errInvalidDirectory($dir);
	}
}

function addImgProcess($path) {
	$img = fread(fopen($path, "r"), filesize($path));
	$thumb = generateThumb($path);
	$fullEncoding = base64_encode($img);
	$fullEncoding = addSalt($fullEncoding, base64_encode($GLOBALS['password']));
	$thumbEncoding = base64_encode($thumb);
	$thumbEncoding = addSalt($thumbEncoding, base64_encode($GLOBALS['password']));
	addImg(
		getImgName($path),
		getExt($path),
		$fullEncoding,
		$thumbEncoding
	);
}

function exportAll() {
	$imgs = getImgs(true);
	while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
		exportImg(
			$img['name'],
			$img['ext'],
			base64_decode(absorbSalt($img['data'], $GLOBALS['password']))
		);
	}
}

function promptPassword($new = false) {
	if($new) {
		echo 'New password: ';
	} else {
		echo 'Password: ';
	}

	// Change terminal to not show typed chars.
	system('stty -echo');
	$password = trim(fgets(STDIN));
	// Change back to show typed chars.
	system('stty echo');
	echo "\n";
	
	return $password;
}

function addSalt($img, $salt) {
	// Security by obscurity... sort of... but not really...
	$index = rand(0, 1000);
	if(strlen($img) >= $index) {
		return substr_replace($img, $salt, $index, 0);
	} else {
		// Just keep trying.
		addSalt($img, $salt);
	}
}

function absorbSalt($img, $salt) {
	// Salt will be within first 1000 chars of image encoding. Get first 1000
	// so we don't have to search the whole string.
	$chars = substr($img, 0, 1000);
	$img = str_replace($salt, '', $img);
	return $img;
}

function checkPassword() {
	$password = promptPassword();
	$password = sha1($password);
	if($password == getPassword()) {
		return true;
	} else {
		return false;
	}
}

function isCompatibleType($imgName) {
	return in_array(getExt($imgName), ["GIF", "JPG", "JPEG", "PNG"]);
}

function isLinux() {
	return (strpos(php_uname(), "Linux") === false) ? false : true;
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
	case "JPEG":
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

function exportImg($name, $ext, $data) {
	echo "Exporting " . $name . ENDL;
	$img = imagecreatefromstring($data);
	switch(getExt($ext)) {
	case "GIF":
		return imagegif($img, $name);
		break;
	case "JPG":
	case "JPEG":
		return imagejpeg($img, $name);
		break;
	case "PNG":
		return imagepng($img, $name);
		break;
	}
}



/*
 * ERROR FUNCTIONS
 */
function errInvalidArguments() {
        echo ARG_ERROR . HELP . ENDL;
}

function errInvalidDirectory($dir) {
        echo "Invalid directory '$dir'" . ENDL;
}

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
		addImgProcess($argv[1]);
	} else {
		errInvalidArguments();
	}
}

function browse($args) {
	if($GLOBALS['browsePid'] == null) {
		$output = [];
		exec("php -S localhost:8888 "
			. " -t ". SCRIPT . "browse/" 
			. " > /dev/null 2>&1 & echo $!",
			$output
		);
		$GLOBALS['browsePid'] = $output[0];
	}

	if(isLinux()) {
		$cmd = "xdg-open";
	} else {
		$cmd = "open";
	}
	exec($cmd . " http://localhost:8888/index.php"
		. "?db=" . urlencode(SCRIPT . $GLOBALS['dbName'])
		. " > /dev/null &"
	);
}

function delete($args) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		deleteImg($argv[1]);
	} else {
		errInvalidArguments();
	}
}

function onExit() {
	if($GLOBALS['browsePid'] != null) {
		exec("kill " . $GLOBALS['browsePid']);
	}
}

function export($args) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		if($argv[1] == "all") {
			exportAll();
		} else {
			$data = absorbSalt(getImg($argv[1]), $GLOBALS['salt']);
			exportImg(
				$data['name'],
				$data['ext'],
				base64_decode($data['data'])
			);
		}
	} else {
		errInvalidArguments();
	}
}

function help() {
echo <<<EOT

  add -- Add new image (GIF, JPG, or PNG)
  browse -- Open image browser
  delete -- Delete image by ID
  exit -- Exit program
  help -- Display help menu
  import -- Add valid images (GIF, JPG, PNG) in directory
  list -- List entries in database
  new -- Create new image database
  open -- Open existing image database
  view -- View entry details
  which -- Information about currently open database


EOT;
}

function import($args) {
        if(countArgs($args, 1)) {
                $argv = explode(" ", $args);
                $dir = $argv[1];
                $imgs = getValidImgsInDir($dir);
                foreach($imgs as $img) {
                        echo "Importing " . $img . ENDL;
                        addImgProcess($img);
                }
        } else {
                errInvalidArguments();
        }
}

function listImgs($args) {
	$imgs = getImgs();
	while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
		echo $img["id"] . " -- " . $img["name"] . ENDL;
	}
}

function newDb($args) {
	// false passed in as third param so we don't check login.
	open($args, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE, false);
	createImgTable();
	createSettingsTable();
	$password = promptPassword(true);
	setPassword($password);
}

function open($args, $flag=SQLITE3_OPEN_READWRITE, $checkPassword=true) {
	if(countArgs($args, 1)) {
		$argv = explode(" ", $args);
		$GLOBALS['dbName'] = $argv[1];
		$GLOBALS['db'] = new SQLite3($argv[1], $flag);
		if($checkPassword) {
			checkPassword();
		}
	} else {
		errInvalidArguments();
	}
}

function which() {
        echo "Current database: ";
	if(checkDB()) {
                 echo $GLOBALS['dbName'] . ENDL;
        } else {
                echo "None" . ENDL;
        }
        if($GLOBALS['browsePid'] != null) {
                echo "Browse PID: " . $GLOBALS['browsePid'] . ENDL;
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
		"ext TEXT, " .
		"data TEXT, " .
		"thumb TEXT" .
		")"
	);
}

function createSettingsTable() {
	$GLOBALS['db']->exec(
		"CREATE TABLE settings(" .
		"password TEXT" .
		")"
	);
}

function setPassword($password) {
	$password = sha1($password);
	$GLOBALS['password'] = $password;
	$GLOBALS['db']->exec(
		"INSERT INTO settings (password) VALUES ('" . $password . "')"
	);
}

function getPassword() {
	$qry = "SELECT password FROM settings";
	return $GLOBALS['db']->query($qry)->fetchArray()['password'];
}

function getImgs($withData = false) {
	$qry = "SELECT id, name, ext, thumb";
	$qry .= ($withData) ? ", data" : "";
	$qry .= " FROM images";
	return $GLOBALS['db']->query($qry);
}

function addImg($name, $ext, $data, $thumb) {
	$GLOBALS['db']->exec(
		"INSERT INTO images (name, ext, data, thumb) " .
		"VALUES('$name', '$ext', '$data', '$thumb')"
	);
}

function getImg($id) {
	$qry = $GLOBALS['db']->prepare("SELECT name, ext, data FROM images WHERE id = :id");
	$qry->bindValue("id", $id, SQLITE3_INTEGER);
	return $qry->execute()->fetchArray();
}

function deleteImg($id) {
        $qry = $GLOBALS['db']->prepare("DELETE FROM images WHERE id = :id");
        $qry->bindValue("id", $id, SQLITE3_INTEGER);
        $qry->execute();
}
