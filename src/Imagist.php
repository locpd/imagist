<?php

namespace phamloc\imagist;

class Imagist
{
	private $imageFile, $size, $tmpFile;
	
	public function __construct($file_path)
	{
		$this->imageFile = $file_path;
	}
	
	/**
	 * Resize the image to given dimensions as JPEG type.
	 * 
	 * $width and $height are both smart coordinates. This means that you can pass any of these values in:
	 *   - positive or negative integer (100, -20, ...)
	 *   - positive or negative percent string (30%, -15%, ...)
	 *   - complex coordinate (50% - 20, 15 + 30%, ...)
	 *   - null: if one dimension is null, it's calculated proportionally from the other.
	 * 
	 * $fit parameter can be set to one of these three values:
	 *   - 'inside': resize proportionally and fit the resulting image tightly in the $width x $height box
	 *   - 'outside': resize proportionally and fit the resulting image tighly outside the box
	 *   - 'fill': resize the image to fill the $width x $height box exactly
	 *
	 * $scale parameter can be:
	 *   - 'down': only resize the image if it's larger than the $width x $height box
	 *   - 'up': only resize the image if it's smaller than the $width x $height box
	 *   - 'any': resize the image
	 *
	 * Example (resize to half-size):
	 * <code>
	 * $smaller = $image->resize('50%');
	 * 
	 * $smaller = $image->resize('100', '100', 'inside', 'down');
	 * </code>
	 * 
	 * @param mixed $width The new width (smart coordinate), or null.
	 * @param mixed $height The new height (smart coordinate), or null.
	 * @param string $fit 'inside', 'outside', 'fill'
	 * @param string $scale 'down', 'up', 'any'
	 * @return this
	 */
	public function resize($width = null, $height = null, $fit = 'inside', $scale = 'any')
	{
		$dim = $this->prepareDimensions($width, $height, $fit);
		
		if (($scale === 'down' && ($dim['width'] >= $this->getWidth() && $dim['height'] >= $this->getHeight())) ||
			($scale === 'up' && ($dim['width'] <= $this->getWidth() && $dim['height'] <= $this->getHeight()))) {
			$dim = array('width' => $this->getWidth(), 'height' => $this->getHeight());
		}
		
		if ($dim['width'] <= 0 || $dim['height'] <= 0) {
			throw new \Exception("Both dimensions must be larger than 0.");
		}
		
		$src = $this->tmpFile ? $this->tmpFile : $this->imageFile;
		$src .= '[0]'; // convert first frame only (useful for animated gif only)
		$dest = $this->getTmpFile();
		$cmd = "convert -strip -interlace Plane -quality 85 -resize {$dim['width']}x{$dim['height']} ".escapeshellarg($src)." ".escapeshellarg($dest);
		exec($cmd, $output, $return_status);
		if ($return_status !== 0) {
			throw new \Exception("Failed to resize with cmd:\n$cmd");
		}
		
		$this->refreshSize();
		
		return $this;
	}
	
	/**
	 * Returns the cropped image as JPEG type
	 *
	 * @param smart_coordinate $left
	 * @param smart_coordinate $top
	 * @param smart_coordinate $width
	 * @param smart_coordinate $height
	 * @return \WideImage\Image
	 */
	public function crop($left, $top, $width, $height)
	{
		$width  = Coordinate::fix($width, $this->getWidth(), $width);
		$height = Coordinate::fix($height, $this->getHeight(), $height);
		$left   = Coordinate::fix($left, $this->getWidth(), $width);
		$top    = Coordinate::fix($top, $this->getHeight(), $height);
		
		if ($left < 0) {
			$width = $left + $width;
			$left  = 0;
		}
		
		if ($width > $this->getWidth() - $left) {
			$width = $this->getWidth() - $left;
		}
		
		if ($top < 0) {
			$height = $top + $height;
			$top    = 0;
		}
		
		if ($height > $this->getHeight() - $top) {
			$height = $this->getHeight() - $top;
		}
		
		if ($width <= 0 || $height <= 0) {
			throw new \Exception("Can't crop outside of an image.");
		}
		
		$src = $this->tmpFile ? $this->tmpFile : $this->imageFile;
		$src .= '[0]'; // convert first frame only (useful for animated gif only)
		$dest = $this->getTmpFile();
		$cmd = "convert -strip -interlace Plane -quality 85 -crop {$width}x{$height}+{$left}+{$top} ".escapeshellarg($src)." ".escapeshellarg($dest);
		exec($cmd, $output, $return_status);
		if ($return_status !== 0) {
			throw new \Exception("Failed to crop with cmd:\n$cmd");
		}
		
		$this->refreshSize();
		
		return $this;
	}
	
