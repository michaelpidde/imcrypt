<?php
include '../imcrypt.php';
session_start();

if(array_key_exists($_SESSION, 'password')) {
	$dbName = urldecode($_GET['db']);
	$id = $_GET['id'];
	$db = new SQLite3($dbName, SQLITE3_OPEN_READWRITE);
	$statement = $db->prepare("SELECT data, ext FROM images WHERE id = :id");
	$statement->bindValue(":id", $id, SQLITE3_INTEGER);
	$img = $statement->execute()->fetchArray();
	header("Content-Type: image/" . $img['ext']);
	echo base64_decode(absorbSalt($img['data']), $_SESSION['salt']);
} else {
	echo '';
}