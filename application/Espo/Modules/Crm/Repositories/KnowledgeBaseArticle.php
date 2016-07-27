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
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\Crm\Repositories;

use Espo\ORM\Entity;

class KnowledgeBaseArticle extends \Espo\Core\ORM\Repositories\RDB
{

    protected function afterRelateCases(Entity $entity, $foreign)
    {
        $case = null;
        if ($foreign instanceof Entity) {
            $case = $foreign;
        } else if (is_string($foreign)) {
            $case = $this->getEntityManager()->getEntity('Case', $foreign);
        }
        if (!$case) return;

        $n = $this->getEntityManager()->getRepository('Note')->where(array(
            'type' => 'Relate',
            'parentId' => $case->id,
            'parentType' => 'Case',
            'relatedId' => $entity->id,
            'relatedType' => $entity->getEntityType()
        ))->findOne();
        if ($n) {
            return;
        }

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set(array(
            'type' => 'Relate',
            'parentId' => $case->id,
            'parentType' => 'Case',
            'relatedId' => $entity->id,
            'relatedType' => $entity->getEntityType()
        ));
        $this->getEntityManager()->saveEntity($note);
    }

    protected function afterUnrelateCases(Entity $entity, $foreign)
    {
        $case = null;
        if ($foreign instanceof Entity) {
            $case = $foreign;
        } else if (is_string($foreign)) {
            $case = $this->getEntityManager()->getEntity('Case', $foreign);
        }
        if (!$case) return;

        $note = $this->getEntityManager()->getRepository('Note')->where(array(
            'type' => 'Relate',
            'parentId' => $case->id,
            'parentType' => 'Case',
            'relatedId' => $entity->id,
            'relatedType' => $entity->getEntityType()
        ))->findOne();
        if (!$note) return;

        $this->getEntityManager()->removeEntity($note);
    }

    protected function beforeSave(Entity $entity, array $options = array())
    {
        parent::beforeSave($entity, $options);
        $order = $entity->get('order');
        if (is_null($order)) {
            $order = $this->min('order');
            if (!$order) {
                $order = 9999;
            }
            $order--;
            $entity->set('order', $order);
        }

    }
}
