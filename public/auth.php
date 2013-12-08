<?php
require_once __DIR__ . '/../vendor/autoload.php';
use OAuth\OAuth2\Service\GitHub;
use OAuth\Common\Storage\Memory;
use OAuth\Common\Consumer\Credentials;

/**
 * Bootstrap the OAuth library
 */
$config = include __DIR__ . '/../config/bitcasa.php';
if (empty($config['client_id']) || empty($config['client_secret'])) {
	die('Set up client_id and client_secret in config/bitcasa.php from <a href="https://developer.bitcasa.com">https://developer.bitcasa.com</a>');
}
$uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
$currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
$currentUri->setQuery('');
$serviceFactory = new \OAuth\ServiceFactory();
$serviceFactory->registerService('Bitcasa', '\BitcasaWebdav\OAuth');

// Setup the credentials for the requests
$credentials = new Credentials($config['client_id'], $config['client_secret'],
		$currentUri->getAbsoluteUri());

// Instantiate the Bitcasa service using the credentials, http client and storage mechanism for the token
$bitcasa = $serviceFactory->createService('Bitcasa', $credentials, new Memory());

if (!empty($_GET['authorization_code'])) {
	// This was a callback request from Bitcasa, get the token
	$token = $bitcasa->requestAccessToken($_GET['authorization_code']);
	$tokenString = $token->getAccessToken();
	echo "Successfully got token: $tokenString<br />";
	file_put_contents(__DIR__ . '/../config/token', $tokenString);
	echo "Ensure it's saved in config/token file";
} elseif (!empty($_GET['go']) && $_GET['go'] === 'go') {
	$url = $bitcasa->getAuthorizationUri();
	header('Location: ' . $url);

} else {
	$url = $currentUri->getRelativeUri() . '?go=go';
	echo "<a href='$url'>Login to Bitcasa to get token</a>";
}
