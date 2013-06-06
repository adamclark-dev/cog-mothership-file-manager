<?php

namespace Message\Mothership\FileManager\File;

use Message\Cog\DB\Query;
use Message\Cog\ValueObject\Authorship;
use Message\Cog\Filesystem\File as FileSystemFile;

class Loader
{

	protected $_locale;
	protected $_query;

	/**
	 * var to toggle the loading of deleted files
	 *
	 * (default value: false)
	 *
	 * @var bool
	 */
	protected $_loadDeleted = false;

	public function __construct(/*\Locale*/ $locale, Query $query)
	{
		$this->_locale = $locale;
		$this->_query = $query;
	}

	/**
	 * Return an array of, or singular File object
	 *
	 * @param  int|array $fileID
	 * @return array|File 	File object
	 */
	public function getByID($fileID)
	{
		$return = array();

		if (!is_array($fileID)) {
			return $this->_load($fileID);
		} else {
			foreach ($fileID as $id) {
				$return[] = $this->_load($id);
			}
		}

		return array_filter($return);
	}

	/**
	 * Returns all the files of a certain file type id
	 *
	 * @param  int 	$typeID
	 * @return array|File 	Array of File objects, or a single File object
	 */
	public function getByType($typeID)
	{
		$result = $this->_query->run('
			SELECT
				file_id
			FROM
				file
			WHERE
				type_id = ?i',
			array(
				$typeID,
			)
		);

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	/**
	 * Find results based on the search term
	 *
	 * @param  string $term search terms
	 * @return array|File 	Array of File objects, or a single File object
	 */
	public function getBySearchTerm($term)
	{
		// Turn the terms into an array
		$terms = explode(' ',$term);

		// Set a bunch of arrays which are used below, seems a lot but it's
		// becasue we have to pass through an array to the sql query so we have to do it twice
		$whereName = array();
		$whereTag = array();
		$whereTerms = array();

		// Loop over the terms and add them to an array to implode in the query
		foreach ($terms as $term) {
			$whereName[] = ' name SOUNDS LIKE "%?%"';
			$whereTag[] = ' tag_name SOUNDS LIKE "%?%"';
			$whereTerms[] = trim($term);
		}
		// Duplciate and add the same array again and merge it to one, this is
		// because we are looking at both the name and tag name in the query
		$where = array_merge($whereTerms, $whereTerms);

		$result = $this->_query->run('
			SELECT
				file.file_id
			FROM
				file
			LEFT JOIN
				file_tag USING (file_id)
			WHERE
				('.implode(' OR',$whereName).')
				OR
				('.implode(' OR',$whereTag).')',
			$where
		);

		// Return the array of results.
		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	/**
	 * Return all files in an array
	 * @return Array|File|false - 	returns either an array of File objects, a
	 * 								single file object or false
	 */
	public function getAll()
	{
		$result = $this->_query->run('
			SELECT
				file_id
			FROM
				file
		');

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	public function getByUnused()
	{

	}

	public function getByUser(\User $user)
	{
		$result = $this->_query->run('
			SELECT
				file_id
			FROM
				file
			WHERE
				created_by = ?i',
			array(
				$user->id
			)
		);

		return count($result) ? $this->getByID($result->flatten()) : false;

	}

	public function setSort(\Sorter $sorter)
	{

	}

	public function setPaging(\Pager $pager)
	{

	}

	/**
	 * Toggle whether or not to load deleted files
	 *
	 * @param bool $bool 	true / false as to whether to include deleted items
	 * @return 	$this 		Loader object in order to chain the methods
	 */
	public function includeDeleted($bool)
	{
		$this->_loadDeleted = $bool;
		return $this;
	}

	/**
	 * Loads the file data out of the table and loads in into a File Object.
	 *
	 * @param  int $fileID fileId of the file to be loaded
	 *
	 * @return File|false return instance of the file is loaded else false
	 */
	protected function _load($fileID)
	{
		$result = $this->_query->run('
			SELECT
				file.file_id AS fileID,
				file.url AS url,
				file.name AS name,
				file.extension AS extension,
				file.file_size AS fileSize,
				file.created_at AS createdAt,
				file.created_by AS createdBy,
				file.updated_at AS updatedAt,
				file.updated_by AS updatedBy,
				file.deleted_at AS deletedAt,
				file.deleted_by AS deletedBy,
				file.type_id AS typeID,
				file.checksum AS checksum,
				file.preview_url AS previewUrl,
				file.dimension_x AS dimensionX,
				file.dimension_y AS dimensionY,
				file.alt_text AS altText,
				file.duration AS duration
			FROM
				file
			WHERE
				file.file_id = ?i', array($fileID)
		);

		if (count($result)) {
			$file = new File;
			$file = $result->bind($file);
			$result = $result->first();
			$file->authorship = new Authorship;

			if ($result->deletedAt && !$this->_loadDeleted) {
				return false;
			}

			$file->authorship->create(new \DateTime(date('c',$result->createdAt)), $result->createdBy);

			if ($result->updatedAt) {
				$file->authorship->update(new \DateTime(date('c',$result->updatedAt)), $result->updatedBy);
			}

			if ($result->deletedAt) {
				$file->authorship->delete(new \DateTime(date('c',$result->deletedAt)), $result->deletedBy);
			}

			$file->tags = $this->_loadTags($file);
			$file->file = new FileSystemFile($file->url);

			return $file;
		}
		return false;

	}

	protected function _loadTags(File $file)
	{
		$tags = array();

		$result = $this->_query->run('
			SELECT
				file_tag.tag_name
			FROM
				file_tag
			WHERE
				file_tag.file_id = ?i', array($file->fileID)
		);

		if (count($result)) {
			$tags = $result->flatten();
		}

		return $tags;

	}

}