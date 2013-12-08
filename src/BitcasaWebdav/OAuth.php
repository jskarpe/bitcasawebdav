<?php
namespace BitcasaWebdav;
use \OAuth\OAuth2\Token\StdOAuth2Token;
use \OAuth\Common\Http\Exception\TokenResponseException;
use \OAuth\Common\Http\Uri\Uri;
use \OAuth\Common\Consumer\CredentialsInterface;
use \OAuth\Common\Http\Client\ClientInterface;
use \OAuth\Common\Storage\TokenStorageInterface;
use \OAuth\Common\Http\Uri\UriInterface;
use \OAuth\OAuth2\Service\AbstractService;

class OAuth extends AbstractService
{
	public function __construct(CredentialsInterface $credentials, ClientInterface $httpClient,
			TokenStorageInterface $storage, $scopes = array(), UriInterface $baseApiUri = null)
	{
		parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri);

		if (null === $baseApiUri) {
			$this->baseApiUri = new Uri('https://developer.api.bitcasa.com/v1');
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAuthorizationEndpoint()
	{
		$uri = $this->baseApiUri->__toString() . '/oauth2/authenticate';
		return new Uri($uri);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAccessTokenEndpoint()
	{
		$uri = $this->baseApiUri->__toString() . '/oauth2/access_token';
		return new Uri($uri);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getAuthorizationMethod()
	{
		return static::AUTHORIZATION_METHOD_QUERY_STRING;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function parseAccessTokenResponse($responseBody)
	{
		$data = json_decode($responseBody, true);

		if (null === $data || !is_array($data)) {
			throw new TokenResponseException('Unable to parse response.');
		} elseif (isset($data['error'])) {
			throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
		}

		$token = new StdOAuth2Token();
		$token->setAccessToken($data['result']['access_token']);
		// I'm invincible!!!
		$token->setEndOfLife(StdOAuth2Token::EOL_NEVER_EXPIRES);
		unset($data['result']['access_token']);

		$token->setExtraParams($data);

		return $token;
	}

	/**
	 * {@inheritdoc}
	 */
	public function requestAccessToken($code)
	{
// 		$bodyParams = array('code' => $code, 
// 		//             'client_id'     => $this->credentials->getConsumerId(),
// 		'secret' => $this->credentials->getConsumerSecret(),
// 		//             'redirect_uri'  => $this->credentials->getCallbackUrl(),
// 		//             'grant_type'    => 'authorization_code',
// 		);

		$uri = new Uri(
				$this->getAccessTokenEndpoint()->__toString() . "?code=$code&secret="
						. $this->credentials->getConsumerSecret());

		$responseBody = $this->httpClient->retrieveResponse($uri, '', array(), 'GET');

		$token = $this->parseAccessTokenResponse($responseBody);
		$this->storage->storeAccessToken($this->service(), $token);

		return $token;
	}
}
