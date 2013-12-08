<?php
namespace BitcasaWebdav;
require_once __DIR__ . '/../DoctrineTestCase.php';
class DirectoryTest extends DoctrineTestCase
{

	public function testCreateReadUpdateDeleteDirectoryDoctrine()
	{
		$path = '/tmp/dummy';
		$name = 'dummy123';
		$em = $this->getEntityManager();

		$dir = new FS\Directory($path);
		$dir->setPath($path);
		$dir->setName($name);
		$dir->setEntityManager($em);

		// Create
		$em->persist($dir);
		$em->flush();

		// Read
		$dir2 = $em->getRepository('\BitcasaWebdav\FS\Directory')->findOneBy(array('path' => $path));
		$this->assertEquals($dir->getPath(), $dir2->getPath());
		$this->assertEquals($dir->getName(), $dir2->getName());

		// Update
		$dir2->setName('dummy321');
		$em->persist($dir2);
		$em->flush();

		$dir3 = $em->getRepository('\BitcasaWebdav\FS\Directory')->findOneBy(array('path' => $path));
		$this->assertEquals($dir2->getPath(), $dir3->getPath());
		$this->assertEquals($dir2->getName(), $dir3->getName());

		// Delete
		$em->remove($dir3);
		$em->flush();
		$dir4 = $em->getRepository('\BitcasaWebdav\FS\Directory')->findOneBy(array('path' => $path));
		$this->assertNull($dir4);
	}

	public function testCreateReadUpdateDeleteDirectoryBitcasaInfinityDrive()
	{
		$dir = new FS\Directory('/');
		$dir->setName('root');
		$dir->setRealPath('/');
		$dir->setEntityManager($this->getEntityManager());
		$children = $dir->getChildren();

		// First find the infinity drive from the root folder
		$this->assertGreaterThan(0, count($children), 'No children found for root node');
		$infinityDrive = false;
		foreach ($children as $child) {
			if ('Bitcasa Infinite Drive' == $child->getName()) {
				$infinityDrive = $child;
				$children = $infinityDrive->getChildren();
				$this
						->assertGreaterThan(0, count($children),
								'No children found for node: ' . $infinityDrive->getName());
			}
			$this->assertInstanceOf('\Sabre\DAV\INode', $child);
		}
		$this->assertTrue(false !== $infinityDrive, 'Did not find infinity drive node in root dir');

		// Create a new directory in the Infinity folder
		$newDirname = 'Test123';
		$infinityDrive->createDirectory($newDirname);

		// List contents of Infinity Drive and look for new folder
		$children = $infinityDrive->getChildren();
		$this->assertGreaterThan(0, count($children), 'No children found for node: ' . $infinityDrive->getName());
		$newDir = false;
		foreach ($children as $child) {
			$this->assertInstanceOf('\Sabre\DAV\INode', $child);
			if ($child instanceof FS\Directory && $child->getName() == $newDirname) {
				$newDir = $child;
			}
		}
		$this->assertTrue(false !== $newDir, 'Did not find newly created directory in Infinity Drive dir');
		$this->assertEquals($newDirname, $newDir->getName());

		// Test subfolder + fresh connection to Bitcasa backend
		$newSubDirname = 'Test321';
		$newDir->createDirectory($newSubDirname);

		$dir = new FS\Directory('/Bitcasa Infinity Drive/' . $newDirname);
		$dir->setName($newDirname);
		$dir->setRealPath($newDir->getRealPath());
		$dir->setEntityManager($this->getEntityManager());
		$children = $dir->getChildren();
		$this->assertGreaterThan(0, count($children), 'No children found for node: ' . $infinityDrive->getName());
		$newSubDir = false;
		foreach ($children as $child) {
			$this->assertInstanceOf('\Sabre\DAV\INode', $child);
			if ($child instanceof FS\Directory && $child->getName() == $newSubDirname) {
				$newSubDir = $child;
			}
		}
		$this
				->assertInstanceOf('\BitcasaWebdav\FS\Directory', $newSubDir,
						'Did not find newly created sub directory in ' . $newDirname);
		$this->assertEquals($newDirname, $newDir->getName());

		// 		$em = $this->getEntityManager();
		// 		$em->getConnection()->getConfiguration()->setSQLLogger(new \Doctrine\DBAL\Logging\EchoSQLLogger());

		// Delete new folder
		$newDir->delete();

		// List contents of Infinity Drive and look for new folder
		$children = $infinityDrive->getChildren();
		$this->assertGreaterThan(0, count($children), 'No children found for node: ' . $infinityDrive->getName());
		$newDir = false;
		foreach ($children as $child) {
			$this->assertInstanceOf('\Sabre\DAV\INode', $child);
			if ($child instanceof FS\Directory && $child->getName() == $newDirname) {
				$this->fail('Should not find newly created directory in Infinity Drive dir');
			}
		}

	}
}

