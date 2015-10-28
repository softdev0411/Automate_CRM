<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
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

namespace Espo\Hooks\Note;

use Espo\ORM\Entity;

class Notifications extends \Espo\Core\Hooks\Base
{
    protected $notificationService = null;

    public static $order = 14;

    protected function init()
    {
        $this->dependencies[] = 'serviceFactory';
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getMentionedUserIdList($entity)
    {
        $mentionedUserList = array();
        $data = $entity->get('data');
        if (($data instanceof \stdClass) && ($data->mentions instanceof \stdClass)) {
            $mentions = get_object_vars($data->mentions);
            foreach ($mentions as $d) {
                $mentionedUserList[] = $d->id;
            }
        }
        return $mentionedUserList;
    }

    protected function getSubscriberIdList($parentType, $parentId)
    {
        $pdo = $this->getEntityManager()->getPDO();
        $sql = "
            SELECT user_id AS userId
            FROM subscription
            WHERE entity_id = " . $pdo->quote($parentId) . " AND entity_type = " . $pdo->quote($parentType);
        $sth = $pdo->prepare($sql);
        $sth->execute();
        $userIdList = [];
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            if ($this->getUser()->id != $row['userId']) {
                $userIdList[] = $row['userId'];
            }
        }
        return $userIdList;
    }

    public function afterSave(Entity $entity)
    {
        if ($entity->isNew()) {
            $parentType = $entity->get('parentType');
            $parentId = $entity->get('parentId');
            $superParentType = $entity->get('superParentType');
            $superParentTypeId = $entity->get('superParentTypeId');

            $userIdList = [];

            if ($parentType && $parentId) {
				$userIdList = array_merge($userIdList, $this->getSubscriberIdList($parentType, $parentId));
                if ($superParentType && $superParentTypeId) {
                    $userIdList = array_merge($userIdList, $this->getSubscriberIdList($superParentType, $superParentTypeId));
                }
            } else {
                $targetType = $entity->get('targetType');
                if ($targetType === 'users') {
                    $targetUserIdList = $entity->get('usersIds');
                    foreach ($targetUserIdList as $userId) {
                        if ($userId === $this->getUser()->id) continue;
                        $userIdList[] = $userId;
                    }
                } else if ($targetType === 'teams') {
                    $targetTeamIdList = $entity->get('teamsIds');
                    foreach ($targetTeamIdList as $teamId) {
                        $team = $this->getEntityManager()->getEntity('Team', $teamId);
                        if (!$team) continue;
                        $targetUserList = $this->getEntityManager()->getRepository('Team')->findRelated($team, 'users', array(
                            'whereClause' => array(
                                'isActive' => true
                            )
                        ));
                        foreach ($targetUserList as $user) {
                            if ($user->id === $this->getUser()->id) continue;
                            $userIdList[] = $user->id;
                        }
                    }
                }
            }

            $userIdList = array_unique($userIdList);

            if (!empty($userIdList)) {
            	$this->getNotificationService()->notifyAboutNote($userIdList, $entity);
            }
        }
    }

    protected function getNotificationService()
    {
        if (empty($this->notificationService)) {
            $this->notificationService = $this->getServiceFactory()->create('Notification');
        }
        return $this->notificationService;
    }
}

