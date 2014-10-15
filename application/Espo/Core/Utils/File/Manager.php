<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/

namespace Espo\Core\Utils\File;

use Espo\Core\Utils,
	Espo\Core\Exceptions\Error;

class Manager
{
	private $permission;

	public function __construct(\Espo\Core\Utils\Config $config = null)
	{
		$params = null;
		if (isset($config)) {
			$params = array(
				'defaultPermissions' => $config->get('defaultPermissions'),
				'permissionMap' => $config->get('permissionMap'),
			);
		}

		$this->permission = new Permission($this, $params);
	}

	public function getPermissionUtils()
	{
		return $this->permission;
	}

	/**
	 * Get a list of files in specified directory
	 *
	 * @param string $path string - Folder path, Ex. myfolder
	 * @param bool | int $recursively - Find files in subfolders
	 * @param string $filter - Filter for files. Use regular expression, Ex. \.json$
	 * @param string $fileType [all, file, dir] - Filter for type of files/directories.
	 * @param bool $isReturnSingleArray - if need to return a single array of file list
	 *
	 * @return array
	 */
	public function getFileList($path, $recursively=false, $filter='', $fileType='all', $isReturnSingleArray = false)
	{
		if (!file_exists($path)) {
			return false;
		}

		$result = array();

		$cdir = scandir($path);
		foreach ($cdir as $key => $value)
		{
			if (!in_array($value,array(".","..")))
			{
				$add= false;
				if (is_dir($path . Utils\Util::getSeparator() . $value)) {
					if ($recursively || (is_int($recursively) && $recursively!=0) ) {
						$nextRecursively = is_int($recursively) ? ($recursively-1) : $recursively;
						$result[$value] = $this->getFileList($path.Utils\Util::getSeparator().$value, $nextRecursively, $filter, $fileType);
					}
					else if (in_array($fileType, array('all', 'dir'))){
						$add= true;
					}
				}
				else if (in_array($fileType, array('all', 'file'))) {
					$add= true;
				}

				if ($add) {
					if (!empty($filter)) {
						if (preg_match('/'.$filter.'/i', $value)) {
							$result[] = $value;
						}
					}
					else {
						$result[] = $value;
					}
				}

			}
		}

		if ($isReturnSingleArray) {
			return $this->getSingeFileList($result);
		}

		return $result;
	}

	/**
	 * Convert file list to a single array
	 *
	 * @param aray $fileList
	 * @param string $parentDirName
	 *
	 * @return aray
	 */
	protected function getSingeFileList(array $fileList, $parentDirName = '')
	{
		$singleFileList = array();
		foreach($fileList as $dirName => $fileName) {

			if (is_array($fileName)) {
			$currentDir = Utils\Util::concatPath($parentDirName, $dirName);
					$singleFileList = array_merge($singleFileList, $this->getSingeFileList($fileName, $currentDir));
			} else {
				$singleFileList[] = Utils\Util::concatPath($parentDirName, $fileName);
			}
		}

		return $singleFileList;
	}

	/**
	 * Reads entire file into a string
	 *
	 * @param  string | array  $path  Ex. 'path.php' OR array('dir', 'path.php')
	 * @param  boolean $useIncludePath
	 * @param  resource  $context
	 * @param  integer $offset
	 * @param  integer $maxlen
	 * @return mixed
	 */
	public function getContents($path, $useIncludePath = false, $context = null, $offset = -1, $maxlen = null)
	{
		$fullPath = $this->concatPaths($path);

		if (file_exists($fullPath)) {

			if (strtolower(substr($fullPath, -4))=='.php') {
				return include($fullPath);
			} else {
				if (isset($maxlen)) {
					return file_get_contents($fullPath, $useIncludePath, $context, $offset, $maxlen);
				} else {
					return file_get_contents($fullPath, $useIncludePath, $context, $offset);
				}
			}

		}

		return false;
	}

