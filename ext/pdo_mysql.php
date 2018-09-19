<?php
/**
 * Pdo MySQL Extension
 *
 * Copyright 2018 kristenzz <kristenzz1314@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ext;

class pdo_mysql extends pdo
{
    //Action
    private $act = '';

    //SQL params
    private $field = '';
    private $table = '';
    private $where = '';

    private $join    = '';
    private $incr    = '';
    private $group   = '';
    private $having  = '';
    private $between = '';

    private $order = [];
    private $limit = '';

    private $sql   = '';
    private $bind  = [];
    private $value = [];

    /**
     * Insert into table
     *
     * @param string $table
     *
     * @return object
     */
    public function insert(string $table = ''): object
    {
        $this->act = 'INSERT';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Select from table
     *
     * @param string $table
     *
     * @return object
     */
    public function select(string $table = ''): object
    {
        $this->act = 'SELECT';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Update table date
     *
     * @param string $table
     *
     * @return $this
     */
    public function update(string $table = ''): object
    {
        $this->act = 'UPDATE';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Delete from table
     *
     * @param string $table
     *
     * @return $this
     */
    public function delete(string $table = ''): object
    {
        $this->act = 'DELETE';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Set insert values
     *
     * @param array $value
     * @param bool  $append
     *
     * @return object
     */
    public function value(array $value, bool $append = false): object
    {
        !$append ? $this->value = &$value : $this->value += $value;

        unset($value, $append);
        return $this;
    }

    /**
     * Set select fields
     *
     * @param string ...$fields
     *
     * @return object
     */
    public function field(string ...$fields): object
    {
        if ('' !== $this->field) {
            $this->field .= ',';
        }

        $this->field .= implode(', ', $fields);

        unset($fields);
        return $this;
    }

    /**
     * Set join conditions
     *
     * @param string $table
     * @param array  $where
     * @param string $type
     *
     * @return object
     */
    public function join(string $table, array $where, string $type = 'INNER'): object
    {
        $this->join .= $this->build_join([$table, $where, $type]);

        unset($table, $where, $type);
        return $this;
    }

    /**
     * Set where conditions
     *
     * @param array $where
     *
     * @return object
     */
    public function where(array $where): object
    {
        $this->where .= $this->build_where($where);

        unset($where);
        return $this;
    }

    /**
     * Set having conditions
     *
     * @param array $having
     *
     * @return object
     */
    public function having(array $having): object
    {
        $this->having[] = &$having;

        unset($having);
        return $this;
    }

    /**
     * Set order
     *
     * @param array $order
     *
     * @return $this
     */
    public function order(array $order): object
    {
        $order[1] = strtoupper($order[1]);

        if (!in_array($order[1], ['ASC', 'DESC'], true)) {
            throw new \PDOException('MySQL: Order method: ' . $order[1] . ' NOT supported!');
        }

        $this->order[] = $this->escape($order[0]) . ' ' . $order[1];

        unset($order);
        return $this;
    }

    /**
     * Set group conditions
     *
     * @param string ...$group
     *
     * @return $this
     */
    public function group(string ...$group): object
    {
        $this->group = implode(', ', $group);

        unset($group);
        return $this;
    }

    /**
     * Set limit
     *
     * @param int $offset
     * @param int $length
     *
     * @return object
     */
    public function limit(int $offset, int $length = 0): object
    {
        $this->limit = 0 === $length ? '0, ' . $offset : $offset . ', ' . $length;

        unset($offset, $length);
        return $this;
    }


    public function exec(): int
    {


    }


    public function fetch(bool $column = false): array
    {

    }


    public function last_insert(string $column = 'id'): string
    {

    }


    /**
     * Set table
     *
     * @param string $table
     */
    private function table(string $table): void
    {
        if ('' === $table) {
            $table = strtr(get_class($this), '\\', '_');
        }

        $this->table = $this->escape($table);
        unset($table);
    }

    /**
     * Escape table name and column
     *
     * @param string $field
     *
     * @return string
     */
    private function escape(string $field): string
    {
        $list = false !== strpos($field, ',') ? explode(',', $field) : [$field];

        $list = array_map(
            static function (string $item): string
            {
                $list = [];
                $item = trim($item);

                $offset = 0;
                $length = strlen($item);
                $symbol = ['+', '-', '*', '/', '(', ')'];

                do {
                    $find  = [];
                    $match = false;

                    //find function position
                    foreach ($symbol as $mark) {
                        if (false === $pos = strpos($item, $mark, $offset)) {
                            continue;
                        }

                        if (empty($find) || $pos < $find[1]) {
                            $find = [$mark, $pos];
                        }

                        $match = true;
                    }

                    //Position NOT found
                    if (!$match) {
                        if ($offset < $length) {
                            $value   = substr($item, $offset);
                            $content = trim($value);

                            $list[] = [is_numeric($content) ? 'num' : ('' !== $content ? 'col' : 'sp'), $value];
                        }

                        break;
                    }

                    //Process symbols
                    if ('(' === $find[0]) {
                        if ($find[1] > $offset) {
                            $list[] = ['func', substr($item, $offset, $find[1] - $offset)];
                        }

                        $list[] = ['opt', '('];
                        ++$find[1];
                    } elseif ($find[1] > $offset) {
                        $value   = substr($item, $offset, $find[1] - $offset);
                        $content = trim($value);

                        $list[] = [is_numeric($content) ? 'num' : ('' !== $content ? 'col' : 'sp'), $value];
                        $list[] = ['opt', substr($item, $find[1]++, 1)];
                    } else {
                        $list[] = ['opt', substr($item, $find[1]++, 1)];
                    }

                    $offset = $find[1];
                } while ($match);

                unset($offset, $length, $symbol, $find, $match, $mark, $pos, $value, $content);

                //Process column values
                foreach ($list as $key => $val) {
                    //Skip functions
                    if ('col' !== $val[0]) {
                        $list[$key] = $val[1];
                        continue;
                    }

                    //Process alias
                    if (false !== strpos($val[1], ' ')) {
                        $val[1] = false === stripos($item, ' as ')
                            ? str_ireplace(' ', '` `', $val[1])
                            : str_ireplace(' as ', '` AS `', $val[1]);
                    }

                    //Process connector
                    if (false !== strpos($val[1], '.')) {
                        $val[1] = str_replace('.', '`.`', $val[1]);
                    }

                    $list[$key] = !isset($list[$key - 1]) || ')' !== $list[$key - 1]
                        ? '`' . trim($val[1], '`') . '`'
                        : trim($val[1], '`') . '`';
                }

                $item = implode($list);

                unset($list, $key, $val);
                return $item;
            }, $list
        );

        $field = implode(', ', $list);

        unset($list);
        return $field;
    }

    /**
     * Build SQL caller
     */
    public function build_sql(): void
    {
        if ('' === $this->act) {
            throw new \PDOException('MySQL: No act provided!');
        }

        $this->{'build_' . strtolower($this->act)}();
    }

    /**
     * Build INSERT SQL
     */
    private function build_insert(): void
    {
        $this->bind = array_values($this->value);
        $this->sql  = 'INSERT INTO ' . $this->table
            . ' (' . $this->escape(implode(', ', array_keys($this->value))) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count($this->bind), '?')) . ')';
    }

    /**
     * Build SELECT SQL
     */
    private function build_select(): void
    {
        $this->sql = 'SELECT ' . ('' !== $this->field ? $this->escape($this->field) : '`*`') . ' FROM ' . $this->table;

        if ('' !== $this->join) {
            $this->sql .= ' ' . $this->join;
        }

        if ('' !== $this->where) {
            $this->sql .= ' WHERE ' . $this->where;
        }


    }


    private function build_join(string $table, array $where, string $type): string
    {
        $join = strtoupper($type) . ' JOIN ' . $this->escape($table) . ' ON ';

        if (count($where) === count($where, 1)) {
            $where = [$where];
        }

        foreach ($where as $value) {



        }


        return $join;
    }


    /**
     * Build where conditions
     *
     * @param array $value
     *
     * @return string
     */
    private function build_where(array $value): string
    {
        $where = '';

        if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
            array_shift($value);
            $where .= $item . ' ';
        } elseif ('' !== $this->where) {
            $where .= 'AND ';
        }

        $where .= $this->escape($value[0]) . ' ';

        if (3 === count($value)) {
            if (!in_array($item = strtoupper($value[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                throw new \PDOException('MySQL: Operator: ' . $value[1] . ' NOT allowed!');
            }

            $where .= $item . ' ';

            if (!is_array($value[2])) {
                $this->bind[] = &$value[2];

                $where .= '? ';
            } else {
                $this->bind = array_merge($this->bind, $value[2]);

                $where .= 'BETWEEN' !== $item ? '(' . implode(', ', array_fill(0, count($value[2]), '?')) . ') ' : '? AND ? ';
            }
        } elseif (!is_array($value[1])) {
            if (!in_array($item = strtoupper($value[1]), ['IS NULL', 'IS NOT NULL'], true)) {
                $this->bind[] = &$value[1];

                $where .= '= ? ';
            } else {
                $where .= $item . ' ';
            }
        } else {
            $this->bind = array_merge($this->bind, $value[1]);

            $where .= 'IN ' . '(' . implode(', ', array_fill(0, count($value[1]), '?')) . ')' . ' ';
        }

        unset($value, $item);
        return $where;
    }
















    //==============================================

    /**
     * Select where condition
     *
     * @param array $where
     *
     * @return object
     */
    public function wherea(array $where): object
    {
        if ('' === $this->where) {
            $this->where = 'WHERE ';
        }


        unset($where, $item);
        return $this;


        $this->table === '' ? $this->table = $table : $this->table;
        $this->act .= 'UPDATE ' . $this->table . ' SET ';
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $this->act    .= $k . '= ? ,';
                $this->bind[] = $v;
            }
        }
        $this->act = rtrim($this->act, ',');

        $this->table = $table;
        $this->act   = 'INSERT INTO ' . $this->table . '(' . implode(',', array_keys($data[0])) . ') VALUES ';
        $prepare     = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->act .= '(' . rtrim(str_repeat('?,', count($value)), ',') . '),';
                $prepare   = array_merge($prepare, array_values($value));
            } else {
                $this->act  .= '(' . rtrim(str_repeat('?,', count($data)), ',') . ')';
                $this->bind = array_values($data);
                break;
            }
        }
        $this->act = rtrim($this->act, ',');
        if (!empty($prepare))
            $this->bind = $prepare;
        unset($data, $key, $value, $table, $prepare);
        return $this;
    }


    //===========================


    // ['age','>','21'],['age','22']['or','age','22']['or','age','<=','18']['in','age',[1,2,3]],['not','age',[18]]['like','language','Chinese']

    //['age',20,21]

    public function incr(string $field, int $step = 1)
    {
        $icon = '';
        if (strpos($this->act, '=') || $this->incr != '') {
            $icon = ',';
        }
        $this->incr .= $icon . '`' . $field . '` = `' . $field . '`+' . $step;
        unset($icon, $field);
        return $this;
    }

    //['a.id','=','b.id']
    public function joina(string $table, array $join, string $type = 'inner')
    {
        if (strpos($table, ' ')) {
            $table = '`' . implode('`', explode(' ', $table)) . '`';
        }
        if (is_array($join) && !empty($join)) {
            $this->join .= ' ' . $type . ' JOIN ' . $table . ' ON ';
            $str        = '';
            foreach ($join as $k => $v) {
                if (!is_array($v)) {
                    if (strpos($v, '.')) {
                        $this->join .= '`' . implode('`.`', explode('.', $v)) . '`';
                    } else {
                        $this->join .= $v . ' ';
                    }
                } else {
                    foreach ($v as $key => $value) {
                        if (strpos($value, '.')) {
                            $str .= '`' . implode('`.`', explode('.', $value)) . '`';
                        } else {
                            $str .= $value . ' ';
                        }
                    }
                    $str = $str . ' AND ';
                }
            }
            if ($str != '') {
                $this->join .= rtrim($str, ' AND');
            }
        }
        unset($table, $join, $type, $str, $k, $v, $key, $value);
        return $this;
    }


    public function execa()
    {
        $sql = $this->act
            . $this->incr
            . $this->where
            . $this->between
            . $this->group
            . $this->having
            . $this->order
            . $this->limit;
        $sth = parent::connect()->prepare($sql);
        unset($sql);
        return $sth->execute($this->bind);
    }

    public function fetchAll(int $fetch_style = \PDO::FETCH_ASSOC)
    {
        if ($this->act == '')
            $this->act = 'SELECT * FROM' . $this->table;
        $this->act .= $this->join
            . $this->where
            . $this->between
            . $this->group
            . $this->having
            . $this->order
            . $this->limit;
        echo $this->act . "\r\n";
        $sth = parent::connect()->prepare($this->act);
        $sth->execute($this->bind);
        unset($field);
        return $sth->fetchAll($fetch_style);
    }

}