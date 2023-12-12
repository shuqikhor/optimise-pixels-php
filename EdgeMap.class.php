<?php

class EdgeMap {
	private array $lines = [];

	public function __construct($from_pixels) {
		foreach ($from_pixels as $pixel) {
			list($x, $y) = $pixel;

			$points = [
				[$x, $y],
				[$x+1, $y],
				[$x+1, $y+1],
				[$x, $y+1]
			];

			$lines = [
				[$points[0], $points[1]],
				[$points[1], $points[2]],
				[$points[2], $points[3]],
				[$points[3], $points[0]],
			];

			foreach ($lines as $line) {
				// normalise line
				$a = min($line);
				$b = max($line);
				$line = [$a, $b];

				// if not found, add
				// if found, delete
				// this will cancel out overlapped lines
				$key = array_search($line, $this->lines);
				if ($key === false) {
					$this->lines[] = $line;
				} else {
					unset($this->lines[$key]);
				}
			}
		}

		$this->lines = array_values($this->lines);
		// $this->print();
	}

	// this is to illustrate the outcome, for debugging purpose
	public function print () {
		echo "<pre>";
		$points = [...array_column($this->lines, 0), ...array_column($this->lines, 1)];
		$x = array_column($points, 0);
		$y = array_column($points, 1);
		$width = max($x) + 1;
		$height = max($y) + 1;
		echo "width x height: $width x $height\n";

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				echo in_array([[$x, $y], [$x+1, $y]], $this->lines) ? "---" : "   ";
			}
			echo "\n";
			for ($x = 0; $x < $width; $x++) {
				echo in_array([[$x, $y],[$x, $y+1]], $this->lines) ? "|  " : "   ";
			}
			echo "\n";
		}
		echo "</pre>";
	}
	
	// trace the lines to generate polygons from the chunk
	public function generate_polygon() {
		$lines = $this->lines;

		// start depth first search
		$polygons = [];
		$explored_dots = [];
		$frontier = [];
		while (count($lines)) {
			$current_line = $lines[0];
			$node = [
				"start" => $current_line[0],
				"end" => $current_line[1],
				"line" => $current_line,
				"parent" => null
			];
			$frontier = [$node];
			$explored_dots[] = $node["start"];

			while ($node = array_shift($frontier)) {
				// skip if line no longer exist in lines set
				$key = array_search($node['line'], $lines);
				if ($key === false) continue;
				array_splice($lines, $key, 1);

				// when hit the start or the middle of the path, trace back and generate a path
				$dot = $node['end'];
				if (in_array($dot, $explored_dots)) {
					$polygon = [$node['start']];

					$node_header = $node;
					while ($node_header['parent'] != null && $node_header['start'] != $dot) {
						$node_header = $node_header['parent'];
						array_unshift($polygon, $node_header['start']);
					}

					if (self::is_clockwise($polygon)) {
						$polygon = array_reverse($polygon);
					}
					$polygons[] = $polygon;
					$explored_dots =  array_filter($explored_dots, function ($current_dot) use ($dot) {
						return !$current_dot == $dot;
					});

					continue;
				}

				// add the next connecting-line to frontier
				foreach ($lines as $line) {
					if ($line[0] != $dot && $line[1] != $dot) continue;

					$start = $line[0] == $dot ? $line[0] : $line[1];
					$end = $line[0] == $dot ? $line[1] : $line[0];
					$new_node = [
						'start' => $start,
						'end' => $end,
						'line' => $line,
						'parent' => $node
					];
					array_unshift($frontier, $new_node);
				}

				$explored_dots[] = $dot;
			}
		}

		return $polygons;
	}

	static private function is_clockwise ($polygon) {
		$result = 0;
		for ($i = 0; $i < count($polygon); $i++) {
			$start = $polygon[$i];
			$end = $polygon[($i+1)%count($polygon)];
			$result += ($end[0] - $start[0]) * ($end[1] + $start[1]);
		}
		return $result < 0;
	}
}