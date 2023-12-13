<?php

/*
workflow:
 - extract all pixel blocks (1x1 rect) from SVG
 - group them by colour (this is for performance optimisation)
 - detect boundaries and separate into chunks
 - plot chunk edges
 - plot paths from the edges (some chunks may have >1 paths if there's a hole in them)
 - find out whether is the chunk a rectangle
    - if rect, convert it to <rect />
    - if not, convert it to <path />
       - determine path direction (clockwise for outer shape, ccw for cutouts)
       - write SVG path
 - sort svg tags by "x" and "y" coordinates
 - enclose with SVG opening/closing tags
*/

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
