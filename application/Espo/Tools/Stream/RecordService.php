<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM – Open Source CRM application.
 * Copyright (C) 2014-2024 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Tools\Stream;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Select\SearchParams;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\Entities\User;
use Espo\Entities\Note;
use Espo\Entities\Email;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\ORM\Query\SelectBuilder;
use Espo\Tools\Stream\RecordService\Helper;
use Espo\Tools\Stream\RecordService\QueryHelper;
use stdClass;

class RecordService
{
    public function __construct(
        private EntityManager $entityManager,
        private User $user,
        private Acl $acl,
        private NoteAccessControl $noteAccessControl,
        private Helper $helper,
        private QueryHelper $queryHelper,
        private Metadata $metadata,
        private FieldUtil $fieldUtil
    ) {}

    /**
     * Find a record stream records.
     *
     * @return RecordCollection<Note>
     * @throws Forbidden
     * @throws BadRequest
     * @throws NotFound
     */
    public function find(string $scope, string $id, SearchParams $searchParams): RecordCollection
    {
        if ($scope === User::ENTITY_TYPE) {
            throw new Forbidden();
        }

        $entity = $this->entityManager->getEntityById($scope, $id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntity($entity, Table::ACTION_STREAM)) {
            throw new Forbidden();
        }

        return $this->findInternal($scope, $id, $searchParams);
    }

    /**
     * Find a record stream records.
     *
     * @return RecordCollection<Note>
     * @throws Forbidden
     * @throws BadRequest
     * @throws NotFound
     */
    public function findUpdates(string $scope, string $id, SearchParams $searchParams): RecordCollection
    {
        if ($this->user->isPortal()) {
            throw new Forbidden();
        }

        if ($this->acl->getPermissionLevel('audit') !== Table::LEVEL_YES) {
            throw new Forbidden();
        }

        $entity = $this->entityManager->getEntityById($scope, $id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($entity)) {
            throw new Forbidden();
        }

        if ($entity instanceof User && !$this->user->isAdmin()) {
            throw new Forbidden();
        }

        $searchParams = $searchParams->withPrimaryFilter('updates');

        return $this->findInternal($scope, $id, $searchParams);
    }

    /**
     * Find a record stream records.
     *
     * @return RecordCollection<Note>
     * @throws Forbidden
     * @throws BadRequest
     */
    private function findInternal(string $scope, string $id, SearchParams $searchParams): RecordCollection
    {
        $builder = $this->queryHelper->buildBaseQueryBuilder($searchParams);

        $where = $this->user->isPortal() ?
            [
                'parentType' => $scope,
                'parentId' => $id,
                'isInternal' => false,
            ] :
            [
                'OR' => [
                    [
                        'parentType' => $scope,
                        'parentId' => $id,
                    ],
                    [
                        'superParentType' => $scope,
                        'superParentId' => $id,
                    ],
                ]
            ];

        $this->applyPortalAccess($builder, $where);
        $this->applyAccess($builder, $id, $scope, $where);
        $this->applyIgnore($where);
        $this->applyStatusIgnore($scope, $where);

        $builder->where($where);

        $offset = $searchParams->getOffset();
        $maxSize = $searchParams->getMaxSize();

        $countBuilder = clone $builder;

        $builder
            ->limit($offset ?? 0, $maxSize)
            ->order('number', 'DESC');

        $collection = $this->entityManager
            ->getRDBRepositoryByClass(Note::class)
            ->clone($builder->build())
            ->find();

        foreach ($collection as $e) {
            $this->prepareNote($e, $scope, $id);
        }

        $count = $this->entityManager
            ->getRDBRepositoryByClass(Note::class)
            ->clone($countBuilder->build())
            ->count();

        return RecordCollection::create($collection, $count);
    }

    /**
     * @param array<string|int, mixed> $where
     */
    private function applyAccess(
        SelectBuilder $builder,
        string $id,
        string $scope,
        array &$where
    ): void {

        if ($this->user->isPortal()) {
            return;
        }

        $onlyTeamEntityTypeList = $this->helper->getOnlyTeamEntityTypeList($this->user);
        $onlyOwnEntityTypeList = $this->helper->getOnlyOwnEntityTypeList($this->user);

        if (
            !count($onlyTeamEntityTypeList) &&
            !count($onlyOwnEntityTypeList)
        ) {
            return;
        }

        $builder
            ->distinct()
            ->leftJoin('teams')
            ->leftJoin('users');

        $where[] = [
            'OR' => [
                'OR' => [
                    [
                        'relatedId!=' => null,
                        'relatedType!=' => array_merge(
                            $onlyTeamEntityTypeList,
                            $onlyOwnEntityTypeList,
                        ),
                    ],
                    [
                        'relatedId=' => null,
                        'superParentId' => $id,
                        'superParentType' => $scope,
                        'parentId!=' => null,
                        'parentType!=' => array_merge(
                            $onlyTeamEntityTypeList,
                            $onlyOwnEntityTypeList,
                        ),
                    ],
                    [
                        'relatedId=' => null,
                        'parentType=' => $scope,
                        'parentId=' => $id,
                    ]
                ],
                [
                    'OR' => [
                        [
                            'relatedId!=' => null,
                            'relatedType=' => $onlyTeamEntityTypeList,
                        ],
                        [
                            'relatedId=' => null,
                            'parentType=' => $onlyTeamEntityTypeList,
                        ]
                    ],
                    [
                        'OR' => [
                            'teamsMiddle.teamId' => $this->user->getTeamIdList(),
                            'usersMiddle.userId' => $this->user->getId(),
                        ]
                    ]
                ],
                [
                    'OR' => [
                        [
                            'relatedId!=' => null,
                            'relatedType=' => $onlyOwnEntityTypeList,
                        ],
                        [
                            'relatedId=' => null,
                            'parentType=' => $onlyOwnEntityTypeList,
                        ]
                    ],
                    'usersMiddle.userId' => $this->user->getId(),
                ]
            ]
        ];
    }

