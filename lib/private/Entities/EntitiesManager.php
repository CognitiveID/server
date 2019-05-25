<?php
declare(strict_types=1);


/**
 * Entities - Entity & Groups of Entities
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2019, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OC\Entities;


use OC;
use OC\Entities\Db\EntitiesAccountsRequest;
use OC\Entities\Db\EntitiesMembersRequest;
use OC\Entities\Db\EntitiesRequest;
use OC\Entities\Db\EntitiesTypesRequest;
use OC\Entities\Exceptions\EntityAccountAlreadyExistsException;
use OC\Entities\Exceptions\EntityAccountCreationException;
use OC\Entities\Exceptions\EntityAccountNotFoundException;
use OC\Entities\Exceptions\EntityAlreadyExistsException;
use OC\Entities\Exceptions\EntityCreationException;
use OC\Entities\Exceptions\EntityMemberAlreadyExistsException;
use OC\Entities\Exceptions\EntityMemberNotFoundException;
use OC\Entities\Exceptions\EntityNotFoundException;
use OC\Entities\Exceptions\EntityTypeNotFoundException;
use OC\Entities\Exceptions\ImplementationNotFoundException;
use OC\Entities\Model\EntityMember;
use OCP\AppFramework\QueryException;
use OCP\Entities\IEntitiesManager;
use OCP\Entities\IEntitiesQueryBuilder;
use OCP\Entities\Implementation\IEntities\IEntitiesAdminRights;
use OCP\Entities\Implementation\IEntities\IEntitiesConfirmCreation;
use OCP\Entities\Implementation\IEntities\IEntitiesSearchDuplicate;
use OCP\Entities\Implementation\IEntities\IEntitiesSearchEntities;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccountsSearchAccounts;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccountsSearchDuplicate;
use OCP\Entities\Implementation\IEntitiesAccounts\IEntitiesAccountsSearchEntities;
use OCP\Entities\Model\IEntity;
use OCP\Entities\Model\IEntityAccount;
use OCP\Entities\Model\IEntityMember;
use OCP\Entities\Model\IEntityType;
use OCP\IConfig;
use OCP\ILogger;
use stdClass;


/**
 * Class EntitiesManager
 *
 * @package OCP\Entities
 */
class EntitiesManager implements IEntitiesManager {


	const INTERFACE_ENTITIES = 'IEntities';
	const INTERFACE_ENTITIES_ACCOUNTS = 'IEntitiesAccounts';
	const INTERFACE_ENTITIES_MEMBERS = 'IEntitiesMembers';
	const INTERFACE_ENTITIES_TYPES = 'IEntitiesTypes';


	/** @var ILogger */
	private $logger;

	/** @var IConfig */
	private $config;

	/** @var EntitiesRequest */
	private $entitiesRequest;

	/** @var EntitiesAccountsRequest */
	private $entitiesAccountsRequest;

	/** @var EntitiesMembersRequest */
	private $entitiesMembersRequest;

	/** @var EntitiesTypesRequest */
	private $entitiesTypesRequest;

	/** @var IEntityType[] */
	private $classes = [];

	/** @var string[] */
	private $logSql = [];


	/**
	 * @param ILogger $logger
	 * @param IConfig $config
	 * @param EntitiesRequest $entitiesRequest
	 * @param EntitiesAccountsRequest $entitiesAccountsRequest
	 * @param EntitiesMembersRequest $entitiesMembersRequest
	 * @param EntitiesTypesRequest $entitiesTypesRequest
	 */
	public function __construct(
		ILogger $logger, IConfig $config, EntitiesRequest $entitiesRequest,
		EntitiesAccountsRequest $entitiesAccountsRequest,
		EntitiesMembersRequest $entitiesMembersRequest,
		EntitiesTypesRequest $entitiesTypesRequest
	) {
		$this->logger = $logger;
		$this->config = $config;
		$this->entitiesRequest = $entitiesRequest;
		$this->entitiesAccountsRequest = $entitiesAccountsRequest;
		$this->entitiesMembersRequest = $entitiesMembersRequest;
		$this->entitiesTypesRequest = $entitiesTypesRequest;
	}


