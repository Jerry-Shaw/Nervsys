<?php
/**
 * Pdo Mysql Extension
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
    private $field = '*';
    private $table = '';
    private $where = '';

    private $incr    = '';
    private $group   = '';
    private $having  = '';
    private $between = '';

    private $order = '';
    private $limit = '';

    private $join = [];
    private $data = [];

    /**
     * Escape table name and columns
     *
     * @param string $name
     *
     * @return string
     */
    private function escape(string $name): string
    {
        //Process alias
        if (false !== strpos($name, ' ')) {
            $name = false === stripos($name, ' as ')
                ? str_ireplace(' ', '` `', $name)
                : str_ireplace(' as ', '` AS `', $name);
        }

        //Process connector
        if (false !== strpos($name, '.')) {
            $name = str_replace('.', '`.`', $name);
        }

        return '`' . $name . '`';
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
     * Set select fields
     *
     * @param string ...$fields
     *
     * @return object
     */
    public function field(string ...$fields): object
    {
        if (!empty($fields)) {
            $fields = array_map(
                static function (string $field): string
                {
                    return $this->escape($field);
                }, $fields
            );

            $this->field = implode(', ', $fields);
        }

        unset($fields);
        return $this;
    }

    /**
     * Select where condition
     *
     * @param array $where
     *
     * @return object
     */
    public function where(array $where): object
    {
        if ('' === $this->where) {
            $this->where = 'WHERE ';
        }

        if (in_array($item = strtoupper($where[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
            array_shift($where);
            $this->where .= $item . ' ';
        }

        if (3 === count($where)) {
            if (!in_array($item = strtoupper($where[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN'], true)) {
                throw new \PDOException('MySQL: Operator NOT supported!');
            }

            $this->where .= $this->escape($where[0]) . ' ' . $item . ' ';

            if (!is_array($where[2])) {
                $this->data[] = &$where[2];
                $item         = '?';
            } else {
                $this->data = array_merge($this->data, $where[2]);
                $item       = '(' . implode(', ', array_fill(0, count($where[2]), '?')) . ')';
            }

            $this->where .= $item . ' ';
        } else {
            if (in_array($item = strtoupper($where[1]), ['IS NULL', 'IS NOT NULL'], true)) {
                $this->where .= $this->escape($where[0]) . ' ' . $item . ' ';
            } else {
                if ('WHERE ' !== $this->where) {
                    $this->where = 'AND ';
                }

                if (!is_array($where[1])) {
                    $this->data[] = &$where[1];
                    $this->where  .= $this->escape($where[0]) . ' = ? ';
                } else {
                    $this->data  = array_merge($this->data, $where[1]);
                    $item        = '(' . implode(', ', array_fill(0, count($where[1]), '?')) . ')';
                    $this->where .= $this->escape($where[0]) . ' IN ' . $item . ' ';
                }
            }
        }

        unset($where, $item);
        return $this;
    }

    //===========================


    public function insert(string $table, array $data)
    {
        $this->table = $table;
        $this->act   = 'INSERT INTO ' . $this->table . '(' . implode(',', array_keys($data[0])) . ') VALUES ';
        $prepare     = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->act .= '(' . rtrim(str_repeat('?,', count($value)), ',') . '),';
                $prepare   = array_merge($prepare, array_values($value));
            } else {
                $this->act  .= '(' . rtrim(str_repeat('?,', count($data)), ',') . ')';
                $this->data = array_values($data);
                break;
            }
        }
        $this->act = rtrim($this->act, ',');
        if (!empty($prepare))
            $this->data = $prepare;
        unset($data, $key, $value, $table, $prepare);
        return $this;
    }

    public function update(string $table, array $data = [])
    {
        $this->table === '' ? $this->table = $table : $this->table;
        $this->act .= 'UPDATE ' . $this->table . ' SET ';
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $this->act    .= $k . '= ? ,';
                $this->data[] = $v;
            }
        }
        $this->act = rtrim($this->act, ',');
        unset($table, $data, $k, $v, $key, $value);
        return $this;
    }

    public function delete(string $table)
    {
        $this->table = $table;
        $this->act   = 'DELETE FROM ' . $this->table . $this->where;
        return $this;
    }


    // ['age','>','21'],['age','22']['or','age','22']['or','age','<=','18']['in','age',[1,2,3]],['not','age',[18]]['like','language','Chinese']

    //['age',20,21]
    public function between(array $where)
    {
        if ($this->where === '') {
            if ($this->between === '') {
                $this->between = ' WHERE `' . $where[0] . '` BETWEEN ' . $where[1] . ' AND ' . $where[2];
            } else {
                $this->between .= ' AND `' . $where[0] . '` BETWEEN ' . $where[1] . ' AND ' . $where[2];
            }
        } else {
            $this->between = ' AND `' . $where[0] . '` BETWEEN ' . $where[1] . ' AND ' . $where[2];

        }
        unset($where);
        return $this;
    }

    public function group(string ...$group)
    {
        $this->group = ' GROUP BY `' . implode(',', $group) . '`';
        unset($group);
        return $this;
    }

    //['sum(score)','>','400'],['age','21']
    public function having(array $having)
    {
        switch (count($having)) {
            case 2:
                if ($this->having === '') {
                    $this->having = ' HAVING `' . $having[0] . '` = ?';
                    $this->data[] = $having[1];
                } else {
                    $this->having .= ' AND `' . $having[0] . '` = ?';

                }
                $this->data[] = $having[1];
                break;
            case 3:
                if ($this->having === '') {
                    $this->having = ' HAVING `' . $having[0] . '` ' . $having[1] . ' ?';
                } else {
                    $this->having .= ' AND `' . $having[0] . '` ' . $having[1] . ' ?';
                }
                $this->data[] = $having[2];
                break;
            default:
                break;
        }
        unset($having);
        return $this;
    }

    //['age','desc']
    public function order(array $order)
    {
        if ($this->order != '') {
            $this->order = ' AND `' . $order[0] . '` ' . $order[1] . ' ';
        } else {
            $this->order = ' ORDER BY `' . $order[0] . '` ' . $order[1] . ' ';

        }
        unset($order);
        return $this;
    }

    public function limit($offset, $length = '')
    {
        if ($length === '' && strpos(',', $offset)) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->limit = ' LIMIT ' . intval($offset) . ($length != '' ? ',' . intval($length) : '');
        unset($offset, $length);
        return $this;
    }

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
    public function join(string $table, array $join, string $type = 'inner')
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


    public function exec()
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
        return $sth->execute($this->data);
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
        $sth->execute($this->data);
        unset($field);
        return $sth->fetchAll($fetch_style);
    }

}