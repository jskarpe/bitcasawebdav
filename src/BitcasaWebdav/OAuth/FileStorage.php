<?php
namespace BitcasaWebdav\OAuth;
use OAuth\Common\Token\TokenInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;

/**
 * Stores a token in a persistent file.
 */
class FileStorage implements TokenStorageInterface
{

	private $tokenFile;

	public function __construct()
	{
		$this->tokenFile = __DIR__ . '/../../../config/token';
	}

	/**
	 * {@inheritDoc}
	 */
	public function retrieveAccessToken($service)
	{
		if ($this->hasAccessToken($service)) {
			return unserialize(file_get_contents($this->tokenFile));
		}

		throw new TokenNotFoundException('Token not found, are you sure you stored it?');
	}

	/**
	 * {@inheritDoc}
	 */
	public function storeAccessToken($service, TokenInterface $token)
	{
		$serializedToken = serialize($token);

		file_put_contents($this->tokenFile, $serializedToken);

		// allow chaining
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasAccessToken($service)
	{
		return is_readable($this->tokenFile);
	}

	/**
	 * {@inheritDoc}
	 */
	public function clearToken($service)
	{
		unlink($this->tokenFile);

		// allow chaining
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function clearAllTokens()
	{
		unlink($this->tokenFile);

		// allow chaining
		return $this;
	}
}
