<?php
/**
 * User: MaxZues
 * email: maxzuesking@gmail.com
 * Date: 2018-11-9
 * Time: PM 8:49
 */
namespace ext;
use core\handler\factory;

/**
 * Public function library for public methods
 * Class common_function
 * @package ext
 */
class common_function extends factory
{

    /**
     * Catch an exception and throw it for interface development
     * @param $code
     * @param $msg
     * @throws \Exception
     */
    function abortMy(int $code,string $msg)
    {
        throw new \Exception($msg,$code);
    }

    /**
     * Used for amount conversion, divided into units of int
     * @param int $money
     * @return string
     */
    public function toMoney(int $money)
    {
        if($money > 0){
            return number_format($money/100,2,'.',',');
        }else{
            return 'N/A';
        }

    }

    /**
     * Get the distance based on the date today
     * @param string $date
     * @return int
     */
    public function getDateNumber(string $date)
    {
        $dateTime = strtotime($date);
        $newTime = time();

        $number = intval((($newTime - $dateTime)/3600/24));
        return $number;
    }

    /**
     * Object to array
     * @param unknown $obj
     * @return unknown|NULL[]
     */
    public function objToArr($obj){
        if(!is_object($obj) && !is_array($obj)) {
            return $obj;
        }
        $arr = array();
        foreach($obj as $k => $v){
            $arr[$k] = $this->objToArr($v);
        }

        return $arr;
    }

    /**
     * Recursively create a folder
     *
     * @param unknown $dir
     * @param number $mode
     * @return boolean
     */
    public function mkdirs($dir, $mode = 0755)
    {
        if (! is_dir($dir)) {
            if (! $this->mkdirs(dirname($dir))) {
                return false;
            }
            if (! mkdir($dir, $mode)) {
                return false;
            }
        }
        return true;
    }

    /***
     * Confuse HTML content, prevent WeChat from detecting html, and reduce the risk of blocked domain names
     * @param unknown $content
     * @return string
     */
    public function encrypt_html( $content ){
        if( empty($content)){
            return $content;
        }
        preg_match_all ( "/[\xc2-\xdf][\x80-\xbf]+|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}|[\x01-\x7f]+/e", $content, $r );
        //匹配utf-8字符，
        $str = $r [0];
        $l = count ( $str );
        for($i = 0; $i < $l; $i ++) {
            $value = ord ( $str [$i] [0] );
            if ($value < 223) {
                $str [$i] = rawurlencode ( utf8_decode ( $str [$i] ) );
                //先将utf8编码转换为ISO-8859-1编码的单字节字符，urlencode单字节字符.
                //utf8_decode()的作用相当于iconv("UTF-8","CP1252",$v)。
            } else {
                //                 $str [$i] = "%u" . strtoupper ( bin2hex ( iconv ( "UTF-8", "UCS-2", $str [$i] ) ) );
                $str [$i] = "%u" . strtoupper ( bin2hex ( mb_convert_encoding($str [$i], "UCS-2", "UTF-8") ) );
            }
        }
        $content =  join ( "", $str );

        $content = str_replace('%', "#",$content );

        $function_name = 's';
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<10;$i++){
            $function_name.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }

        $str = "<meta charset=\"utf-8\"><script charset='UTF-8'>function {$function_name}({$function_name}){document.write( unescape({$function_name}.replace(/#/g,'%')) );}";
        $str .= "{$function_name}('".$content."'); </script>";
        return $str;
    }

    /**
     * Verify that the phone number is correct
     * @author honfei
     * @param number $mobile
     */
    public function isMobile($mobile)
    {
        if (!is_numeric($mobile)) {
            return false;
        }
        return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
    }


    /**
     * Listed yesterday to today, this week, this month
     * @return array
     */
    public function get_month_weeks_today()
    {
        $time = time(); // 格式化时间
        $data = array(); // 格式化时间
        $data['month_star'] = ''; //本月第一天
        $data['month_end'] = ''; //本月最后一天
        $data['today'] = date('Y-m-d 00:00:00',$time); //今天
        $data['weeks_star'] = '';//本周开始
        $data['weeks_end'] = ''; //本周结束
        $data['month_number'] = ''; //本月天数


        $j = date('t');
        $start_time = strtotime(date('Y-m-01'));  //获取本月第一天时间戳

        $array = array();
        for($i=0;$i<$j;$i++){
            $array[] = date('Y-m-d',$start_time+$i*86400); //每隔一天赋值给数组
        }


        // 查看本月有多少天
        $array_number = count($array);
        $data['month_star'] = $array[0];
        $data['month_end'] = $array[$array_number-1].' '.'23:59:59';
        $data['month_number'] = $array_number;

        //本周的第一天和最后一天
        $date = new \DateTime();
        $date->modify('this week');
        $data['weeks_star'] = $date->format('Y-m-d');
        $date->modify('this week +6 days');
        $data['weeks_end'] = $date->format('Y-m-d').' '.'23:59:59';

        return $data;
    }

    /**
     * Two-dimensional arrays are de-weighted according to the key
     * @param $arr
     * @param $key
     * @return array
     */
    public function array_unset_tt($arr,$key)
    {

        $res = array();
        foreach ($arr as $value) {

            if(isset($res[$value[$key]])){

                unset($value[$key]);
            }
            else{

                $res[$value[$key]] = $value;
            }
        }
        return $res;
    }

    /**
     * Pwd key generation
     * @param int $strlen
     * @return string
     */
    public function pwd_key_home(int $strlen = 5):string
    {
        $str = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $len = strlen($str)-1;
        $randString = '';
        for($i = 0;$i < $strlen;$i ++){
            $num = mt_rand(0, $len);
            $randString .= $str[$num];
        }
        return $randString ;
    }


    /**
     * Two-dimensional arrays are sorted by field
     * @params array $array 需要排序的数组
     * @params string $field 排序的字段
     * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
     */
    public function array_sequence($array, $field, $sort = 'SORT_DESC')
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }


    /**
     * Millisecond timestamp converted to date
     * @param $tag
     * @param $time
     * @return false|string
     */
    public function msecdate($tag, $time)
    {
        $a = substr($time,0,10);
        $date = date($tag,$a);
        return $date;
    }

    /**
     * Verify that the mailbox format is correct
     * @param $email
     * @return bool
     */
    public function isEmail(string $email):bool
    {
         $pattern = '/^[a-z0-9]+([._-][a-z0-9]+)*@([0-9a-z]+\.[a-z]{2,14}(\.[a-z]{2})?)$/i';
         if(preg_match($pattern,$email)){
             return true;
         }else {
             return false;
         }
    }


}