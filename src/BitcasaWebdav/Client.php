<?php
namespace BitcasaWebdav;
class Client
{

	const PATH_DOES_NOT_EXIST = 2039;

	private $apiUrl;
	private $accessToken;
	private $tempFiles;
	private $tempDirs;

	public function __construct($accessToken, $apiUrl = 'https://developer.api.bitcasa.com/v1')
	{
		$this->apiUrl = $apiUrl;
		$this->accessToken = $accessToken;
		$this->tempFiles = array();
		$this->tempDirs = array();
	}

	public function __destruct()
	{
		if (is_array($this->tempFiles)) {
			foreach ($this->tempFiles as $file) {
				@unlink($file);
			}
		}
		if (is_array($this->tempDirs)) {
			foreach ($this->tempDirs as $dir) {
				@rmdir($dir);
			}
		}
	}

	public function addFolder($path, $name)
	{
		$url = "https://developer.api.bitcasa.com/v1/folders/$path?access_token=$this->accessToken";

		$postData = array('folder_name' => $name);
		$response = $this->post($url, $postData);

		$result = json_decode($response, true);
		if (null === $result) {
			throw new Exception(
					__METHOD__ . ' GET: ' . $url . ' produced invalid JSON response from server: '
							. var_export($result, true));
		}

		if (!empty($result['error'])) {
			throw new Exception(__METHOD__ . ' Got error: ' . $result['error']);
		}

		return $result;

		// 		Error Codes:

		// 		2002
		// 		2009
		// 		2014
		// 		2020
		// 		2022
		// 		2023
		// 		2024
		// 		2025
		// 		2031

	}

	/**
	 * Delete a folder and its sub-directories and content on Bitcasa.
	 * @param string $path
	 * @param string $name
	 * @throws Exception
	 */
	public function deleteFolder($path, $name)
	{
		$url = "https://developer.api.bitcasa.com/v1/folders?access_token=$this->accessToken";

		$postData = array('path' => $path);
		$response = $this->delete($url, $postData);

		$result = json_decode($response, true);
		if (null === $result) {
			throw new Exception(
					__METHOD__ . ' DELETE: ' . $url . ' produced invalid JSON response from server: '
							. var_export($response, true));
		}

		$deletedDir = array_pop($result['result']['items']);
		if ($deletedDir['path'] != $path) {
			throw new Exception(
					__METHOD__ . "Expected to delete $path, but response sais it deleted {$deletedDir['path']} (!)");
		}

		if (!empty($result['error'])) {
			$error = $result['error'];
			throw new Exception(__METHOD__ . ' Got error ' . $error['code'] . ': ' . $error['message'], $error['code']);
		}

		return $result;

		// 		Error Codes:

		// 		2002
		// 		2003
		// 		2008
		// 		2022
		// 		2023
		// 		2024
		// 		2025
		// 		2026

	}

	public function listFolder($path = '/', $depth = 0)
	{
		$path = trim($path, '/');

		$url = "https://developer.api.bitcasa.com/v1/folders/$path?access_token=$this->accessToken";

		$data = $this->get($url);
		// 		var_dump($url, $data);

		$result = json_decode($data, true);
		if (null === $result) {
			throw new Exception(
					__METHOD__ . ' GET: ' . $url . ' produced invalid JSON response from server: '
							. var_export($data, true));
		}

		if (isset($result['error'])) {
			throw new Exception(__METHOD__ . " Got error from Bitcasa: " . $result['error']['message'],
					$result['error']['code']);
		}

		return $result;
	}

