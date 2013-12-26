<?php
namespace BitcasaWebdav\FS;
use \Sabre\DAV;

/**
 * File class
 * @Table
 * @Entity
 */
class File extends Node implements DAV\IFile
{
	/**
	 * @Column(type="text")
	 */
	private $album;

	/**
	 * @Column(type="text")
	 */
	private $category;

	/**
	 * @Column(type="text")
	 */
	private $duplicates;

	/**
	 * @Column(type="text")
	 */
	private $extension;

	/**
	 * @Column(type="text")
	 */
	private $fileId;

	/**
	 * @Column(type="text")
	 */
	private $incomplete;

	/**
	 * @Column(type="string")
	 */
	private $mime;

	/**
	 * @Column(type="text")
	 */
	private $manifestName;

	/**
	 * @Column(type="integer")
	 */
	private $size;

	public function fromArray(array $array)
	{
		foreach ($array as $key => $value) {
			switch ($key) {
				case 'category':
					$this->setCategory($value);
					break;
				case 'album':
					$this->setAlbum($value);
					break;
				case 'name':
					$this->setName($value);
					break;
				case 'extension':
					$this->setExtension($value);
					break;
// 				case 'duplicates':
// 					$this->setDuplicates($value); // Array - not sure how to handle. Garbage for now
// 					break;
				case 'mirrored':
					$this->setMirrored($value);
					break;
				case 'manifest_name':
					$this->setManifestName($value);
					break;
				case 'mime':
					$this->setMime($value);
					break;
				case 'mtime':
					$this->setMtime($value);
					break;
				case 'path':
					$this->setPath($value);
					break;
				case 'real_path':
					$this->setRealPath($value);
					break;
				case 'type':
					$this->setType($value);
					break;
				case 'file_id':
					$this->setFileId($value);
					break;
				case 'id':
					$this->setId($value);
					break;
				case 'incomplete':
					$this->setIncomplete($value);
					break;
				case 'size':
					$this->setSize($value);
					break;
				default:
					// Ignore unknown indexes
					break;
// 					throw new \InvalidArgumentException(
// 							__METHOD__ . ' Invalid property: ' . $key . ' with value: ' . $value);
			}
		}
		return $this;
	}

	public function toArray()
	{
		return array('category' => $this->getCategory(), 'album' => $this->getAlbum(), 'name' => $this->getName(),
				'extension' => $this->getExtension(), 'duplicates' => $this->getDuplicates(),
				'mirrored' => $this->getMirrored(), 'manifest_name' => $this->getManifestName(),
				'mime' => $this->getMime(), 'mtime' => $this->getMtime(), 'path' => $this->getPath(),
				'type' => $this->getType(), 'id' => $this->getId(), 'file_id' => $this->getFileId(),
				'incomplete' => $this->getIncomplete(), 'size' => $this->getSize()
		);
	}

	/**
	 * Updates the data
	 *
	 * @param resource $data
	 * @return void
	 */
	public function put($data)
	{
		$client = $this->getClient();
		$response = $client->uploadFile($this->getName(), $this->getParent()->getRealPath(), $data);
		
		$newItem = array_pop($response['result']['items']);
		$newItem['real_path'] = $newItem['path'];
		unset($newItem['path']);
		$this->fromArray($newItem); // Store real path
		$em = $this->getEntityManager();
		$em->persist($this);
		$em->flush();
		
// var_dump($response);


		// 		file_put_contents($this->getRealPath(), $data);

	}

	/**
	 * Returns the data
	 *
	 * @return string
	 */
	public function get()
	{
		$client = $this->getClient();
		$fileName = urlencode($this->getName());
		//$file = $client->downloadFile($filename, $this->getRealPath());
		//$file = $client->downloadFileProxied($fileName, $this->getRealPath(), $this->getSize(), $this->getMime());
		$file = $client->downloadFileAdvanced($fileName, $this->getRealPath(), $this->getSize(), $this->getMime());
		
		// SabreDAV expects resource handle returned
		return fopen($file, 'r');
	}

	// 	function serve_file_resumable($file, $contenttype = 'application/octet-stream')
	// 	{

	// 		// Avoid sending unexpected errors to the client - we should be serving a file,
	// 		// we don't want to corrupt the data we send
	// 		@error_reporting(0);

	// 		// Make sure the files exists, otherwise we are wasting our time
	// 		if (!file_exists($file)) {
	// 			header("HTTP/1.1 404 Not Found");
	// 			exit;
	// 		}

	// 		// Get the 'Range' header if one was sent
	// 		if (isset($_SERVER['HTTP_RANGE']))
	// 			$range = $_SERVER['HTTP_RANGE'];
	// 		// IIS/Some Apache versions
	// 		else if ($apache = apache_request_headers()) { // Try Apache again
	// 			$headers = array();
	// 			foreach ($apache as $header => $val)
	// 				$headers[strtolower($header)] = $val;
	// 			if (isset($headers['range']))
	// 				$range = $headers['range'];
	// 			else
	// 				$range = false; // We can't get the header/there isn't one set
	// 		} else
	// 			$range = false; // We can't get the header/there isn't one set

