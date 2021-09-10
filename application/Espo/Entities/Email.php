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

namespace Espo\Entities;

use Espo\Core\Utils\Util;
use Espo\Core\ORM\Entity;
use Espo\Core\Field\DateTime;

use Espo\Entities\Attachment;
use Espo\Services\Email as EmailService;

class Email extends Entity
{
    public const ENTITY_TYPE = 'Email';

    public const STATUS_BEING_IMPORTED = 'Being Imported';

    public const STATUS_ARCHIVED = 'Archived';

    public const STATUS_SENT = 'Sent';

    protected function _getSubject()
    {
        return $this->get('name');
    }

    protected function _setSubject($value)
    {
        $this->set('name', $value);
    }

    protected function _hasSubject()
    {
        return $this->has('name');
    }

    protected function _hasFromName()
    {
        return $this->has('fromString');
    }

    protected function _hasFromAddress()
    {
        return $this->has('fromString');
    }

    protected function _hasReplyToName()
    {
        return $this->has('replyToString');
    }

    protected function _hasReplyToAddress()
    {
        return $this->has('replyToString');
    }

    protected function _getFromName()
    {
        if (!$this->has('fromString')) {
            return null;
        }

        $string = EmailService::parseFromName($this->get('fromString'));

        if ($string === '') {
            return null;
        }

        return $string;
    }

    protected function _getFromAddress()
    {
        if (!$this->has('fromString')) {
            return null;
        }

        return EmailService::parseFromAddress($this->get('fromString'));
    }

    protected function _getReplyToName()
    {
        if (!$this->has('replyToString')) {
            return null;
        }

        $string = $this->get('replyToString');

        if (!$string) {
            return null;
        }

        return EmailService::parseFromName(
            trim(explode(';', $string)[0])
        );
    }

    protected function _getReplyToAddress()
    {
        if (!$this->has('replyToString')) {
            return null;
        }

        $string = $this->get('replyToString');

        if (!$string) {
            return null;
        }

        return EmailService::parseFromAddress(
            trim(explode(';', $string)[0])
        );
    }

    protected function _setIsRead($value)
    {
        $this->setInContainer('isRead', $value !== false);

        if ($value === true || $value === false) {
            $this->setInContainer('isUsers', true);

            return;
        }

        $this->setInContainer('isUsers', false);
    }

    public function isManuallyArchived(): bool
    {
        return $this->get('status') === 'Archived' && $this->get('createdById') !== 'system';
    }

    public function addAttachment(Attachment $attachment): void
    {
        if (!$this->id) {
            return;
        }

        $attachment->set('parentId', $this->id);
        $attachment->set('parentType', 'Email');

        $this->entityManager->saveEntity($attachment);
    }

    protected function _getBodyPlain()
    {
        return $this->getBodyPlain();
    }

    public function hasBodyPlain(): bool
    {
        return $this->hasInContainer('bodyPlain') && $this->getFromContainer('bodyPlain');
    }

    public function getBodyPlain(): ?string
    {
        if ($this->getFromContainer('bodyPlain')) {
            return $this->getFromContainer('bodyPlain');
        }

        $body = $this->get('body') ?? '';

        $breaks = ["<br />", "<br>", "<br/>", "<br />", "&lt;br /&gt;", "&lt;br/&gt;", "&lt;br&gt;"];

        $body = str_ireplace($breaks, "\r\n", $body);
        $body = strip_tags($body);

        $reList = [
            '&(quot|#34);',
            '&(amp|#38);',
            '&(lt|#60);',
            '&(gt|#62);',
            '&(nbsp|#160);',
            '&(iexcl|#161);',
            '&(cent|#162);',
            '&(pound|#163);',
            '&(copy|#169);',
            '&(reg|#174);',
        ];

        $replaceList = [
            '',
            '&',
            '<',
            '>',
            ' ',
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174),
        ];

