This package just requires ImageMagick to be installed on server. Php's imagick extension is not needed.

Example usage:

$image = new phamloc\Imagist\Imagist($img);
$image->resize(300, 300, 'outside', 'any')->crop('center', 'top', 200, 200)->save('output/'.$output);