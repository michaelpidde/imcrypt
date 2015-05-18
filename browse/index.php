<?php
$quitUrl = $_SERVER['PHP_SELF'] . "?quit=" . $pid;
$dbName = urldecode($_GET['db']);
$db = new SQLite3($dbName, SQLITE3_OPEN_READWRITE);
$imgs = $db->query("SELECT id, name, thumb FROM images");
$out = "";
$count = 0;
while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
	$count++;
	$thumb = $img['thumb'];
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
