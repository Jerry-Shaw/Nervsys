<?php
namespace ext;
use Dompdf\Exception;
use ext\lib\fileOperate;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;

class xml extends fileOperate
{
    //xml file path
    private static $_xmlfile;

    //xml object
    private static $_instance;
    private function __construct()
    {
    }

    //init
    public static function instance(string $filename = ''):xml
    {
        if (!self::$_instance instanceof xml) {
            self::$_instance = new self;
        }
        self::setFile($filename);
        return self::$_instance;
    }


    /**
     * get file mime
     * @param string $file
     * @return string
     */
    protected static function fileMime(string $file):string
    {
        $res = finfo_open(FILEINFO_MIME);
        $file = @finfo_file($res,$file);
        finfo_close($res);
        if (!$file) return '';
        return $file;
    }

    //set xml file path
    public static function setFile($filename):void
    {
        ( !$filename || 'xml' != self::fileSuffix($filename) ) &&
        self::$_xmlfile = $filename;
    }

    //get xml file
    public static function getXmlFile():string
    {
        return self::$_xmlfile ?? '';
    }

    /** alter tag value from xml file,root tag start with second
     * @param  array $arr muti-relation array
     * ej1 :xml file content:<a>  <b> <c> 2 </c> </b>  </a> ==> $arr = ['b' => [ 'c' => 'value'] ];
     * ej2 :xml file content:<a> <b> <c>2</c> <c>3</c> </b> </a> ==> $arr = ['b' => [ 'c' => ['value1',''value2]] ];
     * @param  string $xmlFile
     * @return bool
     * */
    public static function alterXmlFileB(array $arr, string $xmlFile = null):bool
    {
        //check
        $xmlFile = $xmlFile ? realpath($xmlFile) : (self::$_xmlfile ?? false);
        if (!$xmlFile || is_dir($xmlFile) || self::fileSuffix($xmlFile) != 'xml') {
            return false;
        }
        //handle xml file -> xml string -> xml object -> xml array-> array replace
        $xml = @file_get_contents($xmlFile);
        $arr = array_replace_recursive(json_decode(json_encode(new \SimpleXMLElement($xml)),true),$arr);
        $xml = self::getRootTag(['xmlStr' => $xml]);
        if (!$xml[0] || null === $arr) return false;

        //xml array rewrite to xml file
        return self::write(self::arrToStr([ $xml[0] => $arr ]),$xmlFile);
    }

    //SimpleXMLElement object
    private static $_xmlobj;
    /** alter tag value from xml file,root tag start with second (recommanded)
     * @param  $arr one dimension array, '/' means the deep of dimension, '--index' means the index of the same level and name tag
     * ej1: xml文件:<a> <b> <c> 2 </c> </b> <a> ==> $arr = ['b/c' => 'value'];
     * ej2 :xml文件:<a>  <b>2</b> <b>3</b>  </a> ==> $arr = ['b--1' => 'value'] ;
     * @return array keys: 'successNum' success alter number, 'errIndex' failed alter index from $arr,
     * 'status' only true when alter all tag value from $arr success
     * */
    public static function alterXmlFileA(array $arr,string $file = null):array
    {
        //check
        $file = $file ? realpath($file) : self::$_xmlfile ?? false;
        if (!$file ||
            is_dir($file) ||
            self::fileSuffix($file) != 'xml' ||
            !$arr ||
            count($arr) != count($arr,1)) {
            return ['status' => false];
        }
        $xmlobj = new \SimpleXMLElement(@file_get_contents($file));//transfer file to object
        $result = ['successNum' => 0,'errIndex' => [],'status' => false];
        foreach ($arr as $k => $v) {
            $node = explode("/", $k);//decompose '/'
            self::$_xmlobj = $xmlobj;
            if (count($node) == 1) {//one dimension tag
                $apart = self::separate($node[0]);//decompose'--',find the position
                self::attr(self::$_xmlobj, $apart['left'],$apart['right']);
                self::recordNsave($result,$file,$xmlobj,$k,$v);//alter it and record it to $result
            } else {//change muti dimension tag
                foreach ($node as $i => $v1I) {
                    $apart = self::separate($v1I);
                    if ($i == count($node) - 1) {
                        self::attr(self::$_xmlobj, $apart['left'], $apart['right']);
                        self::recordNsave($result,$file,$xmlobj,$k,$v);
                    } else {
                        self::attr(self::$_xmlobj, $apart['left'], $apart['right']);
                        if (null === self::$_xmlobj) {//alter failed, record it to $result
                            $result['errIndex'][] = $k;
                            break;
                        }
                    }
                }
            }
        }
        if ($result['successNum'] == count($arr)) $result['status'] = true;
        unset($xmlobj, $arr);
        return  $result;
    }

