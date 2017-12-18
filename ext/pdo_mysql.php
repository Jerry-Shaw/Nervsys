<?php

/**
 * SQL Execution for PDO Extension
 *
 * Author 空城 <694623056@qq.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 空城
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

class pdo_mysql extends pdo
{
    /**
     * Extension config
     * Config is an array composed of the following elements:
     *
     * 'init'    => false       //bool: PDO re-connect option
     * 'type'    => 'mysql'     //string: PDO DSN prefix (database type)
     * 'host'    => '127.0.0.1' //string: Database host address
     * 'port'    => 3306        //int: Database host port
     * 'user'    => 'root'      //string: Database username
     * 'pwd'     => ''          //string: Database password
     * 'db_name' => ''          //string: Database name
     * 'charset' => 'utf8mb4'   //string: Database charset
     * 'persist' => true        //string: Persistent connection option
     *
     * Config will be removed once used
     * Do add 'init' => true to re-connect
     *
     * @var array
     */
    public static $config = [
        'type'    => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'root',
        'pwd'     => '',
        'db_name' => '',
        'charset' => 'utf8mb4',
        'persist' => true
    ];

    //MySQL instance resource
    private static $db_mysql = null;

    /**
     * Extension Initialization
     */
    private static function init(): void
    {
        //No reconnection
        if ((!isset(self::$config['init']) || false === (bool)self::$config['init']) && is_object(self::$db_mysql)) return;

        //Read new config
        $cfg = ['type', 'host', 'port', 'user', 'pwd', 'db_name', 'charset', 'persist'];

        if (!empty(self::$config)) {
            //Set config for PDO
            foreach ($cfg as $key) if (isset(self::$config[$key])) self::$$key = self::$config[$key];
            //Remove config
            self::$config = [];
        }

        //Connect MySQL
        self::$db_mysql = self::connect();

        //Free memory
        unset($cfg, $key);
    }

    /**
     * Insert data
     *
     * @param string $table
     * @param array  $data
     * @param string $last
     *
     * @return bool
     */
    public static function insert(string $table, array $data = [], string &$last = 'id'): bool
    {
        //No data to insert
        if (empty($data)) {
            debug('No data to insert!');
            return false;
        }

        //Build "data"
        $column = self::build_data($data);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'INSERT INTO ' . self::escape($table) . ' (' . implode(', ', $column) . ') VALUES(' . implode(', ', array_keys($column)) . ')';
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        $last = '' === $last ? (string)self::$db_mysql->lastInsertId() : (string)self::$db_mysql->lastInsertId($last);

        unset($table, $data, $column, $sql, $stmt);
        return $result;
    }

    /**
     * Update data
     *
     * @param string $table
     * @param array  $data
     * @param array  $where
     *
     * @return bool
     */
    public static function update(string $table, array $data, array $where): bool
    {
        //No data to update
        if (empty($data)) {
            debug('No data to update!');
            return false;
        }

        //Build "data"
        $data_column = self::build_data($data);

        //Get "SET"
        $set_opt = [];
        foreach ($data_column as $key => $item) $set_opt[] = $item . ' = ' . $key;
        unset($data_column, $key, $item);

        //Build "where"
        $where_opt = self::build_where($where);

        //Merge data
        $data = array_merge($data, $where);
        unset($where);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'UPDATE ' . self::escape($table) . ' SET ' . implode(', ', $set_opt) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($table, $data, $set_opt, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Delete data
     *
     * @param string $table
     * @param array  $where
     *
     * @return bool
     */
    public static function delete(string $table, array $where): bool
    {
        //Delete not allowed
        if (empty($where)) {
            debug('Delete is not allowed!');
            return false;
        }

        //Build "where"
        $where_opt = self::build_where($where);

        //Prepare & execute SQL
        self::init();

        $sql = 'DELETE ' . self::escape($table) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($where);

        unset($table, $where, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Build "data"
     *
     * @param array $data
     *
     * @return array
     */
    private static function build_data(array &$data): array
    {
        //Columns
        $column = [];

        //Process data
        foreach ($data as $key => $value) {
            //Generate bind value
            $bind = ':d_' . $key;

            //Add to columns
            $column[$bind] = self::escape($key);

            //Renew data
            unset($data[$key]);
            $data[$bind] = $value;
        }

        unset($key, $value, $bind);

        return $column;
    }

    /**
     * Build "where"
     *
     * @param array $where
     *
     * @return array
     */
    private static function build_where(array &$where): array
    {
        //Options
        $option = ['WHERE'];

        foreach ($where as $key => $item) {
            unset($where[$key]);

            //Add operator
            if (2 === count($item)) {
                $item[2] = $item[1];
                $item[1] = '=';
            }

            //Generate bind value
            $bind = ':w_' . $item[0] . '_' . mt_rand();

            //Add to "where"
            $where[$bind] = $item[2];

            //Process data
            if (isset($item[3])) {
                $item[3] = strtoupper($item[3]);
                if (in_array($item[3], ['AND', 'OR', 'NOT'], true)) $option[] = $item[3];
            }

            $option[] = self::escape($item[0]);
            $option[] = $item[1];
            $option[] = $bind;
        }

        unset($key, $item, $bind);
        return $option;
    }

    /**
     * Escape column key
     *
     * @param string $value
     *
     * @return string
     */
    private static function escape(string $value): string
    {
        return false !== strpos($value, '.') ? '`' . trim($value, " `\t\n\r\0\x0B") . '`' : trim($value);
    }


    //==============================


    private static function build_opt(array $opt = []): array
    {
        $option = [];

        //Process "field"
        if (isset($opt['field'])) {
            if (is_array($opt['field'])) {
                $column = [];
                foreach ($opt['field'] as $item) $column[] = self::escape($item);
                $option['field'] = implode(', ', $column);
                unset($column);
            } else $option['field'] = &$opt['field'];
        } else $option['field'] = '*';

        //Process "join"
        if (isset($opt['join'])) {
            if (is_array($opt['join'])) {

                $join_data = [];

                foreach ($opt['join'] as $table => $option) {
                    $option[3] = !isset($option[3]) ? 'INNER' : strtoupper($option[3]);
                    $mode = in_array($option[3], ['INNER', 'LEFT', 'RIGHT'], true) ? $option[3] : 'INNER';

                    if (2 === count($option)) {
                        $column_A = $option[0];
                        $column_B = $option[1];
                        $operator = '=';
                    } else {
                        $column_A = $option[0];
                        $column_B = $option[2];
                        $operator = $option[1];
                    }

                    $join_data[] = $mode . ' JOIN ' . self::escape($table) . ' ON ' . $column_A . ' ' . $operator . ' ' . $column_B;
                }

                if (!empty($join_data)) $option['join'] = implode(' ', $join_data);
            } else $option['join'] = &$opt['join'];
        }

        //Process "where"
        if (isset($opt['where'])) {
            $where_opt = self::build_where($opt['where']);


        }


        $option['where'] = !empty($opt['where']) ? self::_where($opt['where']) : self::_where(self::$where);
        $option['order'] = !empty($opt['order']) ? self::_order($opt['order']) : self::_order(self::$order);
        $option['group'] = !empty($opt['group']) ? self::_group($opt['group']) : self::_group(self::$group);
        $option['limit'] = !empty($opt['limit']) ? self::_limit($opt['limit']) : self::_limit(self::$limit);
        return $option;
    }


    public static function select(string $table, array $opt = []): array
    {


        $field = $opt['field'] = !empty($opt['field']) ? $opt['field'] : self::$field;
        if (is_array($field)) {
            foreach ($field as $key => $value) $field[$key] = self::escape($value);
            $field = implode(',', $field);
        } elseif (is_string($field) && $field != '') ;
        else $field = '*';
        self::$sql = 'SELECT ' . $field . ' FROM `' . $opt['table'] . '` ' . $opt['join'] . $opt['where'] . $opt['group'] . $opt['order'] . $opt['limit'];
        unset($opt);
        return self::query();
    }







    //============================

    /**
     * Get columns from a table
     *
     * @param string $table
     *
     * @return array
     */
    private static function column(string $table): array
    {
        if (isset(self::$table_column[$table])) return self::$table_column[$table];

        $sql = 'SELECT COLUMN_NAME, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table';

        $stmt = self::$db_mysql->prepare($sql);

        $stmt->bindValue(':db_name', self::$db_name, \PDO::PARAM_STR);
        $stmt->bindValue(':table', $table, \PDO::PARAM_STR);
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            self::$table_column[$table]['column'][] = $row['COLUMN_NAME'];
            if ('AUTO_INCREMENT' === $row['EXTRA']) self::$table_column[$table]['increment'] = $row['COLUMN_NAME'];
        }

        unset($sql, $stmt, $row);

        return self::$table_column[$table];
    }

    // Format data
    protected static function format(string $table, array $data): array
    {

        $tbColumn = self::column($table);
        $res = [];
        foreach ($data as $key => $val) {
            if (!is_scalar($val)) continue;
            if (!empty($tbColumn[$key])) {
                $key = self::escape($key);
                if (is_int($val)) $val = intval($val);
                elseif (is_float($val)) $val = floatval($val);
                elseif (preg_match('/^\(\w*(\+|\-|\*|\/)?\w*\)$/i', $val)) $val = $val;
                elseif (is_string($val)) $val = addslashes($val);
                $res[$key] = $val;
            }
        }
        unset($data);
        return $res;
    }


    // Preprocessing
    public static function bind(string $key, $value): void
    {
        if (empty(self::$bind[':' . $key])) {
            $k = ':' . $key;
            self::$bind[$k] = $value;
        } else {
            $k = ':' . $key . '_' . mt_rand(1, 9999);
            while (!empty(self::$bind[':' . $k])) {
                $k = ':' . $key . '_' . mt_rand(1, 9999);
            }
            self::$bind[$k] = $value;
        }
        unset($key, $value);
        return $k;
    }


    // Core container


    public static $table = '';
    public static $data  = '';
    public static $field = '*';
    public static $where = '';
    public static $order = '';
    public static $group = '';
    public static $limit = '';
    public static $join  = '';
    public static $bind  = [];
    public static $sql   = '';


    // Query or Exec
    public static function do(string $sql = '', bool $flag = false)
    {
        self::$sql = !empty($sql) ? $sql : self::$sql;
        $preg = 'INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|LOAD DATA|SELECT .* INTO|COPY|ALTER|GRANT|REVOKE|LOCK|UNLOCK';
        if (preg_match('/^\s*"?(' . $preg . ')\s+/i', $sql)) return self::exec('', $flag);
        else return self::query('', $flag);
    }

    // Query
    public static function query(string $sql = '', bool $flag = false): array
    {
        $stmt = self::_start($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $flag ? $result[0] : $result;
    }

    // Exec
    public static function exec(string $sql = '', bool $flag = false): int
    {
        $stmt = self::_start($sql);
        $row = $stmt->rowCount();
        return $flag ? self::$db_mysql->lastInsertId() : $row;
    }

    // Delete
    public static function del(string $table = '', array $where = []): int
    {
        $table = !empty($data) ? $table : self::$table;
        $where = !empty($where) ? self::_where($where) : self::_where(self::$where);
        if ('' === $where) return 0;
        self::$sql = 'DELETE FROM `' . trim($table) . '` ' . $where;
        unset($table, $where);
        return self::exec();
    }

    // Update
    public static function save(string $table = '', array $data = [], $where = []): int
    {
        $table = !empty($table) ? $table : self::$table;
        $data = !empty($data) ? $data : self::$data;
        $where = !empty($where) ? $where : self::$where;
        if (false == $where) {
            $key = self::_tbKey($table);
            $where = [];
            foreach ($key as $k => $v) {
                empty($data[$k]) or $where[$k] = $data[$k];
            }
            $where = self::_where($where);
        } else $where = self::_where($where);
        $data = self::format($table, $data);
        $kv = [];
        foreach ($data as $key => $value) {
            $k = str_replace('`', '', $key);
            $k = self::_setBind($k, $value);
            $kv[] = $key . '=' . $k;
        }
        $kv_str = implode(',', $kv);
        self::$sql = 'UPDATE `' . trim($table) . '` SET ' . trim($kv_str) . ' ' . trim($where);
        unset($kv_str, $data, $kv, $table);
        if ('' === $where) return 0;
        return self::exec();
    }

    // Select
    public static function aselect(string $table = '', array $opt = []): array
    {
        $opt = self::_condition($table, $opt);
        $field = $opt['field'] = !empty($opt['field']) ? $opt['field'] : self::$field;
        if (is_array($field)) {
            foreach ($field as $key => $value) $field[$key] = self::escape($value);
            $field = implode(',', $field);
        } elseif (is_string($field) && $field != '') ;
        else $field = '*';
        self::$sql = 'SELECT ' . $field . ' FROM `' . $opt['table'] . '` ' . $opt['join'] . $opt['where'] . $opt['group'] . $opt['order'] . $opt['limit'];
        unset($opt);
        return self::query();
    }

    // Get a line
    public static function first(string $table = '', array $opt = []): array
    {
        self::$limit = '1';
        $result = self::select($table, $opt);
        return $result[0];
    }

    // Count
    public static function count(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'count');
    }

    // Avg
    public static function avg(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'avg');
    }

    // Sum
    public static function sum(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'sum');
    }

    // Min
    public static function min(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'min');
    }

    // Max
    public static function max(string $table = '', array $opt = []): array
    {
        $option = self::_condition($table, $opt);
        return self::_common($option, 'max');
    }

    // Dec
    public static function dec(string $table = '', $data = [], $where = []): int
    {
        return self::_setCol($table, $data, $where, '-');
    }

    // Inc
    public static function inc(string $table = '', $data = [], $where = []): int
    {
        return self::_setCol($table, $data, $where, '+');
    }

    // Clear
    public static function clear(): void
    {
        self::$data = '';
        self::$field = '*';
        self::$where = '';
        self::$order = '';
        self::$group = '';
        self::$limit = '';
        self::$bind = [];
    }

    // SetAttribute
    public static function setAttr($key, $val): bool
    {
        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        return self::$db_mysql->setAttribute($key, $val);
    }

    // BeginTransaction
    public static function begin(): bool
    {
        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        return self::$db_mysql->beginTransaction();
    }

    // Commit
    public static function commit(): bool
    {
        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        return self::$db_mysql->commit();
    }

    // RollBack
    public static function rollBack(): bool
    {
        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        return self::$db_mysql->rollBack();
    }

    // Mosaic SQL
    protected static function _condition(string $table, array $opt): array
    {
        $option = [];
        $option['table'] = !empty($table) ? $table : self::$table;
        $option['field'] = !empty($opt['field']) ? $opt['field'] : self::$field;
        $option['join'] = !empty($opt['join']) ? self::_join($opt['join']) : self::_join(self::$join);
        $option['where'] = !empty($opt['where']) ? self::_where($opt['where']) : self::_where(self::$where);
        $option['order'] = !empty($opt['order']) ? self::_order($opt['order']) : self::_order(self::$order);
        $option['group'] = !empty($opt['group']) ? self::_group($opt['group']) : self::_group(self::$group);
        $option['limit'] = !empty($opt['limit']) ? self::_limit($opt['limit']) : self::_limit(self::$limit);
        return $option;
    }

    // Exec SQL common function
    protected static function _start(string $sql = '')
    {
        empty($sql) or self::$sql = $sql;
        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        $stmt = self::$db_mysql->prepare(self::$sql);
        $stmt->execute(self::$bind);
        self::clear();
        return $stmt;
    }

    // Common
    protected static function _common(array $opt, string $func): array
    {
        if (is_string($opt['field']) && $opt['field'] != "") {
            $strField = $opt['field'];
            $fieldArr = explode(",", $strField);
            $strField = '_' . implode("_,_", $fieldArr) . '_';
        } elseif (is_array($opt['field'])) {
            $fieldArr = $opt['field'];
            $strField = '_' . implode("_,_", $opt['field']) . '_';
        } else return false;

        foreach ($fieldArr as $v) {
            $val = self::escape($v);
            $alias = str_replace('.', '_', $val);
            $alias = ' AS ' . (false === strpos($val, '*') ? $alias : '`' . $alias . '`');
            $strField = str_replace('_' . $v . '_', $func . '(' . $val . ') ' . $alias, $strField);
        }
        self::$sql = 'SELECT ' . $strField . ' FROM `' . $opt['table'] . '` ' . $opt['join'] . $opt['where'] . $opt['group'] . $opt['order'] . $opt['limit'];
        unset($opt, $func, $fieldArr, $strField, $alias);
        $result = self::query();
        return count($result) == 1 && !empty($result[0]) ? $result[0] : $result;
    }

    // Set field
    protected static function _setCol(string $table = '', $data = '', $where = [], string $type): int
    {
        $table = !empty($table) ? $table : self::$table;
        $data = !empty($data) ? $data : self::$data;
        $where = !empty($where) ? self::_where($where) : self::_where(self::$where);

        if (is_array($data)) {
            $new_data = [];
            foreach ($data as $key => $value) {
                if (is_string($key)) $new_data[$key] = $key . $type . abs($value);
                else $new_data[$value] = $value . $type . '1';
            }
        } elseif (is_string($data)) $new_data[$data] = $data . $type . '1';
        $kv = [];
        foreach ($new_data as $key => $value) {
            $kv[] = self::escape($key) . '=' . $value;
        }
        $kv_str = implode(',', $kv);
        self::$sql = 'UPDATE `' . trim($table) . '` SET ' . trim($kv_str) . ' ' . trim($where);
        unset($data);
        if ('' === $where) return 0;
        return self::exec();
    }

    // Preprocessing
    protected static function _setBind(string $key, $value): string
    {
        if (empty(self::$bind[':' . $key])) {
            $k = ':' . $key;
            self::$bind[$k] = $value;
        } else {
            $k = ':' . $key . '_' . mt_rand(1, 9999);
            while (!empty(self::$bind[':' . $k])) {
                $k = ':' . $key . '_' . mt_rand(1, 9999);
            }
            self::$bind[$k] = $value;
        }
        unset($key, $value);
        return $k;
    }

    // Join
    protected static function _join($opt): string
    {
        $join = '';
        if (is_string($opt) && '' !== trim($opt)) return $opt;
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $mode = 'INNER';
                if (is_array($value)) {
                    if (!empty($value[2]) && 0 === strcasecmp($value[2], 'LEFT')) $mode = 'LEFT';
                    elseif (!empty($value[2]) && 0 === strcasecmp($value[2], 'RIGHT')) $mode = 'RIGHT';
                    $relative = !empty($value[3]) ? $value[3] : '=';
                    $condition = ' ' . $mode . ' JOIN ' . $key . ' ON ' . self::escape($value[0]) . $relative . self::escape($value[1]) . ' ';
                } else {
                    $condition = ' ' . $mode . ' JOIN ' . $key . ' ON ' . $value . ' ';
                }
                $join .= $condition;
            }
        }
        unset($opt);
        return $join;
    }

    // Where
    protected static function _where($opt): string
    {
        $where = '';
        if (is_string($opt) && '' !== trim($opt)) return ' WHERE ' . $opt;
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $k = self::escape($key);
                if (is_array($value)) {
                    $key = self::_setBind($key, $value[0]);
                    $relative = !empty($value[1]) ? $value[1] : '=';
                    $link = !empty($value[2]) ? $value[2] : 'AND';
                    $condition = ' (' . $k . ' ' . $relative . ' ' . $key . ') ';
                } else {
                    $key = self::_setBind($key, $value);
                    $link = 'AND';
                    $condition = ' (' . $k . '=' . $key . ') ';
                }
                $where .= $where !== '' ? $link . $condition : ' WHERE ' . $condition;
            }
        }
        unset($opt);
        return $where;
    }

    // Order
    protected static function _order($opt): string
    {
        $order = '';
        if (is_string($opt) && '' !== trim($opt)) return ' ORDER BY ' . _avoidKey($opt);
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $link = ',';
                if (is_string($key)) {
                    if (0 === strcasecmp($value, 'DESC')) $condition = ' ' . self::escape($key) . ' DESC ';
                    else $condition = ' ' . self::escape($key) . ' ASC ';
                } else $condition = ' ' . self::escape($value) . ' ASC ';
                $order .= $order !== '' ? $link . addslashes($condition) : ' ORDER BY ' . addslashes($condition);
            }
        }
        unset($opt);
        return $order;
    }

    // Limit
    protected static function _limit($opt): string
    {
        $limit = '';
        if (is_string($opt) && '' !== trim($opt)) return ' LIMIT ' . $opt;
        elseif (is_array($opt) && 2 == count($opt)) $limit = ' LIMIT ' . (int)$opt[0] . ',' . (int)$opt[1];
        elseif (is_array($opt) && 1 == count($opt)) $limit = ' LIMIT ' . (int)$opt[0];
        unset($opt);
        return $limit;
    }

    // Group
    protected static function _group($opt): string
    {
        $group = '';
        if (is_string($opt) && '' !== trim($opt)) return ' GROUP BY ' . _avoidKey($opt);
        elseif (is_array($opt)) {
            foreach ($opt as $key => $value) {
                $link = ',';
                $condition = ' ' . self::escape($value) . ' ';
                $group .= $group !== '' ? $link . addslashes($condition) : ' GROUP BY ' . addslashes($condition);
            }
        }
        unset($opt);
        return $group;
    }

    // Format data
    protected static function _format(string $table, $data): array
    {
        if (!is_array($data)) return [];

        $tbColumn = self::column($table);
        $res = [];
        foreach ($data as $key => $val) {
            if (!is_scalar($val)) continue;
            if (!empty($tbColumn[$key])) {
                $key = self::escape($key);
                if (is_int($val)) $val = intval($val);
                elseif (is_float($val)) $val = floatval($val);
                elseif (preg_match('/^\(\w*(\+|\-|\*|\/)?\w*\)$/i', $val)) $val = $val;
                elseif (is_string($val)) $val = addslashes($val);
                $res[$key] = $val;
            }
        }
        unset($data);
        return $res;
    }

    // Get primary key
    protected static function _tbKey(string $table): array
    {
        $sql = 'SELECT k.column_name FROM information_schema.table_constraints t JOIN information_schema.key_column_usage k USING (constraint_name,table_schema,table_name) WHERE t.constraint_type="PRIMARY KEY" AND t.table_schema="' . self::$db_name . '" AND t.table_name="' . $table . '"';

        !empty(self::$db_mysql) or self::$db_mysql = self::connect();
        $stmt = self::$db_mysql->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $res = [];
        foreach ($result as $key => $value) {
            $res[$value['column_name']] = 1;
        }
        unset($result, $stmt);
        return $res;
    }
}
