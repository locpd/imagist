<?php

namespace phamloc\imagist;

/**
 * Requires ImageMagick, gifsicle to be installed on server
 */
class Optimizer
{
	private static $max_width = 2816, $max_height = 2112;
	
	/**
	 * Regardless of dest's extension, optimizer may convert file type of optimized image
	 * jpeg, png, bmp: convert to jpeg format
	 * gif: keep gif format
	 * 
	 * @param string $src
	 * @param string $dest If dest is falsy, it is the same as src
	 * @throws \InvalidArgumentException if image type of src is not supported
	 * @throws \Exception if failed to run external programs to optimize
	 */
	public static function compress($src, $dest = null)
	{
		if ( ! $dest) {
			$dest = $src;
		}
		
		$src_type = exif_imagetype($src);

		if ( !in_array($src_type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_BMP]) ) {
			throw new \InvalidArgumentException('Unsupported image type');
		}
		
		if ($src_type === IMAGETYPE_GIF) {
			self::compressGIF($src, $dest);
		} else {
			self::compressGeneral($src, $dest);
		}
	}

	/**
	 * Optimize any image file type, converting them to JPEG
	 * 
	 * @param string $src Path to source file
	 * @param string $dest Path to optimized file
	 * @throws \Exception if failed to run external programs to optimize
	 */
	private static function compressGeneral($src, $dest)
	{
		$src_size = filesize($src);

		$tmp_file = sys_get_temp_dir().'/'.uniqid().'.jpg';

		// limit width & height
		$limit_str = '';
		list($width, $height) = getimagesize($src);
		if ($width > self::max_width) {
			$limit_str .= self::max_width;
		}
		if ($height > self::max_height) {
			$limit_str .= 'x'.self::max_height;
		}
		if ( ! empty($limit_str)) {
			$limit_str = "-resize $limit_str";
		}

		// compress by 80%
		$cmd = "convert -strip -interlace Plane -quality 80 $limit_str ".escapeshellarg($src)." $tmp_file";
		exec($cmd, $output, $return_status);
		if ($return_status !== 0) {
			throw new \Exception('Unable to use ImageMagick to optimize image '.$src);
		}

		// compressed file is bigger than source, and source is already jpeg type
		if ( filesize($tmp_file) > $src_size && exif_imagetype($src) === IMAGETYPE_JPEG ) {
			copy($src, $tmp_file);
		}

		rename($tmp_file, $dest);
	}

	/**
	 * Optimize gif file type, keeping gif format
	 * 
	 * @param string $src
	 * @param string $dest
	 * @throws \Exception if gifsicle failed to run
	 */
	private static function compressGIF($src, $dest)
	{
		$tmp_file = sys_get_temp_dir().'/'.uniqid().'.gif';
		
		exec("gifsicle $src -o $tmp_file -O3", $output, $return_status);
		if ($return_status !== 0) {
			throw new \Exception('Unable to use gifsicle to optimize image '.$src);
		}

		// optimized file is bigger than source
		if (filesize($tmp_file) > $src) {
			copy($src, $tmp_file);
		}
		
		rename($tmp_file, $dest);
	}
}