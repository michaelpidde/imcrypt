<?php

function loginForm() {
	$out = "";
	$out .= '<form action="' . $_SERVER['PHP_SELF'] . '" method="post">';
	$out .= '<label for="password">Password: </label>';
	$out .= '<input type="password" name="password">';
	$out .= '<input type="submit" name="submit">';
	$out .= '</form>';
	return $out;
}