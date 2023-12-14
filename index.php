<?php

/* -- replace $_POST when JSON is received from FETCH API -- */
if (!empty($_SERVER['CONTENT_TYPE']) && preg_match("/application\/json/i", $_SERVER['CONTENT_TYPE'])) {
	if ($php_input = json_decode(trim(file_get_contents("php://input")), true)) {
		$_POST = array_merge_recursive($_POST, $php_input);
	}
}


if (empty($_FILES)) {
	require("form.php");
	exit;
}


if (isset($_FILES['svg']) && file_exists($_FILES['svg']['tmp_name'])) {
	require("OptimisePixels.class.php");
	require("EdgeMap.class.php");
	require("SVGhelper.php");

	$filename = $_FILES['svg']['name'];
	$icon_name = str_replace(".svg", "", strtolower($filename));
	$icon_name = str_replace(" ", "-", $icon_name);

	$svg = new OptimisePixels($_FILES['svg']['tmp_name'], $icon_name);
	$svg->print();
	exit;
}
