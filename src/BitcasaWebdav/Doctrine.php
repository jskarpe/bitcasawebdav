<?php
namespace BitcasaWebdav;
class Doctrine
{
	
	protected $cache;
	
	public function getNodeForPath($path)
	{
		if (isset($this->cache[$path])) {
			return $this->cache[$path];
		}
		
		$em = $this->getEntityManager();
	}
	
	public function getChildren($path)
	{
		
	}
	
	/**
	 * @return \Doctrine\ORM\EntityManager
	 */
	public function getEntityManager()
	{
		
	}
}


// bootstrap.php
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

require_once "vendor/autoload.php";

// Create a simple "default" Doctrine ORM configuration for Annotations
$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__."/src"), $isDevMode);

// database configuration parameters
$conn = array(
		'driver' => 'pdo_sqlite',
		'path' => __DIR__ . '/db.sqlite',
);

// obtaining the entity manager
$entityManager = EntityManager::create($conn, $config);