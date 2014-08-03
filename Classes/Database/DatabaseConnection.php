<?php
namespace EssentialDots\EdScale\Database;
use PHPSQL\Parser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Nikola Stojiljkovic, Essential Dots d.o.o. Belgrade
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class DatabaseConnection extends \TYPO3\CMS\Core\Database\DatabaseConnection {

	/**
	 * @var array<\TYPO3\CMS\Core\Database\DatabaseConnection>
	 */
	protected $databaseConnections = array();

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $lastUsedDatabaseConnection = null;

	/**
	 * @var array
	 */
	protected $configuration = array();

	/**
	 * @var array
	 */
	protected $tableNameToConfigurationNameCache = array(
		'r' => [],
		'w' => []
	);

	/**
	 * @var bool
	 */
	protected $debugSQLParsing = TRUE;

	/**
	 * Initialize the database connection
	 *
	 * @return void
	 */
	public function initialize() {
		$this->configuration = is_array($GLOBALS['TYPO3_CONF_VARS']['DB_SCALE']) ? $GLOBALS['TYPO3_CONF_VARS']['DB_SCALE'] : array();
		if (!array_key_exists('default', $this->configuration)) {
			$this->configuration['default'] = $GLOBALS['TYPO3_CONF_VARS']['DB'];
			$this->configuration['default']['allowedOperations'] = 'rw';
			$this->configuration['default']['matchTablesPlain'] = '*';
		}

		foreach ($this->configuration as $configurationName => $configuration) {
			$this->databaseConnections[$configurationName] = new \TYPO3\CMS\Core\Database\DatabaseConnection();
			$databaseConnection = &$this->databaseConnections[$configurationName]; /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->setDatabaseUsername($configuration['host']);
			$databaseConnection->setDatabasePassword($configuration['host']);
			$databaseConnection->setDatabaseHost($configuration['host']);
			$databaseConnection->setDatabaseHost($configuration['host']);
			$databaseConnection->setDatabaseHost($configuration['host']);

			$databaseConnection->setDatabaseName($configuration['database']);
			$databaseConnection->setDatabaseUsername($configuration['username']);
			$databaseConnection->setDatabasePassword($configuration['password']);

			$databaseHost = $configuration['host'];
			if (isset($configuration['port'])) {
				$databaseConnection->setDatabasePort($configuration['port']);
			} elseif (strpos($databaseHost, ':') > 0) {
				list($databaseHost, $databasePort) = explode(':', $databaseHost);
				$databaseConnection->setDatabasePort($databasePort);
			}
			if (isset($configuration['socket'])) {
				$databaseConnection->setDatabaseSocket($configuration['socket']);
			}
			$databaseConnection->setDatabaseHost($databaseHost);
		}

		if (array_key_exists('default', $this->databaseConnections)) {
			$this->lastUsedDatabaseConnection = $this->databaseConnections['default'];
		}
	}

	/**
	 * @param $configurationName
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	public function getConnectionByName($configurationName) {
		return $this->databaseConnections[$configurationName];
	}

	/**
	 * @param string $table
	 * @param string $operation
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnectionForTable($table, $operation = 'r') {
		$this->lastUsedDatabaseConnection = $this->databaseConnections[$this->getDatabaseConnectionNameForTable($table, $operation)];
		return $this->lastUsedDatabaseConnection;
	}

	/**
	 * @param array $tables
	 * @param string $operation
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 * @throws \Exception
	 */
	protected function getDatabaseConnectionForTables($tables, $operation = 'r') {
		$result = null;

		foreach($tables as $table) {
			$databaseConnection = $this->getDatabaseConnectionNameForTable($table, $operation);
			if ($result === null) {
				$result = $databaseConnection;
			} elseif ($result !== $databaseConnection) {
				throw new \Exception("Tables ".implode(', ', $tables)." are not configured to use the same database connection!");
			}
		}

		$this->lastUsedDatabaseConnection = $this->databaseConnections[$result];
		return $this->lastUsedDatabaseConnection;
	}

	/**
	 * @param string $table
	 * @param string $operation
	 * @return $string
	 * @throws \Exception
	 */
	protected function getDatabaseConnectionNameForTable($table, $operation = 'r') {
		if (!array_key_exists($table, $this->tableNameToConfigurationNameCache[$operation])) {
			$foundConfigurationName = '';
			foreach ($this->configuration as $configurationName => $configuration) {
				if (strpos($configuration['allowedOperations'], $operation) === FALSE) {
					continue;
				}
				if (array_key_exists('matchTablesPlain', $configuration)) {
					$rules = GeneralUtility::trimExplode(',', $configuration['matchTablesPlain']);
					foreach ($rules as $rule) {
						if ($rule == $table || $rule = '*') {
							$foundConfigurationName = $configurationName;
							break;
						}
					}
				}
				if (!$foundConfigurationName) {
					if (array_key_exists('matchTablesRegex', $configuration)) {
						if (preg_match($configuration['matchTablesRegex'], $table) === 1) {
							$foundConfigurationName = $configurationName;
						}
					}
				}

				if ($foundConfigurationName) {
					foreach (str_split($configuration['allowedOperations']) as $op) {
						if (!array_key_exists($table, $this->tableNameToConfigurationNameCache[$op])) {
							$this->tableNameToConfigurationNameCache[$op][$table] = $foundConfigurationName;
						}
					}

					break;
				}
			}
			if (!$foundConfigurationName) {
				throw new \Exception('No database connection found for the table '.$table);
			}
		}

		return $this->tableNameToConfigurationNameCache[$operation][$table];
	}

	/**
	 * @param $query
	 * @param int $limit
	 * @param bool $runEverywhere
	 * @return array
	 */
	protected function getTableNamesUsedInQuery($query, $limit = -1, &$runEverywhere = false) {
		$matches = array();
		if (preg_match('/^\s*#\s*@tables_used\s*=\s*(.*)\s*;/msU', $query, $matches)) {
			$tableNames = GeneralUtility::trimExplode(',', $matches[1]);
		} elseif (preg_match('/^(CREATE\s+TABLE|ALTER\s+TABLE|INSERT\s+INTO)\s+([^\s]+)(\s+|\()/msU', $query, $matches)) {
			$tableNames = array($matches[2]);
		} else {
			// no luck, we need to parse the query
			if ($this->debugSQLParsing) {
				$time_start = microtime(true);
			}

			$parser = new Parser($query, false);

			$tableNames = array();
			$this->findTableNames($parser->parsed, $tableNames);

			if ($limit > 0) {
				$tableNames = array_slice($tableNames, 0, $limit);
			}

			if (count($tableNames)==0) {
				if (array_key_exists('SET', $parser->parsed)) {
					$runEverywhere = true;
				}
			}

			if ($this->debugSQLParsing) {
				$time_end = microtime(true);
				$time = $time_end - $time_start;
				$this->getLogger()->log(\TYPO3\CMS\Core\Log\LogLevel::DEBUG, "$time took to parse query: $query");
			}
		}

		return $tableNames;
	}

	/**
	 * @return \TYPO3\CMS\Core\Log\Logger
	 */
	protected function getLogger() {
		/** @var $logManager \TYPO3\CMS\Core\Log\LogManager */
		$logManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager');

		return $logManager->getLogger(get_class($this));
	}

	/**
	 * @param array $conf
	 * @param array $tableNames
	 */
	protected function findTableNames(&$conf, &$tableNames) {
		foreach ($conf as $k => &$v) {
			if ($k === 'table' && is_string($v) && !in_array($v, $tableNames)) {
				$tableNames[] = $v;
			} elseif (is_array($v)) {
				$this->findTableNames($v, $tableNames);
			}
		}
	}

	/************************************
	 *
	 * Query execution
	 *
	 * These functions are the RECOMMENDED DBAL functions for use in your applications
	 * Using these functions will allow the DBAL to use alternative ways of accessing data (contrary to if a query is returned!)
	 * They compile a query AND execute it immediately and then return the result
	 * This principle heightens our ability to create various forms of DBAL of the functions.
	 * Generally: We want to return a result pointer/object, never queries.
	 * Also, having the table name together with the actual query execution allows us to direct the request to other databases.
	 *
	 **************************************/

	/**
	 * Creates and executes an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 * Using this function specifically allows us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Table name
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$insertFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->exec_INSERTquery($table, $fields_values, $no_quote_fields);
	}

	/**
	 * Creates and executes an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->exec_INSERTmultipleRows($table, $fields, $rows, $no_quote_fields);
	}

	/**
	 * Creates and executes an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 * Using this function specifically allow us to handle BLOB and CLOB fields depending on DB
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param array $fields_values Field values as key=>value pairs. Values will be escaped internally. Typically you would fill an array like "$updateFields" with 'fieldname'=>'value' and pass it to this function as argument.
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields);
	}

	/**
	 * Creates and executes a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table Database tablename
	 * @param string $where WHERE clause, eg. "uid=1". NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_DELETEquery($table, $where) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->exec_DELETEquery($table, $where);
	}

	/**
	 * Creates and executes a SELECT SQL-statement
	 * Using this function specifically allow us to handle the LIMIT feature independently of DB.
	 *
	 * @param string $select_fields List of fields to select from the table. This is what comes right after "SELECT ...". Required value.
	 * @param string $from_table Table(s) from which to select. This is what comes right after "FROM ...". Required value.
	 * @param string $where_clause Additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Creates and executes a SELECT query, selecting fields ($select) from two/three tables joined
	 * Use $mm_table together with $local_table or $foreign_table to select over two tables. Or use all three tables to select the full MM-relation.
	 * The JOIN is done with [$local_table].uid <--> [$mm_table].uid_local  / [$mm_table].uid_foreign <--> [$foreign_table].uid
	 * The function is very useful for selecting MM-relations between tables adhering to the MM-format used by TCE (TYPO3 Core Engine). See the section on $GLOBALS['TCA'] in Inside TYPO3 for more details.
	 *
	 * @param string $select Field list for SELECT
	 * @param string $local_table Tablename, local table
	 * @param string $mm_table Tablename, relation table
	 * @param string $foreign_table Tablename, foreign table
	 * @param string $whereClause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself! DO NOT PUT IN GROUP BY, ORDER BY or LIMIT! You have to prepend 'AND ' to this parameter yourself!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param string $limit Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see exec_SELECTquery()
	 * @throws \Exception
	 */
	public function exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $whereClause = '', $groupBy = '', $orderBy = '', $limit = '') {
		$databaseConnection = $this->getDatabaseConnectionForTables(array($local_table, $mm_table, $foreign_table), 'r');
		return $databaseConnection->exec_SELECT_mm_query($select, $local_table, $mm_table, $foreign_table, $whereClause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Executes a select based on input query parts array
	 *
	 * @param array $queryParts Query parts array
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @see exec_SELECTquery()
	 */
	public function exec_SELECT_queryArray($queryParts) {
		return parent::exec_SELECT_queryArray($queryParts);
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND traverse result set and returns array with records in.
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @param string $uidIndexField If set, the result array will carry this field names value as index. Requires that field to be selected of course!
	 * @return array|NULL Array of rows, or NULL in case of SQL error
	 */
	public function exec_SELECTgetRows($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $uidIndexField = '') {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->exec_SELECTgetRows($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, $uidIndexField);
	}

	/**
	 * Creates and executes a SELECT SQL-statement AND gets a result set and returns an array with a single record in.
	 * LIMIT is automatically set to 1 and can not be overridden.
	 *
	 * @param string $select_fields List of fields to select from the table.
	 * @param string $from_table Table(s) from which to select.
	 * @param string $where_clause Optional additional WHERE clauses put in the end of the query. NOTICE: You must escape values in this argument with $this->fullQuoteStr() yourself!
	 * @param string $groupBy Optional GROUP BY field(s), if none, supply blank string.
	 * @param string $orderBy Optional ORDER BY field(s), if none, supply blank string.
	 * @param boolean $numIndex If set, the result will be fetched with sql_fetch_row, otherwise sql_fetch_assoc will be used.
	 * @return array|FALSE|NULL Single row, FALSE on empty result, NULL on error
	 */
	public function exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $numIndex = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $numIndex);
	}

	/**
	 * Counts the number of rows in a table.
	 *
	 * @param string $field Name of the field to use in the COUNT() expression (e.g. '*')
	 * @param string $table Name of the table to count rows for
	 * @param string $where (optional) WHERE statement of the query
	 * @return mixed Number of rows counter (integer) or FALSE if something went wrong (boolean)
	 */
	public function exec_SELECTcountRows($field, $table, $where = '') {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'r');
		return $databaseConnection->exec_SELECTcountRows($field, $table, $where);
	}

	/**
	 * Truncates a table.
	 *
	 * @param string $table Database tablename
	 * @return mixed Result from handler
	 */
	public function exec_TRUNCATEquery($table) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->exec_TRUNCATEquery($table);
	}

	/**************************************
	 *
	 * Query building
	 *
	 **************************************/
	/**
	 * Creates an INSERT SQL-statement for $table from the array with field/value pairs $fields_values.
	 *
	 * @param string $table See exec_INSERTquery()
	 * @param array $fields_values See exec_INSERTquery()
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return string|NULL Full SQL query for INSERT, NULL if $fields_values is empty
	 */
	public function INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->INSERTquery($table, $fields_values, $no_quote_fields);
	}

	/**
	 * Creates an INSERT SQL-statement for $table with multiple rows.
	 *
	 * @param string $table Table name
	 * @param array $fields Field names
	 * @param array $rows Table rows. Each row should be an array with field values mapping to $fields
	 * @param boolean $no_quote_fields See fullQuoteArray()
	 * @return string|NULL Full SQL query for INSERT, NULL if $rows is empty
	 */
	public function INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->INSERTmultipleRows($table, $fields, $rows, $no_quote_fields);
	}

	/**
	 * Creates an UPDATE SQL-statement for $table where $where-clause (typ. 'uid=...') from the array with field/value pairs $fields_values.
	 *
	 *
	 * @param string $table See exec_UPDATEquery()
	 * @param string $where See exec_UPDATEquery()
	 * @param array $fields_values See exec_UPDATEquery()
	 * @param boolean $no_quote_fields
	 * @return string Full SQL query for UPDATE
	 */
	public function UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->UPDATEquery($table, $where, $fields_values, $no_quote_fields);
	}

	/**
	 * Creates a DELETE SQL-statement for $table where $where-clause
	 *
	 * @param string $table See exec_DELETEquery()
	 * @param string $where See exec_DELETEquery()
	 * @return string Full SQL query for DELETE
	 * @throws \InvalidArgumentException
	 */
	public function DELETEquery($table, $where) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->DELETEquery($table, $where);
	}

	/**
	 * Creates a SELECT SQL-statement
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @return string Full SQL query for SELECT
	 */
	public function SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '') {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
	}

	/**
	 * Creates a SELECT SQL-statement to be used as subquery within another query.
	 * BEWARE: This method should not be overriden within DBAL to prevent quoting from happening.
	 *
	 * @param string $select_fields List of fields to select from the table.
	 * @param string $from_table Table from which to select.
	 * @param string $where_clause Conditional WHERE statement
	 * @return string Full SQL query for SELECT
	 */
	public function SELECTsubquery($select_fields, $from_table, $where_clause) {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->SELECTsubquery($select_fields, $from_table, $where_clause);
	}

	/**
	 * Creates a TRUNCATE TABLE SQL-statement
	 *
	 * @param string $table See exec_TRUNCATEquery()
	 * @return string Full SQL query for TRUNCATE TABLE
	 */
	public function TRUNCATEquery($table) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'w');
		return $databaseConnection->TRUNCATEquery($table);
	}

	/**
	 * Returns a WHERE clause that can find a value ($value) in a list field ($field)
	 * For instance a record in the database might contain a list of numbers,
	 * "34,234,5" (with no spaces between). This query would be able to select that
	 * record based on the value "34", "234" or "5" regardless of their position in
	 * the list (left, middle or right).
	 * The value must not contain a comma (,)
	 * Is nice to look up list-relations to records or files in TYPO3 database tables.
	 *
	 * @param string $field Field name
	 * @param string $value Value to find in list
	 * @param string $table Table in which we are searching (for DBAL detection of quoteStr() method)
	 * @return string WHERE clause for a query
	 * @throws \InvalidArgumentException
	 */
	public function listQuery($field, $value, $table) {
		return parent::listQuery($field, $value, $table);
	}

	/**
	 * Returns a WHERE clause which will make an AND or OR search for the words in the $searchWords array in any of the fields in array $fields.
	 *
	 * @param array $searchWords Array of search words
	 * @param array $fields Array of fields
	 * @param string $table Table in which we are searching (for DBAL detection of quoteStr() method)
	 * @param string $constraint How multiple search words have to match ('AND' or 'OR')
	 * @return string WHERE clause for search
	 */
	public function searchQuery($searchWords, $fields, $table, $constraint = self::AND_Constraint) {
		return parent::searchQuery($searchWords, $fields, $table, $constraint);
	}

	/**************************************
	 *
	 * Prepared Query Support
	 *
	 **************************************/
	/**
	 * Creates a SELECT prepared SQL statement.
	 *
	 * @param string $select_fields See exec_SELECTquery()
	 * @param string $from_table See exec_SELECTquery()
	 * @param string $where_clause See exec_SELECTquery()
	 * @param string $groupBy See exec_SELECTquery()
	 * @param string $orderBy See exec_SELECTquery()
	 * @param string $limit See exec_SELECTquery()
	 * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 */
	public function prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array()) {
		$databaseConnection = $this->getDatabaseConnectionForTable($from_table, 'r');
		return $databaseConnection->prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, $input_parameters);
	}

	/**
	 * Creates a SELECT prepared SQL statement based on input query parts array
	 *
	 * @param array $queryParts Query parts array
	 * @param array $input_parameters An array of values with as many elements as there are bound parameters in the SQL statement being executed. All values are treated as \TYPO3\CMS\Core\Database\PreparedStatement::PARAM_AUTOTYPE.
	 * @return \TYPO3\CMS\Core\Database\PreparedStatement Prepared statement
	 */
	public function prepare_SELECTqueryArray(array $queryParts, array $input_parameters = array()) {
		return parent::prepare_SELECTqueryArray($queryParts, $input_parameters);
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query The query to execute
	 * @param array $queryComponents The components of the query to execute
	 * @return \mysqli_stmt|object MySQLi statement / DBAL object
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 */
	public function prepare_PREPAREDquery($query, array $queryComponents) {
		// @todo: check if this works
		// this method seems to be not used in the core atm
		return $this->lastUsedDatabaseConnection->prepare_PREPAREDquery($query, $queryComponents);
	}

	/**************************************
	 *
	 * Various helper functions
	 *
	 * Functions recommended to be used for
	 * - escaping values,
	 * - cleaning lists of values,
	 * - stripping of excess ORDER BY/GROUP BY keywords
	 *
	 **************************************/
	/**
	 * Escaping and quoting values for SQL statements.
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @param boolean $allowNull Whether to allow NULL values
	 * @return string Output string; Wrapped in single quotes and quotes in the string (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function fullQuoteStr($str, $table, $allowNull = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'r');
		return $databaseConnection->fullQuoteStr($str, $table, $allowNull);
	}

	/**
	 * Will fullquote all values in the one-dimensional array so they are ready to "implode" for an sql query.
	 *
	 * @param array $arr Array with values (either associative or non-associative array)
	 * @param string $table Table name for which to quote
	 * @param boolean|array $noQuote List/array of keys NOT to quote (eg. SQL functions) - ONLY for associative arrays
	 * @param boolean $allowNull Whether to allow NULL values
	 * @return array The input array with the values quoted
	 * @see cleanIntArray()
	 */
	public function fullQuoteArray($arr, $table, $noQuote = FALSE, $allowNull = FALSE) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'r');
		return $databaseConnection->fullQuoteArray($arr, $table, $noQuote, $allowNull);
	}

	/**
	 * Substitution for PHP function "addslashes()"
	 * Use this function instead of the PHP addslashes() function when you build queries - this will prepare your code for DBAL.
	 * NOTICE: You must wrap the output of this function in SINGLE QUOTES to be DBAL compatible. Unless you have to apply the single quotes yourself you should rather use ->fullQuoteStr()!
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to quote string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @return string Output string; Quotes (" / ') and \ will be backslashed (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function quoteStr($str, $table) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'r');
		return $databaseConnection->quoteStr($str, $table);
	}

	/**
	 * Escaping values for SQL LIKE statements.
	 *
	 * @param string $str Input string
	 * @param string $table Table name for which to escape string. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how to quote the string!).
	 * @return string Output string; % and _ will be escaped with \ (or otherwise based on DBAL handler)
	 * @see quoteStr()
	 */
	public function escapeStrForLike($str, $table) {
		return parent::escapeStrForLike($str, $table);
	}

	/**
	 * Will convert all values in the one-dimensional array to integers.
	 * Useful when you want to make sure an array contains only integers before imploding them in a select-list.
	 *
	 * @param array $arr Array with values
	 * @return array The input array with all values cast to (int)
	 * @see cleanIntList()
	 */
	public function cleanIntArray($arr) {
		return parent::cleanIntArray($arr);
	}

	/**
	 * Will force all entries in the input comma list to integers
	 * Useful when you want to make sure a commalist of supposed integers really contain only integers; You want to know that when you don't trust content that could go into an SQL statement.
	 *
	 * @param string $list List of comma-separated values which should be integers
	 * @return string The input list but with every value cast to (int)
	 * @see cleanIntArray()
	 */
	public function cleanIntList($list) {
		return parent::cleanIntList($list);
	}

	/**
	 * Removes the prefix "ORDER BY" from the input string.
	 * This function is used when you call the exec_SELECTquery() function and want to pass the ORDER BY parameter by can't guarantee that "ORDER BY" is not prefixed.
	 * Generally; This function provides a work-around to the situation where you cannot pass only the fields by which to order the result.
	 *
	 * @param string $str eg. "ORDER BY title, uid
	 * @return string eg. "title, uid
	 * @see exec_SELECTquery(), stripGroupBy()
	 */
	public function stripOrderBy($str) {
		return parent::stripOrderBy($str);
	}

	/**
	 * Removes the prefix "GROUP BY" from the input string.
	 * This function is used when you call the SELECTquery() function and want to pass the GROUP BY parameter by can't guarantee that "GROUP BY" is not prefixed.
	 * Generally; This function provides a work-around to the situation where you cannot pass only the fields by which to order the result.
	 *
	 * @param string $str eg. "GROUP BY title, uid
	 * @return string eg. "title, uid
	 * @see exec_SELECTquery(), stripOrderBy()
	 */
	public function stripGroupBy($str) {
		return parent::stripGroupBy($str);
	}

	/**
	 * Takes the last part of a query, eg. "... uid=123 GROUP BY title ORDER BY title LIMIT 5,2" and splits each part into a table (WHERE, GROUPBY, ORDERBY, LIMIT)
	 * Work-around function for use where you know some userdefined end to an SQL clause is supplied and you need to separate these factors.
	 *
	 * @param string $str Input string
	 * @return array
	 */
	public function splitGroupOrderLimit($str) {
		return parent::splitGroupOrderLimit($str);
	}

	/**
	 * Returns the date and time formats compatible with the given database table.
	 *
	 * @param string $table Table name for which to return an empty date. Just enter the table that the field-value is selected from (and any DBAL will look up which handler to use and then how date and time should be formatted).
	 * @return array
	 */
	public function getDateTimeFormats($table) {
		$databaseConnection = $this->getDatabaseConnectionForTable($table, 'r');
		return $databaseConnection->getDateTimeFormats($table);
	}

	/**************************************
	 *
	 * MySQL(i) wrapper functions
	 * (For use in your applications)
	 *
	 **************************************/
	/**
	 * Executes query
	 * MySQLi query() wrapper function
	 * Beware: Use of this method should be avoided as it is experimentally supported by DBAL. You should consider
	 * using exec_SELECTquery() and similar methods instead.
	 *
	 * @param string $query Query to execute
	 * @param string $fallbackConnection
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function sql_query($query, $fallbackConnection = 'default') {
		$runEverywhere = false;
		$tables = $this->getTableNamesUsedInQuery($query, -1, $runEverywhere);
		// @todo: should we maybe detect the type of query used (r or w)
		$databaseConnection = $this->getDatabaseConnectionForTables($tables, 'r');
		if ($runEverywhere) {
			$result = null;
			foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
				$result = $databaseConnection->sql_query($query);
			}
			return $result;
		} else {
			if (is_null($databaseConnection)) {
				$databaseConnection = $this->databaseConnections[$fallbackConnection];
				$this->lastUsedDatabaseConnection = $databaseConnection;
			}
			return $databaseConnection->sql_query($query);
		}
	}

	/**
	 * Returns the error status on the last query() execution
	 *
	 * @return string MySQLi error string.
	 */
	public function sql_error() {
		return $this->lastUsedDatabaseConnection->sql_error();
	}

	/**
	 * Returns the error number on the last query() execution
	 *
	 * @return integer MySQLi error number
	 */
	public function sql_errno() {
		return $this->lastUsedDatabaseConnection->sql_errno();
	}

	/**
	 * Returns the number of selected rows.
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return integer Number of resulting rows
	 */
	public function sql_num_rows($res) {
		return $this->lastUsedDatabaseConnection->sql_num_rows($res);
	}

	/**
	 * Returns an associative array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * MySQLi fetch_assoc() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Associative array of result row.
	 */
	public function sql_fetch_assoc($res) {
		return $this->lastUsedDatabaseConnection->sql_fetch_assoc($res);
	}

	/**
	 * Returns an array that corresponds to the fetched row, or FALSE if there are no more rows.
	 * The array contains the values in numerical indices.
	 * MySQLi fetch_row() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return array|boolean Array with result rows.
	 */
	public function sql_fetch_row($res) {
		return $this->lastUsedDatabaseConnection->sql_fetch_row($res);
	}

	/**
	 * Free result memory
	 * free_result() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function sql_free_result($res) {
		return $this->lastUsedDatabaseConnection->sql_free_result($res);
	}

	/**
	 * Get the ID generated from the previous INSERT operation
	 *
	 * @return integer The uid of the last inserted record.
	 */
	public function sql_insert_id() {
		return $this->lastUsedDatabaseConnection->sql_insert_id();
	}

	/**
	 * Returns the number of rows affected by the last INSERT, UPDATE or DELETE query
	 *
	 * @return integer Number of rows affected by last query
	 */
	public function sql_affected_rows() {
		return $this->lastUsedDatabaseConnection->sql_affected_rows();
	}

	/**
	 * Move internal result pointer
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param integer $seek Seek result number.
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function sql_data_seek($res, $seek) {
		return $this->lastUsedDatabaseConnection->sql_data_seek($res, $seek);
	}

	/**
	 * Get the type of the specified field in a result
	 * mysql_field_type() wrapper function
	 *
	 * @param boolean|\mysqli_result|object $res MySQLi result object / DBAL object
	 * @param integer $pointer Field index.
	 * @return string Returns the name of the specified field index, or FALSE on error
	 */
	public function sql_field_type($res, $pointer) {
		return $this->lastUsedDatabaseConnection->sql_field_type($res, $pointer);
	}

	/**
	 * Open a (persistent) connection to a MySQL server
	 *
	 * @param string $host Deprecated since 6.1, will be removed in two versions. Database host IP/domain[:port]
	 * @param string $username Deprecated since 6.1, will be removed in two versions. Username to connect with.
	 * @param string $password Deprecated since 6.1, will be removed in two versions. Password to connect with.
	 * @return boolean|void
	 * @throws \RuntimeException
	 */
	public function sql_pconnect($host = NULL, $username = NULL, $password = NULL) {
		$result = true;
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$result = $result && !is_null($databaseConnection->sql_pconnect($host, $username, $password));
		}

		// note that parent implementation returns resource, but the signature denotes it should be a boolean (and that's how it's used in the core)
		return $result;
	}

	/**
	 * Select a SQL database
	 *
	 * @param string $TYPO3_db Deprecated since 6.1, will be removed in two versions. Database to connect to.
	 * @return boolean Returns TRUE on success or FALSE on failure.
	 */
	public function sql_select_db($TYPO3_db = NULL) {
		$result = true;
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$result = $result && $databaseConnection->sql_select_db($TYPO3_db);
		}

		return $result;
	}

	/**************************************
	 *
	 * SQL admin functions
	 * (For use in the Install Tool and Extension Manager)
	 *
	 **************************************/
	/**
	 * Listing databases from current MySQL connection. NOTICE: It WILL try to select those databases and thus break selection of current database.
	 * This is only used as a service function in the (1-2-3 process) of the Install Tool.
	 * In any case a lookup should be done in the _DEFAULT handler DBMS then.
	 * Use in Install Tool only!
	 *
	 * @return array Each entry represents a database name
	 * @throws \Exception
	 */
	public function admin_get_dbs() {
		$databaseConnection = $this->getDefaultConnection();
		return $databaseConnection->admin_get_dbs();
	}

	/**
	 * Returns the list of tables from the default database, TYPO3_db (quering the DBMS)
	 * In a DBAL this method should 1) look up all tables from the DBMS  of
	 * the _DEFAULT handler and then 2) add all tables *configured* to be managed by other handlers
	 *
	 * @return array Array with tablenames as key and arrays with status information as value
	 */
	public function admin_get_tables() {
		$result = array();
		foreach($this->databaseConnections as $configurationName => $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$tables = $databaseConnection->admin_get_tables();
			foreach ($tables as $tableName => $tableConf) {
				$designatedConnectionNameForTable = $this->getDatabaseConnectionNameForTable($tableName, 'w');
				if ($designatedConnectionNameForTable === $configurationName && !array_key_exists($tableName, $result)) {
					$result[$tableName] = $tableConf;
				}
			}
		}

		return $result;
	}

	/**
	 * Returns information about each field in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * @param string $tableName Table name
	 * @return array Field information in an associative array with fieldname => field row
	 */
	public function admin_get_fields($tableName) {
		$databaseConnection = $this->getDatabaseConnectionForTable($tableName, 'r');
		return $databaseConnection->admin_get_fields($tableName);
	}

	/**
	 * Returns information about each index key in the $table (quering the DBMS)
	 * In a DBAL this should look up the right handler for the table and return compatible information
	 *
	 * @param string $tableName Table name
	 * @return array Key information in a numeric array
	 */
	public function admin_get_keys($tableName) {
		$databaseConnection = $this->getDatabaseConnectionForTable($tableName, 'r');
		return $databaseConnection->admin_get_keys($tableName);
	}

	/**
	 * Returns information about the character sets supported by the current DBM
	 * This function is important not only for the Install Tool but probably for
	 * DBALs as well since they might need to look up table specific information
	 * in order to construct correct queries. In such cases this information should
	 * probably be cached for quick delivery.
	 *
	 * This is used by the Install Tool to convert tables with non-UTF8 charsets
	 * Use in Install Tool only!
	 *
	 * @return array Array with Charset as key and an array of "Charset", "Description", "Default collation", "Maxlen" as values
	 */
	public function admin_get_charsets() {
		$result = null;
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$charsets = $databaseConnection->admin_get_charsets();
			if (is_null($result)) {
				$result = $charsets;
			} else {
				foreach($result as $charset => &$conf) {
					if (!array_key_exists($charset, $charsets)) {
						unset($result[$charset]); // filter our charsets which are not supported by all DB connections!
					}
				}
			}
		}

		return $result;
	}

	/**
	 * mysqli() wrapper function, used by the Install Tool and EM for all queries regarding management of the database!
	 *
	 * @param string $query Query to execute
	 * @param string $fallbackConnection
	 * @return boolean|\mysqli_result|object MySQLi result object / DBAL object
	 */
	public function admin_query($query, $fallbackConnection = 'default') {
		$runEverywhere = false;
		$tables = $this->getTableNamesUsedInQuery($query, -1, $runEverywhere);
		$databaseConnection = $this->getDatabaseConnectionForTables($tables, 'w');
		if ($runEverywhere) {
			$result = null;
			foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
				$result = $databaseConnection->admin_query($query);
			}
			return $result;
		} else {
			if (is_null($databaseConnection)) {
				$databaseConnection = $this->databaseConnections[$fallbackConnection];
				$this->lastUsedDatabaseConnection = $databaseConnection;
			}
			return $databaseConnection->admin_query($query);
		}
	}

	/******************************
	 *
	 * Connect handling
	 *
	 ******************************/

	/**
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDefaultConnection() {
		if (defined('PHPUnit_MAIN_METHOD') && count($this->databaseConnections)>1) {
			if (array_key_exists('DB_SCALE_PHPUNIT', $GLOBALS['TYPO3_CONF_VARS'])) {
				$GLOBALS['TYPO3_CONF_VARS']['DB_SCALE'] = array('default' => $GLOBALS['TYPO3_CONF_VARS']['DB_SCALE_PHPUNIT']);
			} else {
				$GLOBALS['TYPO3_CONF_VARS']['DB_SCALE'] = array('default' => $this->configuration['default']);
			}
			$this->databaseConnections = array();
			$this->tableNameToConfigurationNameCache = [
				'r' => [],
				'w' => []
			];
			$this->initialize();
		}
		$databaseConnection = $this->databaseConnections['default']; /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
		if (is_null($databaseConnection)) {
			$this->initialize();
			$databaseConnection = $this->databaseConnections['default'];
		}
		return $databaseConnection;
	}

	/**
	 * Set database host
	 *
	 * @param string $host
	 */
	public function setDatabaseHost($host = 'localhost') {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabaseHost($host);
	}

	/**
	 * Set database port
	 *
	 * @param integer $port
	 */
	public function setDatabasePort($port = 3306) {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabasePort($port);
	}

	/**
	 * Set database socket
	 *
	 * @param string|NULL $socket
	 */
	public function setDatabaseSocket($socket = NULL) {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabaseSocket($socket);
	}

	/**
	 * Set database name
	 *
	 * @param string $name
	 */
	public function setDatabaseName($name) {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabaseName($name);
	}

	/**
	 * Set database username
	 *
	 * @param string $username
	 */
	public function setDatabaseUsername($username) {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabaseUsername($username);
	}

	/**
	 * Set database password
	 *
	 * @param string $password
	 */
	public function setDatabasePassword($password) {
		$databaseConnection = $this->getDefaultConnection();
		$databaseConnection->setDatabasePassword($password);
	}

	/**
	 * Set persistent database connection
	 *
	 * @param boolean $persistentDatabaseConnection
	 * @see http://php.net/manual/de/mysqli.persistconns.php
	 */
	public function setPersistentDatabaseConnection($persistentDatabaseConnection) {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->setPersistentDatabaseConnection($persistentDatabaseConnection);
		}
	}

	/**
	 * Set connection compression. Might be an advantage, if SQL server is not on localhost
	 *
	 * @param bool $connectionCompression TRUE if connection should be compressed
	 */
	public function setConnectionCompression($connectionCompression) {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->setConnectionCompression($connectionCompression);
		}
	}

	/**
	 * Set commands to be fired after connection was established
	 *
	 * @param array $commands List of SQL commands to be executed after connect
	 */
	public function setInitializeCommandsAfterConnect(array $commands) {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->setInitializeCommandsAfterConnect($commands);
		}
	}

	/**
	 * Set the charset that should be used for the MySQL connection.
	 * The given value will be passed on to mysqli_set_charset().
	 *
	 * The default value of this setting is utf8.
	 *
	 * @param string $connectionCharset The connection charset that will be passed on to mysqli_set_charset() when connecting the database. Default is utf8.
	 * @return void
	 */
	public function setConnectionCharset($connectionCharset = 'utf8') {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->setConnectionCharset($connectionCharset);
		}
	}

	/**
	 * Connects to database for TYPO3 sites:
	 *
	 * @param string $host Deprecated since 6.1, will be removed in two versions Database. host IP/domain[:port]
	 * @param string $username Deprecated since 6.1, will be removed in two versions. Username to connect with
	 * @param string $password Deprecated since 6.1, will be removed in two versions. Password to connect with
	 * @param string $db Deprecated since 6.1, will be removed in two versions. Database name to connect to
	 * @throws \RuntimeException
	 * @throws \UnexpectedValueException
	 * @internal param string $user Username to connect with.
	 * @return void
	 */
	public function connectDB($host = NULL, $username = NULL, $password = NULL, $db = NULL) {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->connectDB();
		}
	}

	/**
	 * Checks if database is connected
	 *
	 * @return boolean
	 */
	public function isConnected() {
		$result = true;
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$result = $result && $databaseConnection->isConnected();
		}
		return $result;
	}

	/**
	 * Returns current database handle
	 *
	 * @return \mysqli|NULL
	 */
	public function getDatabaseHandle() {
		return $this->lastUsedDatabaseConnection->getDatabaseHandle();
	}

	/**
	 * Set current database handle, usually \mysqli
	 *
	 * @param \mysqli $handle
	 * @throws \Exception
	 */
	public function setDatabaseHandle($handle) {
		throw new \Exception("Extension ed_scale doesn't support explicit setting of the database handle atm.");
	}

	/******************************
	 *
	 * Debugging
	 *
	 ******************************/
	/**
	 * Debug function: Outputs error if any
	 *
	 * @param string $func Function calling debug()
	 * @param string $query Last query if not last built query
	 * @return void
	 */
	public function debug($func, $query = '') {
		$this->lastUsedDatabaseConnection->debug($func, $query);
	}

	/**
	 * Checks if record set is valid and writes debugging information into devLog if not.
	 *
	 * @param boolean|\mysqli_result|object MySQLi result object / DBAL object
	 * @return boolean TRUE if the  record set is valid, FALSE otherwise
	 */
	public function debug_check_recordset($res) {
		return $this->lastUsedDatabaseConnection->debug_check_recordset($res);
	}

	/**
	 * Serialize destructs current connection
	 *
	 * @return array All protected properties that should be saved
	 */
	public function __sleep() {
		foreach($this->databaseConnections as $databaseConnection) { /** @var $databaseConnection \TYPO3\CMS\Core\Database\DatabaseConnection */
			$databaseConnection->__sleep();
		}
		$this->disconnectIfConnected();
		return array(
			'debugOutput',
			'explainOutput',
//			'databaseHost',
//			'databasePort',
//			'databaseSocket',
//			'databaseName',
//			'databaseUsername',
//			'databaseUserPassword',
			'configuration',
			'persistentDatabaseConnection',
			'connectionCompression',
			'initializeCommandsAfterConnect',
			'default_charset',
		);
	}

} 