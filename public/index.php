<?php
include_once __DIR__ . '/../bootstrap.php';
if (!file_exists(__DIR__ . '/../cache/db.sqlite3')) {
	$tool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
	$entityManager->getConnection()->connect();
	$tool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
}

// Files we need
require_once __DIR__ . '/../vendor/autoload.php';

// $u = 'admin';
// $p = '1234';

// $auth = new \Sabre\HTTP\DigestAuth();
// $auth->init();

// if ($auth->getUsername() != $u || !$auth->validatePassword($p)) {

// 	$auth->requireLogin();
// 	echo "Authentication required\n";
// 	die();
// }

// Create the root node
$rootNode = new \BitcasaWebdav\FS\Directory('/');
$rootNode->setEntityManager($entityManager);
$rootNode->setName('/');
$rootNode->setRealPath('/');

// The rootnode needs in turn to be passed to the server class
$server = new \Sabre\DAV\Server($rootNode);

// Inject entity manager
$emPlugin = new \BitcasaWebdav\FS\Plugin\EntityManager();
$server->addPlugin($emPlugin);

if (isset($baseUri))
	$server->setBaseUri($baseUri);

// Support for LOCK and UNLOCK
$lockBackend = new \Sabre\DAV\Locks\Backend\File(sys_get_temp_dir() . '/locksdb');
$lockPlugin = new \Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

// Support for html frontend
$browser = new \Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

$mount = new \Sabre\DAV\Mount\Plugin();
$server->addPlugin($mount);

// Automatically guess (some) contenttypes, based on extension
$server->addPlugin(new \Sabre\DAV\Browser\GuessContentType());

// Authentication backend
// $authBackend = new \Sabre\DAV\Auth\Backend\File('.htdigest');
// $auth = new \Sabre\DAV\Auth\Plugin($authBackend,'SabreDAV');
// $server->addPlugin($auth);

// Temporary file filter
// $tempFF = new \Sabre\DAV\TemporaryFileFilterPlugin($tmpDir);
// $server->addPlugin($tempFF);

// And off we go!
$server->exec();
