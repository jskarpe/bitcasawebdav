<?php
namespace BitcasaWebdav\FS;
use BitcasaWebdav\Exception;

use \Sabre\DAV, \Sabre\DAV\URLUtil;

/**
 * 
 * http://stackoverflow.com/questions/5823905/how-to-manage-single-table-inheritance-within-doctrine-2
 * @Entity
 * @Table(indexes={@Index(name="search_idx", columns={"name", "realPath"})})
 * @InheritanceType("SINGLE_TABLE")
 * @DiscriminatorColumn(name="node_type", type="string")
 * @DiscriminatorMap({"file" = "File", "directory" = "Directory"})
 *
 * 
 * Base node-class
 *
 * The node class implements the method used by both the File and the Directory classes
 */
abstract class Node implements DAV\INode
{

	/**
	 * @var integer $id
	 * @Column(name="id", type="integer", nullable=false)
	 * @Id
	 * @GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @var string $name
	 * @Column(type="string", length=255, nullable=false)
	 */
	private $name;

	/**
	 * @Column(type="integer", nullable=false)
	 */
	private $mtime;

	/**
	 * The path to the current node
	 * @Column(type="text")
	 */
	private $path;

	/**
	 * @Column(type="text", nullable=true)
	 */
	private $realPath;

	/**
	 * @Column(type="integer", nullable=true)
	 */
	private $type;

	/**
	 * @Column(type="integer", nullable=true)
	 */
	private $mirrored;

	/**
	 * @ManyToOne(targetEntity="Directory", inversedBy="children", cascade={"persist"})
	 * @JoinColumn(name="parent_path", referencedColumnName="id")
	 */
	private $parent;

	protected $client;
	protected $entityManager;

	/**
	 * Sets up the node, expects a full path name
	 *
	 * @param string $path
	 */
	public function __construct($path)
	{
		$this->setMtime(time());
		$this->setPath($path);
	}

	/**
	 * @return \BitcasaWebdav\Client
	 */
	public function getClient()
	{
		if (null === $this->client) {
			$tokenFile = realpath(__DIR__ . '/../../../config/token');
			if (!file_exists($tokenFile)) {
				throw new Exception(__METHOD__ , "No token file found at: $tokenFile");
			}
			$token = file_get_contents($tokenFile);
			$this->client = new \BitcasaWebdav\Client($token);
		}
		return $this->client;
	}
	
	public function setClient(\BitcasaWebdav\Client $client)
	{
		$this->client = $client;
	}
	
	/**
	 * Returns the name of the node
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Renames the node
	 *
	 * @param string $name The new name
	 * @return void
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	public function getPath()
	{
		return $this->path;
	}

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int
	 */
	public function getLastModified()
	{
		return $this->getMTime();
	}

	public function setEntityManager(\Doctrine\ORM\EntityManager $em)
	{
		$this->entityManager = $em;
	}

	/**
	 * @return \Doctrine\ORM\EntityManager
	 */
	public function getEntityManager()
	{
		if (null === $this->entityManager) {
			// FIXME - globals are bad. Find a proper way to inject it instead!
			global $entityManager;
			if (isset($entityManager)) {
				$this->entityManager = $entityManager;
			}
		}
		return $this->entityManager;
	}

	public function getId()
	{
		return $this->id;
	}

	public function setId($id)
	{
		$this->id = $id;
	}

	public function getMtime()
	{
		return $this->mtime;
	}

	public function setMtime($mtime)
	{
		$this->mtime = $mtime;
	}

	public function setPath($path)
	{
		$this->path = $path;
	}

	public function getRealPath()
	{
		return $this->realPath;
	}

	public function setRealPath($realPath)
	{
		$this->realPath = $realPath;
	}

	public function getType()
	{
		return $this->type;
	}

	public function setType($type)
	{
		$this->type = $type;
	}

	public function getMirrored()
	{
		return $this->mirrored;
	}

	public function setMirrored($mirrored)
	{
		$this->mirrored = $mirrored;
	}

	public function getParent()
	{
		return $this->parent;
	}

	public function setParent($parent)
	{
		$this->parent = $parent;
	}
}