	// 		// Get the data range requested (if any)
	// 		$filesize = filesize($file);
	// 		if ($range) {
	// 			$partial = true;
	// 			list($param, $range) = explode('=', $range);
	// 			if (strtolower(trim($param)) != 'bytes') { // Bad request - range unit is not 'bytes'
	// 				header("HTTP/1.1 400 Invalid Request");
	// 				exit;
	// 			}
	// 			$range = explode(',', $range);
	// 			$range = explode('-', $range[0]); // We only deal with the first requested range
	// 			if (count($range) != 2) { // Bad request - 'bytes' parameter is not valid
	// 				header("HTTP/1.1 400 Invalid Request");
	// 				exit;
	// 			}
	// 			if ($range[0] === '') { // First number missing, return last $range[1] bytes
	// 				$end = $filesize - 1;
	// 				$start = $end - intval($range[0]);
	// 			} else if ($range[1] === '') { // Second number missing, return from byte $range[0] to end
	// 				$start = intval($range[0]);
	// 				$end = $filesize - 1;
	// 			} else { // Both numbers present, return specific range
	// 				$start = intval($range[0]);
	// 				$end = intval($range[1]);
	// 				if ($end >= $filesize || (!$start && (!$end || $end == ($filesize - 1))))
	// 					$partial = false;
	// 				// Invalid range/whole file specified, return whole file
	// 			}
	// 			$length = $end - $start + 1;
	// 		} else
	// 			$partial = false; // No range requested

	// 		// Send standard headers
	// 		header("Content-Type: $contenttype");
	// 		header("Content-Length: $filesize");
	// 		header('Content-Disposition: attachment; filename="' . basename($file) . '"');
	// 		header('Accept-Ranges: bytes');

	// 		// if requested, send extra headers and part of file...
	// 		if ($partial) {
	// 			header('HTTP/1.1 206 Partial Content');
	// 			header("Content-Range: bytes $start-$end/$filesize");
	// 			if (!$fp = fopen($file, 'r')) { // Error out if we can't read the file
	// 				header("HTTP/1.1 500 Internal Server Error");
	// 				exit;
	// 			}
	// 			if ($start)
	// 				fseek($fp, $start);
	// 			while ($length) { // Read in blocks of 8KB so we don't chew up memory on the server
	// 				$read = ($length > 8192) ? 8192 : $length;
	// 				$length -= $read;
	// 				print(fread($fp, $read));
	// 			}
	// 			fclose($fp);
	// 		} else
	// 			readfile($file); // ...otherwise just send the whole file

	// 		// Exit here to avoid accidentally sending extra content on the end of the file
	// 		exit;

	// 	}

	/**
	 * Delete the current file
	 *
	 * @return void
	 */
	public function delete()
	{
		// Delete from Bitcasa
		$client = $this->getClient();
		$client->deleteFile($this->getRealPath());
		
		// Delete from cache
		$parent = $this->getParent();
		$parent->removeChild($this);
		$em = $this->getEntityManager();
		$em->remove($this);
		$em->persist($parent);
		$em->flush();
	}

	public function setSize($size)
	{
		return $this->size = $size;
	}

	/**
	 * Returns the size of the node, in bytes
	 *
	 * @return int
	 */
	public function getSize()
	{

		return $this->size;

	}

	/**
	 * Returns the ETag for a file
	 *
	 * An ETag is a unique identifier representing the current version of the file. If the file changes, the ETag MUST change.
	 * The ETag is an arbitrary string, but MUST be surrounded by double-quotes.
	 *
	 * Return null if the ETag can not effectively be determined
	 *
	 * @return mixed
	 */
	public function getETag()
	{

		return null;

	}

	/**
	 * Returns the mime-type for a file
	 *
	 * If null is returned, we'll assume application/octet-stream
	 *
	 * @return mixed
	 */
	public function getContentType()
	{
		return $this->getMime();
	}

	public function getAlbum()
	{
		return $this->album;
	}

	public function setAlbum($album)
	{
		$this->album = $album;
	}

	public function getCategory()
	{
		return $this->category;
	}

	public function setCategory($category)
	{
		$this->category = $category;
	}

	public function getDuplicates()
	{
		return $this->duplicates;
	}

	public function setDuplicates($duplicates)
	{
		$this->duplicates = $duplicates;
	}

	public function getExtension()
	{
		return $this->extension;
	}

	public function setExtension($extension)
	{
		$this->extension = $extension;
	}

	public function getFileId()
	{
		return $this->fileId;
	}

	public function setFileId($fileId)
	{
		$this->fileId = $fileId;
	}

	public function getIncomplete()
	{
		return $this->incomplete;
	}

	public function setIncomplete($incomplete)
	{
		$this->incomplete = $incomplete;
	}

	public function getMime()
	{
		return $this->mime;
	}

	public function setMime($mime)
	{
		$this->mime = $mime;
	}

	public function getManifestName()
	{
		return $this->manifestName;
	}

	public function setManifestName($manifestName)
	{
		$this->manifestName = $manifestName;
	}

	public function setContentType($contentType)
	{
		$this->contentType = $contentType;
	}
}