    //separate string $str with '--',get the left part and right part of $str
    private static function separate(string &$str):array
    {
        if (strrpos($str, '--') === false) {//没有多个平行节点
            $result['left'] = $str;
            $result['right'] = 0;
        } else {//有多个平行节点
            $result['left'] = strstr($str, '--', true);
            $result['right'] = str_replace('--', '', strstr($str, '--'));
        }
        return $result;
    }

    //alter xml file and save it to $result
    private static function recordNsave(array &$result,string &$file,\SimpleXMLElement &$xmlobj, string &$index,string &$value):void
    {
        if (null !== self::$_xmlobj) {
            $result['successNum']+=1;
            self::$_xmlobj[0] = $value;//修改最里面一层节点的值
            self::$_xmlobj = "";//指针重置
            $xmlobj->saveXML($file);//保存已修改的xml文件
            return;
        }
        $result['errIndex'][] = $index;//标记修改失败的index
        return;
    }

    /* access xml Object's attribute value
    * @param $xmlobj SimpleXMLElement object
    * @param $att  xml Object's attribute/xml file's xml node
    * @param $index
     * */
    private static function attr(\SimpleXMLElement &$xmlobj,string $att, int $index = 0):void
    {
        $xmlobj = $xmlobj->$att[$index];
    }

    /* write xml string to file
     * mention: overwrite existed file
     * @param string $file file path
     * @return bool
     * */
    public static function write(string $xmlStr, string $file = ''):bool
    {
        //check
        $file = $file ? : (self::$_xmlfile ?? false);
        parent::clean($file);
        if ( !@is_dir(dirname($file)) ||
            self::fileSuffix($file) != 'xml' ||
            false === @simplexml_load_string($xmlStr)  ) {
            return false;
        }
        //write
        $fes = @fopen($file, "w+");
        $xmlStr = '<?xml version="1.0"?>'."\n".$xmlStr;
        $fw = @fwrite($fes, $xmlStr);
        @fclose($fes);
        if (false === $fes || false === $fw) {
            return $fes;
        }
        unset($file, $fes, $fw);
        return true;
    }

    //transfer xml file to array
    public static function fileToArr(string $file):array
    {
        return self::toArr($file);
    }

    //transfer xml string to array
    public static function strToArr(string $xml):array
    {
        return self::toArr($xml);
    }

    /* transfer xml to array
     * @param string $mixed xml file path or xml string
     * */
    private static function toArr(string $mixed):array
    {   //check
        $mixed = $mixed ? :( self::$_xmlfile ?? false );
        if (!$mixed  ||
            false ===
            ( $mixed = file_exists($mixed) ? @simplexml_load_file($mixed) : @simplexml_load_string($mixed)  )) {
            return [];
        }//transfer
        $mixed = json_decode(json_encode($mixed), true);
        if (!$mixed) return [];
        return $mixed;
    }

    /* transfer xml array to xml string
     *  you can add attribute when build xml string
     *  if add attribute you can write as 'tagName-attribute$value#' => 'tagValue'
     *
     *  @param $xmlArr
     * @return xml string
     * */
    public static function arrToStr(array $xmlArr):string
    {
        //init
        $DOMDocument = new \DOMDocument("1.0", "utf-8");
        $DOMDocument->formatOutput = true;
        $DOMElement = &$DOMDocument;
        //check xml format (only one root node except version node)
        if (count(array_keys($xmlArr)) != 1 ) return '';
        //start build xml string
        try {
            self::setDom($xmlArr, $DOMElement, $DOMDocument);
        } catch (\Throwable $e) {
            Throw new Exception($e->getMessage(),$e->getCode());
        }
        return $DOMDocument->saveXML();
    }

    //loop find the tag , tag value ,tag attribute, tag attribute value and set them
    private static function setDom (array $mixed,\DOMNode $domElement, \DOMNode $DOMDocument):void
    {
        foreach ($mixed as $index=>$mixedElement) {
            if (is_int($index)) {
                if ($index==0) {//create first
                    $node = $domElement;
                } else {//create muti same node
                    $node = $DOMDocument->createElement($domElement->tagName);
                    $domElement->parentNode->appendChild($node);
                }
            } else {
                //create node in document
                $index = self::separate($index);
                $node = $DOMDocument->createElement($index['left']);

                //attributes append to element
                if ($index['right']) {
                    //filter last '#' if it existed
                    ($end = strrpos($index['right'], '#')) == strlen($index['right']) - 1 &&
                    $index['right'] = substr($index['right'], 0, $end);
                    $attributes = explode('#', $index['right']);
                    $end = [];
                    //pairs of attributes ,means how many number do string 'attr$val#' have
                    foreach ($attributes as $v) {
                        if (count($value = explode('$', $v)) == 2) {
                            $end[$value[0]] = $value[1];
                        }
                    }
                    self::setAttr($end, $DOMDocument, $node);
                }
                $domElement->appendChild($node);
            }
            is_array($mixedElement) ? self::setDom($mixedElement,$node,$DOMDocument) :
                self::setTagVal($mixedElement,$node,$DOMDocument);
        }
    }