	public function uploadFile($filename, $path, $data)
	{
		$path = trim($path, '/');
		// 		$fullPath = "https://files.api.bitcasa.com/v1/files/$path/?access_token=" . $this->accessToken;
		$fullPath = "https://developer.api.bitcasa.com/v1/files/$path?access_token=" . $this->accessToken;
		// 		var_dump($fullPath);
		$options = array(CURLOPT_URL => $fullPath);
		// 		if (is_resource($data)) {
		// PUT
		// 			$options[CURLOPT_PUT] = true;
		// 			$options[CURLOPT_INFILE] = $data;
		// 			$options[CURLOPT_INFILESIZE] = 1234;

		// Can't use PUT to send stream to curl directly due to limitations with Bitcasa API
		// Inefficient for large files! 
		// For non-chunked uploads, we should use uploaded_file instead! (HTML4 style)

		/**
		 * We can't use PUT to send stream directly due to limitations in Bitcasa
		 * requreing form data. This is very inefficient for large files which has
		 * to be written to local disk before uploaded again to Bitcasa.
		 * 
		 * TODO For HTML4 style uploads (single POST request by client) we should
		 * use the already existing temp file, instead of creating new.
		 * 
		 * Another limitation is that the filename needs to be correct for the 
		 * POST request to be constructed properly with filename (for Bitcasa).
		 * To avoid duplicate filenames in local temp filesystem, a temporary 
		 * directory is needed per file upload (ugly!)
		 */
		$tmpDir = sys_get_temp_dir() . '/' . uniqid('BitcasaWebdav');
		mkdir($tmpDir);
		$tmpFile = "$tmpDir/$filename";
		$this->tempFiles[] = $tmpFile;
		$this->tempDirs[] = $tmpDir;
		file_put_contents($tmpFile, $data);

		$options[CURLOPT_POST] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		// 			$options[CURLOPT_CUSTOMREQUEST] = "PUT";
		// 			$options[CURLOPT_HTTPHEADER] = array('X-HTTP-Method-Override: PUT');

		$post = array('file' => "@$tmpFile", 'filename' => $filename);
		$options[CURLOPT_POSTFIELDS] = $post;
		// 			curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
		// 			CURLOPT_PUT 	TRUE to HTTP PUT a file. The file to PUT must be set with CURLOPT_INFILE and CURLOPT_INFILESIZE.
		// 		} else {
		// 			// String upload
		// 			$options[CURLOPT_POST] = true;
		// 			$post = array('file' => $data);
		// 		}

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		curl_close($ch);
		unlink($tmpFile);
		rmdir($tmpDir);

		$result = json_decode($response, true);
		if (null === $result) {
			throw new \Sabre\DAV\Exception(
					
					// Windows WebDav ends up here on upload. Why? Rename? 
			// FIXME - introduce Monolog to log events such as these for easier debugging!
					
					
					__METHOD__ . ' GET: ' . $fullPath . ' produced invalid JSON response from server: '
							. var_export($response, true));
		}

		if (isset($result['error'])) {
			throw new \Sabre\DAV\Exception(__METHOD__ . " Got error from Bitcasa: " . $result['error']['message'],
					$result['error']['code']);
		}

		return $result;
	}

	public function deleteFile($path)
	{
		$url = "https://developer.api.bitcasa.com/v1/files?access_token=" . $this->accessToken;
		$response = $this->delete($url, array('path' => $path));

		$result = json_decode($response, true);
		if (null === $result) {
			throw new \Sabre\DAV\Exception(
					__METHOD__ . ' DELETE: ' . $url . ' produced invalid JSON response from server: '
							. var_export($response, true));
		}

		if (isset($result['error'])) {
			switch ($result['error']['code']) {
				case self::PATH_DOES_NOT_EXIST:
					return true; // File no longer there - yay
					break;
				default:
					throw new \Sabre\DAV\Exception(
							__METHOD__ . " Got error from Bitcasa: [" . $result['error']['code'] . "]"
									. $result['error']['message'], $result['error']['code']);

					break;
			}

		}

		return $result;
	}

	public function downloadFile($filename, $path)
	{
		$url = "https://files.api.bitcasa.com/v1/files/$filename?access_token=$this->accessToken&path=$path";

		$file = tempnam(sys_get_temp_dir(), 'BitcasaWebdav');
		$this->tempFiles[] = $file;

		$fp = fopen($file, 'w+');
		$ch = curl_init(str_replace(" ", "%20", $url));//Here is the file we are downloading, replace spaces with %20
		curl_setopt($ch, CURLOPT_TIMEOUT, 50);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$return = curl_exec($ch); // get curl response
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		fclose($fp);

		if ($code >= 400) {
			switch ($code) {
				case 404:
				case 400:
					throw new Exception\BadRequest('Bad request');
				case 401:
					throw new Exception\NotAuthenticated('Not authenticated');
				case 402:
					throw new Exception\PaymentRequired('Payment required');
				case 403:
					throw new Exception\Forbidden('Forbidden');
				case 404:
					throw new Exception\NotFound('Resource not found.');
				case 405:
					throw new Exception\MethodNotAllowed('Method not allowed');
				case 409:
					throw new Exception\Conflict('Conflict');
				case 412:
					throw new Exception\PreconditionFailed('Precondition failed');
				case 416:
					throw new Exception\RequestedRangeNotSatisfiable('Requested Range Not Satisfiable');
				case 500:
					throw new Exception('Internal server error');
				case 501:
					throw new Exception\NotImplemented('Not Implemented');
				case 502:
					return $this->get(); // Bad gateway (Bitcasa internal timeout)
				case 507:
					throw new Exception\InsufficientStorage('Insufficient storage');
				default:
					throw new Exception('HTTP error response. (errorcode ' . $code . ')');
			}
		}

		if (false === $return) {
			throw new \Sabre\DAV\Exception($error);
		}

		return $file;
	}

	protected function get($url)
	{
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
		}

		curl_close($ch);
		return $data;
	}

	protected function delete($url, array $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			throw new \Sabre\DAV\Exception(__METHOD__ . "$error");
		}
		curl_close($ch);
		return $response;
	}

	protected function post($url, array $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			throw new Exception(__METHOD__ . "$error");
		}

		curl_close($ch);
		return $response;
	}

	protected function put($url, array $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			throw new Exception(__METHOD__ . "$error");
		}

		curl_close($ch);
		return $response;
	}
}

