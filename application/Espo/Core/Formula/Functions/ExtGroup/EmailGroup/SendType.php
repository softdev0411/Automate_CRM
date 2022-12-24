<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
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

namespace Espo\Core\Formula\Functions\ExtGroup\EmailGroup;

use Espo\Core\ApplicationUser;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Entities\Email;
use Espo\Tools\Email\SendService;
use Exception;
use Espo\Core\Formula\{
    Functions\BaseFunction,
    ArgumentList,
};

use Espo\Core\Di;

class SendType extends BaseFunction implements
    Di\EntityManagerAware,
    Di\ServiceFactoryAware,
    Di\ConfigAware,
    Di\InjectableFactoryAware
{
    use Di\EntityManagerSetter;
    use Di\ServiceFactorySetter;
    use Di\ConfigSetter;
    use Di\InjectableFactorySetter;

    public function process(ArgumentList $args)
    {
        if (count($args) < 1) {
            $this->throwTooFewArguments(1);
        }

        $args = $this->evaluate($args);

        $id = $args[0];

        if (!$id || !is_string($id)) {
            $this->throwBadArgumentType(1, 'string');
        }

        $em = $this->entityManager;

        /** @var ?Email $email */
        $email = $em->getEntityById(Email::ENTITY_TYPE, $id);

        if (!$email) {
            $this->log("Email '{$id}' does not exist.");

            return false;
        }

        $status = $email->getStatus();

        if ($status === Email::STATUS_SENT) {
            $this->log("Can't send email that has 'Sent' status.");

            return false;
        }

        /** @var \Espo\Services\Email $service */
        $service = $this->serviceFactory->create(Email::ENTITY_TYPE);

        $service->loadAdditionalFields($email);

        $toSave = false;

        if ($status !== Email::STATUS_SENDING) {
            $email->set('status', Email::STATUS_SENDING);

            $toSave = true;
        }

        if (!$email->get('from')) {
            $from = $this->config->get('outboundEmailFromAddress');

            if ($from) {
                $email->set('from', $from);

                $toSave = true;
            }
        }

        if ($toSave) {
            $em->saveEntity($email, [
                SaveOption::SILENT => true,
                SaveOption::MODIFIED_BY_ID => ApplicationUser::SYSTEM_USER_ID,
            ]);
        }

        $sendService = $this->injectableFactory->create(SendService::class);

        try {
            $sendService->send($email);
        }
        catch (Exception $e) {
            $message = $e->getMessage();
            $this->log("Error while sending. Message: {$message}." , 'error');

            return false;
        }

        return true;
    }
}
