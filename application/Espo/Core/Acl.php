<?php

namespace Espo\Core;

class Acl
{
	private $data = array();

	private $cacheFile;

	private $actionList = array('read', 'edit', 'delete');

	private $levelList = array('all', 'team', 'own', 'no');

	public function __construct(\Espo\Entities\User $user)
	{
		$this->user = $user;
		$this->user->loadLinkMultipleField('teams');

		$this->cacheFile = 'data/cache/application/acl/' . $user->id;

		if (file_exists($this->cacheFile)) {
			$cached = include $this->cacheFile;
		} else {
			$this->load();
			$this->initSolid();
			$this->buildCache();
		}
	}
	
	public function checkScope($scope, $action = null, $isOwner = null, $inTeam = null)
	{
		if (array_key_exists($scope, $this->data)) {			
			if ($this->data[$scope] === false) {
				return false;
			}
			if ($this->data[$scope] === true) {
				return true;
			}
			if (!is_null($action)) {		
				if (array_key_exists($action, $this->data[$scope])) {
					$value = $this->data[$scope][$action];
			
					if ($value === 'all' || $value === true) {
						return true;					
					}
			
					if (!$value || $value === 'no') {
						return false;					
					}					
				
					if (is_null($isOwner)) {
						return true;
					}
				
					if ($isOwner) {
						if ($value === 'own' || $value === 'team') {
							return true;
						}
					}
			
					if ($inTeam) {
						if ($value === 'team') {
							return true;
						}
					}
			
					return false;
				}
			}
			return false;
		}
		return true;		
	}
	
	public function toArray()
	{
		return $this->data;
	}

	public function check($subject, $action = null, $isOwner = null, $inTeam = null)
	{	
		if ($this->user->isAdmin()) {
			return true;
		}
		if (is_string($subject)) {
			return $this->checkScope($subject, $action, $isOwner, $inTeam);
		} else {
			$entity = $subject;
			$entityName = $entity->getEntityName();			
			return $this->checkScope($entityName, $action, $this->checkIsOwner($entity), $this->checkInTeam($entity));
		}
	}
			
	public function checkReadOnlyTeam($scope)
	{
		if (isset($this->data[$scope]) && isset($this->data[$scope]['read'])) {
			return $this->data[$scope]['read'] === 'team';
		}
		return false;
	}
	
	public function checkReadOnlyOwn($scope)
	{
		if ($this->user->isAdmin()) {
			return false;
		}
		if (isset($this->data[$scope]) && isset($this->data[$scope]['read'])) {
			return $this->data[$scope]['read'] === 'own';
		}
		return false;
	}
	
	public function checkIsOwner($entity)
	{
		if ($this->user->isAdmin()) {
			return false;
		}
		$userId = $this->user->id;
		if ($userId === $entity->get('assignedUserId') || $userId === $entity->get('createdById')) {
			return true;
		}
		return false;
	}
	
	public function checkInTeam($entity)
	{
		$userTeamIds = $this->user->get('teamsIds');
		$teamIds = $entity->get('teamsIds');
		
		foreach ($userTeamIds as $id) {
			if (in_array($id, $teamIds)) {
				return true;
			}
		}		
		return false;
	}

	private function load()
	{
		$aclTables = array();

		$userRoles = $this->user->get('roles');
		
		foreach ($userRoles as $role) {
			$aclTables[] = json_decode($role->get('data'));
		}

		$teams = $this->user->get('teams');
		foreach ($teams as $team) {
			$teamRoles = $team->get('roles');
			foreach ($teamRoles as $role) {
				$aclTables[] = json_decode($role->get('data'));
			}
		}

		$this->data = $this->merge($aclTables);
	}
	
	private function initSolid()
	{
		$this->data['User'] = array(
			'read' => 'all',
			'edit' => 'no',
			'delete' => 'no',					
		);
		$this->data['Team'] = array(
			'read' => 'all',
			'edit' => 'no',
			'delete' => 'no',					
		);
		$this->data['Role'] = false;
	}

	private function merge($tables)
	{
		$data = array();
		foreach ($tables as $table) {
			foreach ($table as $scope => $row) {
				if ($row == false) {
					if (!isset($data[$scope])) {
						$data[$scope] = false;
					}
				} else {
					if (!isset($data[$scope])) {
						$data[$scope] = array();
					}
					if ($data[$scope] == false) {
						$data[$scope] = array();
					}
					foreach ($row as $action => $level) {
						if (!isset($data[$scope][$action])) {
							$data[$scope][$action] = $level;
						} else {
							if (array_search($data[$scope][$action], $this->levelList) > array_search($level, $this->levelList)) {
								$data[$scope][$action] = $level;
							}
						}
					}
				}
			}
		}
		return $data;
	}

	private function buildCache()
	{
		$contents = '<' . '?'. 'php return ' .  var_export($this->data, true)  . ';';
		file_put_contents($this->cacheFile, $contents);
	}
}

