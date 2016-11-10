<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Public interface of ownCloud for apps to use.
 * Preview interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP;

use OCP\Files\File;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\NotFoundException;

/**
 * This class provides functions to render and show thumbnails and previews of files
 * @since 6.0.0
 */
interface IPreview {

	/**
	 * @since 9.2.0
	 */
	const EVENT = self::class . ':' . 'PreviewRequested';

	const MODE_FILL = 'fill';
	const MODE_COVER = 'cover';

	/**
	 * In order to improve lazy loading a closure can be registered which will be
	 * called in case preview providers are actually requested
	 *
	 * $callable has to return an instance of \OCP\Preview\IProvider
	 *
	 * @param string $mimeTypeRegex Regex with the mime types that are supported by this provider
	 * @param \Closure $callable
	 * @return void
	 * @since 8.1.0
	 */
	public function registerProvider($mimeTypeRegex, \Closure $callable);

	/**
	 * Get all providers
	 * @return array
	 * @since 8.1.0
	 */
	public function getProviders();

	/**
	 * Does the manager have any providers
	 * @return bool
	 * @since 8.1.0
	 */
	public function hasProviders();

	/**
	 * Return a preview of a file
	 * @param string $file The path to the file where you want a thumbnail from
	 * @param int $maxX The maximum X size of the thumbnail. It can be smaller depending on the shape of the image
	 * @param int $maxY The maximum Y size of the thumbnail. It can be smaller depending on the shape of the image
	 * @param boolean $scaleUp Scale smaller images up to the thumbnail size or not. Might look ugly
	 * @return \OCP\IImage
	 * @since 6.0.0
	 * @deprecated 9.2.0 Use getPreview
	 */
	public function createPreview($file, $maxX = 100, $maxY = 75, $scaleUp = false);

	/**
	 * Returns a preview of a file
	 *
	 * The cache is searched first and if nothing usable was found then a preview is
	 * generated by one of the providers
	 *
	 * @param File $file
	 * @param int $width
	 * @param int $height
	 * @param bool $crop
	 * @param string $mode
	 * @param string $mimeType To force a given mimetype for the file (files_versions needs this)
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @since 9.2.0
	 */
	public function getPreview(File $file, $width = -1, $height = -1, $crop = false, $mode = IPreview::MODE_FILL, $mimeType = null);

	/**
	 * Returns true if the passed mime type is supported
	 * @param string $mimeType
	 * @return boolean
	 * @since 6.0.0
	 */
	public function isMimeSupported($mimeType = '*');

	/**
	 * Check if a preview can be generated for a file
	 *
	 * @param \OCP\Files\FileInfo $file
	 * @return bool
	 * @since 8.0.0
	 */
	public function isAvailable(\OCP\Files\FileInfo $file);
}
