<?php
namespace BitcasaWebdav\FS\Plugin;

use Sabre\DAV;

/**
 * This plugin provides support for RFC4709: Mounting WebDAV servers
 *
 * Simply append ?mount to any collection to generate the davmount response.
 *
 * @copyright Copyright (C) 2007-2013 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class EntityManager extends DAV\ServerPlugin
{

	private $entityManager;
	
	/**
	 * Reference to Server class
	 *
	 * @var Sabre\DAV\Server
	 */
	protected $server;

	/**
	 * Initializes the plugin and registers event handles
	 *
	 * @param DAV\Server $server
	 * @return void
	 */
	public function initialize(DAV\Server $server)
	{

		$this->server = $server;
		$this->server->subscribeEvent('beforeGetProperties', array($this, 'beforeGetProperties'), 90);

	}

	function beforeGetProperties($path, \Sabre\DAV\INode $node, &$requestedProperties, &$returnedProperties)
	{
		if (null === $node->getEntityManager()) {
			$node->setEntityManager($this->getEntityManager());
		}
	}

	public function getEntityManager()
	{
	    return $this->entityManager;
	}

	public function setEntityManager($entityManager)
	{
	    $this->entityManager = $entityManager;
	}
}
