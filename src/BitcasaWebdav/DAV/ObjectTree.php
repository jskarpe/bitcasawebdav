<?php
namespace BitcasaWebdav\DAV;
use \Sabre\DAV;
use \Sabre\DAV\URLUtil;
class ObjectTree extends DAV\ObjectTree
{

	/**
	 * Copies a file from path to another
	 *
	 * @param string $sourcePath The source location
	 * @param string $destinationPath The full destination path
	 * @return void
	 */
	public function copy($sourcePath, $destinationPath)
	{

		$sourceNode = $this->getNodeForPath($sourcePath);

		// grab the dirname and basename components
		list($destinationDir, $destinationName) = URLUtil::splitPath($destinationPath);

		$destinationParent = $this->getNodeForPath($destinationDir);
		$this->copyNode($sourceNode, $destinationParent, $destinationName);

		$this->markDirty($destinationDir);

	}

	/**
	 * Moves a file from one location to another
	 *
	 * @param string $sourcePath The path to the file which should be moved
	 * @param string $destinationPath The full destination path, so not just the destination parent node
	 * @return int
	 */
	public function move($sourcePath, $destinationPath)
	{
		list($sourceDir, $sourceName) = URLUtil::splitPath($sourcePath);
		list($destinationDir, $destinationName) = URLUtil::splitPath($destinationPath);

		$node = $this->getNodeForPath($sourcePath);
		$em = $node->getEntityManager();
		$client = $node->getClient();

		$node->setName($destinationName);
		$node->setPath('/' . trim($destinationPath, '/'));

		if ($sourceDir === $destinationDir) {
			// Rename
			if ($node instanceof \BitcasaWebdav\FS\Directory) {
				if ($log = $node->getClient()->getLogger()) {
					$log->debug('Renaming directory ' . $sourceName . ' to ' . $destinationName);
				}
				$reply = $client->renameDirectory($node->getRealPath(), $destinationName);
				// FIXME Update children?
			} else {
				if ($log = $node->getClient()->getLogger()) {
					$log->debug('Renaming file ' . $sourceName . ' to ' . $destinationName);
				}
				$reply = $client->renameFile($node->getRealPath(), $destinationName);
			}
		} else {
			// Move
			$destinationParent = $this->getNodeForPath($destinationDir);
			if ($node instanceof \BitcasaWebdav\FS\Directory) {
				if ($log = $node->getClient()->getLogger()) {
					$log->debug('Moving directory ' . $sourcePath . ' to ' . $destinationPath);
				}
				$reply = $client
						->moveDirectory($node->getRealPath(), $destinationParent->getRealPath(), $destinationName);
				// FIXME Update children?
			} else {
				if ($log = $node->getClient()->getLogger()) {
					$log->debug('Moving file ' . $sourcePath . ' to ' . $destinationPath);
				}
				$reply = $client->moveFile($node->getRealPath(), $destinationParent->getRealPath(), $destinationName);
			}

			// Update cache
			$newProperties = array_pop($reply['result']['items']);
			if (!empty($newProperties['path'])) {
				$newProperties['real_path'] = $newProperties['path'];
				unset($newProperties['path']);
				if ($log = $node->getClient()->getLogger()) {
					$log
							->debug(
									"Updating real path from " . $node->getRealPath() . ' to '
											. $newProperties['real_path']);
				}
				$node->fromArray($newProperties); // Store real path
			}

			$sourceParent = $this->getNodeForPath($sourceDir);
			$sourceParent->removeChild($node);
			$destinationParent->addChild($node);

			// Update cache
			$em->persist($sourceParent);
			$em->persist($destinationParent);
			// 			$this->copy($sourcePath, $destinationPath);
			// 			$this->getNodeForPath($sourcePath)->delete();
		}

		$em->persist($node);
		$em->flush();

		$this->markDirty($sourceDir);
		$this->markDirty($destinationDir);

	}

}
