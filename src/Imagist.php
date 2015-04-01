<?php

namespace phamloc\Imagist;

class Imagist
{
	private $imageFile, $imageRes, $width, $height;
	
	public function __construct($file_path)
	{
		$this->imageFile = $file_path;
		$this->imageRes = new \Gmagick($this->imageFile);
		$this->imageRes->setCompressionQuality(90);
	}
	
	/**
	 * Resize the image to given dimensions.
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
		
		$this->imageRes->scaleimage($dim['width'], $dim['height']);
		
		$this->width = null;
		$this->height = null;
		
		return $this;
	}
	
	/**
	 * Returns a cropped image
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
		
		$this->imageRes->cropimage($width, $height, $left, $top);
		
		$this->width = null;
		$this->height = null;
		
		return $this;
	}
	
	public function save($file_name)
	{
		$this->imageRes->writeimage($file_name, true);
	}
	
	public function output()
	{
		header("Content-type: " . image_type_to_mime_type(exif_imagetype($this->imageFile)));
		echo $this->imageRes;
	}
	
	public function getWidth()
	{
		if ( empty($this->width) ) {
			$this->width = $this->imageRes->getimagewidth();
		}
		return $this->width;
	}
	
	public function getHeight()
	{
		if ( empty($this->height) ) {
			$this->height = $this->imageRes->getimageheight();
		}
		return $this->height;
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