	/**
	 * @param IEntity $entity
	 * @param string $ownerId
	 *
	 * @throws EntityAlreadyExistsException
	 * @throws EntityCreationException
	 * @throws EntityMemberAlreadyExistsException
	 * @throws EntityAccountNotFoundException
	 */
	public function saveEntity(IEntity $entity, string $ownerId = ''): void {

		if ($ownerId !== '') {
			$owner = $this->getAccount($ownerId);
			$entity->setOwner($owner);

			$member = new EntityMember();
			$member->setEntityId($entity->getId());
			$member->setAccountId($owner->getId());
			$member->setStatus(IEntityMember::STATUS_MEMBER);
			$member->setLevel(IEntityMember::LEVEL_OWNER);

			$this->saveMember($member);
		}

		try {
			$this->confirmCreationStatus($entity);
			$knownEntity = $this->searchDuplicateEntity($entity);

			$this->logger->log(
				2,
				'Entity Creation Exception: duplicate entry ' . json_encode($entity)
				. ' known as ' . json_encode($knownEntity)
			);

			$entity->setId($knownEntity->getId());
			throw new EntityAlreadyExistsException(
				'Entity already exists (' . $knownEntity->getId() . ')'
			);
		} catch (EntityTypeNotFoundException $e) {
			$this->logger->log(
				2,
				'Entity Creation Exception: Type not found ' . json_encode($entity)
			);
			throw new EntityCreationException('Unknown Entity Type');
		} catch (EntityNotFoundException $e) {
			$this->entitiesRequest->create($entity);
		}
	}


	/**
	 * @param IEntityAccount $account
	 *
	 * @throws EntityAccountCreationException
	 * @throws EntityAccountAlreadyExistsException
	 */
	public function saveAccount(IEntityAccount $account): void {

		try {
			$knownAccount = $this->searchDuplicateEntityAccount($account);

			$this->logger->log(
				2,
				'EntityAccount Creation Exception: duplicate entry ' . json_encode($account)
				. ' known as ' . json_encode($knownAccount)
			);

			throw new EntityAccountAlreadyExistsException(
				'EntityAccount already exists (' . $knownAccount->getId() . ')'
			);

		} catch (EntityTypeNotFoundException $e) {
			$this->logger->log(
				2,
				'EntityAccount Creation Exception: Type not found ' . json_encode($account)
			);
			throw new EntityAccountCreationException('Unknown EntityAccount Type');

		} catch (EntityAccountNotFoundException $e) {
			$this->entitiesAccountsRequest->create($account);
		}

	}


	/**
	 * @param IEntityMember $member
	 *
	 * @throws EntityMemberAlreadyExistsException
	 */
	public function saveMember(IEntityMember $member): void {
		try {
			$knownMember = $this->entitiesMembersRequest->getMemberStatus(
				$member->getAccountId(), $member->getEntityId()
			);

			throw new EntityMemberAlreadyExistsException(
				'EntityMember already exists (' . $knownMember->getId() . ')'
			);
		} catch (EntityMemberNotFoundException $e) {
			$this->entitiesMembersRequest->create($member);
		}
	}


	/**
	 * @param string $type
	 *
	 * @return IEntity[]
	 */
	public function getAllEntities(string $type = ''): array {
		return $this->entitiesRequest->getAll($type);
	}


	/**
	 * @param string $type
	 *
	 * @return IEntityAccount[]
	 */
	public function getAllAccounts(string $type = ''): array {
		return $this->entitiesAccountsRequest->getAll($type);
	}


	/**
	 * @param string $needle
	 * @param string $type
	 *
	 * @return IEntity[]
	 */
	public function searchEntities(string $needle, string $type = ''): array {
//		$classes = [
//			self::INTERFACE_ENTITIES_ACCOUNTS => $this->getClasses(
//				self::INTERFACE_ENTITIES_ACCOUNTS, IEntitiesAccountsSearch::class
//			)
//		];
// Testing
		$classes = array_merge(
			$this->getClasses(
				self::INTERFACE_ENTITIES_ACCOUNTS, IEntitiesAccountsSearchEntities::class
			),
			$this->getClasses(
				self::INTERFACE_ENTITIES, IEntitiesSearchEntities::class
			)
		);

		return $this->entitiesRequest->search($needle, $type, $classes);
	}


