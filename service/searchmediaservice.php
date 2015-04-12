<?php
/**
 * ownCloud - galleryplus
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Olivier Paroz <owncloud@interfasys.ch>
 *
 * @copyright Olivier Paroz 2014-2015
 */

namespace OCA\GalleryPlus\Service;

use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\Node;

/**
 * Searches the instance for media files which can be shown
 *
 * @package OCA\GalleryPlus\Service
 */
class SearchMediaService extends FilesService {

	/**
	 * @type null|array<string,string|int>
	 */
	private $images = [];
	/**
	 * @type string[]
	 */
	private $supportedMediaTypes;

	/**
	 * This returns the list of all media files which can be shown starting from the given folder
	 *
	 * @param Folder $folder
	 * @param string[] $supportedMediaTypes
	 *
	 * @return array<string,string|int> all the images we could find
	 */
	public function getMediaFiles($folder, $supportedMediaTypes) {
		$this->supportedMediaTypes = $supportedMediaTypes;
		$this->searchFolder($folder);

		return $this->images;
	}

	/**
	 * Look for media files and folders in the given folder
	 *
	 * @param Folder $folder
	 * @param int $subDepth
	 *
	 * @return int
	 */
	private function searchFolder($folder, $subDepth = 0) {
		$albumImageCounter = 0;
		$subFolders = [];

		$nodes = $this->getNodes($folder, $subDepth);
		foreach ($nodes as $node) {
			if (!$this->isLocalAndAvailable($node)) {
				continue;
			}
			//$this->logger->debug("Sub-Node path : {path}", ['path' => $node->getPath()]);
			$nodeType = $this->getNodeType($node);
			$subFolders = array_merge($subFolders, $this->getAllowedSubFolder($node, $nodeType));
			$albumImageCounter = $this->addMediaFiles($node, $nodeType, $albumImageCounter);
			if ($this->haveEnoughPictures($albumImageCounter, $subDepth)) {
				break;
			}

		}
		$albumImageCounter = $this->searchSubFolders($subFolders, $subDepth, $albumImageCounter);

		return $albumImageCounter;
	}

	/**
	 * Fills the album with images and
	 *
	 * @param $node
	 * @param $nodeType
	 * @param $albumImageCounter
	 *
	 * @return bool
	 */
	private function addMediaFiles($node, $nodeType, $albumImageCounter) {
		if ($nodeType === 'file') {
			$albumImageCounter = $albumImageCounter + (int)$this->isPreviewAvailable($node);
		}

		return $albumImageCounter;
	}

	/**
	 * Checks if we've collected enough pictures to be able to build the view
	 *
	 * An album is full when we find max 4 pictures at the same level
	 *
	 * @param int $albumImageCounter
	 * @param int $subDepth
	 *
	 * @return bool
	 */
	private function haveEnoughPictures($albumImageCounter, $subDepth) {
		if ($subDepth === 0) {
			return false;
		}
		if ($albumImageCounter === 4) {
			return true;
		}

		return false;
	}

	/**
	 * Looks for pictures in sub-folders
	 *
	 * If we're at level 0, we need to look for pictures in sub-folders no matter what
	 * If we're at deeper levels, we only need to go further if we haven't managed to find one
	 * picture in the current folder
	 *
	 * @param array <Folder> $subFolders
	 * @param int $subDepth
	 * @param int $albumImageCounter
	 *
	 * @return int
	 */
	private function searchSubFolders($subFolders, $subDepth, $albumImageCounter) {
		if ($this->folderNeedsToBeSearched($subFolders, $subDepth, $albumImageCounter)) {
			$subDepth++;
			foreach ($subFolders as $subFolder) {
				//$this->logger->debug("Sub-Node path : {path}", ['path' => $subFolder->getPath()]);
				$albumImageCounter = $this->searchFolder($subFolder, $subDepth);
				if ($this->abortSearch($subDepth, $albumImageCounter)) {
					break;
				}
			}
		}

		return $albumImageCounter;
	}

	/**
	 * Checks if we need to look for media files in the specified folder
	 *
	 * @param array <Folder> $subFolders
	 * @param int $subDepth
	 * @param int $albumImageCounter
	 *
	 * @return bool
	 */
	private function folderNeedsToBeSearched($subFolders, $subDepth, $albumImageCounter) {
		if (!empty($subFolders) && ($subDepth === 0 || $albumImageCounter === 0)) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if there is no need to check any other sub-folder at the same depth level
	 *
	 * @param int $subDepth
	 * @param int $count
	 *
	 * @return bool
	 */
	private function abortSearch($subDepth, $count) {
		if ($subDepth > 1 && $count > 0) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the file is of a supported media type and adds it to the array of items to
	 * return
	 *
	 * @todo We could potentially check if the file is readable ($file->stat() maybe) in order to
	 *     only return valid files, but this may slow down operations
	 *
	 * @param File $file the file to test
	 *
	 * @return bool
	 */
	private function isPreviewAvailable($file) {
		try {
			$mimeType = $file->getMimetype();
			if (in_array($mimeType, $this->supportedMediaTypes)) {
				$this->addFileToResults($file);

				return true;
			}
		} catch (\Exception $exception) {
			return false;
		}

		return false;
	}

	/**
	 * Adds various information about a file to the list of results
	 *
	 * @param File $file
	 */
	private function addFileToResults($file) {
		$imagePath = $this->environment->getPathFromVirtualRoot($file);
		$imageId = $file->getId();
		$mimeType = $file->getMimetype();
		$mTime = $file->getMTime();

		$imageData = [
			'path'     => $imagePath,
			'fileid'   => $imageId,
			'mimetype' => $mimeType,
			'mtime'    => $mTime
		];

		$this->images[] = $imageData;

		//$this->logger->debug("Image path : {path}", ['path' => $imagePath]);
	}

}