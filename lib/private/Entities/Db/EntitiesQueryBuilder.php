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


namespace OC\Entities\Db;


use daita\NcSmallPhpTools\Db\ExtendedQueryBuilder;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use OC;
use OC\SystemConfig;
use OCP\Entities\IEntitiesQueryBuilder;
use OCP\IDBConnection;
use OCP\ILogger;


class EntitiesQueryBuilder extends ExtendedQueryBuilder implements IEntitiesQueryBuilder {


	/** @var CoreRequestBuilder */
	private $logSql = false;


	/**
	 * CoreRequestBuilder constructor.
	 *
	 * @param IDBConnection $connection
	 * @param SystemConfig $config
	 * @param ILogger $logger
	 * @param bool $logSql
	 */
	public function __construct(
		IDBConnection $connection, SystemConfig $config, ILogger $logger, $logSql = false
	) {
		$this->logSql = $logSql;
		parent::__construct($connection, $config, $logger);
	}


	/**
	 * @return Statement|int
	 */
	public function execute() {
		if ($this->logSql) {
			$time1 = microtime(true);
		}

		$result = parent::execute();

		if ($this->logSql) {
			$time2 = microtime(true);
			OC::$server->getEntitiesManager()
					   ->logSql($this, ($time2 - $time1));
		}

		return $result;
	}

	/**
	 * Limit the request to the Interface
	 *
	 * @param string $interface
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToInterface(string $interface): IEntitiesQueryBuilder {
		$this->limitToDBField('interface', $interface, false);

		return $this;
	}


	/**
	 * Limit the request to the Type
	 *
	 * @param string $type
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToType(string $type): IEntitiesQueryBuilder {
		$this->limitToDBField('type', $type, false);

		return $this;
	}


	/**
	 * Limit the request to the OwnerId
	 *
	 * @param string $ownerId
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToOwnerId(string $ownerId): IEntitiesQueryBuilder {
		$this->limitToDBField('owner_id', $ownerId, false);

		return $this;
	}


	/**
	 * Limit the request to the Name
	 *
	 * @param string $name
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToName(string $name): IEntitiesQueryBuilder {
		$this->limitToDBField('name', $name, false);

		return $this;
	}


	/**
	 * @param string $like
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function searchInName(string $like): IEntitiesQueryBuilder {
		$this->searchInDBField('name', $like);

		return $this;
	}


	/**
	 * @param string $account
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToAccount(string $account): IEntitiesQueryBuilder {
		$this->limitToDBField('account', $account, false);

		return $this;
	}


	/**
	 * @param string $like
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function searchInAccount(string $like): IEntitiesQueryBuilder {
		$this->searchInDBField('account', $like);

		return $this;
	}


	/**
	 * @param string $accountId
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToAccountId(string $accountId): IEntitiesQueryBuilder {
		$this->limitToDBField('account_id', $accountId, false);

		return $this;
	}


	/**
	 * @param string $entityId
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function limitToEntityId(string $entityId): IEntitiesQueryBuilder {
		$this->limitToDBField('entity_id', $entityId, false);

		return $this;
	}


	/**
	 * @param string $fieldEntityId
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function leftJoinEntity(string $fieldEntityId = 'entity_id'): IEntitiesQueryBuilder {
		if ($this->getType() !== QueryBuilder::SELECT) {
			return $this;
		}

		$pf = CoreRequestBuilder::LEFT_JOIN_PREFIX_ENTITIES;
		$expr = $this->expr();
		$this->selectAlias('lj_e.id', $pf . 'id')
			 ->selectAlias('lj_e.type', $pf . 'type')
			 ->selectAlias('lj_e.owner_id', $pf . 'owner_id')
			 ->selectAlias('lj_e.visibility', $pf . 'visibility')
			 ->selectAlias('lj_e.access', $pf . 'access')
			 ->selectAlias('lj_e.name', $pf . 'name')
			 ->selectAlias('lj_e.creation', $pf . 'creation')
			 ->leftJoin(
				 $this->getDefaultSelectAlias(), CoreRequestBuilder::TABLE_ENTITIES, 'lj_e',
				 $expr->eq($this->getDefaultSelectAlias() . '.' . $fieldEntityId, 'lj_e.id')
			 );

		return $this;
	}


	/**
	 * @param string $fieldOwnerId
	 *
	 * @return IEntitiesQueryBuilder
	 */
	public function leftJoinEntityAccount(string $fieldOwnerId = 'account_id'
	): IEntitiesQueryBuilder {
		if ($this->getType() !== QueryBuilder::SELECT) {
			return $this;
		}

		$pf = CoreRequestBuilder::LEFT_JOIN_PREFIX_ENTITIES_ACCOUNT;
		$expr = $this->expr();
		$this->selectAlias('lj_ea.id', $pf . 'id')
			 ->selectAlias('lj_ea.type', $pf . 'type')
			 ->selectAlias('lj_ea.account', $pf . 'account')
			 ->selectAlias('lj_ea.creation', $pf . 'creation')
			 ->leftJoin(
				 $this->getDefaultSelectAlias(), CoreRequestBuilder::TABLE_ENTITIES_ACCOUNTS,
				 'lj_ea',
				 $expr->eq($this->getDefaultSelectAlias() . '.' . $fieldOwnerId, 'lj_ea.id')
			 );

		return $this;
	}

}

