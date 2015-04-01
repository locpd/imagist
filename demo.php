<?php
require 'src/Imagist.php';
require 'src/Coordinate.php';

$files = scandir('images');

foreach($files as $img) {
	if (in_array($img, ['.', '..'])) continue;
	
	$img = 'images/'.$img;
	$output = basename($img);
	$image = new phamloc\Imagist\Imagist($img);
	$image->resize(300, 300, 'outside', 'any')->crop('center', 'middle', 200, 200)->save('output/'.$output);
}