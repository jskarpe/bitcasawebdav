<?php
namespace BitcasaWebdav;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
class DoctrineTestCase extends \PHPUnit_Framework_TestCase
{

	private $em;

	/**
	 * @return \Doctrine\ORM\EntityManager
	 */
	public function getEntityManager()
	{
		if (null === $this->em) {
			$entityFolder = realpath(__DIR__ . "/../../src/BitcasaWebdav/FS");
			$config = Setup::createAnnotationMetadataConfiguration(array($entityFolder),
					true);

			// database configuration parameters
			$conn = array('driver' => 'pdo_sqlite', 'path' => ':memory:',);

			// obtaining the entity manager
			$this->em = EntityManager::create($conn, $config);
		}

		return $this->em;
	}

	public function setUp()
	{
		$em = $this->getEntityManager();
		$tool = new \Doctrine\ORM\Tools\SchemaTool($em);
		$tool->dropDatabase();
		$em->getConnection()->connect();
		$tool->createSchema($em->getMetadataFactory()->getAllMetadata());
		parent::setUp();
	}

	public function tearDown()
	{
		$em = $this->getEntityManager();
		$em->getConnection()->close();
		$tool = new \Doctrine\ORM\Tools\SchemaTool($em);
		$tool->dropDatabase();
		parent::tearDown();
	}
}
