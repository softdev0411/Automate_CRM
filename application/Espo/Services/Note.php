<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2021 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
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
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Services;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;

use Espo\Core\{
    Acl\Table as AclTable,
};

use Espo\Entities\Note as NoteEntity;

use Espo\ORM\Entity;

use DateTime;
use StdClass;
use Exception;

class Note extends Record
{
    protected $noteNotificationPeriod = '1 hour';

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        $entity->loadAttachments();
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->processFollowAfterCreate($entity);
    }

    protected function processFollowAfterCreate(NoteEntity $entity): void
    {
        $parentType = $entity->get('parentType');
        $parentId = $entity->get('parentId');

        if ($entity->get('type') !== NoteEntity::TYPE_POST || !$parentType || !$parentId) {
            return;
        }

        if (!$this->metadata->get(['scopes', $parentType, 'stream'])) {
            return;
        }

        $preferences = $this->entityManager->getEntity('Preferences', $this->user->getId());

        if (!$preferences) {
            return;
        }

        if (!$preferences->get('followEntityOnStreamPost')) {
            return;
        }

        $parent = $this->entityManager->getEntity($parentType, $parentId);

        if (!$parent || $this->user->isSystem() || $this->user->isApi()) {
            return;
        }

        $this->getStreamService()->followEntity($parent, $this->user->getId());
    }

    /**
     * @param NoteEntity $entity
     * @param StdClass $data
     */
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $parentType = $data->parentType ?? null;
        $parentId = $data->parentId ?? null;

        if ($parentType && $parentId) {
            $parent = $this->entityManager->getEntity($data->parentType, $data->parentId);

            if ($parent && !$this->acl->check($parent, AclTable::ACTION_READ)) {
                throw new Forbidden();
            }
        }

        parent::beforeCreateEntity($entity, $data);

        if ($entity->isPost()) {
            $this->handlePostText($entity);
        }

        $targetType = $entity->getTargetType();

        $entity->clear('isGlobal');

        switch ($targetType) {
            case NoteEntity::TARGET_ALL:

                $entity->clear('usersIds');
                $entity->clear('teamsIds');
                $entity->clear('portalsIds');
                $entity->set('isGlobal', true);

                break;

            case NoteEntity::TARGET_SELF:

                $entity->clear('usersIds');
                $entity->clear('teamsIds');
                $entity->clear('portalsIds');
                $entity->set('usersIds', [$this->user->id]);
                $entity->set('isForSelf', true);

                break;

            case NoteEntity::TARGET_USERS:

                $entity->clear('teamsIds');
                $entity->clear('portalsIds');

                break;

            case NoteEntity::TARGET_TEAMS:

                $entity->clear('usersIds');
                $entity->clear('portalsIds');

                break;

            case NoteEntity::TARGET_PORTALS:

                $entity->clear('usersIds');
                $entity->clear('teamsIds');

                break;
        }
    }

    /**
     * @param NoteEntity $entity
     * @param StdClass $data
     */
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        if ($entity->isPost()) {
            $this->handlePostText($entity);
        }

        $entity->clear('targetType');
        $entity->clear('usersIds');
        $entity->clear('teamsIds');
        $entity->clear('portalsIds');
        $entity->clear('isGlobal');
    }

    protected function handlePostText(Entity $entity)
    {
        $post = $entity->get('post');

        if (empty($post)) {
            return;
        }

        $siteUrl = $this->getConfig()->getSiteUrl();

        $regexp = '/' . preg_quote($siteUrl, '/') .
            '(\/portal|\/portal\/[a-zA-Z0-9]*)?\/#([A-Z][a-zA-Z0-9]*)\/view\/([a-zA-Z0-9]*)/';

        $post = preg_replace($regexp, '[\2/\3](#\2/view/\3)', $post);

        $entity->set('post', $post);
    }

    public function processAssignmentCheck(Entity $entity)
    {
        if (!$entity->isNew()) {
            return;
        }

        $targetType = $entity->getTargetType();

        if (!$targetType) {
            return;
        }

        $userTeamIdList = $this->user->getTeamIdList();

        $userIdList = $entity->getLinkMultipleIdList('users');
        $portalIdList = $entity->getLinkMultipleIdList('portals');
        $teamIdList = $entity->getLinkMultipleIdList('teams');

        $targetUserList = null;

        if ($targetType === NoteEntity::TARGET_USERS) {
            $targetUserList = $this->entityManager
                ->getRDBRepository('User')
                ->select(['id', 'type'])
                ->where([
                    'id' => $userIdList,
                ])
                ->find();
        }

        $hasPortalTargetUser = false;
        $allTargetUsersArePortal = true;

        foreach ($targetUserList as $user) {
            if (!$user->isPortal()) {
                $allTargetUsersArePortal = false;
            }

            if ($user->isPortal()) {
                $hasPortalTargetUser = true;
            }
        }

        $assignmentPermission = $this->acl->get('assignment');

        if ($assignmentPermission === AclTable::LEVEL_NO) {
            if (
                $targetType !== NoteEntity::TARGET_SELF &&
                $targetType !== NoteEntity::TARGET_PORTALS &&
                !(
                    $targetType === NoteEntity::TARGET_USERS &&
                    count($userIdList) === 1 &&
                    $userIdList[0] === $this->user->getId()
                ) &&
                !(
                    $targetType === NoteEntity::TARGET_USERS && $allTargetUsersArePortal
                )
            ) {
                throw new Forbidden('Not permitted to post to anybody except self.');
            }
        }

        if ($targetType === NoteEntity::TARGET_TEAMS) {
            if (empty($teamIdList)) {
                throw new BadRequest("No team IDS.");
            }
        }

        if ($targetType === NoteEntity::TARGET_USERS) {
            if (empty($userIdList)) {
                throw new BadRequest("No user IDs.");
            }
        }

        if ($targetType === NoteEntity::TARGET_PORTALS) {
            if (empty($portalIdList)) {
                throw new BadRequest("No portal IDs.");
            }

            if ($this->acl->get('portal') !== AclTable::LEVEL_YES) {
                throw new Forbidden('Not permitted to post to portal users.');
            }
        }

        if ($targetType === NoteEntity::TARGET_USERS && $this->acl->get('portal') !== AclTable::LEVEL_YES) {
            if ($hasPortalTargetUser) {
                throw new Forbidden('Not permitted to post to portal users.');
            }
        }

        if ($assignmentPermission === AclTable::LEVEL_TEAM) {
            if ($targetType === NoteEntity::TARGET_ALL) {
                throw new Forbidden('Not permitted to post to all.');
            }
        }

        if (
            $assignmentPermission === AclTable::LEVEL_TEAM &&
            $targetType === NoteEntity::TARGET_TEAMS
        ) {
            if (empty($userTeamIdList)) {
                throw new Forbidden('Not permitted to post to foreign teams.');
            }

            foreach ($teamIdList as $teamId) {
                if (!in_array($teamId, $userTeamIdList)) {
                    throw new Forbidden("Not permitted to post to foreign teams.");
                }
            }
        }

        if (
            $assignmentPermission === AclTable::LEVEL_TEAM &&
            $targetType === NoteEntity::TARGET_USERS
        ) {
            if (empty($userTeamIdList)) {
                throw new Forbidden('Not permitted to post to users from foreign teams.');
            }

            foreach ($targetUserList as $user) {
                if ($user->getId() === $this->user->getId()) {
                    continue;
                }

                if ($user->isPortal()) {
                    continue;
                }

                $inTeam = $this->entityManager
                        ->getRepository('User')
                        ->checkBelongsToAnyOfTeams($user->getId(), $userTeamIdList);

                if (!$inTeam) {
                    throw new Forbidden('Not permitted to post to users from foreign teams.');
                }
            }
        }
    }

    public function link(string $id, string $link, string $foreignId) : void
    {
        if ($link === 'teams' || $link === 'users') {
            throw new Forbidden();
        }

        parent::link($id, $link, $foreignId);
    }

    public function unlink(string $id, string $link, string $foreignId) : void
    {
        if ($link === 'teams' || $link === 'users') {
            throw new Forbidden();
        }

        parent::unlink($id, $link, $foreignId);
    }

    public function processNoteAclJob(StdClass $data): void
    {
        $targetType = $data->targetType;
        $targetId = $data->targetId;

        if ($targetType && $targetId && $this->entityManager->hasRepository($targetType)) {
            $entity = $this->entityManager->getEntity($targetType, $targetId);

            if ($entity) {
                $this->processNoteAcl($entity, true);
            }
        }
    }

    /**
     * Changes users and teams of notes related to an entity according users and teams of the entity.
     */
    public function processNoteAcl(Entity $entity, bool $forceProcessNoteNotifications = false): void
    {
        $entityType = $entity->getEntityType();

        if (in_array($entityType, ['Note', 'User', 'Team', 'Role', 'Portal', 'PortalRole'])) {
            return;
        }

        if (!$this->getMetadata()->get(['scopes', $entityType, 'acl'])) {
            return;
        }

        if (!$this->getMetadata()->get(['scopes', $entityType, 'object'])) {
            return;
        }

        $usersAttributeIsChanged = false;
        $teamsAttributeIsChanged = false;

        $ownerUserField = $this->getAclManager()->getReadOwnerUserField($entityType);

        /* @var $defs \Espo\ORM\Defs\EntityDefs */
        $defs = $this->entityManager->getDefs()->getEntity($entity->getEntityType());

        $userIdList = [];

        if ($ownerUserField) {
            if (!$defs->hasField($ownerUserField)) {
                throw new Error("Non-existing read-owner user field.");
            }

            $fieldDefs = $defs->getField($ownerUserField);

            if ($fieldDefs->getType() === 'linkMultiple') {
                $ownerUserIdAttribute = $ownerUserField . 'Ids';
            }
            else if ($fieldDefs->getType() === 'link') {
                $ownerUserIdAttribute = $ownerUserField . 'Id';
            }
            else {
                throw new Error("Bad read-owner user field type.");
            }

            if ($entity->isAttributeChanged($ownerUserIdAttribute)) {
                $usersAttributeIsChanged = true;
            }

            if ($usersAttributeIsChanged || $forceProcessNoteNotifications) {
                if ($fieldDefs->getType() === 'linkMultiple') {
                    $userIdList = $entity->getLinkMultipleIdList($ownerUserField);
                }
                else {
                    $userId = $entity->get($ownerUserIdAttribute);

                    $userIdList = $userId ? [$userId] : [];
                }
            }
        }

        if ($entity->hasLinkMultipleField('teams')) {
            if ($entity->isAttributeChanged('teamsIds')) {
                $teamsAttributeIsChanged = true;
            }

            if ($teamsAttributeIsChanged || $forceProcessNoteNotifications) {
                $teamIdList = $entity->getLinkMultipleIdList('teams');
            }
        }

        if (!$usersAttributeIsChanged && !$teamsAttributeIsChanged && !$forceProcessNoteNotifications) {
            return;
        }

        $noteList = $this->entityManager
            ->getRepository('Note')
            ->where([
                'OR' => [
                    [
                        'relatedId' => $entity->getId(),
                        'relatedType' => $entityType,
                    ],
                    [
                        'parentId' => $entity->getId(),
                        'parentType' => $entityType,
                        'superParentId!=' => null,
                        'relatedId' => null,
                    ]
                ]
            ])
            ->select([
                'id',
                'parentType',
                'parentId',
                'superParentType',
                'superParentId',
                'isInternal',
                'relatedType',
                'relatedId',
                'createdAt',
            ])
            ->find();

        $noteOptions = [];

        if (!empty($forceProcessNoteNotifications)) {
            $noteOptions['forceProcessNotifications'] = true;
        }

        $period = '-' . $this->getConfig()->get('noteNotificationPeriod', $this->noteNotificationPeriod);

        $threshold = (new DateTime())->modify($period);


        foreach ($noteList as $note) {
            $this->processNoteAclItem($entity, $note, [
                'teamsAttributeIsChanged' => $teamsAttributeIsChanged,
                'usersAttributeIsChanged' => $usersAttributeIsChanged,
                'forceProcessNoteNotifications' => $forceProcessNoteNotifications,
                'teamIdList' => $teamIdList,
                'userIdList' => $userIdList,
                'threshold' => $threshold,
            ]);
        }
    }

    protected function processNoteAclItem(Entity $entity, NoteEntity $note, array $params): void
    {
        $teamsAttributeIsChanged = $params['teamsAttributeIsChanged'];
        $usersAttributeIsChanged = $params['usersAttributeIsChanged'];
        $forceProcessNoteNotifications = $params['forceProcessNoteNotifications'];

        $teamIdList = $params['teamIdList'];
        $userIdList = $params['userIdList'];

        $threshold = $params['threshold'];

        $noteOptions = [
            'forceProcessNotifications' => $forceProcessNoteNotifications,
        ];

        if (!$entity->isNew() && $note->get('createdAt')) {
            try {
                $createdAtDt = new DateTime($note->get('createdAt'));
            }
            catch (Exception $e) {
                return;
            }

            if ($createdAtDt->getTimestamp() < $threshold->getTimestamp()) {
                return;
            }
        }

        if ($teamsAttributeIsChanged || $forceProcessNoteNotifications) {
            $note->set('teamsIds', $teamIdList);
        }

        if ($usersAttributeIsChanged || $forceProcessNoteNotifications) {
            $note->set('usersIds', $userIdList);
        }

        $this->entityManager->saveEntity($note, $noteOptions);
    }
}