	/**
	 * Write data to a file
	 *
	 * @param  string | array  $path
	 * @param  mixed  $data
	 * @param  integer $flags
	 * @param  resource  $context
	 *
	 * @return bool
	 */
	public function putContents($path, $data, $flags = 0, $context = null)
	{
		$fullPath = $this->concatPaths($path); //todo remove after changing the params

		if ($this->checkCreateFile($fullPath) === false) {
			throw new Error('Permission denied in '. $fullPath);
		}

		$res = (file_put_contents($fullPath, $data, $flags, $context) !== FALSE);
		if ($res && function_exists('opcache_invalidate')) {
			opcache_invalidate($fullPath);
		}

		return $res;
	}

	/**
	 * Save PHP content to file
	 *
	 * @param string | array $path
	 * @param string $data
	 *
	 * @return bool
	 */
	public function putContentsPHP($path, $data)
	{
		return $this->putContents($path, $this->getPHPFormat($data));
	}

	/**
	 * Save JSON content to file
	 *
	 * @param string | array $path
	 * @param string $data
	 * @param  integer $flags
	 * @param  resource  $context
	 *
	 * @return bool
	 */
	public function putContentsJson($path, $data)
	{
		if (!Utils\Json::isJSON($data)) {
			$data = Utils\Json::encode($data, JSON_PRETTY_PRINT);
		}

		return $this->putContents($path, $data);
	}

	/**
	 * Merge file content and save it to a file
	 *
	 * @param string | array $path
	 * @param string $content JSON string
	 * @param bool $isJSON
	 * @param string | array $removeOptions - List of unset keys from content
	 * @param bool $isReturn - Is result to be returned or stored
	 *
	 * @return bool | array
	 */
	public function mergeContents($path, $content, $isJSON = false, $removeOptions = null, $isReturn = false)
	{
		$fileContent = $this->getContents($path);

		$savedDataArray = Utils\Json::getArrayData($fileContent);
		$newDataArray = Utils\Json::getArrayData($content);

		if (isset($removeOptions)) {
			$savedDataArray = Utils\Util::unsetInArray($savedDataArray, $removeOptions);
			$newDataArray = Utils\Util::unsetInArray($newDataArray, $removeOptions);
		}

		$data = Utils\Util::merge($savedDataArray, $newDataArray);
		if ($isJSON) {
			$data = Utils\Json::encode($data, JSON_PRETTY_PRINT);
		}

		if ($isReturn) {
			return $data;
		}

		return $this->putContents($path, $data);
	}

	/**
	 * Merge PHP content and save it to a file
	 *
	 * @param string | array $path
	 * @param string $content JSON string
	 * @param string | array $removeOptions - List of unset keys from content
	 * @return bool
	 */
	public function mergeContentsPHP($path, $content, $removeOptions = null)
	{
		$data = $this->mergeContents($path, $content, false, $removeOptions, true);

		return $this->putContentsPHP($path, $data);
	}

