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
    protected $param_field = '*';
    protected $param_table = '';
    protected $param_where = '';
    protected $param_orWhere = '';
    protected $param_between = '';
    protected $param_group = '';
    protected $param_having = '';
    protected $param_order = '';
    protected $param_limit = '';
    protected $param_join = '';
    protected $increment_decrement = '';
    protected $sql = '';
    protected $param_prepare = [];
    public function table(string $table)
    {
        if(strpos($table,' ')){
            $this->param_table = '`'.implode('`',explode(' ',$table)).'`';
        }else{
            $this->param_table = '`'.$table.'`';
        }
        unset($table);
        return $this;
    }
    // ['age','>','21'],['age','22']['or','age','22']['or','age','<=','18']['in','age',[1,2,3]],['not','age',[18]]['like','language','Chinese']
    public function where(array $where)
    {

        switch ($where[0])
        {
            case 'or':
                if(count($where) === 3)
                {
                    $this->param_where .= ' OR `'.$where[1].'` = ?';
                    $this->param_prepare[] = $where[2];
                }elseif (count($where) === 4)
                {
                    $this->param_where .= ' OR `'.$where[1].'` '.$where[2].' ?';
                    $this->param_prepare[] = $where[3];
                }
                break;
            case 'in':
                if($this->param_where === '')
                {
                    if(is_string($where[2]))
                    {
                        $where[2] = explode(',',$where[2]);
                    }
                    $this->param_where = ' WHERE `'.$where[1].'` IN ("'.implode('","',$where[2]).'")';
                }else{
                    if(is_string($where[2]))
                    {
                        $where[2] = explode(',',$where[2]);
                    }
                    $this->param_where .= ' AND `'.$where[1].'` IN ("'.implode('","',$where[2]).'")';
                }
                break;
            case 'not':
                if($this->param_where === '')
                {
                    if(is_string($where[2]))
                    {
                        $where[2] = explode(',',$where[2]);
                    }
                    $this->param_where = ' WHERE `'.$where[1].'` NOT IN ("'.implode('","',$where[2]).'")';
                }else{
                    if(is_string($where[2]))
                    {
                        $where[2] = explode(',',$where[2]);
                    }
                    $this->param_where .= ' AND `'.$where[1].'` NOT IN ("'.implode('","',$where[2]).'")';
                }

                break;
            case 'like':
                if($this->param_where === '')
                {
                    $this->param_where = ' WHERE `'.$where[1].'` LIKE "'.$where[2].'"';
                }else{
                    $this->param_where .= ' AND `'.$where[1].'` LIKE "'.$where[2].'"';
                }
                break;
            default:
                if(count($where) === 2)
                {
                    if($this->param_where != '')
                    {
                        $this->param_where .= ' AND `'.$where[0].'` = ? ';
                    }else{
                        $this->param_where = ' WHERE `'.$where[0].'` = ? ';
                    }
                    $this->param_prepare[] = $where[1];
                    break;
                }elseif (count($where) === 3)
                {
                    if($this->param_where != '')
                    {
                        $this->param_where = ' AND `'.$where[0].'` '.$where[1].' ? ';
                    }else{
                        $this->param_where = ' WHERE `'.$where[0].'` '.$where[1].' ? ';
                    }
                    $this->param_prepare[] = $where[2];
                }
                break;
        }
        unset($where);
        return $this;
    }
    //['age',20,21]
    public function between(array $where)
    {
        if ($this->param_where === ''){
            if($this->param_between === ''){
                $this->param_between = ' WHERE `'.$where[0].'` BETWEEN '.$where[1].' AND '.$where[2];
            }else{
                $this->param_between .= ' AND `'.$where[0].'` BETWEEN '.$where[1].' AND '.$where[2];
            }
        }else{
            $this->param_between = ' AND `'.$where[0].'` BETWEEN '.$where[1].' AND '.$where[2];

        }
        unset($where);
        return $this;
    }

    public function group(string ...$group)
    {
        $this->param_group = ' GROUP BY `'.implode(',',$group).'`';
        unset($group);
        return $this;
    }
    //['sum(score)','>','400'],['age','21']
    public function having(array $having)
    {
        switch (count($having)) {
            case 2:
                if($this->param_having ==='')
                {
                    $this->param_having = ' HAVING `'.$having[0].'` = ?';
                    $this->param_prepare[] = $having[1];
                }else{
                    $this->param_having .= ' AND `'.$having[0].'` = ?';

                }
                $this->param_prepare[] = $having[1];
                break;
            case 3:
                if($this->param_having === '')
                {
                    $this->param_having = ' HAVING `'.$having[0].'` '.$having[1].' ?';
                }else{
                    $this->param_having .= ' AND `'.$having[0].'` '.$having[1].' ?';
                }
                $this->param_prepare[] = $having[2];
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
        if($this->param_order != '')
        {
            $this->param_order = ' AND `'.$order[0].'` '.$order[1].' ';
        }else{
            $this->param_order = ' ORDER BY `'.$order[0].'` '.$order[1].' ';

        }
        unset($order);
        return $this;
    }

    public function limit($offset,$length = '')
    {
        if ($length === '' && strpos(',', $offset)) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->param_limit = ' LIMIT '.intval($offset).( $length != ''? ','.intval($length) : '' );
        unset($offset,$length);
        return $this;
    }

    public function increment(string $field,int $step = 1)
    {
        $icon = '';
        if(strpos($this->sql,'=') || $this->increment_decrement != ''){
            $icon = ',';
        }
        $this->increment_decrement .= $icon.'`'.$field.'` = `'.$field.'`+'.$step;
        unset($icon,$field);
        return $this;
    }

    public function decrement(string $field,int $step = 1)
    {
        $icon = '';
        if(strpos($this->sql,'=') || $this->increment_decrement != ''){
            $icon = ',';
        }
        $this->increment_decrement .= $icon.'`'.$field.'` = `'.$field.'`-'.$step;
        unset($icon,$field);
        return $this;
    }

    //['a.id','=','b.id']
    public function join(string $table,array $join,string $type = 'inner')
    {
        if(strpos($table,' ')){
            $table = '`'.implode('`',explode(' ',$table)).'`';
        }
        if(is_array($join) && !empty($join))
        {
            $this->param_join .= ' '.$type .' JOIN '.$table.' ON ';
            $str = '';
            foreach ($join as $k => $v){
                if(!is_array($v))
                {
                    if(strpos($v,'.'))
                    {
                        $this->param_join .= '`'.implode('`.`',explode('.',$v)).'`';
                    }else{
                        $this->param_join .= $v.' ';
                    }
                }else{
                    foreach ($v as $key => $value)
                    {
                        if(strpos($value,'.'))
                        {
                            $str .= '`'.implode('`.`',explode('.',$value)).'`';
                        }else{
                            $str .= $value.' ';
                        }
                    }
                    $str = $str.' AND ';
                }
            }
            if($str != '')
            {
                $this->param_join .= rtrim($str,' AND');
            }
        }
        unset($table,$join,$type,$str,$k,$v,$key,$value);
        return $this;
    }

    public function select(string ...$field)
    {
        if (!empty($field))
            $this->param_field = implode(',',$field);
        $this->sql = ' SELECT  '.$this->param_field.' FROM '.$this->param_table;
        unset($table);
        return $this;
    }

    public function insert(string $table,array $data)
    {
        $this->param_table = $table;
        $this->sql = 'INSERT INTO '.$this->param_table.'('.implode(',',array_keys($data[0])).') VALUES ';
        $prepare = [];
        foreach ($data as $key => $value)
        {
            if(is_array($value))
            {
                $this->sql .= '('.rtrim(str_repeat('?,',count($value)),',').'),';
                $prepare = array_merge($prepare,array_values($value));
            }else{
                $this->sql .= '('.rtrim(str_repeat('?,',count($data)),',').')';
                $this->param_prepare = array_values($data);
                break;
            }
        }
        $this->sql = rtrim($this->sql,',');
        if(!empty($prepare))
            $this->param_prepare = $prepare;
        unset($data,$key,$value,$table,$prepare);
        return $this;
    }

    public function update(string $table,array $data = [])
    {
        $this->param_table === '' ?$this->param_table = $table:$this->param_table;
        $this->sql .= 'UPDATE '.$this->param_table.' SET ';
        if(!empty($data)){
            foreach ($data as $k => $v)
            {
                $this->sql .= $k.'= ? ,';
                $this->param_prepare[] = $v;
            }
        }
        $this->sql = rtrim($this->sql,',');
        unset($table,$data,$k,$v,$key,$value);
        return $this;
    }

    public function delete(string $table)
    {
        $this->param_table = $table;
        $this->sql = 'DELETE FROM '.$this->param_table.$this->param_where;
        return $this;
    }
    public function exec()
    {
        $sql = $this->sql
            .$this->increment_decrement
            .$this->param_where
            .$this->param_between
            .$this->param_group
            .$this->param_having
            .$this->param_order
            .$this->param_limit;
        $sth = parent::connect()->prepare($sql);
        unset($sql);
        return $sth->execute($this->param_prepare);
    }

    public function fetchAll(int $fetch_style = \PDO::FETCH_ASSOC)
    {
        if($this->sql == '')
            $this->sql = 'SELECT * FROM'.$this->param_table;
        $this->sql .= $this->param_join
            .$this->param_where
            .$this->param_between
            .$this->param_group
            .$this->param_having
            .$this->param_order
            .$this->param_limit;
        echo $this->sql."\r\n";
        $sth = parent::connect()->prepare($this->sql);
        $sth->execute($this->param_prepare);
        unset($field);
        return  $sth->fetchAll($fetch_style);
    }

}