	/**
	 * Watermark image
	 * 
	 * @param string $watermark_file Location of watermark file
	 * @param string $x_edge Horizontal edge of watermark, can be 'left'|'right'
	 * @param string $y_edge Vertical edge of watermark, can be 'top'|'bottom'
	 * @param int $x_padding Horizontal padding from horizontal edge of watermark
	 * @param int $y_padding Vertical padding from vertical edge of watermark
	 * @param int $opacity Opacity of watermark, from 0 to 100
	 * @return \phamloc\imagist\Imagist
	 */
	public function watermark($watermark_file, $x_edge, $y_edge, $x_padding = 0, $y_padding = 0, $opacity = 100)
	{
		$arr_x_edge = ['left' => 'west', 'right' => 'east'];
		$arr_y_edge = ['top' => 'north', 'bottom' => 'south'];
		if ( ! isset($arr_x_edge[$x_edge]) || ! isset($arr_y_edge[$y_edge]) ) {
			throw new \Exception('Invalid x_edge or y_edge');
		}
		$gravity = $arr_x_edge[$x_edge].$arr_y_edge[$y_edge];
		
		$x_padding = (int) $x_padding;
		$y_padding = (int) $y_padding;
		
		$opacity = (int) $opacity;
		if ($opacity > 100 || $opacity < 0) {
			throw new \Exception("Invalid opacity");
		}
		
		$src = $this->tmpFile ? $this->tmpFile : $this->imageFile;
		$dest = $this->getTmpFile();
		$cmd = "composite -geometry +$x_padding+$y_padding -dissolve $opacity% -gravity $gravity ".escapeshellarg($src)." ".escapeshellarg($src)." ".escapeshellarg($dest);
		exec($cmd, $output, $return_status);
		if ($return_status !== 0) {
			throw new \Exception("Failed to watermark with cmd:\n$cmd");
		}
		
		$this->refreshSize();
		
		return $this;
	}
	
	public function save($file_name)
	{
		if ($this->tmpFile) {
			rename($this->tmpFile, $file_name);
		} else {
			copy($this->imageFile, $file_name);
		}
	}
	
	public function output()
	{
		$file = $this->tmpFile ? $this->tmpFile : $this->imageFile;
		header("Content-type: " . image_type_to_mime_type(exif_imagetype($file)));
		echo file_get_contents($file);
		
		if ($this->tmpFile) {
			unlink($this->tmpFile);
		}
	}
	
	private function getSize()
	{
		if ( empty($this->size) ) {
			$file = $this->tmpFile ? $this->tmpFile : $this->imageFile;
			$size = getimagesize($file);
			$this->size = ['width' => $size[0], 'height' => $size[1]];
		}
		return $this->size;
	}
	
	private function refreshSize()
	{
		$this->size = null;
	}
	
	public function getWidth()
	{
		$size = $this->getSize();
		return $size['width'];
	}
	
	public function getHeight()
	{
		$size = $this->getSize();
		return $size['height'];
	}
	
	private function getTmpFile()
	{
		if ( empty($this->tmpFile) ) {
			$this->tmpFile = sys_get_temp_dir().'/'.uniqid().'.jpg';
		}
		return $this->tmpFile;
	}
	
	/**
	 * Prepares and corrects smart coordinates
	 *
	 * @param smart_coordinate $width
	 * @param smart_coordinate $height
	 * @param string $fit
	 * @return array
	 */
	protected function prepareDimensions($width, $height, $fit)
	{
		if ( ! $width && ! $height ) {
			return array('width' => $this->getWidth(), 'height' => $this->getHeight());
		}
		
		if ($width) {
			$width = Coordinate::fix($width, $this->getWidth());
			$rx    = $this->getWidth() / $width;
		} else {
			$rx = null;
		}
		
		if ($height) {
			$height = Coordinate::fix($height, $this->getHeight());
			$ry     = $this->getHeight() / $height;
		} else {
			$ry = null;
		}
		
		if ($rx === null && $ry !== null) {
			$rx    = $ry;
			$width = round($this->getWidth() / $rx);
		}
		
		if ($ry === null && $rx !== null) {
			$ry     = $rx;
			$height = round($this->getHeight() / $ry);
		}
		
		if ($width === 0 || $height === 0) {
			return array('width' => 0, 'height' => 0);
		}
		
		if ($fit == null) {
			$fit = 'inside';
		}
		
		$dim = array();
		
		if ($fit == 'fill') {
			$dim['width']  = $width;
			$dim['height'] = $height;
		} elseif ($fit == 'inside' || $fit == 'outside') {
			if ($fit == 'inside') {
				$ratio = ($rx > $ry) ? $rx : $ry;
			} else {
				$ratio = ($rx < $ry) ? $rx : $ry;
			}
			
			$dim['width']  = round($this->getWidth() / $ratio);
			$dim['height'] = round($this->getHeight() / $ratio);
		} else {
			throw new InvalidFitMethodException("{$fit} is not a valid resize-fit method.");
		}
		
		return $dim;
	}
}