	/**
	 * @param string $needle
	 * @param string $type
	 *
	 * @return IEntityAccount[]
	 */
	public function searchAccounts(string $needle, string $type = ''): array {
		$classes = array_merge(
			$this->getClasses(
				self::INTERFACE_ENTITIES_ACCOUNTS, IEntitiesAccountsSearchAccounts::class
			)
		);

		return $this->entitiesAccountsRequest->search($needle, $type, $classes);
	}


	/**
	 * @param string $entityId
	 *
	 * @return IEntity
	 * @throws EntityNotFoundException
	 */
	public function getEntity(string $entityId): IEntity {
		return $this->entitiesRequest->getFromId($entityId);
	}


	/**
	 * @param string $accountId
	 *
	 * @return IEntityAccount
	 * @throws EntityAccountNotFoundException
	 */
	public function getAccount(string $accountId): IEntityAccount {
		return $this->entitiesAccountsRequest->getFromId($accountId);
	}


	/**
	 * @param string $memberId
	 *
	 * @return IEntityMember
	 * @throws EntityMemberNotFoundException
	 */
	public function getMember(string $memberId): IEntityMember {
		return $this->entitiesMembersRequest->getFromId($memberId);
	}


	/**
	 * @param IEntity $entity
	 *
	 * @return IEntity[]
	 */
	public function entityBelongsTo(IEntity $entity): array {
//		return $this->entitiesMembersRequest->getMembership($entity);
//		return $this->entitiesRequest->getMembership($entity);
	}


	/**
	 * @param IEntity $entity
	 *
	 * @return IEntity[]
	 */
	public function entityGetMembers(IEntity $entity): array {
		return $this->entitiesMembersRequest->getMembers($entity);
	}


	/**
	 * @param IEntity $entity
	 *
	 * @return bool
	 */
	public function entityHasAdminRights(IEntity $entity): bool {
		try {
			/** @var IEntitiesAdminRights $class */
			$class = $this->getClass(
				self::INTERFACE_ENTITIES, $entity->getType(), IEntitiesAdminRights::class
			);
		} catch (EntityTypeNotFoundException | ImplementationNotFoundException $e) {
			return false;
		}

		return $class->hasAdminRights($entity);
	}


	/**
	 * @param IEntityAccount $account
	 *
	 * @return IEntityMember[]
	 */
	public function accountBelongsTo(IEntityAccount $account): array {
		return $this->entitiesMembersRequest->getMembership($account);
	}


	/**
	 * @param IEntityAccount $account
	 *
	 * @return bool
	 */
	public function accountHasAdminRights(IEntityAccount $account): bool {
		foreach ($account->belongsTo() as $to) {
			if ($to->getEntity()
				   ->hasAdminRights()) {
				return true;
			}
		}

		return false;
	}



//
//	/**
//	 * @param IEntity $entity
//	 *
//	 * @return IEntity
//	 * @throws EntityNotFoundException
//	 */
//	public function searchEntity(IEntity $entity): IEntity {
//		return $this->entitiesRequest->search($entity);
//	}


	/**
	 * @param string $userId
	 *
	 * @return IEntityAccount
	 * @throws EntityAccountNotFoundException
	 */
	public function getLocalAccount(string $userId): IEntityAccount {
		return $this->entitiesAccountsRequest->getFromLocalUserId($userId);
	}


	/**
	 * @param IEntity $entity
	 *
	 * @throws EntityCreationException
	 */
	public function confirmCreationStatus(IEntity $entity): void {

		try {
			/** @var IEntitiesConfirmCreation $class */
			$class = $this->getClass(
				self::INTERFACE_ENTITIES, $entity->getType(), IEntitiesConfirmCreation::class
			);
		} catch (ImplementationNotFoundException $e) {
			return;
		} catch (EntityTypeNotFoundException $e) {
			throw new EntityCreationException();
		}

		$class->confirmCreationStatus($entity);
	}


