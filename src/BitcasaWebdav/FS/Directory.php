<?php
namespace BitcasaWebdav\FS;
use Doctrine\Common\Collections\ArrayCollection;
use Sabre\DAV;

/**
 * @Table
 * @Entity
 */
class Directory extends Node implements DAV\ICollection, DAV\IQuota
{
	/**
	 * @Column(type="text")
	 */
	private $category = 'folders';

	/**
	 * @OneToMany(targetEntity="Node", mappedBy="parent", orphanRemoval=true, cascade={"persist", "remove"})
	 */
	private $children;

	/**
	 * @Column(type="boolean")
	 */
	private $childrenFetched = false;

	protected $client;

	/**
	 * Creates a new file in the directory
	 *
	 * Data will either be supplied as a stream resource, or in certain cases
	 * as a string. Keep in mind that you may have to support either.
	 *
	 * After successful creation of the file, you may choose to return the ETag
	 * of the new file here.
	 *
	 * The returned ETag must be surrounded by double-quotes (The quotes should
	 * be part of the actual string).
	 *
	 * If you cannot accurately determine the ETag, you should not return it.
	 * If you don't store the file exactly as-is (you're transforming it
	 * somehow) you should also not return an ETag.
	 *
	 * This means that if a subsequent GET to this new file does not exactly
	 * return the same contents of what was submitted here, you are strongly
	 * recommended to omit the ETag.
	 *
	 * @param string $name Name of the file
	 * @param resource|string $data Initial payload
	 * @return null|string
	 */
	public function createFile($name, $data = null)
	{
		$path = $this->getPath();
		$path = ('/' == $path) ? '' : $path;
		$newPath = $path . '/' . $name;
		$file = new \BitcasaWebdav\FS\File($newPath);
		$file->setClient($this->getClient());
		$file->setEntityManager($this->getEntityManager());
		$file->setName($name);
		$this->addChild($file);
		$file->put($data);
		
		
	
// 		file_put_contents($this->getRealPath(), $data);
// 		$this->setChildrenFetched(false);
		$em = $this->getEntityManager();
		$em->persist($this);
		$em->flush();
// 		$file = new \BitcasaWebdav\FS\File($newPath);
	}

	/**
	 * Creates a new subdirectory
	 *
	 * @param string $name
	 * @return void
	 */
	public function createDirectory($name)
	{
		// Add to Bitcasa
		$client = $this->getClient();
		$response = $client->addFolder($this->getRealPath(), $name);
		$item = array_pop($response['result']['items']);
		$node = $this->mapItem($item);
		$path = ($this->getPath() == '/') ? '/' . $item['name'] : $this->getPath() . '/' . $item['name'];
		$this->addChild($node);

		// Store in persistent cache
		$em = $this->getEntityManager();
		$em->persist($node);
		$em->persist($this);
		$em->flush();
	}

	private function mapItem(array $item)
	{
		$path = ($this->getPath() == '/') ? '/' . $item['name'] : $this->getPath() . '/' . $item['name'];
		if ($item['category'] == 'folders') {
			$node = new Directory($path);
		} else {
			$node = new File($path);
			$node->setContentType($item['mime']);
			$node->setSize($item['size']);
			$node->setId($item['id']);
			$node->setAlbum($item['album']);
			$node->setMirrored($item['mirrored']);
		}
		$node->setRealPath($item['path']);
		$node->setName($item['name']);
		$utime = $item['mtime'];
		$mtime = intval($utime / 1000);
		$node->setMtime('@' . $mtime);
		$node->setParent($this);
		$node->setEntityManager($this->getEntityManager());

		return $node;
	}

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * This method must throw DAV\Exception\NotFound if the node does not
	 * exist.
	 *
	 * @param string $name
	 * @throws DAV\Exception\NotFound
	 * @return DAV\INode
	 */
	public function getChild($name)
	{
		$path = $this->getPath();
		$index = ($path == '/') ? "/$name" : "$path/$name";
		$children = $this->getChildren();
		foreach ($children as $childNode) {
			if ($childNode->getPath() == $index) {
				return $childNode;
			}
		}

		throw new DAV\Exception\NotFound("$name not found in $path");
	}

