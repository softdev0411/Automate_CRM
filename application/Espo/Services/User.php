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

namespace Espo\Services;

use Espo\Entities\User as UserEntity;
use Espo\Entities\Email as EmailEntity;
use Espo\Entities\PasswordChangeRequest;
use Espo\Entities\Portal as PortalEntity;

use Espo\Repositories\Portal as PortalRepository;

use Espo\Core\Mail\Sender;

use Espo\Core\{
    Exceptions\Forbidden,
    Exceptions\Error,
    Exceptions\NotFound,
    Exceptions\BadRequest,
    Utils\Util,
    Utils\PasswordHash,
    Utils\ApiKey as ApiKeyUtil,
    Di,
    Password\Recovery,
    Record\CreateParams,
    Record\UpdateParams,
    Record\DeleteParams,
};

use Espo\ORM\Entity;

use stdClass;
use Exception;

/**
 * @extends Record<\Espo\Entities\User>
 */
class User extends Record implements

    Di\TemplateFileManagerAware,
    Di\EmailSenderAware,
    Di\HtmlizerFactoryAware,
    Di\FileManagerAware,
    Di\DataManagerAware
{
    use Di\TemplateFileManagerSetter;
    use Di\EmailSenderSetter;
    use Di\HtmlizerFactorySetter;
    use Di\FileManagerSetter;
    use Di\DataManagerSetter;

    /**
     * @var string[]
     */
    protected $mandatorySelectAttributeList = [
        'isActive',
        'userName',
        'type',
    ];

    /**
     * @var string[]
     */
    protected $validateSkipFieldList = ['name', "firstName", "lastName"];

    /**
     * @var string[]
     */
    protected $allowedUserTypeList = ['regular', 'admin', 'portal', 'api'];

    public function getEntity(string $id): ?Entity
    {
        if ($id == 'system') {
            throw new Forbidden();
        }

        /** @var ?UserEntity $entity */
        $entity = parent::getEntity($id);

        if ($entity && $entity->isSuperAdmin() && !$this->getUser()->isSuperAdmin()) {
            throw new Forbidden();
        }

        if ($entity && $entity->isSystem()) {
            throw new Forbidden();
        }

        return $entity;
    }

    public function changePassword(
        string $userId,
        string $password,
        bool $checkCurrentPassword = false,
        ?string $currentPassword = null
    ): void {

        /** @var ?UserEntity $user */
        $user = $this->getEntityManager()->getEntity('User', $userId);

        if (!$user) {
            throw new NotFound();
        }

        if ($user->isSuperAdmin() && !$this->getUser()->isSuperAdmin()) {
            throw new Forbidden();
        }

        if (!$user->isAdmin() && $this->getConfig()->get('authenticationMethod', 'Espo') !== 'Espo') {
            throw new Forbidden();
        }

        if (empty($password)) {
            throw new Error("Password can't be empty.");
        }

        if ($checkCurrentPassword) {
            $u = $this->getEntityManager()
                ->getRDBRepository('User')
                ->where([
                    'id' => $user->getId(),
                    'password' => $this->createPasswordHashUtil()->hash($currentPassword ?? ''),
                ])
                ->findOne();

            if (!$u) {
                throw new Forbidden("Wrong password.");
            }
        }

        if (!$this->checkPasswordStrength($password)) {
            throw new Forbidden("Change password: Password is weak.");
        }

        $user->set('password', $this->hashPassword($password));

        $this->getEntityManager()->saveEntity($user);
    }

    public function checkPasswordStrength(string $password): bool
    {
        $minLength = $this->getConfig()->get('passwordStrengthLength');

        if ($minLength) {
            if (mb_strlen($password) < $minLength) {
                return false;
            }
        }

        $requiredLetterCount = $this->getConfig()->get('passwordStrengthLetterCount');

        if ($requiredLetterCount) {
            $letterCount = 0;

            foreach (str_split($password) as $c) {
                if (ctype_alpha($c)) {
                    $letterCount++;
                }
            }

            if ($letterCount < $requiredLetterCount) {
                return false;
            }
        }

        $requiredNumberCount = $this->getConfig()->get('passwordStrengthNumberCount');

        if ($requiredNumberCount) {
            $numberCount = 0;

            foreach (str_split($password) as $c) {
                if (is_numeric($c)) {
                    $numberCount++;
                }
            }

            if ($numberCount < $requiredNumberCount) {
                return false;
            }
        }

        $bothCases = $this->getConfig()->get('passwordStrengthBothCases');

        if ($bothCases) {
            $ucCount = 0;
            $lcCount = 0;

            foreach (str_split($password) as $c) {
                if (ctype_alpha($c) && $c === mb_strtoupper($c)) {
                    $ucCount++;
                }

                if (ctype_alpha($c) && $c === mb_strtolower($c)) {
                    $lcCount++;
                }
            }
            if (!$ucCount || !$lcCount) {
                return false;
            }
        }

        return true;
    }

    private function createRecoveryService(): Recovery
    {
        return $this->injectableFactory->create(Recovery::class);
    }

    public function passwordChangeRequest(string $userName, string $emailAddress, ?string $url = null): void
    {
        $this->createRecoveryService()->request($emailAddress, $userName, $url);
    }

    public function changePasswordByRequest(string $requestId, string $password): stdClass
    {
        $recovery = $this->createRecoveryService();

        $request = $recovery->getRequest($requestId);

        $userId = $request->get('userId');

        if (!$userId) {
            throw new Error();
        }

        $this->changePassword($userId, $password);

        $recovery->removeRequest($requestId);

        return (object) [
            'url' => $request->get('url'),
        ];
    }

    public function removeChangePasswordRequestJob(stdClass $data): void
    {
        if (empty($data->id)) {
            return;
        }

        $id = $data->id;

        $p = $this->getEntityManager()->getEntity(PasswordChangeRequest::ENTITY_TYPE, $id);

        if ($p) {
            $this->getEntityManager()->removeEntity($p);
        }
    }

    protected function hashPassword(string $password): string
    {
        $passwordHash = $this->injectableFactory->create(PasswordHash::class);

        return $passwordHash->hash($password);
    }

    protected function filterInput($data)
    {
        parent::filterInput($data);

        if (!$this->getUser()->isSuperAdmin()) {
            unset($data->isSuperAdmin);
        }

        if (!$this->getUser()->isAdmin()) {
            if (!$this->getAcl()->checkScope('Team')) {
                unset($data->defaultTeamId);
            }
        }
    }

    public function create(stdClass $data, CreateParams $params): Entity
    {
        $newPassword = $data->password ?? null;

        if ($newPassword === '') {
            $newPassword = null;
        }

        if ($newPassword !== null && !is_string($newPassword)) {
            throw new BadRequest();
        }

        if ($newPassword !== null) {
            if (!$this->checkPasswordStrength($newPassword)) {
                throw new Forbidden("Password is weak.");
            }

            $data->password = $this->hashPassword($data->password);
        }

        /** @var UserEntity $user */
        $user = parent::create($data, $params);

        $sendAccessInfo = !empty($data->sendAccessInfo);

        if (!$sendAccessInfo || !$user->isActive() || $user->isApi()) {
            return $user;
        }

        try {
            if ($newPassword !== null) {
                $this->sendPassword($user, $newPassword);

                return $user;
            }

            $this->sendAccessInfoNew($user);
        }
        catch (Exception $e) {
            $this->log->error("Could not send user access info. " . $e->getMessage());
        }

        return $user;
    }

    public function update(string $id, stdClass $data, UpdateParams $params): Entity
    {
        if ($id == 'system') {
            throw new Forbidden();
        }

        $newPassword = null;

        if (property_exists($data, 'password')) {
            $newPassword = $data->password;

            if (!$this->checkPasswordStrength($newPassword)) {
                throw new Forbidden("Password is weak.");
            }

            $data->password = $this->hashPassword($data->password);
        }

        if ($id == $this->getUser()->id) {
            unset($data->isActive);
            unset($data->isPortalUser);
            unset($data->type);
        }

        /** @var UserEntity $user */
        $user = parent::update($id, $data, $params);

        if (!is_null($newPassword)) {
            try {
                if ($user->isActive() && !empty($data->sendAccessInfo)) {
                    $this->sendPassword($user, $newPassword);
                }
            }
            catch (Exception $e) {}
        }

        return $user;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        assert($entity instanceof UserEntity);

        parent::prepareEntityForOutput($entity);

        if ($entity->isApi()) {
            if ($this->getUser()->isAdmin()) {
                if ($entity->get('authMethod') === 'Hmac') {
                    $secretKey = $this->getSecretKeyForUserId($entity->getId());
                    $entity->set('secretKey', $secretKey);
                }
            } else {
                $entity->clear('apiKey');
                $entity->clear('secretKey');
            }
        }
    }

    protected function getSecretKeyForUserId(string $id): ?string
    {
        $apiKeyUtil = $this->injectableFactory->create(ApiKeyUtil::class);

        return $apiKeyUtil->getSecretKeyForUserId($id);
    }

    public function generateNewApiKeyForEntity(string $id): Entity
    {
        /** @var ?UserEntity $entity */
        $entity = $this->getEntity($id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!$entity->isApi()) {
            throw new Forbidden();
        }

        $apiKey = Util::generateApiKey();

        $entity->set('apiKey', $apiKey);

        if ($entity->get('authMethod') === 'Hmac') {
            $secretKey = Util::generateSecretKey();

            $entity->set('secretKey', $secretKey);
        }

        $this->getEntityManager()->saveEntity($entity);

        $this->prepareEntityForOutput($entity);

        return $entity;
    }

    private function generatePassword(): string
    {
        $length = $this->config->get('passwordStrengthLength');
        $letterCount = $this->config->get('passwordStrengthLetterCount');
        $numberCount = $this->config->get('passwordStrengthNumberCount');

        $generateLength = $this->config->get('passwordGenerateLength', 10);
        $generateLetterCount = $this->config->get('passwordGenerateLetterCount', 4);
        $generateNumberCount = $this->config->get('passwordGenerateNumberCount', 2);

        $length = is_null($length) ? $generateLength : $length;
        $letterCount = is_null($letterCount) ? $generateLetterCount : $letterCount;
        $numberCount = is_null($letterCount) ? $generateNumberCount : $numberCount;

        if ($length < $generateLength) {
            $length = $generateLength;
        }

        if ($letterCount < $generateLetterCount) {
            $letterCount = $generateLetterCount;
        }

        if ($numberCount < $generateNumberCount) {
            $numberCount = $generateNumberCount;
        }

        return Util::generatePassword($length, $letterCount, $numberCount, true);
    }

    public function sendPasswordChangeLink(string $id, bool $allowNonAdmin = false): void
    {
        if (!$allowNonAdmin && !$this->user->isAdmin()) {
            throw new Forbidden();
        }

        /** @var UserEntity|null $user */
        $user = $this->entityManager->getEntityById(UserEntity::ENTITY_TYPE, $id);

        if (!$user) {
            throw new NotFound();
        }

        if (!$user->isActive()) {
            throw new Forbidden("User is not active.");
        }

        if (
            !$user->isRegular() &&
            !$user->isAdmin() &&
            !$user->isPortal()
        ) {
            throw new Forbidden();
        }

        $this->createRecoveryService()->createAndSendRequestForExistingUser($user);
    }

    public function generateNewPasswordForUser(string $id, bool $allowNonAdmin = false): void
    {
        if (!$allowNonAdmin) {
            if (!$this->user->isAdmin()) {
                throw new Forbidden();
            }
        }

        /** @var ?UserEntity $user */
        $user = $this->getEntity($id);

        if (!$user) {
            throw new NotFound();
        }

        if ($user->isApi()) {
            throw new Forbidden();
        }

        if ($user->isSuperAdmin()) {
            throw new Forbidden();
        }

        if ($user->isSystem()) {
            throw new Forbidden();
        }

        if (!$user->get('emailAddress')) {
            throw new Forbidden(
                "Generate new password: Can't process because user doesn't have email address."
            );
        }

        if (!$this->isSmtpConfigured()) {
            throw new Forbidden(
                "Generate new password: Can't process because SMTP is not configured."
            );
        }

        $password = $this->generatePassword();

        $this->sendPassword($user, $password);

        $this->saveUserPassword($user, $password);
    }

    private function isSmtpConfigured(): bool
    {
        return $this->emailSender->hasSystemSmtp() || $this->config->get('internalSmtpServer');
    }

    private function saveUserPassword(UserEntity $user, string $password, bool $silent = false): void
    {
        $user->set('password', $this->createPasswordHashUtil()->hash($password));

        $this->entityManager->saveEntity($user, ['silent' => $silent]);
    }

    private function createPasswordHashUtil(): PasswordHash
    {
        return $this->injectableFactory->create(PasswordHash::class);
    }

    protected function getInternalUserCount(): int
    {
        return $this->getEntityManager()
            ->getRDBRepository('User')
            ->where([
                'isActive' => true,
                'type' => ['admin', 'regular'],
                'type!=' => 'system',
            ])
            ->count();
    }

    protected function getPortalUserCount(): int
    {
        return $this->getEntityManager()
            ->getRDBRepository('User')
            ->where([
                'isActive' => true,
                'type' => 'portal',
            ])
            ->count();
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        /** @var UserEntity $entity */

        if (
            $this->getConfig()->get('userLimit') &&
            !$this->getUser()->isSuperAdmin() &&
            !$entity->isPortal() && !$entity->isApi()
        ) {
            $userCount = $this->getInternalUserCount();

            if ($userCount >= $this->getConfig()->get('userLimit')) {
                throw new Forbidden(
                    'User limit '.$this->getConfig()->get('userLimit').' is reached.'
                );
            }
        }
        if (
            $this->getConfig()->get('portalUserLimit') &&
            !$this->getUser()->isSuperAdmin() &&
            $entity->isPortal()
        ) {
            $portalUserCount = $this->getPortalUserCount();

            if ($portalUserCount >= $this->getConfig()->get('portalUserLimit')) {
                throw new Forbidden(
                    'Portal user limit ' . $this->getConfig()->get('portalUserLimit').' is reached.'
                );
            }
        }

        if ($entity->isApi()) {
            $apiKey = Util::generateApiKey();

            $entity->set('apiKey', $apiKey);

            if ($entity->get('authMethod') === 'Hmac') {
                $secretKey = Util::generateSecretKey();

                $entity->set('secretKey', $secretKey);
            }
        }

        if (!$entity->isSuperAdmin()) {
            if (
                $entity->get('type') &&
                !in_array($entity->get('type'), $this->allowedUserTypeList)
            ) {
                throw new Forbidden();
            }
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        /** @var UserEntity $entity */

        if ($this->getConfig()->get('userLimit') && !$this->getUser()->isSuperAdmin()) {
            if (
                (
                    $entity->get('isActive') && $entity->isAttributeChanged('isActive') &&
                    !$entity->isPortal() && !$entity->isApi()
                )
                ||
                (
                    !$entity->isPortal() && !$entity->isApi() && $entity->isAttributeChanged('type') &&
                    ($entity->isRegular() || $entity->isAdmin()) &&
                    ($entity->getFetched('type') == 'portal' || $entity->getFetched('type') == 'api')
                )
            ) {
                $userCount = $this->getInternalUserCount();

                if ($userCount >= $this->getConfig()->get('userLimit')) {
                    throw new Forbidden('User limit '.$this->getConfig()->get('userLimit').' is reached.');
                }
            }
        }

        if ($this->getConfig()->get('portalUserLimit') && !$this->getUser()->isSuperAdmin()) {
            if (
                ($entity->get('isActive') && $entity->isAttributeChanged('isActive') && $entity->isPortal())
                ||
                ($entity->isPortal() && $entity->isAttributeChanged('type'))
            ) {
                $portalUserCount = $this->getPortalUserCount();

                if ($portalUserCount >= $this->getConfig()->get('portalUserLimit')) {
                    throw new Forbidden(
                        'Portal user limit '. $this->getConfig()->get('portalUserLimit').' is reached.'
                    );
                }
            }
        }

        if ($entity->isApi()) {
            if (
                $entity->isAttributeChanged('authMethod') &&
                $entity->get('authMethod') === 'Hmac'
            ) {
                $secretKey = Util::generateSecretKey();

                $entity->set('secretKey', $secretKey);
            }
        }

        if (!$entity->isSuperAdmin()) {
            if (
                $entity->isAttributeChanged('type') &&
                $entity->get('type') &&
                !in_array($entity->get('type'), $this->allowedUserTypeList)
            ) {
                throw new Forbidden();
            }
        }
    }

    /**
     * @return array{?string,?string,?array<string,mixed>}
     */
    private function getAccessInfoTemplateData(
        UserEntity $user,
        ?string $password = null,
        ?PasswordChangeRequest $passwordChangeRequest = null
    ): array {

        $data = [];

        if ($password !== null) {
            $data['password'] = $password;
        }

        $urlSuffix = '';

        if ($passwordChangeRequest !== null) {
            $urlSuffix = '?entryPoint=changePassword&id=' . $passwordChangeRequest->getRequestId();
        }

        $siteUrl = $this->config->getSiteUrl() . '/' . $urlSuffix;

        if ($user->isPortal()) {
            $subjectTpl = $this->templateFileManager->getTemplate('accessInfoPortal', 'subject', 'User');
            $bodyTpl = $this->templateFileManager->getTemplate('accessInfoPortal', 'body', 'User');

            $urlList = [];

            $portalList = $this->entityManager
                ->getRDBRepository(PortalEntity::ENTITY_TYPE)
                ->distinct()
                ->join('users')
                ->where([
                    'isActive' => true,
                    'users.id' => $user->getId(),
                ])
                ->find();

            foreach ($portalList as $portal) {
                /** @var PortalEntity $portal */
                $this->getPortalRepository()->loadUrlField($portal);

                $urlList[] = $portal->getUrl() . $urlSuffix;
            }

            if (count($urlList) === 0) {
                return [null, null, null];
            }

            $data['siteUrlList'] = $urlList;

            return [$subjectTpl, $bodyTpl, $data];
        }

        $subjectTpl = $this->templateFileManager->getTemplate('accessInfo', 'subject', 'User');
        $bodyTpl = $this->templateFileManager->getTemplate('accessInfo', 'body', 'User');

        $data['siteUrl'] = $siteUrl;

        return [$subjectTpl, $bodyTpl, $data];
    }

    protected function sendPassword(UserEntity $user, string $password): void
    {
        $emailAddress = $user->get('emailAddress');

        if (empty($emailAddress)) {
            return;
        }

        /** @var EmailEntity $email */
        $email = $this->getEntityManager()->getEntity('Email');

        if (!$this->isSmtpConfigured()) {
            return;
        }

        [$subjectTpl, $bodyTpl, $data] = $this->getAccessInfoTemplateData($user, $password);

        if ($data === null) {
            return;
        }

        $htmlizer = $this->htmlizerFactory->createNoAcl();

        $subject = $htmlizer->render($user, $subjectTpl ?? '', null, $data, true);
        $body = $htmlizer->render($user, $bodyTpl ?? '', null, $data, true);

        $email->set([
            'subject' => $subject,
            'body' => $body,
            'to' => $emailAddress,
        ]);

        $this->getEmailSenderForAccessInfo()->send($email);
    }

    private function getEmailSenderForAccessInfo(): Sender
    {
        $sender = $this->emailSender->create();

        if (!$this->emailSender->hasSystemSmtp()) {
            $sender->withSmtpParams([
                'server' => $this->config->get('internalSmtpServer'),
                'port' => $this->config->get('internalSmtpPort'),
                'auth' => $this->config->get('internalSmtpAuth'),
                'username' => $this->config->get('internalSmtpUsername'),
                'password' => $this->config->get('internalSmtpPassword'),
                'security' => $this->config->get('internalSmtpSecurity'),
                'fromAddress' => $this->config->get(
                    'internalOutboundEmailFromAddress',
                    $this->config->get('outboundEmailFromAddress')
                ),
            ]);
        }

        return $sender;
    }

    public function delete(string $id, DeleteParams $params): void
    {
        if ($id == 'system') {
            throw new Forbidden();
        }

        if ($id == $this->getUser()->id) {
            throw new Forbidden();
        }

        parent::delete($id, $params);
    }

    public function afterUpdateEntity(Entity $entity, $data)
    {
        assert($entity instanceof UserEntity);

        parent::afterUpdateEntity($entity, $data);

        if (
            property_exists($data, 'rolesIds') ||
            property_exists($data, 'teamsIds') ||
            property_exists($data, 'type') ||
            property_exists($data, 'portalRolesIds') ||
            property_exists($data, 'portalsIds')
        ) {
            $this->clearRoleCache($entity->getId());
        }

        if (
            property_exists($data, 'portalRolesIds') ||
            property_exists($data, 'portalsIds') ||
            property_exists($data, 'contactId') ||
            property_exists($data, 'accountsIds')
        ) {
            $this->clearPortalRolesCache();
        }

        if (
            $entity->isPortal() && $entity->get('contactId') &&
            (
                property_exists($data, 'firstName') ||
                property_exists($data, 'lastName') ||
                property_exists($data, 'salutationName')
            )
        ) {
            $contact = $this->getEntityManager()->getEntity('Contact', $entity->get('contactId'));

            if ($contact) {
                if (property_exists($data, 'firstName')) {
                    $contact->set('firstName', $data->firstName);
                }

                if (property_exists($data, 'lastName')) {
                    $contact->set('lastName', $data->lastName);
                }

                if (property_exists($data, 'salutationName')) {
                    $contact->set('salutationName', $data->salutationName);
                }

                $this->getEntityManager()->saveEntity($contact);
            }
        }
    }

    protected function clearRoleCache(string $id): void
    {
        $this->fileManager->removeFile('data/cache/application/acl/' . $id . '.php');
        $this->fileManager->removeFile('data/cache/application/aclMap/' . $id . '.php');

        $this->dataManager->updateCacheTimestamp();
    }

    protected function clearPortalRolesCache(): void
    {
        $this->fileManager->removeInDir('data/cache/application/aclPortal');
        $this->fileManager->removeInDir('data/cache/application/aclPortalMap');

        $this->dataManager->updateCacheTimestamp();
    }

    public function sendAccessInfoNew(UserEntity $user): void
    {
        $primaryAddress = $user->getEmailAddressGroup()->getPrimary();

        if ($primaryAddress === null) {
            throw new Error("Can't send access info for user '{$user->getId()}' w/o email address.");
        }

        $emailAddress = $primaryAddress->getAddress();

        if (!$this->isSmtpConfigured()) {
            throw new Error("Can't send access info. SMTP is not configured.");
        }

        $stubPassword = $this->generatePassword();

        $this->saveUserPassword($user, $stubPassword, true);

        $request = $this->createRecoveryService()->createRequestForNewUser($user);

        [$subjectTpl, $bodyTpl, $data] = $this->getAccessInfoTemplateData($user, null, $request);

        if ($data === null) {
            throw new Error("Could not send access info.");
        }

        /** @var EmailEntity $email */
        $email = $this->entityManager->getEntity(EmailEntity::ENTITY_TYPE);

        $htmlizer = $this->htmlizerFactory->createNoAcl();

        $subject = $htmlizer->render($user, $subjectTpl ?? '', null, $data, true);
        $body = $htmlizer->render($user, $bodyTpl ?? '', null, $data, true);

        $email
            ->addToAddress($emailAddress)
            ->setSubject($subject)
            ->setBody($body);

        $this->getEmailSenderForAccessInfo()->send($email);
    }

    private function getPortalRepository(): PortalRepository
    {
        /** @var PortalRepository */
        return $this->entityManager->getRDBRepository(PortalEntity::ENTITY_TYPE);
    }
}
