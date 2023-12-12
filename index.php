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

require("EdgeMap.class.php");
require("SVGhelper.php");

class OptimisePixels {
	private static $instance = null;

	static public function init ():OptimisePixels {
		if (self::$instance instanceof OptimisePixels) return self::$instance;

		self::$instance = new OptimisePixels();
		return self::$instance;
	}

	private function __construct () {
		if (isset($_FILES['svg']) && file_exists($_FILES['svg']['tmp_name'])) {
			echo "<pre>";

			// extract all pixels and group them by colour
			$pixel_groups = self::extract_pixels_from_svg($_FILES['svg']['tmp_name']);

			// convert pixel groups to chunks of pixels
			$pixel_groups = array_map('self::group_pixels', $pixel_groups);

			# setup edge map
			$edge_maps = [];
			foreach ($pixel_groups as $colour => $chunks) {
				$edge_maps[$colour] = [];

				foreach ($chunks as $chunk) {
					# here we will get a list of paths
					$edge_map = new EdgeMap($chunk);
					$polygons = $edge_map->generate_polygon();
					
					# precalculate left, top, width, height of the path
					$polygons = array_map('self::precalculate', $polygons);
					$edge_maps[$colour][] = $polygons;
				}

				# sort the chunks by y then x (to appear nicely in svg)
				usort($edge_maps[$colour], function ($a, $b) {
					$a_top = array_column($a, 'top');
					$b_top = array_column($b, 'top');

					if ($a_top == $b_top) {
						$a_left = array_column($a, 'left');
						$b_left = array_column($b, 'left');

						return $a_left <=> $b_left;
					}
					
					return $a_top <=> $b_top;
				});
			}

			/*
				current state:
				edge_maps = [
					"#XXXXXX"=> [                                                                            # a colour group
						[                                                                                    # a chunk with a hole
							[ left:int, top:int, width:int, height:int, points:array([x,y], [x,y], ...)],    # a polygon
							[ left:int, top:int, width:int, height:int, points:array([x,y], [x,y], ...)]     # a hole polygon
						],
						[                                                                                    # another chunk
							[ left:int, top:int, width:int, height:int, points:array([x,y], [x,y], ...)]     # a polygon
						]
					]
				]
			*/

			$tags = [];
			foreach ($edge_maps as $colour => $chunks) {
				foreach ($chunks as $chunk) {
					// if chunk is a rectange, convert to <rect />
					if (count($chunk) == 1 && self::is_rect($chunk[0]['points'])) {
						$tag = SVG::get_svg_rect($chunk[0], $colour);
					}
					
					# otherwise, convert to <path />
					else {
						$tag = SVG::get_svg_path($chunk, $colour);
					}

					$tags[] = $tag;
				}
			}
			echo "</pre>";

			$svg_content = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 9 9\" width=\"90\" height=\"90\">\n";
			$svg_content .= implode("\n", array_map(function ($tag) { return "\t$tag"; }, $tags)) . "\n";
			$svg_content .= "</svg>";

			$svg_base64 = base64_encode($svg_content);
			echo "<div><img src=\"data:image/svg+xml;base64,$svg_base64\"></div>";
			
			$svg_escaped = str_replace("<", "&lt;", $svg_content);
			$svg_escaped = str_replace(">", "&gt;", $svg_escaped);
			echo "<pre>$svg_escaped</pre>";
		}

		print_form();
	}

