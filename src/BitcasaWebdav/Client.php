<?php
namespace BitcasaWebdav;
use \Monolog\Logger;
class Client
{

	const PATH_DOES_NOT_EXIST = 2039;
	const BITCASA_INTERNAL_TIMEOUT = 500;

	private $apiUrl;
	private $accessToken;
	private $tempFiles;
	private $tempDirs;
	private $logger;

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

		$postData = array(
				'folder_name' => $name
		);
		$response = $this->post($url, $postData);

		$result = json_decode($response, true);
		if (null === $result) {
			throw new Exception(
					__METHOD__ . ' POST: ' . $url . ' produced invalid JSON response from server: '
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

		$postData = array(
				'path' => $path
		);
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
	}

	public function listFolder($path = '/', $depth = 0)
	{
		$path = trim($path, '/');

		$url = "https://developer.api.bitcasa.com/v1/folders/$path?access_token=$this->accessToken";

		if ($log = $this->getLogger()) {
			$log->debug("Listing folder $path from Bitcasa");
		}
		$data = $this->get($url);

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
		$fullPath = "https://developer.api.bitcasa.com/v1/files/$path?access_token=" . $this->accessToken;
		$options = array(
				CURLOPT_URL => $fullPath
		);

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

		if (0 == filesize($tmpFile)) {
			/**
			 * Windows seems to perform the following actions when doing a PUT request:
			 * - Create an empty file using PUT
			 * - Lock the newly created file
			 * - PUT on the same file again with the actual file body
			 * - A PROPPATCH request
			 */
			if ($log = $this->getLogger()) {
				$log->debug('0 byte file received. Storing it in cache only, no request to Bitcasa sent',
								array(
										'upload'
								));
			}
			unlink($tmpFile);
			rmdir($tmpDir);
			return array(
					'size' => 0
			);
		}

		$options[CURLOPT_POST] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$exist = 'overwrite';
		$post = array(
				'file' => "@$tmpFile",
				'filename' => $filename,
				'exists' => $exist
		);
		$options[CURLOPT_POSTFIELDS] = $post;

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		if ($log = $this->getLogger()) {
			$log->debug("Uploading $filename to $fullPath with exist=$exist", array(
							'upload'
					));
		}
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		unlink($tmpFile);
		rmdir($tmpDir);

		switch ($code) {
			case 502:
				if ($log = $this->getLogger()) {
					$log->warn('Bitcasa return 502 Bad Gateway. Retrying upload to ' . $fullPath,
									array(
											'upload'
									));
				}
				return $this->uploadFile($filename, $path, $data); // Bad gateway (Bitcasa internal timeout)
		}

		$result = json_decode($response, true);
		if (null === $result) {
			throw new \Sabre\DAV\Exception(
					// Windows WebDav ends up here on upload. Why? Rename? 
					// FIXME - introduce Monolog to log events such as these for easier debugging!
					// Assuming it's gateway timeout this time around

					__METHOD__ . ' GET: ' . $fullPath . ' produced invalid JSON response from server: '
							. var_export($response, true));
		}

		if (isset($result['error'])) {
			if ($log = $this->getLogger()) {
				$log->error(
								'Got error from Bitcasa during upload: ' . $result['error']['message']
										. " on url: $fullPath", array(
										'upload'
								));
			}
			throw new \Sabre\DAV\Exception(__METHOD__ . " Got error from Bitcasa: " . $result['error']['message'],
					$result['error']['code']);
		}

		return $result;
	}

	public function deleteFile($path)
	{
		$url = "https://developer.api.bitcasa.com/v1/files?access_token=" . $this->accessToken;
		$response = $this->delete($url, array(
						'path' => $path
				));

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

	public function renameFile($realPath, $newName, $exists = 'fail')
	{
		$params = array(
				"from" => $realPath,
				"filename" => $newName,
				"exists" => $exists
		);
		$url = "https://developer.api.bitcasa.com/v1/files?operation=rename&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function moveFile($fromPath, $toPath, $filename = null, $exists = 'fail')
	{
		$params = array(
				"from" => $fromPath,
				"to" => $toPath,
				"exists" => $exists
		);
		if (!empty($filename)) {
			$params['filename'] = $filename;
		}
		$url = "https://developer.api.bitcasa.com/v1/files?operation=move&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function copyFile($fromPath, $toPath, $filename = null, $exists = 'fail')
	{
		$params = array(
				"from" => $fromPath,
				"to" => $toPath,
				"exists" => $exists
		);
		if (!empty($filename)) {
			$params['filename'] = $filename;
		}
		$url = "https://developer.api.bitcasa.com/v1/files?operation=copy&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function renameDirectory($realPath, $newName, $exists = 'fail')
	{
		$params = array(
				"from" => $realPath,
				"filename" => $newName,
				"exists" => $exists
		);
		$url = "https://developer.api.bitcasa.com/v1/folders?operation=rename&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function moveDirectory($fromPath, $toPath, $filename = null, $exists = 'fail')
	{
		$params = array(
				"from" => $fromPath,
				"to" => $toPath,
				"exists" => $exists
		);
		if (!empty($filename)) {
			$params['filename'] = $filename;
		}
		$url = "https://developer.api.bitcasa.com/v1/folders?operation=move&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function copyDirectory($fromPath, $toPath, $filename = null, $exists = 'fail')
	{
		$params = array(
				"from" => $fromPath,
				"to" => $toPath,
				"exists" => $exists
		);
		if (!empty($filename)) {
			$params['filename'] = $filename;
		}
		$url = "https://developer.api.bitcasa.com/v1/folders?operation=copy&access_token=$this->accessToken";
		$response = $this->post($url, $params);
		return $this->decodeAndVerifyResponse($response);
	}

	public function downloadFileAdvanced($filename, $path, $filesize, $mimeType = 'application/download')
	{
		$url = "https://files.api.bitcasa.com/v1/files/$filename?access_token=$this->accessToken&path=$path";

		if ($log = $this->getLogger()) {
			$log->debug("Downloading file $filename from Bitcasa using curl_multi");
		}

		$bodyStream = fopen('php://output', 'w');
		$headerStream = fopen('php://temp', 'rw');

		$ch = curl_init(str_replace(" ", "%20", $url)); //Here is the file we are downloading, replace spaces with %20
		curl_setopt($ch, CURLOPT_WRITEHEADER, $headerStream);
		curl_setopt($ch, CURLOPT_FILE, $bodyStream);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if (isset($_SERVER['HTTP_RANGE'])) {
			preg_match('/bytes=(.*)/', $_SERVER['HTTP_RANGE'], $match);
			curl_setopt($ch, CURLOPT_RANGE, $match[1]);
		}

		$mh = curl_multi_init();
		curl_multi_add_handle($mh, $ch);
		$headerProcessed = false;
		ob_start(); // Buffer body output until headers are ready
		do {
			$mrc = curl_multi_exec($mh, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);

			if (curl_errno($ch)) {
				throw new \Sabre\DAV\Exception(curl_error($ch) . "-" . curl_errno($ch));
			}

			// Process headers
			if (!$headerProcessed) {
				$currentPos = ftell($headerStream);
				rewind($headerStream);
				$header = stream_get_contents($headerStream);
				fseek($headerStream, $currentPos); // Is this really needed?
				if (strpos($header, "\r\n\r\n") !== false) {
					// End of headers reached
					if (self::BITCASA_INTERNAL_TIMEOUT == $this->verifyHeader($header)) {
						return $this->downloadFileAdvanced($filename, $path);
					}

					$this->generateProxyHeader($header, $filesize, $mimeType);

					// Headers received, send output to browser
					$headerProcessed = true;
					ob_end_flush();
				}
			}
		}

		curl_multi_remove_handle($mh, $ch);
		curl_close($ch);
		curl_multi_close($mh);
		fclose($bodyStream);
		exit; // Download complete, stop processing
	}

	protected function verifyHeader($header)
	{
		/**
		 * HTTP/1.1 is RFC2616. In Section 6.1, it describes the Status-Line, 
		 * the first component of a Response, as:
		 * Status-Line = HTTP-Version SP Status-Code SP Reason-Phrase CRLF
		 */
		$data = explode(' ', "HTTP/1.1 200 OK");
		$code = $data[1];
		if ($code >= 400) {
			switch ($code) {
				case 400:
					throw new \Sabre\DAV\Exception\Exception\BadRequest('Bad request');
				case 401:
					throw new \Sabre\DAV\Exception\Exception\NotAuthenticated('Not authenticated');
				case 402:
					throw new \Sabre\DAV\Exception\Exception\PaymentRequired('Payment required');
				case 403:
					throw new \Sabre\DAV\Exception\Exception\Forbidden('Forbidden');
				case 404:
					throw new \Sabre\DAV\Exception\Exception\NotFound('Resource not found.');
				case 405:
					throw new \Sabre\DAV\Exception\Exception\MethodNotAllowed('Method not allowed');
				case 409:
					throw new \Sabre\DAV\Exception\Exception\Conflict('Conflict');
				case 412:
					throw new \Sabre\DAV\Exception\Exception\PreconditionFailed('Precondition failed');
				case 416:
					throw new \Sabre\DAV\Exception\Exception\RequestedRangeNotSatisfiable(
							'Requested Range Not Satisfiable');
				case 500:
					throw new \Sabre\DAV\Exception\Exception('Internal server error');
				case 501:
					throw new \Sabre\DAV\Exception\Exception\NotImplemented('Not Implemented');
				case 502:
					return self::BITCASA_INTERNAL_TIMEOUT;
				//return $this->downloadFile($filename, $path); // Bad gateway (Bitcasa internal timeout)
				case 507:
					throw new \Sabre\DAV\Exception\Exception\InsufficientStorage('Insufficient storage');
				default:
					throw new \Sabre\DAV\Exception\Exception('HTTP error response. (errorcode ' . $code . ')');
			}
		}
	}

	protected function generateProxyHeader($headerString, $filesize, $mimeType)
	{
		$headers = explode("\r\n", $headerString);
		header($headers[0]); // HTTP status
		header('Expires: Tue, 06 Dec 2000 20:24:15 GMT');
		header('Cache-Control: must-revalidate');
		header("Content-Type: $mimeType");
		if (!isset($_SERVER['HTTP_RANGE'])) {
			header("Content-Length: $filesize");
		}
		foreach ($headers as $header) {
			if (preg_match('/^Content-Type/', $header)) {
				header($header, true);
			}
			if (preg_match('/^Accept-Ranges/', $header)) {
				header($header);
			}
			if (preg_match('/^Content-Disposition/', $header)) {
				header($header);
			}
			if (preg_match('/^Content-Length/', $header)) {
				header($header, true);
			}
		}

		//X-Forwarded-For:

	}

	public function downloadFileProxied($filename, $path, $filesize, $mimeType = 'application/download')
	{
		$url = "https://files.api.bitcasa.com/v1/files/$filename?access_token=$this->accessToken&path=$path";

		if ($log = $this->getLogger()) {
			$log->debug("Downloading file $filename from Bitcasa");
		}

		header("Content-Type: $mimeType");
		header('Content-Disposition: attachment; filename=' . $filename);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . $filesize);
		$fp = fopen('php://output', 'w');
		$ch = curl_init(str_replace(" ", "%20", $url)); //Here is the file we are downloading, replace spaces with %20

		//curl_setopt($ch, CURLOPT_WRITEHEADER, fopen('php://output', 'w'));
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$return = curl_exec($ch); // get curl response
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		fclose($fp);
		if ($code >= 400) {
			switch ($code) {
				case 400:
					throw new \Sabre\DAV\Exception\Exception\BadRequest('Bad request');
				case 401:
					throw new \Sabre\DAV\Exception\Exception\NotAuthenticated('Not authenticated');
				case 402:
					throw new \Sabre\DAV\Exception\Exception\PaymentRequired('Payment required');
				case 403:
					throw new \Sabre\DAV\Exception\Exception\Forbidden('Forbidden');
				case 404:
					throw new \Sabre\DAV\Exception\Exception\NotFound('Resource not found.');
				case 405:
					throw new \Sabre\DAV\Exception\Exception\MethodNotAllowed('Method not allowed');
				case 409:
					throw new \Sabre\DAV\Exception\Exception\Conflict('Conflict');
				case 412:
					throw new \Sabre\DAV\Exception\Exception\PreconditionFailed('Precondition failed');
				case 416:
					throw new \Sabre\DAV\Exception\Exception\RequestedRangeNotSatisfiable(
							'Requested Range Not Satisfiable');
				case 500:
					throw new \Sabre\DAV\Exception\Exception('Internal server error');
				case 501:
					throw new \Sabre\DAV\Exception\Exception\NotImplemented('Not Implemented');
				case 502:
					return $this->downloadFileProxied($filename, $path, $filesize); // Bad gateway (Bitcasa internal timeout)
				case 507:
					throw new \Sabre\DAV\Exception\Exception\InsufficientStorage('Insufficient storage');
				default:
					throw new \Sabre\DAV\Exception\Exception('HTTP error response. (errorcode ' . $code . ')');
			}
		}

		if (false === $return) {
			throw new \Sabre\DAV\Exception($error);
		}
		exit; // No more processing
	}

	public function downloadFile($filename, $path)
	{
		$url = "https://files.api.bitcasa.com/v1/files/$filename?access_token=$this->accessToken&path=$path";

		if ($log = $this->getLogger()) {
			$log->debug("Downloading file $filename from Bitcasa");
		}

		$file = tempnam(sys_get_temp_dir(), 'BitcasaWebdav');
		$this->tempFiles[] = $file;

		$fp = fopen($file, 'w+');
		$ch = curl_init(str_replace(" ", "%20", $url));//Here is the file we are downloading, replace spaces with %20
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$return = curl_exec($ch); // get curl response
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		fclose($fp);

		if ($code >= 400) {
			switch ($code) {
				case 400:
					throw new \Sabre\DAV\Exception\Exception\BadRequest('Bad request');
				case 401:
					throw new \Sabre\DAV\Exception\Exception\NotAuthenticated('Not authenticated');
				case 402:
					throw new \Sabre\DAV\Exception\Exception\PaymentRequired('Payment required');
				case 403:
					throw new \Sabre\DAV\Exception\Exception\Forbidden('Forbidden');
				case 404:
					throw new \Sabre\DAV\Exception\Exception\NotFound('Resource not found.');
				case 405:
					throw new \Sabre\DAV\Exception\Exception\MethodNotAllowed('Method not allowed');
				case 409:
					throw new \Sabre\DAV\Exception\Exception\Conflict('Conflict');
				case 412:
					throw new \Sabre\DAV\Exception\Exception\PreconditionFailed('Precondition failed');
				case 416:
					throw new \Sabre\DAV\Exception\Exception\RequestedRangeNotSatisfiable(
							'Requested Range Not Satisfiable');
				case 500:
					throw new \Sabre\DAV\Exception\Exception('Internal server error');
				case 501:
					throw new \Sabre\DAV\Exception\Exception\NotImplemented('Not Implemented');
				case 502:
					return $this->downloadFile($filename, $path); // Bad gateway (Bitcasa internal timeout)
				case 507:
					throw new \Sabre\DAV\Exception\Exception\InsufficientStorage('Insufficient storage');
				default:
					throw new \Sabre\DAV\Exception\Exception('HTTP error response. (errorcode ' . $code . ')');
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
		if ($log = $this->getLogger()) {
			$log->debug("GET $url");
		}
		$data = curl_exec($ch);
		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			if ($log = $this->getLogger()) {
				$log->error("GET $url failed: $error");
			}
			throw new \Sabre\DAV\Exception(__METHOD__ . " $error");
		}

		curl_close($ch);

		if ($info['http_code'] == 502) {
			// Bad gateway (Bitcasa internal timeout)
			return $this->get($url);
		}

		return $data;
	}

	protected function delete($url, array $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($log = $this->getLogger()) {
			$log->debug("DELETE $url");
		}
		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		if (false === $data) {
			$error = curl_error($ch);
			$errno = curl_errno($ch);
			if ($log = $this->getLogger()) {
				$log->error("DELETE $url failed: $error");
			}
			throw new \Sabre\DAV\Exception(__METHOD__ . " $error");
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
		if ($log = $this->getLogger()) {
			$log->debug("PUT $url");
		}
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

	protected function decodeAndVerifyResponse($httpResponse)
	{
		if ($log = $this->getLogger()) {
			$log->debug('HTTP response: ' . $httpResponse);
		}
		$result = json_decode($httpResponse, true);
		if (null === $result) {
			if ($log = $this->getLogger()) {
				$log->error('Invalid JSON response from server: ' . var_export($httpResponse, true));
			}
			throw new \Sabre\DAV\Exception(
					__METHOD__ . ' Invalid JSON response from server: ' . var_export($httpResponse, true));
		}

		if (isset($result['error'])) {
			if ($log = $this->getLogger()) {
				$log->error(" Got error {$result['error']['code']} from Bitcasa: " . $result['error']['message']);
			}
			throw new \Sabre\DAV\Exception(__METHOD__ . " Got error from Bitcasa: " . $result['error']['message'],
					$result['error']['code']);
		}

		return $result;
	}

	/**
	 * @return \Monolog\Logger
	 */
	public function getLogger()
	{
		if (null === $this->logger) {
			if (class_exists('\Monolog\Logger')) {
				$this->logger = new Logger('BitcasaWebdav');
				$this->logger
						->pushHandler(new \Monolog\Handler\StreamHandler('/tmp/bitcasawebdav.log', Logger::DEBUG));
			}
		}
		return $this->logger;
	}

	public function getAllHeaders()
	{
		if (!function_exists('getallheaders')) {
			function getallheaders()
			{
				$headers = '';
				foreach ($_SERVER as $name => $value) {
					if (substr($name, 0, 5) == 'HTTP_') {
						$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
					}
				}
				return $headers;
			}
		}

		return getallheaders();
	}
}