    //set tag value,the end of loop
    public static function setTagVal(string $value,\DOMNode $domElement,\DOMNode $DOMDocument):void
    {
        $domElement->appendChild($DOMDocument->createTextNode($value));
    }

    //dom element('a') create attribute('attr') value('val') :  <a attr=val></a>
    public static function setAttr(array $attr,\DOMDocument &$dom,\DOMElement &$domEle):void
    {
        foreach ($attr as $k => $v) {
            //dom create attribute
            $attri = $dom->createAttribute(trim($k));
            $attri->value = trim($v);
            //element insert pairs of attributes
            $domEle->appendChild($attri);
        }
    }


    /* get the extension of file
     * @param $exist check the file existed(true) ,if not then false
     * */
    public static function fileSuffix(string $filename, bool $exist = false):string
    {
        if($exist && false === realpath($filename) ) {
            return '';
        }
        $filename = substr(strrchr($filename, '.'), 1);
        return $filename;
    }

    /* transfer xml array to xml file
     * @param array $xmlArr
     * */
    public static function arrToFile(array $xmlArr, string $file = null):bool
    {
        if (!self::write(self::arrToStr($xmlArr), self::$_xmlfile ? : $file)) {
            return false;
        }
        unset($xmlArr,$file);
        return true;
    }

    //hook function
    /* mention: the following function is used for parase xml string ,and get value from it
     * xml string: <tagName attr=attrVal>   tagVal  </tagName>
     * tagName:tag name
     * attr: tag attribute name
     * attrVal: tag attribute value
     * tagVal: tag value
     * xmlStr: xml string
     * */