	public function setChildren(ArrayCollection $children)
	{
		$this->children = $children;
	}

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return DAV\INode[]
	 */
	public function getChildren()
	{
		if (!$this->getChildrenFetched()) {

			$this->children = new ArrayCollection();

			// Look in persistent cache
			$em = $this->getEntityManager();
			$dql = "SELECT n FROM \BitcasaWebdav\FS\Node n JOIN n.parent p WHERE p.realPath = '{$this->getRealPath()}'";
			$query = $em->createQuery($dql);
			$children = $query->getResult();
			if (!empty($children)) {
				foreach ($children as $child) {
					$this->addChild($child);
				}
				return $this->children;
			}

			// Retrieve from Bitcasa
			$children = array();
			$client = $this->getClient();
			$response = $client->listFolder($this->getRealPath());

			// 		"result": {
			// 			"items": [{
			// 				"category": "folders", "mount_point": "%%%HOME%%%/Bitcasa/Bitcasa Infinite Drive", "name": "Bitcasa Infinite Drive", "deleted": false, "mirrored": false, "origin_device": null, "mtime": 1385664683000.0, "origin_device_id": null, "path": "/bVnH7L6rSl-UUzmgS52LXQ", "type": 1, "sync_type": "infinite drive"}]}, "error": null}

			foreach ($response['result']['items'] as $item) {
				$path = ($this->getPath() == '/') ? '/' . $item['name'] : $this->getPath() . '/' . $item['name'];

				if ($item['category'] == 'folders') {
					$node = new Directory($path);
				} else {
					$node = new File($path);
					$node->setContentType($item['mime']);
					$node->setSize($item['size']);
					$node->setId($item['id']);
					$node->setAlbum($item['album']);
					$node->setMirrored($item['mirrored']);
				}
				$node->setRealPath($item['path']);
				$node->setName($item['name']);
				$utime = $item['mtime'];
				$mtime = intval($utime / 1000);
				$node->setMtime('@' . $mtime);
				$node->setParent($this);
				$node->setEntityManager($this->getEntityManager());
				$this->addChild($node);
				$em->persist($node);
			}

			// Store in persistent cache
			$this->setChildrenFetched(true);
			
			$em->persist($this);
			$em->flush();
		}

		return $this->children;
	}

	/**
	 * Checks if a child exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name)
	{
		try {
			$this->getChild($name);
		} catch (DAV\Exception\NotFound $e) {
			return false;
		}
		return true;
	}

	public function removeChild(Node $child)
	{
		$this->getChildren()->removeElement($child);
	}

	public function addChild(Node $child)
	{
		$this->children[] = $child;
		$child->setParent($this);
	}

	/**
	 * Deletes all files in this directory, and then itself
	 *
	 * @return void
	 */
	public function delete()
	{

		// Clear from cache
		$em = $this->getEntityManager();
		$em->remove($this);
		$em->flush();

		// Delete from Bitcasa
		$client = $this->getClient();
		$client->deleteFolder($this->getRealPath(), $this->getName());
	}

	/**
	 * Returns available diskspace information
	 *
	 * @return array
	 */
	public function getQuotaInfo()
	{

		return array(0, 0);

		// 		return array(
		// 				disk_total_space($this->path)-disk_free_space($this->path),
		// 				disk_free_space($this->path)
		// 		);

	}

	protected function translateDoctrineToDav(\BitcasaWebdav\Entity\Node $node)
	{
		switch (get_class($node)) {
			case 'BitcasaWebdav\Entity\Directory':
				$davNode = new \BitcasaWebdav\FS\Directory($node->getPath());
				break;
			case 'BitcasaWebdav\Entity\File':
				$davNode = new \BitcasaWebdav\FS\File($node->getPath());
				$davNode->setSize($node->getSize());
				$davNode->setContentType($node->getMime());
				break;
			default:
				throw new InvalidArgumentException(__METHOD__ . ' Unsupported class: ' . get_class($node));
		}

		$davNode->setName($node->getName());
		$davNode->setDisplayName($node->getName());
		$davNode->setEntityManager($this->getEntityManager());
		$davNode->setLastModified($node->getMtime());
		return $davNode;
	}

	public function getCategory()
	{
		return $this->category;
	}

	public function setCategory($category)
	{
		$this->category = $category;
	}

	public function getChildrenFetched()
	{
		return $this->childrenFetched;
	}

	public function setChildrenFetched($childrenFetched)
	{
		$this->childrenFetched = $childrenFetched;
	}
}
