<?php
/**
 * MeekroDB - Modernisiert für PHP 8.x
 * Ursprünglich von Sergey Tsalkov
 * 
 * @package MeekroDB
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class MeekroDBException extends Exception {
	protected string $query = '';

	public function __construct(string $message = '', string$query = '') {
		parent::__construct($message);
		$this->query =$query;
	}

	public function getQuery(): string { 
		return $this->query; 
	}
}

#[AllowDynamicProperties]
class DB {
	// initial connection
	public static string $dbName = '';
	public static string $user = '';
	public static string $password = '';
	public static string $host = 'localhost';
	public static ?int $port = null;
	public static string $encoding = 'utf8mb4';

	// configure workings
	public static string $queryMode = 'queryAllRows';
	public static string $param_char = '%';
	public static $success_handler = false;
	public static $error_handler = true;
	public static bool $throw_exception_on_error = false;
	public static $nonsql_error_handler = null;
	public static bool $throw_exception_on_nonsql_error = false;

	// internal
	protected static ?MeekroDB $mdb = null;

	public static function getMDB(): MeekroDB {
		$mdb = self::$mdb;

		if ($mdb === null) {
			$mdb = self::$mdb = new MeekroDB();
		}

		if ($mdb->queryMode !== self::$queryMode) $mdb->queryMode = self::$queryMode;
		if ($mdb->param_char !== self::$param_char) $mdb->param_char = self::$param_char;
		if ($mdb->success_handler !== self::$success_handler) $mdb->success_handler = self::$success_handler;
		if ($mdb->error_handler !== self::$error_handler) $mdb->error_handler = self::$error_handler;
		if ($mdb->throw_exception_on_error !== self::$throw_exception_on_error) $mdb->throw_exception_on_error = self::$throw_exception_on_error;
		if ($mdb->nonsql_error_handler !== self::$nonsql_error_handler) $mdb->nonsql_error_handler = self::$nonsql_error_handler;
		if ($mdb->throw_exception_on_nonsql_error !== self::$throw_exception_on_nonsql_error) $mdb->throw_exception_on_nonsql_error = self::$throw_exception_on_nonsql_error;

		return $mdb;
	}

	public static function query(...$args) { return call_user_func_array([self::getMDB(), 'query'],$args); }
	public static function quickPrepare(...$args) { return call_user_func_array([self::getMDB(), 'quickPrepare'],$args); }
	public static function queryFirstRow(...$args) { return call_user_func_array([self::getMDB(), 'queryFirstRow'],$args); }
	public static function queryOneRow(...$args) { return call_user_func_array([self::getMDB(), 'queryOneRow'],$args); }
	public static function queryFirstList(...$args) { return call_user_func_array([self::getMDB(), 'queryFirstList'],$args); }
	public static function queryOneList(...$args) { return call_user_func_array([self::getMDB(), 'queryOneList'],$args); }
	public static function queryFirstColumn(...$args) { return call_user_func_array([self::getMDB(), 'queryFirstColumn'],$args); }
	public static function queryOneColumn(...$args) { return call_user_func_array([self::getMDB(), 'queryOneColumn'],$args); }
	public static function queryFirstField(...$args) { return call_user_func_array([self::getMDB(), 'queryFirstField'],$args); }
	public static function queryOneField(...$args) { return call_user_func_array([self::getMDB(), 'queryOneField'],$args); }
	public static function queryRaw(...$args) { return call_user_func_array([self::getMDB(), 'queryRaw'],$args); }
	public static function queryNull(...$args) { return call_user_func_array([self::getMDB(), 'queryNull'],$args); }
	public static function queryBuf(...$args) { return call_user_func_array([self::getMDB(), 'queryBuf'],$args); }
	public static function queryUnbuf(...$args) { return call_user_func_array([self::getMDB(), 'queryUnbuf'],$args); }

	public static function insert(...$args) { return call_user_func_array([self::getMDB(), 'insert'],$args); }
	public static function insertIgnore(...$args) { return call_user_func_array([self::getMDB(), 'insertIgnore'],$args); }
	public static function insertUpdate(...$args) { return call_user_func_array([self::getMDB(), 'insertUpdate'],$args); }
	public static function replace(...$args) { return call_user_func_array([self::getMDB(), 'replace'],$args); }
	public static function update(...$args) { return call_user_func_array([self::getMDB(), 'update'],$args); }
	public static function delete(...$args) { return call_user_func_array([self::getMDB(), 'delete'],$args); }

	public static function insertId(...$args) { return call_user_func_array([self::getMDB(), 'insertId'],$args); }
	public static function count(...$args) { return call_user_func_array([self::getMDB(), 'count'],$args); }
	public static function affectedRows(...$args) { return call_user_func_array([self::getMDB(), 'affectedRows'],$args); }

	public static function useDB(...$args) { return call_user_func_array([self::getMDB(), 'useDB'],$args); }
	public static function startTransaction(...$args) { return call_user_func_array([self::getMDB(), 'startTransaction'],$args); }
	public static function commit(...$args) { return call_user_func_array([self::getMDB(), 'commit'],$args); }
	public static function rollback(...$args) { return call_user_func_array([self::getMDB(), 'rollback'],$args); }
	public static function tableList(...$args) { return call_user_func_array([self::getMDB(), 'tableList'],$args); }
	public static function columnList(...$args) { return call_user_func_array([self::getMDB(), 'columnList'],$args); }

	public static function sqlEval(...$args) { return call_user_func_array([self::getMDB(), 'sqlEval'],$args); }
	public static function nonSQLError(...$args) { return call_user_func_array([self::getMDB(), 'nonSQLError'],$args); }

	public static function debugMode($handler = true): void {
		self::$success_handler =$handler;
	}
}

#[AllowDynamicProperties]
class MeekroDB {
	public string $dbName = '';
	public string $user = '';
	public string $password = '';
	public string $host = 'localhost';
	public ?int $port = null;
	public string $encoding = 'utf8mb4';

	public string $queryMode = 'queryAllRows';
	public string $param_char = '%';
	public $success_handler = false;
	public $error_handler = true;
	public bool $throw_exception_on_error = false;
	public $nonsql_error_handler = null;
	public bool $throw_exception_on_nonsql_error = false;

	public ?mysqli $internal_mysql = null;
	public int $insert_id = 0;
	public ?int $num_rows = 0;
	public int $affected_rows = 0;
	public $queryResult = null;
	public ?string $queryResultType = null;
	public ?string $old_db = null;
	public ?string $current_db = null;

	public function __construct(?string $host = null, ?string $user = null, ?string $password = null, ?string $dbName = null, ?int $port = null, ?string $encoding = null) {$this->host = $host ?? DB::$host;
		$this->user = $user ?? DB::$user;
		$this->password = $password ?? DB::$password;
		$this->dbName = $dbName ?? DB::$dbName;
		$this->port = $port ?? DB::$port;
		$this->encoding = $encoding ?? DB::$encoding;
	}

	public function get(): mysqli {
		$mysql =$this->internal_mysql;

		if ($mysql === null) {
			if (!$this->port) {
				$defaultPort = ini_get('mysqli.default_port');$this->port = $defaultPort ? (int)$defaultPort : 3306;
			}
			$this->current_db =$this->dbName;
			
			mysqli_report(MYSQLI_REPORT_OFF);
			$mysql = new mysqli($this->host, $this->user,$this->password, $this->dbName,$this->port);

			if ($mysql->connect_error) {$this->nonSQLError('Unable to connect to MySQL server! Error: ' . $mysql->connect_error);
			}

			$mysql->set_charset($this->encoding);
			$this->internal_mysql =$mysql;
		}

		return $mysql;
	}

	public function nonSQLError(string $message): void {
		if ($this->throw_exception_on_nonsql_error) {
			throw new MeekroDBException($message);
		}

		$error_handler = is_callable($this->nonsql_error_handler) ?$this->nonsql_error_handler : 'meekrodb_error_handler';

		call_user_func($error_handler, [
			'type' => 'nonsql',
			'error' => $message
		]);
	}

	public function debugMode($handler = true): void {
		$this->success_handler =$handler;
	}

	public function insertId(): int { return $this->insert_id; }
	public function affectedRows(): int { return $this->affected_rows; }
	public function count(...$args) { return call_user_func_array([$this, 'numRows'],$args); }
	public function numRows(): ?int { return $this->num_rows; }

	public function useDB($dbName) { return $this->setDB($dbName); }
	public function setDB(string $dbName): void {
		$db =$this->get();
		$this->old_db =$this->current_db;
		if (!$db->select_db($dbName)) {$this->nonSQLError("Unable to set database to $dbName");
		}
		$this->current_db =$dbName;
	}

	public function startTransaction(): void {
		$this->queryNull('START TRANSACTION');
	}

	public function commit(): void {
		$this->queryNull('COMMIT');
	}

	public function rollback(): void {
		$this->queryNull('ROLLBACK');
	}

	public function escape(string $str): string {
		$db =$this->get();
		return $db->real_escape_string($str);
	}

	public function sanitize($value): string {
		if (is_object($value) && ($value instanceof MeekroDBEval)) {
			$value =$value->text;
		} else {
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}

			if (is_string($value)) {$value = "'" . $this->escape($value) . "'";
			} else if (is_null($value)) {$value = 'NULL';
			} else if (is_bool($value)) {
				$value = ($value ? '1' : '0');
			}
		}

		return (string)$value;
	}

	protected function formatTableName(string $table): string {$table = str_replace('`', '', $table);
		if (strpos($table, '.')) {
			[$table_db, $table_table] = explode('.', $table, 2);
			$table = "`$table_db`.`$table_table`";
		} else {
			$table = "`$table`";
		}

		return $table;
	}

	protected function prependCall(callable $function, array $args, $prepend) {
		array_unshift($args, $prepend);
		return call_user_func_array($function, $args);
	}

	protected function wrapStr($strOrArray, string $wrapChar, bool $escape = false) {
		if (!is_array($strOrArray)) {
			if ($escape) return $wrapChar . $this->escape((string)$strOrArray) . $wrapChar;
			else return $wrapChar . $strOrArray . $wrapChar;
		} else {
			$R = [];
			foreach ($strOrArray as $element) {
				$R[] = $this->wrapStr($element, $wrapChar, $escape);
			}
			return $R;
		}
	}

	public function freeResult($result): void {
		if ($result instanceof mysqli_result) {
			$result->free();
		}
	}

	public function update(...$args): void {
		$table = array_shift($args);
		$params = array_shift($args);
		$where = array_shift($args);
		$keyval = [];
		foreach ($params as $key => $value) {
			$value = $this->sanitize($value);
			$keyval[] = "`" . $key . "`=" . $value;
		}

		$buildquery = "UPDATE " . $this->formatTableName($table) . " SET " . implode(', ', $keyval) . " WHERE " . $where;
		array_unshift($args, $buildquery);
		call_user_func_array([$this, 'queryNull'], $args);
	}

	public function insertOrReplace(string $which, string $table, $datas, array $options = []): void {
		$datas = unserialize(serialize($datas)); 
		$keys = null;

		if (isset($datas[0]) && is_array($datas[0])) {
			$many = true;
		} else {
			$datas = [$datas];
			$many = false;
		}

		$values = [];
		foreach ($datas as $data) {
			if (!$keys) {
				$keys = array_keys($data);
				if ($many) sort($keys);
			}

			$insert_values = [];

			foreach ($keys as $key) {
				if ($many && !isset($data[$key])) {
					$this->nonSQLError('insert/replace many: each assoc array must have the same keys!');
				}
				$datum = $data[$key] ?? null;
				$datum = $this->sanitize($datum);
				$insert_values[] = $datum;
			}

			$values[] = '(' . implode(', ', $insert_values) . ')';
		}

		$tableFormatted = $this->formatTableName($table);
		$keys_str = implode(', ', $this->wrapStr($keys, '`'));
		$values_str = implode(',',$values);

		if (isset($options['ignore']) &&$options['ignore'] && strtolower($which) === 'insert') {$this->queryNull("INSERT IGNORE INTO $tableFormatted ($keys_str) VALUES$values_str");
		} else if (isset($options['update']) &&$options['update'] && strtolower($which) === 'insert') {$this->queryNull("INSERT INTO $tableFormatted ($keys_str) VALUES $values_str ON DUPLICATE KEY UPDATE {$options['update']}");
		} else {
			$this->queryNull("$which INTO$tableFormatted ($keys_str) VALUES$values_str");
		}
	}

	public function insert(string $table, $data) { return$this->insertOrReplace('INSERT', $table,$data); }
	public function insertIgnore(string $table, $data) { return$this->insertOrReplace('INSERT', $table,$data, ['ignore' => true]); }
	public function replace(string $table, $data) { return$this->insertOrReplace('REPLACE', $table,$data); }

	public function insertUpdate(...$args) {
		$table = array_shift($args);
		$data = array_shift($args);

		if (!isset($args[0])) {
			if (isset($data[0]) && is_array($data[0])) {$this->nonSQLError("Badly formatted insertUpdate() query -- you didn't specify the update component!");
			}
			$args[0] =$data;
		}

		if (is_array($args[0])) {$keyval = [];
			foreach ($args[0] as $key =>$value) {
				$value =$this->sanitize($value);$keyval[] = "`" . $key . "`=" . $value;
			}
			$updatestr = implode(', ', $keyval);
		} else {
			$updatestr = call_user_func_array([$this, 'parseQueryParams'], $args);
		}

		return $this->insertOrReplace('INSERT', $table, $data, ['update' => $updatestr]);
	}

	public function delete(...$args): void {
		$table = $this->formatTableName(array_shift($args));
		$where = array_shift($args);
		$buildquery = "DELETE FROM $table WHERE $where";
		array_unshift($args, $buildquery);
		call_user_func_array([$this, 'queryNull'], $args);
	}

	public function sqleval(...$args): MeekroDBEval {
		$text = call_user_func_array([$this, 'parseQueryParams'], $args);
		return new MeekroDBEval($text);
	}

	public function columnList(string $table): array {
		return $this->queryOneColumn('Field', "SHOW COLUMNS FROM $table");
	}

	public function tableList(?string $db = null): array {
		if ($db) $this->useDB($db);
		$result = $this->queryFirstColumn('SHOW TABLES');
		if ($db && $this->old_db) $this->useDB($this->old_db);
		return $result;
	}

	public function parseQueryParamsOld(...$args) {
		$sql = array_shift($args);
		$types = array_shift($args);
		$typeList = str_split($types);

		foreach ($args as $arg) {
			$type = array_shift($typeList);
			$pos = strpos($sql, '?');
			if ($pos === false) $this->nonSQLError("Badly formatted SQL query: $sql");

			if ($type === 's') $replacement = "'" . $this->escape((string)$arg) . "'";
			else if ($type === 'i') $replacement = (string)(int)$arg;
			else $this->nonSQLError("Badly formatted SQL query: $sql");

			$sql = substr_replace($sql, $replacement, $pos, 1);
		}
		return $sql;
	}

	public function parseQueryParamsNew(...$args) {
		$sql = array_shift($args);
		$args_all = $args;
		$posList = [];
		$pos_adj = 0;
		$param_char_length = strlen($this->param_char);
		$types = [
			$this->param_char . 'll',
			$this->param_char . 'ls',
			$this->param_char . 'l',
			$this->param_char . 'li',
			$this->param_char . 'ld',
			$this->param_char . 'lb',
			$this->param_char . 's',
			$this->param_char . 'i',
			$this->param_char . 'd',
			$this->param_char . 'b',
			$this->param_char . 'ss'
		];

		foreach ($types as $type) {
			$lastPos = 0;
			while (($pos = strpos($sql, $type, $lastPos)) !== false) {
				$lastPos = $pos + 1;
				if (isset($posList[$pos]) && strlen($posList[$pos]) > strlen($type)) continue;
				$posList[$pos] = $type;
			}
		}

		ksort($posList);

		foreach ($posList as $pos => $type) {
			$typeStr = substr($type, $param_char_length);
			$length_type = strlen($typeStr) + $param_char_length;

			$arg_number_length = strspn($sql, '0123456789', $pos + $pos_adj + $length_type);
			if ($arg_number_length > 0) {
				$arg_number = (int)substr($sql, $pos + $pos_adj + $length_type, $arg_number_length);
				if (!isset($args_all[$arg_number])) {
					$this->nonSQLError("Non existent argument reference (arg $arg_number): $sql");
				}
				$arg = $args_all[$arg_number];
			} else {
				$arg_number = 0;
				$arg = array_shift($args);
			}

			$array_type = false;
			$result = null;

			if (in_array($typeStr, ['s', 'i', 'd', 'b', 'l'], true)) {
				$array_type = false;
				$arg = [$arg];
				$typeStr = 'l' . $typeStr;
			} else if ($typeStr === 'ss') {
				$result = "'%" . $this->escape(str_replace(['%', '_'], ['\%', '\_'], (string)$arg)) . "%'";
			} else {
				$array_type = true;
				if (!is_array($arg)) {
					$this->nonSQLError("Badly formatted SQL query: $sql -- expecting array, but didn't get one!");
				}
			}

			if ($typeStr === 'ls') $result = $this->wrapStr($arg, "'", true);
			else if ($typeStr === 'li') $result = array_map('intval', $arg);
			else if ($typeStr === 'ld') $result = array_map('floatval', $arg);
			else if ($typeStr === 'lb') $result = array_map([$this, 'formatTableName'], $arg);
			else if ($typeStr === 'll') $result = $arg;
			else if ($result === null) $this->nonSQLError("Badly formatted SQL query: $sql");

			if (is_array($result)) {
				if (!$array_type) $result = $result[0];
				else $result = '(' . implode(',', $result) . ')';
			}

			$sql = substr_replace($sql, (string)$result, $pos + $pos_adj, $length_type + $arg_number_length);
			$pos_adj += strlen((string)$result) - ($length_type + $arg_number_length);
		}
		return $sql;
	}

	public function parseQueryParams(...$args) {
		if (count($args) < 2) return $args[0] ?? '';

		if (is_string($args[1]) && preg_match('/^[is]+$/', $args[1]) && substr_count($args[0], '?') > 0) {
			return call_user_func_array([$this, 'parseQueryParamsOld'], $args);
		} else {
			return call_user_func_array([$this, 'parseQueryParamsNew'], $args);
		}
	}

	public function quickPrepare(...$args) { return call_user_func_array([$this, 'query'], $args); }

	public function query(...$args) {
		if ($this->queryMode === 'buffered' || $this->queryMode === 'unbuffered') {
			return $this->prependCall([$this, 'queryHelper'], $args, $this->queryMode);
		} else {
			return call_user_func_array([$this, 'queryAllRows'], $args);
		}
	}

	public function queryNull(...$args) { return $this->prependCall([$this, 'queryHelper'], $args, 'null'); }
	public function queryRaw(...$args) { return $this->prependCall([$this, 'queryHelper'],$args, 'buffered'); }
	public function queryBuf(...$args) { return$this->prependCall([$this, 'queryHelper'],$args, 'buffered'); }
	public function queryUnbuf(...$args) { return$this->prependCall([$this, 'queryHelper'],$args, 'unbuffered'); }

	protected function queryHelper(...$args) {
		$type = array_shift($args);
		if ($type !== 'buffered' &&$type !== 'unbuffered' && $type !== 'null') {$this->nonSQLError('Error -- first argument to queryHelper must be buffered or unbuffered!');
		}
		$is_buffered = ($type === 'buffered');
		$is_null = ($type === 'null');

		$sql = call_user_func_array([$this, 'parseQueryParams'],$args);
		$db =$this->get();

		$runtime = 0;
		if ($this->success_handler)$starttime = microtime(true);
		$result =$db->query($sql,$is_buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);
		if ($this->success_handler) $runtime = microtime(true) -$starttime;

		if (!$sql || ($error =$this->checkError())) {
			if ($this->error_handler) {$error_handler = is_callable($this->error_handler) ?$this->error_handler : 'meekrodb_error_handler';

				call_user_func($error_handler, [
					'type' => 'sql',
					'query' => $sql,
					'error' => $error
				]);
			}

			if ($this->throw_exception_on_error) {
				throw new MeekroDBException($error ?: 'Unknown database error', $sql);
			}
		} else if ($this->success_handler) {$runtimeFormatted = sprintf('%f', $runtime * 1000);$success_handler = is_callable($this->success_handler) ?$this->success_handler : 'meekrodb_debugmode_handler';

			call_user_func($success_handler, [
				'query' => $sql,
				'runtime' => $runtimeFormatted
			]);
		}

		$this->queryResult =$result;
		$this->queryResultType =$type;
		$this->insert_id =$db->insert_id;
		$this->affected_rows =$db->affected_rows;

		if ($is_buffered &&$result instanceof mysqli_result) {
			$this->num_rows =$result->num_rows;
		} else {
			$this->num_rows = null;
		}

		if ($is_null) {
			$this->freeResult($result);
			$this->queryResult =$this->queryResultType = null;
			return null;
		}

		return $result;
	}

	public function queryAllRows(...$args): array {
		$query = call_user_func_array([$this, 'queryUnbuf'], $args);$result = $this->fetchAllRows($query);
		$this->freeResult($query);
		$this->num_rows = count($result);

		return $result;
	}

	public function queryAllArrays(...$args): array {
		$query = call_user_func_array([$this, 'queryUnbuf'], $args);$result = $this->fetchAllArrays($query);
		$this->freeResult($query);
		$this->num_rows = count($result);

		return $result;
	}

	public function queryOneList(...$args) { return call_user_func_array([$this, 'queryFirstList'],$args); }
	public function queryFirstList(...$args): ?array {
		$query = call_user_func_array([$this, 'queryUnbuf'], $args);$result = $this->fetchArray($query);
		$this->freeResult($query);
		return $result !== null ? $result : null;
	}

	public function queryOneRow(...$args) { return call_user_func_array([$this, 'queryFirstRow'],$args); }
	public function queryFirstRow(...$args): ?array {
		$query = call_user_func_array([$this, 'queryUnbuf'], $args);$result = $this->fetchRow($query);
		$this->freeResult($query);
		return $result !== null ? $result : null;
	}

	public function queryFirstColumn(...$args): array {
		$results = call_user_func_array([$this, 'queryAllArrays'], $args);$ret = [];

		if (!count($results) || !count($results[0])) return$ret;

		foreach ($results as$row) {
			$ret[] =$row[0] ?? null;
		}

		return $ret;
	}

	public function queryOneColumn(...$args): array {
		$column = array_shift($args);
		$results = call_user_func_array([$this, 'queryAllRows'], $args);$ret = [];

		if (!count($results) || !count($results[0])) return$ret;
		if ($column === null) {
			$keys = array_keys($results[0]);
			$column =$keys[0];
		}

		foreach ($results as $row) {$ret[] = $row[$column] ?? null;
		}

		return $ret;
	}

	public function queryFirstField(...$args) {$row = call_user_func_array([$this, 'queryFirstList'],$args);
		if ($row === null) return null;
		return $row[0] ?? null;
	}

	public function queryOneField(...$args) {$column = array_shift($args);$row = call_user_func_array([$this, 'queryOneRow'],$args);
		if ($row === null) {
			return null;
		} else if ($column === null) {
			$keys = array_keys($row);
			$column =$keys[0];
		}

		return $row[$column] ?? null;
	}

	protected function checkError() {
		$db =$this->get();
		if ($db->error) {
			$error =$db->error;
			$db->rollback();
			return $error;
		}

		return false;
	}

	public function fetchRow($result = null): ?array {
		if ($result === null) $result =$this->queryResult;
		if (!($result instanceof mysqli_result)) return null;
		$row =$result->fetch_assoc();
		return $row !== null ? $row : null;
	}

	public function fetchAllRows($result = null): array {$A = [];
		while ($row = $this->fetchRow($result)) {
			$A[] =$row;
		}
		return $A;
	}

	public function fetchArray($result = null): ?array {
		if ($result === null) $result =$this->queryResult;
		if (!($result instanceof mysqli_result)) return null;
		$row =$result->fetch_row();
		return $row !== null ? $row : null;
	}

	public function fetchAllArrays($result = null): array {$A = [];
		while ($row = $this->fetchArray($result)) {
			$A[] =$row;
		}
		return $A;
	}
}

#[AllowDynamicProperties]
class WhereClause {
	public string $type = 'and';
	public bool $negate = false;
	public array $clauses = [];
	public ?MeekroDB $mdb = null;

	public function __construct(string $type, $mdb = null) {
		$type = strtolower($type);
		if ($type !== 'or' && $type !== 'and') {
			DB::nonSQLError('you must use either WhereClause(and) or WhereClause(or)');
		}
		$this->type = $type;

		if ($mdb === null) {
			$this->mdb = DB::getMDB();
		} else if ($mdb instanceof MeekroDB) {
			$this->mdb = $mdb;
		} else {
			DB::nonSQLError('the second argument to new WhereClause() must be an instance of class MeekroDB');
		}
	}

	public function add(...$args) {
		if (isset($args[0]) && $args[0] instanceof WhereClause) {
			$this->clauses[] = $args[0];
			return $args[0];
		} else {
			$r = call_user_func_array([$this->mdb, 'parseQueryParams'], $args);
			$this->clauses[] = $r;
			return $r;
		}
	}

	public function negateLast(): void {
		$i = count($this->clauses) - 1;
		if (!isset($this->clauses[$i])) return;

		if ($this->clauses[$i] instanceof WhereClause) {
			$this->clauses[$i]->negate();
		} else {
			$this->clauses[$i] = 'NOT (' . $this->clauses[$i] . ')';
		}
	}

	public function negate(): void {
		$this->negate = !$this->negate;
	}

	public function addClause(string $type): WhereClause {
		$r = new WhereClause($type, $this->mdb);
		$this->add($r);
		return $r;
	}

	public function count(): int {
		return count($this->clauses);
	}

	public function text(): string {
		if (count($this->clauses) === 0) return '(1)';

		$A = [];
		foreach ($this->clauses as $clause) {
			if ($clause instanceof WhereClause) $clause = $clause->text();
			$A[] = '(' . $clause . ')';
		}

		$A = array_unique($A);
		if ($this->type === 'and') $A = implode(' AND ', $A);
		else $A = implode(' OR ', $A);

		if ($this->negate) $A = '(NOT ' . $A . ')';
		return $A;
	}
}

#[AllowDynamicProperties]
class DBTransaction {
	private bool $committed = false;

	public function __construct() {
		DB::startTransaction();
	}

	public function __destruct() {
		if (!$this->committed) DB::rollback();
	}

	public function commit(): void {
		DB::commit();
		$this->committed = true;
	}
}

function meekrodb_error_handler($params): void {
	$out = [];
	if (isset($params['query'])) $out[] = "QUERY: " . $params['query'];
	if (isset($params['error'])) $out[] = "ERROR: " . $params['error'];
	$out[] = "";

	if (php_sapi_name() === 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
		echo implode("\n", $out);
	} else {
		echo implode("<br>\n", $out);
	}

	debug_print_backtrace();
	exit;
}

function meekrodb_debugmode_handler($params): void {
	echo "QUERY: " . $params['query'] . " [" . $params['runtime'] . " ms]";
	if (php_sapi_name() === 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
		echo "\n";
	} else {
		echo "<br>\n";
	}
}

#[AllowDynamicProperties]
class MeekroDBEval {
	public string $text = '';

	public function __construct(string $text) {
		$this->text = $text;
	}
}