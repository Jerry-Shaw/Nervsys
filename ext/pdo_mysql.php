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
    protected $db;
    protected $params = [
        'where' =>'',
        'between' =>'',
        'group' =>'',
        'having' =>'',
        'order' =>'',
        'limit' =>'',
    ];
    public function __construct()
    {
        $this->db = parent::connect();
    }

    public function table(string $table)
    {
        $this->params['table'] = $table;
        unset($table);
        return $this;
    }

    // ['age','>','21'],['age','22']
    public function where(array $where)
    {
        switch (count($where))
        {
            case 2:
                if($this->params['where'] != '')
                {
                    $this->params['where'] .= ' AND '.$where[0].' = ? ';
                }else{
                    $this->params['where'] = ' WHERE '.$where[0].' = ? ';
                }
                $this->params['prepare'][] = $where[1];
                break;
            case 3:
                if($this->params['where'] != '')
                {
                    $this->params['where'] = ' AND '.$where[0].' '.$where[1].' ? ';
                }else{
                    $this->params['where'] = ' WHERE '.$where[0].' '.$where[1].' ? ';
                }
                $this->params['prepare'][] = $where[2];
                break;
            default:
                break;
        }
        unset($where);
        return $this;
    }

    //['age',20,21]
    public function between(array $where)
    {
        if ($this->params['where'] === ''){
            if($this->params['between'] === ''){
                $this->params['between'] = ' WHERE '.$where[0].' BETWEEN '.$where[1].' AND '.$where[2];
            }else{
                $this->params['between'] .= ' AND '.$where[0].' BETWEEN '.$where[1].' AND '.$where[2];
            }
        }else{
            $this->params['between'] = ' AND '.$where[0].' BETWEEN '.$where[1].' AND '.$where[2];
        }
        unset($where);
        return $this;
    }

    public function group(array $group)
    {
        $this->params['group'] = ' GROUP BY '.implode(',',$group);
        unset($group);
        return $this;
    }

    //['sum(score)','>','400'],['age','21']
    public function having(array $having)
    {
        switch (count($having))
        {
            case 2:
                if($this->params['having'] ==='')
                {
                    $this->params['having'] = ' HAVING '.$having[0].' = '.$having[1];
                }else{
                    $this->params['having'] .= ' AND '.$having[0].' = '.$having[1];
                }
                break;
            case 3:
                if($this->params['having'] === '')
                {
                    $this->params['having'] = ' HAVING '.$having[0].' '.$having[1].' '.$having[2];
                }else{
                    $this->params['having'] .= ' AND '.$having[0].' '.$having[1].' '.$having[2];
                }
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
        if($this->params['order'] != '')
        {
            $this->params['order'] = ' AND '.$order[0].' '.$order[1].' ';
        }else{
            $this->params['order'] = ' ORDER BY '.$order[0].' '.$order[1].' ';
        }
        unset($order);
        return $this;
    }

    /**
     * @param $offset
     * @param string $length
     * @return $this
     */
    public function limit($offset,$length = '')
    {
        if($length === '' && strpos(',',$offset))
        {
            list($offset,$length)   =   explode(',',$offset);
        }
        $this->params['limit'] = ' LIMIT '.intval($offset).( $length != ''? ','.intval($length) : '' );
        unset($offset,$length);
        return $this;
    }

    /**
     * @param array $field
     * @return array
     */
    public function select(array $field = ['*'])
    {
        $sql = "SELECT ".implode(',',$field)." FROM ".$this->params['table']
        .$this->params['where']
        .$this->params['between']
        .$this->params['group']
        .$this->params['having']
        .$this->params['order']
        .$this->params['limit'];
        $sth = $this->db->prepare($sql);
        if(isset($this->params['prepare']))
        {
            $sth->execute($this->params['prepare']);
        }else{
            $sth->execute();
        }
        $data = $sth->fetchAll();
        unset($field,$sql);
        return $data;
    }

    /**
     * @param array $data
     * @return int
     */
    public function insert(array $data)
    {
        $sql = 'INSERT INTO '.$this->params['table'].'('.implode(',',array_keys($data[0])).') VALUES ';
        foreach ($data as $key => $value)
        {
            if(is_array($value))
            {
                $sql .= "('".implode("','",array_values($value))."'".') ,';
            }else{
                $sql .= "('".implode("','",array_values($data))."'".') ';
            }
        }
        $sql = rtrim($sql,',');
        unset($data,$key,$value);
        return $this->db->exec($sql);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function update(array $data)
    {
        $sql = "UPDATE ".$this->params['table']." SET ";
        foreach ($data as $k => $v)
        {
            $sql .= $k.'="'.$v.'",';
        }
        $sql = rtrim($sql,',');
        $sql .= $this->params['where'];
        $sth = $this->db->prepare($sql);
        unset($sql);
        return $sth->execute($this->params['prepare']);
    }

    /**
     * @return bool
     */
    public function delete()
    {
        $sql = 'DELETE FROM '.$this->params['table'].$this->params['where'];
        $sth = $this->db->prepare($sql);
        unset($sql);
        return $sth->execute($this->params['prepare']);
    }
}