	/**
	 * Append the content to the end of the file
	 *
	 * @param string | array $path
	 * @param mixed $data
	 *
	 * @return bool
	 */
	public function appendContents($path, $data)
	{
		return $this->putContents($path, $data, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Unset some element of content data
	 *
	 * @param  string | array $path
	 * @param  array | string $unsets [description]
	 * @return bool
	 */
	public function unsetContents($path, $unsets, $isJSON = true)
	{
		$currentData = $this->getContents($path);
		if ($currentData == false) {
			$GLOBALS['log']->notice('FileManager::unsetContents: File ['.$this->concatPaths($path).'] does not exist.');
			return false;
		}

		$currentDataArray = Utils\Json::getArrayData($currentData);

		$unsettedData = Utils\Util::unsetInArray($currentDataArray, $unsets);

		if ($isJSON) {
			return $this->putContentsJson($path, $unsettedData);
		}

		return $this->putContents($path, $unsettedData);
	}


	/**
	 * Concat paths
	 * @param  string | array  $paths Ex. array('pathPart1', 'pathPart2', 'pathPart3')
	 * @return string
	 */
	protected function concatPaths($paths)
	{
		if (is_string($paths)) {
			return $paths;
		}

		$fullPath = '';
		foreach ($paths as $path) {
			$fullPath = Utils\Util::concatPath($fullPath, $path);
		}

		return $fullPath;
	}

	/**
	 * Create a new dir
	 *
	 * @param  string | array $path
	 * @param  int $permission - ex. 0755
	 * @return bool
	 */
	public function mkdir($path, $permission = null)
	{
		$fullPath = $this->concatPaths($path);

		if (file_exists($fullPath) && is_dir($path)) {
			return true;
		}

		if (!isset($permission)) {
			$defaultPermissions = $this->getPermissionUtils()->getDefaultPermissions();
			$permission = (string) $defaultPermissions['dir'];
			$permission = base_convert($permission, 8, 10);
		}

		try {
			$result = mkdir($fullPath, $permission, true);
		} catch (\Exception $e) {
			$GLOBALS['log']->critical('Permission denied: unable to create the folder on the server - '.$fullPath);
		}

		return isset($result) ? $result : false;
	}

	/**
	 * Copy files from one direcoty to another
	 * Ex. $sourcePath = 'data/uploads/extensions/file.json', $destPath = 'data/uploads/backup', result will be data/uploads/backup/data/uploads/backup/file.json.
	 *
	 * @param  string  $sourcePath
	 * @param  string  $destPath
	 * @param  boolean $recursively
	 * @param  array $fileList - list of files that should be copied
	 * @param  boolean $copyOnlyFiles - copy only files, instead of full path with directories, Ex. $sourcePath = 'data/uploads/extensions/file.json', $destPath = 'data/uploads/backup', result will be 'data/uploads/backup/file.json'
	 * @return boolen
	 */
	public function copy($sourcePath, $destPath, $recursively = false, array $fileList = null, $copyOnlyFiles = false)
	{
		$sourcePath = $this->concatPaths($sourcePath);
		$destPath = $this->concatPaths($destPath);

		if (isset($fileList)) {
			if (!empty($sourcePath)) {
				foreach ($fileList as &$fileName) {
					$fileName = $this->concatPaths(array($sourcePath, $fileName));
				}
			}
		} else {
			$fileList = is_file($sourcePath) ? (array) $sourcePath : $this->getFileList($sourcePath, $recursively, '', 'file', true);
		}

		/** Check permission before copying */
		$permissionDeniedList = array();
		foreach ($fileList as $file) {

			if ($copyOnlyFiles) {
				$file = pathinfo($file, PATHINFO_BASENAME);
			}

			$destFile = $this->concatPaths(array($destPath, $file));

			$isFileExists = file_exists($destFile);

			if ($this->checkCreateFile($destFile) === false) {
				$permissionDeniedList[] = $destFile;
			} else if (!$isFileExists) {
				$this->removeFile($destFile);
			}
		}
		/** END */

		if (!empty($permissionDeniedList)) {
			$betterPermissionList = $this->getPermissionUtils()->arrangePermissionList($permissionDeniedList);
			throw new Error("Permission denied in <br>". implode(", <br>", $betterPermissionList));
		}

		$res = true;
		foreach ($fileList as $file) {

			if ($copyOnlyFiles) {
				$file = pathinfo($file, PATHINFO_BASENAME);
			}

			$sourceFile = is_file($sourcePath) ? $sourcePath : $this->concatPaths(array($sourcePath, $file));
			$destFile = $this->concatPaths(array($destPath, $file));

			$res &= copy($sourceFile, $destFile);
		}

		return $res;
	}

	/**
	 * Create a new file if not exists with all folders in the path.
	 *
	 * @param string $filePath
	 * @return string
	 */
	protected function checkCreateFile($filePath)
	{
		$defaultPermissions = $this->getPermissionUtils()->getDefaultPermissions();

		if (file_exists($filePath)) {

			if (!in_array($this->getPermissionUtils()->getCurrentPermission($filePath), array($defaultPermissions['file'], $defaultPermissions['dir']))) {
				return $this->getPermissionUtils()->setDefaultPermissions($filePath, true);
			}
			return true;
		}

		$pathParts = pathinfo($filePath);
		if (!file_exists($pathParts['dirname'])) {
			$dirPermission = $defaultPermissions['dir'];
			$dirPermission = is_string($dirPermission) ? base_convert($dirPermission,8,10) : $dirPermission;

			if (!mkdir($pathParts['dirname'], $dirPermission, true)) {
				throw new Error('Permission denied: unable to create a folder on the server - '.$pathParts['dirname']);
			}
		}

		if (touch($filePath)) {
			return $this->getPermissionUtils()->setDefaultPermissions($filePath, true);
		}

		return false;
	}
	
	/**
	 * Remove file/files by given path
	 *
	 * @param array $filePaths - File paths list
	 * @return bool
	 */
	public function unlink($filePaths)
	{
		return $this->removeFile($filePaths);
	}

	/**
	 * Remove file/files by given path
	 *
	 * @param array $filePaths - File paths list
	 * @param string $dirPath - directory path
	 * @return bool
	 */
	public function removeFile($filePaths, $dirPath = null)
	{
		if (!is_array($filePaths)) {
			$filePaths = (array) $filePaths;
		}

		$result = true;
		foreach ($filePaths as $filePath) {
			if (isset($dirPath)) {
				$filePath = Utils\Util::concatPath($dirPath, $filePath);
			}

			if (file_exists($filePath) && is_file($filePath)) {
				$result &= unlink($filePath);
			}
		}

		return $result;
	}

	/**
	 * Remove all files inside given path
	 *
	 * @param string $dirPath - directory path
	 * @param bool $removeWithDir - if remove with directory
	 *
	 * @return bool
	 */
	public function removeInDir($dirPath, $removeWithDir = false)
	{
		$fileList = $this->getFileList($dirPath, false);

		$result = true;
		if (is_array($fileList)) {
			foreach ($fileList as $file) {
				$fullPath = Utils\Util::concatPath($dirPath, $file);
				if (is_dir($fullPath)) {
					$result &= $this->removeInDir($fullPath, true);
				} else if (file_exists($fullPath)) {
					$result &= unlink($fullPath);
				}
			}
		}

		if ($removeWithDir) {
			if (file_exists($dirPath)) {
				rmdir($dirPath);
			}
		}

		return $result;
	}

	/**
	 * Remove items (files or directories)
	 *
	 * @param  string | array $items
	 * @param  string $dirPath
	 * @return boolean
	 */
	public function remove($items, $dirPath = null)
	{
		if (!is_array($items)) {
			$items = (array) $items;
		}

		$result = true;
		foreach ($items as $item) {
			if (isset($dirPath)) {
				$item = Utils\Util::concatPath($dirPath, $item);
			}

			if (is_dir($item)) {
				$result = $this->removeInDir($item, true);
			} else {
				$result = $this->removeFile($item);
			}
		}

		return $result;
	}

	/**
	 * Get a filename without the file extension
	 *
	 * @param string $filename
	 * @param string $ext - extension, ex. '.json'
	 *
	 * @return array
	 */
	public function getFileName($fileName, $ext='')
	{
		if (empty($ext)) {
			$fileName= substr($fileName, 0, strrpos($fileName, '.', -1));
		}
		else {
			if (substr($ext, 0, 1)!='.') {
				$ext= '.'.$ext;
			}

			if (substr($fileName, -(strlen($ext)))==$ext) {
				$fileName= substr($fileName, 0, -(strlen($ext)));
			}
		}

		$exFileName = explode('/', Utils\Util::toFormat($fileName, '/'));

		return end($exFileName);
	}


	/**
	 * Get a directory name from the path
	 *
	 * @param string $path
	 * @param bool $isFullPath
	 *
	 * @return array
	 */
	public function getDirName($path, $isFullPath = true)
	{
		$pathInfo = pathinfo($path);

		if (!$isFullPath) {
			$pieces = explode('/', $pathInfo['dirname']);

			return $pieces[count($pieces)-1];
		}

		return $pathInfo['dirname'];
	}

	/**
	 * Return content of PHP file
	 *
	 * @param string $varName - name of variable which contains the content
	 * @param array $content
	 *
	 * @return string | false
	 */
	public function getPHPFormat($content)
	{
		if (empty($content)) {
			return false;
		}

		return '<?php

return '.var_export($content, true).';

?>';
	}

}