    /**
     * @param array<string|int, mixed> $where
     */
    private function applyIgnore(array &$where): void
    {
        $ignoreScopeList = $this->helper->getIgnoreScopeList($this->user, true);
        $ignoreRelatedScopeList = $this->helper->getIgnoreScopeList($this->user);

        if ($ignoreRelatedScopeList === []) {
            return;
        }

        $where[] = [
            'OR' => [
                'relatedType' => null,
                'relatedType!=' => $ignoreRelatedScopeList,
            ]
        ];

        $where[] = [
            'OR' => [
                'parentType' => null,
                'parentType!=' => $ignoreScopeList,
            ]
        ];

        if (!in_array(Email::ENTITY_TYPE, $ignoreRelatedScopeList)) {
            return;
        }

        $where[] = [
            'type!=' => [
                Note::TYPE_EMAIL_RECEIVED,
                Note::TYPE_EMAIL_SENT,
            ]
        ];
    }

    /**
     * @param array<string|int, mixed> $where
     */
    private function applyPortalAccess(SelectBuilder $builder, array &$where): void
    {
        if (!$this->user->isPortal()) {
            return;
        }

        $notAllEntityTypeList = $this->helper->getNotAllEntityTypeList($this->user);

        $orGroup = [
            [
                'relatedId' => null,
            ],
            [
                'relatedId!=' => null,
                'relatedType!=' => $notAllEntityTypeList,
            ],
        ];

        if ($this->acl->check(Email::ENTITY_TYPE, Table::ACTION_READ)) {
            $builder->leftJoin(
                'noteUser',
                'noteUser',
                [
                    'noteUser.noteId=:' => 'id',
                    'noteUser.deleted' => false,
                    'note.relatedType' => Email::ENTITY_TYPE,
                ]
            );

            $orGroup[] = [
                'relatedId!=' => null,
                'relatedType' => Email::ENTITY_TYPE,
                'noteUser.userId' => $this->user->getId(),
            ];
        }

        $where[] = [
            'OR' => $orGroup,
        ];
    }

    /**
     * @param array<string|int, mixed> $where
     */
    private function applyStatusIgnore(string $scope, array &$where): void
    {
        $field = $this->metadata->get("scopes.$scope.statusField");

        if (!$field) {
            return;
        }

        if ($this->acl->checkField($scope, $field)) {
            return;
        }

        $where[] = ['type!=' => Note::TYPE_STATUS];
    }

    private function prepareNote(Note $note, string $scope, string $id): void
    {
        if (
            $note->getType() === Note::TYPE_POST ||
            $note->getType() === Note::TYPE_EMAIL_RECEIVED
        ) {
            $note->loadAttachments();
        }

        if (
            $note->getParentId() && $note->getParentType() &&
            ($note->getParentId() !== $id || $note->getParentType() !== $scope)
        ) {
            $note->loadParentNameField('parent');
        }

        if ($note->getRelatedId() && $note->getRelatedType()) {
            $note->loadParentNameField('related');
        }

        $this->noteAccessControl->apply($note, $this->user);

        if ($note->getType() === Note::TYPE_UPDATE) {
            $this->prepareNoteUpdate($note);
        }
    }

    private function prepareNoteUpdate(Note $note): void
    {
        $data = $note->getData();

        /** @var ?string[] $fieldList */
        $fieldList = $data->fields ?? null;
        $attributes = $data->attributes ?? null;

        if (!$attributes instanceof stdClass) {
            return;
        }

        $was = $attributes->was ?? null;

        if (!$was instanceof stdClass) {
            return;
        }

        if (!is_array($fieldList)) {
            return;
        }

        foreach ($fieldList as $field) {
            if ($this->loadNoteUpdateWasForField($note, $field, $was)) {
                $note->setData($data);
            }
        }
    }

    private function loadNoteUpdateWasForField(Note $note, string $field, stdClass $was): bool
    {
        if (!$note->getParentType() || !$note->getParentId()) {
            return false;
        }

        $type = $this->fieldUtil->getFieldType($note->getParentType(), $field);

        if ($type === FieldType::LINK_MULTIPLE) {
            $this->loadNoteUpdateWasForFieldLinkMultiple($note, $field, $was);

            return true;
        }

        return false;
    }

    private function loadNoteUpdateWasForFieldLinkMultiple(Note $note, string $field, stdClass $was): void
    {
        /** @var ?string[] $ids */
        $ids = $was->{$field . 'Ids'} ?? null;

        $names = (object) [];

        if (!is_array($ids)) {
            return;
        }

        $entityType = $note->getParentType();

        if (!$entityType) {
            return;
        }

        $foreignEntityType = $this->entityManager
            ->getDefs()
            ->getEntity($entityType)
            ->tryGetRelation($field)
            ?->tryGetForeignEntityType();

        if (!$foreignEntityType) {
            return;
        }

        $collection = $this->entityManager
            ->getRDBRepository($foreignEntityType)
            ->select(['id', 'name'])
            ->where(['id' => $ids])
            ->find();

        foreach ($collection as $entity) {
            $names->{$entity->getId()} = $entity->get('name');
        }

        $was->{$field . 'Names'} = $names;
    }
}
