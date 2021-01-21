<?php

/**
 * Database implementation
 */
namespace Ubiquity\db;

use Ubiquity\exceptions\CacheException;
use Ubiquity\db\traits\DatabaseOperationsTrait;
use Ubiquity\exceptions\DBException;
use Ubiquity\db\traits\DatabaseTransactionsTrait;
use Ubiquity\controllers\Startup;
use Ubiquity\db\traits\DatabaseMetadatas;
use Ubiquity\cache\database\DbCache;

/**
 * Ubiquity Generic database class.
 * Ubiquity\db$Database
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.1.4
 *
 */
class Database extends AbstractDatabase {
	use DatabaseOperationsTrait,DatabaseTransactionsTrait,DatabaseMetadatas;
	public $quote;

	/**
	 *
	 * @var \Ubiquity\db\providers\AbstractDbWrapper
	 */
	protected $wrapperObject;

	/**
	 * Constructor
	 *
	 * @param string $dbWrapperClass
	 * @param string $dbName
	 * @param string $serverName
	 * @param string $port
	 * @param string $user
	 * @param string $password
	 * @param array $options
	 * @param boolean|string $cache
	 * @param mixed $pool
	 */
	public function __construct($dbWrapperClass, $dbType, $dbName, $serverName = "127.0.0.1", $port = "3306", $user = "root", $password = "", $options = [ ], $cache = false, $pool = null) {
		$this->setDbWrapperClass ( $dbWrapperClass, $dbType );
		$this->dbName = $dbName;
		$this->serverName = $serverName;
		$this->port = $port;
		$this->user = $user;
		$this->password = $password;
		$this->options = $options;
		if ($cache !== false) {
			if ($cache instanceof \Closure) {
				$this->cache = $cache ();
			} else {
				if (\class_exists ( $cache )) {
					$this->cache = new $cache ();
				} else {
					throw new CacheException ( $cache . " is not a valid value for database cache" );
				}
			}
		}
		if ($pool && (\method_exists ( $this->wrapperObject, 'pool' ))) {
			$this->wrapperObject->setPool ( $pool );
		}
	}

	protected function setDbWrapperClass($dbWrapperClass, $dbType) {
		$this->wrapperObject = new $dbWrapperClass ( $this->dbType = $dbType );
	}

	/**
	 * Creates the Db instance and realize a safe connection.
	 *
	 * @throws DBException
	 * @return boolean
	 */
	public function connect() {
		try {
			$this->_connect ();
			$this->quote = $this->wrapperObject->quote;
			return true;
		} catch ( \Exception $e ) {
			throw new DBException ( $e->getMessage (), $e->getCode (), $e->getPrevious () );
		}
	}

	/**
	 * Starts and returns a database instance corresponding to an offset in config
	 *
	 * @param string $offset
	 * @param array $config Ubiquity config file content
	 * @return \Ubiquity\db\Database|NULL
	 */
	public static function start(string $offset = null, ?array $config = null): ?self {
		$config ??= Startup::$config;
		$db = $offset ? ($config ['database'] [$offset] ?? ($config ['database'] ?? [ ])) : ($config ['database'] ?? [ ]);
		if ($db ['dbName'] !== '') {
			$database = new Database ( $db ['wrapper'] ?? \Ubiquity\db\providers\pdo\PDOWrapper::class, $db ['type'], $db ['dbName'], $db ['serverName'] ?? '127.0.0.1', $db ['port'] ?? 3306, $db ['user'] ?? 'root', $db ['password'] ?? '', $db ['options'] ?? [ ], $db ['cache'] ?? false);
			$database->connect ();
			return $database;
		}
		return null;
	}

	public function quoteValue($value, $type = 2) {
		return $this->wrapperObject->quoteValue ( ( string ) $value, $type );
	}

	public function getUpdateFieldsKeyAndValues($keyAndValues, $fields) {
		$ret = array ();
		foreach ( $fields as $field ) {
			$ret [] = $this->quote . $field . $this->quote . ' = ' . $this->quoteValue ( $keyAndValues [$field] );
		}
		return \implode ( ',', $ret );
	}

	public function getInsertValues($keyAndValues) {
		$ret = array ();
		foreach ( $keyAndValues as $value ) {
			$ret [] = $this->quoteValue ( $value );
		}
		return \implode ( ',', $ret );
	}

	public function getCondition(array $keyValues, $separator = ' AND ') {
		$retArray = array ();
		foreach ( $keyValues as $key => $value ) {
			$retArray [] = $this->quote . $key . $this->quote . " = " . $this->quoteValue ( $value );
		}
		return \implode ( $separator, $retArray );
	}

	public function getSpecificSQL($key, ?array $params = null) {
		switch ($key) {
			case 'groupconcat' :
				return $this->wrapperObject->groupConcat ( $params [0], $params [1] ?? ',');
			case 'tostring' :
				return $this->wrapperObject->toStringOperator ();
		}
	}
}
