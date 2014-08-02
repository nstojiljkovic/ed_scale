TYPO3 Scalable DB
===========

This extension allows horizontal scaling of the TYPO3 database. It is custom developed for cloud-based TYPO3 projects by Essential Dots. This extension is intended for usage with continuous integration procedure, although you can of course configure it manually.

Features:

* ability to define what MySQL database will be used for what tables,
* ability to define separate MySQL databases for read and write operations per table (for master/slave configurations).

## 1. Configuration

Main idea is to split the complete set of TYPO3 tables in few databases. For example:

* cache tables (you could also easily configure caching framework to use some backend other than TYPO3 DB)
* configuration tables (like sys_template, sys_language, tx_scheduler_task...)
* content tables (pages, tt_content...)

In a multi-node setup, each node can use it's own cache and configuration tables. Each node would have it's own cache (depending on the cache warm-up procedure and actual tasks performed by the node), while the configuration tables would be the same on all nodes sharing the same version of the application.

All nodes should share the same configuration for the content tables (where you could easily configure master-slave architecture or use some of the existing cloud MySQL DBaaS).

Complete configuration is done via LocalConfiguration.php. Sample:

```
	'DB_SCALE' => array(
		'cache' => array(
			'allowedOperations' => 'rw',
			'matchTablesRegex' => '/^(cache_|cf_).*/',
			'database' => 'cache_db_name',
			'host' => 'localhost',
			'password' => '...',
			'username' => '...',
		),
		'configuration' => array(
			'allowedOperations' => 'rw',
			'matchTablesPlain' => 'sys_template,sys_language',
			'database' => 'cache_db_name',
			'host' => 'localhost',
			'password' => '...',
			'username' => '...',
		),
		'content_master' => array(
			'allowedOperations' => 'w',
			'matchTablesPlain' => '*',
			'database' => 'content_db_master_name',
			'host' => 'some_remote_server',
			'password' => '...',
			'username' => '...',
		),
		'default' => array(
			'allowedOperations' => 'r',
			'matchTablesPlain' => '*',
			'database' => 'content_db_slave_name',
			'host' => 'some_remote_server',
			'password' => '...',
			'username' => '...',
		)
	),
```

The configuration options are the same as for the default TYPO3 core's $GLOBALS['TYPO3_CONF_VARS']['DB'], with addition of 3 settings:

* allowedOperations - can be r (for read-only), w (for write-only), or rw (for read-write),
* matchTablesPlain - wildcard * or comma separated list of tables,
* matchTablesRegex - regular expression to match the table names.

Configuration labeled 'default' should be put at the very end.

## 2. SQL parsing

In most of the cases SQL parsing is not needed. However, with complex queries extension needs to parse the SQL query in order to determine what DB connection should be used. There are 2 ways how this is performed:

* by parsing the SQL using the required composer package "soundintheory/php-sql-parser" (which should be avoided as this introduces huge performance drop),
* by updating SQL queries with additional easily parsable information in the SQL comments. For example:

```
...
$query = <<<SQL
    # @tables_used=some_table,some_table2;

    SELECT a,b,c
      FROM some_table an_alias
      JOIN (SELECT d, max(f) max_f
              FROM some_table2
             WHERE id = 37
            GROUP BY d) `subqry` ON subqry.d = an_alias.d
     WHERE d > 5;
SQL;

$query = $this->createQuery(); /* @var $query \TYPO3\CMS\Extbase\Persistence\Generic\Query */
$query->statement($sql);
...
```

## 3. Limitations

* SQL joins on tables across different MySQL database servers is not possible

## 4. Support

Please use github issue tracker to submit questions. Of course, feel free to fork and contribute.
