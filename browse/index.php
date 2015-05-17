<?php
$db = new SQLite3("test.db", SQLITE3_OPEN_READWRITE);
$imgs = $db->query("SELECT id, name, thumb FROM images");
while($img = $imgs->fetchArray(SQLITE3_ASSOC)) {
	$thumb = $img['thumb'];
	echo "<img src=\"data:image/jpeg;base64," . $thumb . "\" />";
	echo "<br>";
}
