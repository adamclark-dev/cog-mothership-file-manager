<?php

namespace Message\Mothership\FileManager\Controller;
/**
 * Controller listing files from file manager
 *
 * @author Daniel Hannah <danny@message.co.uk>
 */
class Detail extends \Message\Cog\Controller\Controller
{

	public function index($fileID)
	{
		$file = $this->_services['filesystem.file.loader']->getByID($fileID);
		$author = $this->_services['user.loader']->getByID($file->authorship->createdBy());
		$data = array(
			'file' => $file,
			'author' => $author,
		);
		return $this->render('::detail', $data);
	}

	public function edit($fileID)
	{
		// Load the file object
		$file = $this->_services['filesystem.file.loader']->getByID($fileID);
		// Load the changed data from the request
		if ($edits = $this->_services['request']->get('file')) {
			// Set the alt text
			$file->altText = $edits['alt_text'];
			// Turn the tags into an array and trim the values
			$file->tags = array_filter(array_map('trim', explode(',',$edits['tags'])));
			// Save the file
			if ($file = $this->_services['filesystem.file.edit']->save($file)) {
				$this->addFlash('success', $file->file->getBasename().' was updated successfully');
			} else {
				$this->addFlash('error', $file->file->getBasename().' could not be updated.');
			}

		}
		// Redirect the page to where is was
		return $this->redirect($this->generateUrl('ms.file_manager.detail',array('fileID' => $file->fileID)));
	}

	public function delete($fileID)
	{
		// Load the file object
		$file = $this->_services['filesystem.file.loader']->getByID($fileID);

		// Check that the delete request has been sent
		if ($delete = $this->_services['request']->get('delete')) {

			if ($file = $this->_services['filesystem.file.delete']->delete($file)) {
				$this->addFlash('success', $file->file->getBasename().' was deleted. <a href="'.$this->generateUrl('ms.file_manager.restore',array('fileID' => $file->fileID)).'">Undo</a>');
			} else {
				$this->addFlash('error', $file->file->getBasename().' could not be deleted.');
			}

		}

		return $this->redirect($this->generateUrl('ms.file_manager.listing'));
	}

	public function restore($fileID)
	{
		$file = $this->_services['filesystem.file.loader']->includeDeleted(true)->getByID($fileID);

		if ($this->_services['filesystem.file.delete']->restore($file)) {
			$this->addFlash('success', $file->file->getBasename().' was restored successfully');
		} else {
			$this->addFlash('error', $file->file->getBasename().' could not be restored.');
		}
		return $this->redirect($this->generateUrl('ms.file_manager.listing'));

	}
}
