<?php

class SVG {
	static public function get_svg_path ($polygons, $colour) {
		$left_most = min(array_column($polygons, 'left'));
		$top_most = min(array_column($polygons, 'top'));

		usort($polygons, function ($a, $b) {
			return $a["left"] <=> $b["left"];
		});

		$path = [];
		foreach ($polygons as $polygon) {
			$points = $polygon["points"];

			// reverse points (make it counter-clockwise) if it's a cutout
			if ($polygon["left"] != $left_most || $polygon["top"] != $top_most) {
				$points = array_reverse($points);
			}

			$path = "";
			$last_point = end($points);
			for ($i = 0; $i < count($points); $i++) {
				$point = $points[$i];
				$next_point = $points[($i+1)%count($points)];

				if ($point[0] == $next_point[0] && $point[0] == ($last_point !== null ? $last_point[0] : false))
					continue;

				if ($point[1] == $next_point[1] && $point[1] == ($last_point !== null ? $last_point[1] : false))
					continue;

				if (empty($path)) {
					$path .= "M{$point[0]},{$point[1]}";
				} else {
					$dx = $point[0] - $last_point[0];
					$dy = $point[1] - $last_point[1];

					if ($dx == 0) {
						// $path .= "v$dy";
						$path .= "V{$point[1]}";
					} elseif ($dy == 0) {
						// $path .= "h$dx";
						$path .= "H{$point[0]}";
					} else {
						// $path .= "l$dx,$dy";
						$path .= "L{$point[0]},{$point[1]}";
					}
				}
				
				$last_point = $point;
			}
			$path .= "z";
			$paths[] = $path;
		}
		$svg_path = implode(" ", $paths);
		return "<path fill=\"$colour\" d=\"$svg_path\"/>";
	}

	static public function get_svg_rect ($polygon, $colour) {
		extract($polygon);
		return "<rect fill=\"$colour\" x=\"$left\" y=\"$top\" width=\"$width\" height=\"$height\"/>";
	}
}
