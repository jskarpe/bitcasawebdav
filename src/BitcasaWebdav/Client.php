<?php
namespace BitcasaWebdav;
class Client
{
	private $apiUrl;
	private $accessToken;

	public function __construct($accessToken, $apiUrl = 'https://developer.api.bitcasa.com/v1')
	{
		$this->apiUrl = $apiUrl;
		$this->accessToken = $accessToken;
	}

	public function getFileUri($filename, $realPath)
	{
		$path = "https://files.api.bitcasa.com/v1/files/$filename?access_token=$this->accessToken&path=$realPath";
		return $path;
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
		return $result;
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
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
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
}

