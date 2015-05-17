<?php
$dbName = urldecode($_GET['db']);
$id = $_GET['id'];
$db = new SQLite3($dbName, SQLITE3_OPEN_READWRITE);
$statement = $db->prepare("SELECT data FROM images WHERE id = :id");
$statement->bindValue(":id", $id, SQLITE3_INTEGER);
$img = $statement->execute();
header('Content-Type: image/jpeg');
echo base64_decode($img->fetchArray()['data']);