        foreach ($reList as $i => $re) {
            $body = mb_ereg_replace($re, $replaceList[$i], $body, 'i');
        }

        return $body;
    }

    public function getBodyPlainForSending(): string
    {
        return $this->getBodyPlain() ?? '';
    }

    public function getBodyForSending(): string
    {
        $body = $this->get('body') ?? '';

        if (!empty($body)) {
            $attachmentList = $this->getInlineAttachments();

            foreach ($attachmentList as $attachment) {
                $id = $attachment->getId();

                $body = str_replace(
                    "\"?entryPoint=attachment&amp;id={$id}\"",
                    "\"cid:{$id}\"",
                    $body
                );
            }
        }

        return str_replace(
            "<table class=\"table table-bordered\">",
            "<table class=\"table table-bordered\" width=\"100%\">",
            $body
        );
    }

    /**
     * @return Attachment[]
     */
    public function getInlineAttachments(): array
    {
        $idList = [];

        $body = $this->get('body');

        if (empty($body)) {
            return [];
        }

        $matches = [];

        if (!preg_match_all("/\?entryPoint=attachment&amp;id=([^&=\"']+)/", $body, $matches)) {
            return [];
        }

        if (empty($matches[1]) || !is_array($matches[1])) {
            return [];
        }

        $attachmentList = [];

        foreach ($matches[1] as $id) {
            if (in_array($id, $idList)) {
                continue;
            }

            $idList[] = $id;

            $attachment = $this->entityManager->getEntity('Attachment', $id);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        return $attachmentList;
    }

    public function getDateSent(): ?DateTime
    {
        return $this->getValueObject('dateTime');
    }

    public function getSubject(): ?string
    {
        return $this->get('subject');
    }

    public function setSubject(?string $subject): void
    {
        $this->set('subject', $subject);
    }

    public function getBody(): ?string
    {
        return $this->get('body');
    }

    public function setBody(?string $body): void
    {
        $this->set('body', $body);
    }

    public function isHtml(): ?bool
    {
        return $this->get('isHtml');
    }

    public function setIsHtml(bool $isHtml = true): void
    {
        $this->set('isHtml', $isHtml);
    }

    public function setIsPlain(bool $isPlain = true): void
    {
        $this->set('isHtml', !$isPlain);
    }

    public function addToAddress(string $address): void
    {
        $list = $this->getToAddressList();

        $list[] = $address;

        $this->set('to', implode(';', $list));
    }

    public function addCcAddress(string $address): void
    {
        $list = $this->getCcAddressList();

        $list[] = $address;

        $this->set('cc', implode(';', $list));
    }

    public function addBccAddress(string $address): void
    {
        $list = $this->getBccAddressList();

        $list[] = $address;

        $this->set('bcc', implode(';', $list));
    }

    public function addReplyToAddress(string $address): void
    {
        $list = $this->getReplyToAddressList();

        $list[] = $address;

        $this->set('replyTo', implode(';', $list));
    }

    /**
     * @return string[]
     */
    public function getToAddressList(): array
    {
        $value = $this->get('to');

        if (!$value) {
            return [];
        }

        return explode(';', $value);
    }

    /**
     * @return string[]
     */
    public function getCcAddressList(): array
    {
        $value = $this->get('cc');

        if (!$value) {
            return [];
        }

        return explode(';', $value);
    }

    /**
     * @return string[]
     */
    public function getBccAddressList(): array
    {
        $value = $this->get('bcc');

        if (!$value) {
            return [];
        }

        return explode(';', $value);
    }

    /**
     * @return string[]
     */
    public function getReplyToAddressList(): array
    {
        $value = $this->get('replyTo');

        if (!$value) {
            return [];
        }

        return explode(';', $value);
    }

    public function setDummyMessageId(): void
    {
        $this->set('messageId', 'dummy:' . Util::generateId());
    }

    public function getMessageId(): ?string
    {
        return $this->get('messageId');
    }
}
