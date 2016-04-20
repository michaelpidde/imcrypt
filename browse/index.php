<?php
include '../imcrypt.php';
include 'functions.php';

session_start();

$out = "";

if(array_key_exists($_POST, 'submit')) {
	$dbPassword = getPassword();
	$enteredPassword = sha1($_POST['password']);
	if($dbPassword == $enteredPassword) {
		$_SESSION['password'] = base64_encode($enteredPassword);
	} else {
		// This will trickle down and show the login form.
		unset($_SESSION['password']);
	}
}

if(array_key_exists($_SESSION, 'password')) {
	$dbName = urldecode($_GET['db']);
	$db = new SQLite3($dbName, SQLITE3_OPEN_READWRITE);
	$imgs = $db->query("SELECT id, name, thumb FROM images");
	$count = 0;
	while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
		$count++;
		$thumb = absorbSalt($img['thumb'], $_SESSION['salt']);
	        $out .= "<div class=\"stupid-wrapper\">";
	        $out .= "<div class=\"image\">";
	        $out .= "<a href=\"http://localhost:8888/getImg.php?db=";
	        $out .= $_GET['db'] . "&id=" . $img['id'];
	        $out .= "\">";
		$out .= "<img src=\"data:image/jpeg;base64," . $thumb . "\" />";
	        $out .= "</a>";
		$out .= "</div>";
	        $out .= "<div class=\"name\">" . $img['name'] . "</div>";
	        $out .= "</div>";
	}
} else {
	$out = loginForm();
}
?>

<!doctype html>
<html>
<head>
<title>imcrypt Image Browser</title>
<link href="style.css" rel="stylesheet">
<script src="script.js"></script>
</head>
<body>
<main>
	<header>
		<b>Database:</b> <?php echo $dbName; ?><br>
		<b>Images:</b> <?php echo $count; ?>
	</header>
	<?php echo $out; ?>
</main>
</body>
</html>
