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

if (isset($_FILES['svg']) && file_exists($_FILES['svg']['tmp_name'])) {
	require("OptimisePixels.class.php");
	require("EdgeMap.class.php");
	require("SVGhelper.php");

	$svg = new OptimisePixels($_FILES['svg']['tmp_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Optimise Pixels</title>
</head>
<body>
	<form action="index.php" method="post" enctype="multipart/form-data">
		<div><input type="file" name="svg" id="input-svg" accept="image/svg+xml"></div>
		<div><button type="submit">Submit</button></div>
	</form>

	<?php
		if (isset($svg)) $svg->print();
	?>
</body>
</html>