	/**
	 * @param IEntity $entity
	 *
	 * @return IEntity
	 * @throws EntityNotFoundException
	 * @throws EntityTypeNotFoundException
	 */
	public function searchDuplicateEntity(IEntity $entity): IEntity {

		try {
			/** @var IEntitiesSearchDuplicate $class */
			$class = $this->getClass(
				self::INTERFACE_ENTITIES, $entity->getType(), IEntitiesSearchDuplicate::class
			);
		} catch (ImplementationNotFoundException $e) {
			throw new EntityNotFoundException();
		}

		$qb = $this->entitiesRequest->getEntitiesSelectSql();
		$class->buildSearchDuplicate($qb, $entity);

		return $this->entitiesRequest->getItemFromRequest($qb);
	}


	/**
	 * @param IEntitiesQueryBuilder $qb
	 * @param float $time
	 */
	public function logSql(IEntitiesQueryBuilder $qb, float $time): void {
		$this->logSql[] = [
			'sql'    => $qb->getSQL(),
			'values' => $qb->getParameters(),
			'time'   => $time
		];
	}


	/**
	 * @param IEntityAccount $account
	 *
	 * @return IEntityAccount
	 * @throws EntityAccountNotFoundException
	 * @throws EntityTypeNotFoundException
	 */
	private function searchDuplicateEntityAccount(IEntityAccount $account): IEntityAccount {

		try {
			/** @var IEntitiesAccountsSearchDuplicate $class */
			$class = $this->getClass(
				self::INTERFACE_ENTITIES_ACCOUNTS, $account->getType(),
				IEntitiesAccountsSearchDuplicate::class
			);
		} catch (ImplementationNotFoundException $e) {
			throw new EntityAccountNotFoundException();
		}

		$qb = $this->entitiesAccountsRequest->getEntitiesAccountsSelectSql();
		$class->buildSearchDuplicate($qb, $account);

		return $this->entitiesAccountsRequest->getItemFromRequest($qb);
	}


	/**
	 * @param string $interface
	 * @param string $type
	 * @param string $implements
	 *
	 * @return stdClass
	 * @throws EntityTypeNotFoundException
	 * @throws ImplementationNotFoundException
	 */
	private function getClass(string $interface, string $type, $implements = '') {
		$this->retrieveClasses();

		foreach ($this->classes as $entityType) {

			if ($entityType->getInterface() !== $interface || $entityType->getType() !== $type) {
				continue;
			}

			try {
				if ($entityType->hasClass()) {
					$class = $entityType->getClass();
				} else {
					$class = OC::$server->query($entityType->getClassName());
					$entityType->setClass($class);
				}
			} catch (QueryException $e) {
				throw new EntityTypeNotFoundException($e->getMessage());
			}

			if ($implements === '') {
				return $class;
			}

			if (!($class instanceof $implements)) {
				throw new ImplementationNotFoundException(
					get_class($class) . ' does not implement ' . $implements
				);
			}

			return $class;
		}

		throw new EntityTypeNotFoundException();
	}


	/**
	 * @param string $interface
	 * @param string $implements
	 *
	 * @return stdClass[]
	 */
	private function getClasses(string $interface, string $implements = ''): array {
		$this->retrieveClasses();

		$classes = [];
		foreach ($this->classes as $entityType) {
			if ($entityType->getInterface() !== $interface) {
				continue;
			}

			try {
				if ($entityType->hasClass()) {
					$class = $entityType->getClass();
				} else {
					$class = OC::$server->query($entityType->getClassName());
					$entityType->setClass($class);
				}
			} catch (QueryException $e) {
				continue;
			}

			if ($implements !== '' && !($class instanceof $implements)) {
				continue;
			}

			$classes[] = $class;
		}

		return $classes;
	}


	/**
	 *
	 */
	private function retrieveClasses(): void {
		if (!empty($this->classes)) {
			return;
		}

		$this->classes = $this->entitiesTypesRequest->getClasses();
	}


	/**
	 *
	 */
	public function __destruct() {
		if (empty($this->logSql)) {
			return;
		}

		$toLog = [
			'date' => date('Y-m-d_H:i:s'),
			'log'  => $this->logSql
		];

		if ($this->config->getSystemValue('entities.log.sql', '0') === '2') {
			$filename = $this->config->getSystemValue('datadirectory') . '/entities_manager.log';
			$temp = fopen($filename, 'a');
			fwrite(
				$temp, json_encode($toLog) . "\n"
			);
			fclose($temp);

			return;
		}

		$this->logger->log(1, json_encode($toLog));
	}

}