    /*
    * @param string $function
    * @param array $param  the parameters which function need
    * */
    public static function hook(string $function,array $param):array
    {
        return is_callable(array('self',$function)) ?
            self::$function($param) : [''];
    }
    //get functions and relevant required parameters
    public static function getHookFun():string
    {
        $functions = [
            'getValByTagName' => [
                'getValByTagName(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                    'tagName' => 'tag name',
                ],
                'description' => 'xmlStr + tagName -> tag value'
            ],
            'getAttrValByTagName' => [
                'getAttrValByTagName(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                    'tagName' => 'tag name',
                    'attr' => 'tag attribute',
                ],
                'description' => 'xmlStr + tagName + attr -> tag attribute value'
            ],
            'getValByTagNameAtt' => [
                'getValByTagNameAtt(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                    'tagName' => 'tag name',
                    'attr' => 'tag attribute name',
                    'attrVal' => 'tag attribute value',
                ],
                'description' => 'xmlStr + tagName + attr + attrVal -> tag value'
            ],
            'getAttrValByAttr' => [
                'getAttrValByAttr(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                    'tagName' => 'tag name',
                    'attr' => 'tag attribute name',
                    'attrVal' => 'tag attribute value',
                    'searchAttr' => 'tag attribute name searched',
                ],
                'description' => 'xmlStr + tagName + attr + attrVal + searchAttr -> tag attribute value searched'
            ],
            'getRootTag' => [
                'getRootTag(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                ],
                'description' => 'xmlStr -> root tag name'
            ],
            'levelSearch' => [
                'levelSearch(array $param)' => 'function name',
                '$param' => [
                    'xmlStr' => 'xml string',
                    'tagName' => 'tag name',
                    'attr' => 'tag attribute name',
                    'attrVal' => 'tag attribute value',
                    'level' => 'tag level(root tag level is 1)'
                ],
                'description' => 'xmlStr + tagName + attr + attrVal + level -> tag value',
            ],
        ];
        return json_encode($functions,JSON_PRETTY_PRINT);
    }

    /*find the tag value with tag name
    @param array $param keys:tagName,xmlStr
    @return array $value tag values
     * */
    private static function getValByTagName(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['getValByTagName']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type'] = 'tagVal';
        $value = self::strSearch($param);
        return $value;
    }
    /*find the tag attribute value
    *@param array $param keys:tagName,xmlStr,attr
     * */
    private static function getAttrValByTagName(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['getAttrValByTagName']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type'] = 'attrVal';
        $value = self::strSearch($param);
        return $value;
    }
    /*find the tag value with tag name,attribute,attribute value
    @param array $param keys:tagName,xmlStr，attr,attrVal
    @return array $value  tag values
     * */
    private static function getValByTagNameAtt(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['getValByTagNameAtt']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type']='tagValByAttr';
        $value = self::strSearch($param);
        return $value;
    }

    /*find the attribute value with tag name,attribute,attribute value,attribute
     @param array $param keys:tagName,xmlStr，attr,attrVal,searchAttr
     @return array $value attribute values
     * */
    private static function getAttrValByAttr(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['getAttrValByAttr']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type']='attrValByAttr';
        $value = self::strSearch($param);
        return $value;
    }

    /*find the root tag
     @param array $param keys: xmlStr
     * */
    private static function  getRootTag(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['getRootTag']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type']='getRoot';
        $value = self::strSearch($param);
        return $value;
    }

    private static function levelSearch(array $param):array
    {
        $funParam = array_keys((json_decode(self::getHookFun(),true))['levelSearch']['$param']);
        $param = self::checkParam($funParam, $param);
        if (!$param) return [''];
        $param['type']='level';
        //search
        $value = self::strSearch($param);
        return $value;
    }

    /*check input parameters
    *@param array $funParam function required parameters
     * @param array $inputParam input parameters
     * @return array if failed []
     * */
    private static function checkParam(array $funParam, array $inputParam):array
    {

        foreach ($inputParam as $k1 => $v1) {
            if (!in_array($k1, $funParam,true)){
                $inputParam = [];
                break;
            }
        }
        return $inputParam;
    }
    /*decompose xml string to one dimension array
     *@param string $xmlStr xml string
     *@return array $xmlArr xml array (include all tag,tag value,attribute,attribute name,level)
      * */
    private static function strParse(string $xmlStr):array
    {
        $resouce = xml_parser_create();
        xml_parser_set_option($resouce, XML_OPTION_SKIP_WHITE, 1);
        xml_parser_set_option($resouce, XML_OPTION_CASE_FOLDING, 0);
        $xmlStr = xml_parse_into_struct($resouce, $xmlStr, $xmlArr);
        if (!$xmlStr) return [];
        foreach ($xmlArr as $k=>$v) {
            if ($v['type']=='cdata' || $v['type']=='close') {
                unset($xmlArr[$k]);
            }
        }
        xml_parser_free($resouce);
        return $xmlArr;
    }

    /* search in the xml array
     * @param array $param input parameters
     * @return array $value values if failed $value = [ 0 => '']
     * */
    private static function strSearch(array $param):array
    {
        $xmlArr =  self::strParse($param['xmlStr']);
        $value = array();//save values
        if (!$xmlArr) return $value[''];
        $end = false;
        fo1:foreach ($xmlArr as $k => $item) {
        sw2: switch ($param['type']) {
            case 'getRoot':
                $item['level'] == 1 ? $value[0] = $item['tag'] : true;
                $end = true;
                break 2;
            case 'level':
                if ($item['level'] == (int)$param['level']) {
                    $attr = isset($item['attributes'][$param['attr']]) && $item['attributes'][$param['attr']] == $param['attrVal'] ? true : false;
                    if ($attr) {
                        $value[] = $item['value'] ??  '';
                    }
                }
                break 1;
            default:
                //based on tag name
                if ($item['tag'] == $param['tagName']) {
                    sw3: switch ($param['type']) {
                        case 'tagVal':
                            $value[] = isset($item['value']) ? $item['value'] : '';
                            break 2;
                        case 'attrVal':
                            $value[] = isset($item['attributes'][$param['attr']]) ? $item['attributes'][$param['attr']] : '';
                            break 2;
                        case 'tagValByAttr':
                            $attr = (isset($item['attributes'][$param['attr']]) && $item['attributes'][$param['attr']]==$param['attrVal'])?true:false;
                            if ($attr) {
                                $value[] = isset($item['value'])?$item['value'] : '';
                            }
                            break 2;
                        case 'attrValByAttr':
                            $attr = (isset($item['attributes'][$param['attr']]) && $item['attributes'][$param['attr']]==$param['attrVal'])?true:false;
                            if ($attr) {
                                $value[] = isset($item['attributes'][$param['searchAttr']]) ? $item['attributes'][$param['searchAttr']] : '';
                            }
                            break 2;
                    }
                };
                break 1;
        }
        if ($end) {
            break 1;
        }
    }
        if (!$value) {
            $value = [''];
        }
        return $value;
    }


}