	static private function extract_pixels_from_svg (string $file):array {
		$svg = simplexml_load_file($file);
		$svg->registerXPathNamespace('svg', 'http://www.w3.org/2000/svg');
		$svg->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');
		
		$style = $svg->xpath('.//svg:style');
		$css_classes = [];
		foreach ($style as $style_content) {
			$style_text = explode("\n", (string)$style_content);
			$current_class = null;
			$current_colour = null;
			
			foreach ($style_text as $line) {
				preg_match("/\.(\w+)/", $line, $css_search);
				if (!empty($css_search)) {
					$current_class = $css_search[1];
				}
	
				preg_match("/fill\s*:\s*(#[A-Fa-f\d]{6})/", $line, $fill_search);
				if (!empty($fill_search)) {
					$current_colour = $fill_search[1];
				}
	
				if ($current_class && $current_colour) {
					$css_classes[$current_class] = $current_colour;
					$current_class = null;
					$current_colour = null;
				}
			}
		}

		$rects = $svg->xpath('.//svg:rect');
		$pixel_groups = [];
		foreach ($rects as $rect) {
			$attr = $rect->attributes();
			$x = property_exists($attr, 'x') ? (int)$attr['x'] : 0;
			$y = property_exists($attr, 'y') ? (int)$attr['y'] : 0;

			$colour = null;
			if (property_exists($attr, 'fill')) {
				$colour = strtoupper($attr['fill']);
			} elseif (property_exists($attr, 'class') && array_key_exists((string)$attr['class'], $css_classes)) {
				$colour = $css_classes[(string)$attr['class']];
			} elseif (property_exists($attr, 'style')) {
				preg_match("/fill\s*:\s*(#[A-Fa-f\d]{6})/", (string)$attr['style'], $fill_search);
				if (!empty($fill_search)) {
					$colour = strtoupper($fill_search[1]);
				}
			}
			if (!$colour) continue;

			$pixel_groups[$colour][] = [$x, $y];
		}

		return $pixel_groups;
	}

	static private function group_pixels ($pixels) {
		$groups = [];

		while (!empty($pixels)) {
			$frontier = [array_pop($pixels)];
			$group = [];

			while (!empty($frontier)) {
				$head = array_shift($frontier);
				$group[] = $head;

				foreach ([-1, 1] as $dy) {
					$neighbour = [$head[0], $head[1] + $dy];
					$key = array_search($neighbour, $pixels);
					if ($key !== false) {
						$frontier[] = $neighbour;
						unset($pixels[$key]);
					}
				}

				# trace left and right until boundary
				foreach ([-1, 1] as $dx) {
					$neighbour_x = [$head[0] + $dx, $head[1]];
					while (in_array($neighbour_x, $pixels)) {
						$mark_for_delete = [];
						foreach ([-1, 1] as $dy) {
							$neighbour_y = [$neighbour_x[0], $neighbour_x[1] + $dy];
							if (in_array($neighbour_y, $pixels)) {
								$frontier[] = $neighbour_y;
								$mark_for_delete[] = $neighbour_y;
							}
						}

						$mark_for_delete[] = $neighbour_x;
						$group[] = $neighbour_x;
						$neighbour_x = [$neighbour_x[0] + $dx, $neighbour_x[1]];

						$pixels = array_filter($pixels, function ($pixel) use ($mark_for_delete) {
							return !in_array($pixel, $mark_for_delete);
						});
					}
				}
			}

			$groups[] = $group;
		}

		return $groups;
	}

	static private function precalculate ($polygon) {
		$x = array_map(function ($p) { return $p[0]; }, $polygon);
		$y = array_map(function ($p) { return $p[1]; }, $polygon);
		return [
			"left" => min($x),
			"top" => min($y),
			"width" => max($x) - min($x),
			"height" => max($y) - min($y),
			"points" => $polygon
		];
	}

	static private function is_rect ($polygon) {
		$optimised = [];
		$last_point = end($polygon);
		for ($i = 0; $i < count($polygon); $i++) {
			$this_point = $polygon[$i];
			$next_point = $polygon[($i+1) % count($polygon)];
			if ($last_point[0] == $this_point[0] && $this_point[0] == $next_point[0])
				continue;
			if ($last_point[1] == $this_point[1] && $this_point[1] == $next_point[1])
				continue;
			$optimised[] = $polygon[$i];
			$last_point = $this_point;
		}
		
		return count($optimised) == 4;
	}
}

function print_form () {
	?>
		<form action="index.php" method="post" enctype="multipart/form-data">
			<div><input type="file" name="svg" id="input-svg" accept="image/svg+xml"></div>
			<div><button type="submit">Submit</button></div>
		</form>
	<?php
}

$main = OptimisePixels::init();