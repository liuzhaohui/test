<?php
/***************************************************************************
 * 
 * 用法 ： 
 * $obj = new Geohash();
 * $obj->set('115.858353', '39.041595', 40);
 * $intCode = $obj->getIntCode(35);
 * $arrNeighbor = $obj->getNeighbors(35);
 **************************************************************************/

class Geohash {
    private $_strLng = '';
    private $_strLat = '';
    private $_strCode = '';
    private $_arrCode = array();
    const CODE_INTERVAL_LEN = 2;
    private static $_arrCodeLen = array(15,19,23,27,31,35,39);
    const CODE_LEFT_EDGE = 0;
    const CODE_RIGHT_EDGE = 31;

    private static function _hashone($floatIn, $intLen, $floatS, $floatE) {
        if ($intLen <= 0) return ''; 
        else {
            $floatM = ($floatS + $floatE) / 2;
            if ($floatIn >= $floatM) {	// 大于等于(等于即边界线上)编码为1
                return '1' .  self::_hashone($floatIn, $intLen -1 , $floatM, $floatE);
            } else {
                return '0' .  self::_hashone($floatIn, $intLen -1 , $floatS, $floatM);
            }
        }
        return '';
    }
    
    private function _merge($strCodeLg, $strCodeLt) {
    	$intLenLg = strlen($strCodeLg);
    	$intLenLt = strlen($strCodeLt);
    	$arrCode[0] = $strCodeLg;
        $arrCode[1] = $strCodeLt;
        $arrFlag[0] = 0;
        $arrFlag[1] = 0;
    
        $strCode = '';
        $intFlag = 0;
        for(; ($arrFlag[0] < $intLenLg) || ($arrFlag[1] < $intLenLt); ) {
                $i = $arrFlag[$intFlag];
                $strCode .= "" . intval($arrCode[$intFlag][$i]);
                if ($intFlag == 0) {
                    $arrFlag[$intFlag] ++;
                    $intFlag = 1; 
                }
                else {
                    $arrFlag[$intFlag] ++;
                    $intFlag = 0;
                }
        }
        return $strCode;
    }
    
    /**
     * 通过浮点形式的经纬度，获取到规定长度的整形的经纬度
     * @param unknown_type $strLg
     * @param unknown_type $strLt
     * @param unknown_type $intLen
     */
	public static function hash($strLg, $strLt, $intLen = 39) {
        $floatLg = floatval($strLg); 
        $floatLt = floatval($strLt);  
        $intLen = intval($intLen);
        
        // 经纬度分别编码, 计算编码长度
        $intLngLen = intval($intLen / 2);  
        $intLatLen = intval($intLen / 2);  
        if ($intLen % 2 == 1) {
        	$intLngLen = $intLngLen + 1;
        }
        
        // 经纬度分别计算二进制编码
        $strLng = self::_hashone($floatLg, $intLngLen, -180, 180);
        $strLat = self::_hashone($floatLt, $intLatLen, -90, 90);
        
        $arrOut = array();
        $arrOut['lng'] = self::_bin2int($strLng);
        $arrOut['lat'] = self::_bin2int($strLat);
        return $arrOut;
    }
    
    /**
     * 设置经纬度以及二进制编码长度
     * @param string $strLg  经度
     * @param string $strLt  纬度
     * @param int $intLen    二进制编码长度
     * @return string  二进制编码
     */
    public function set($strLg, $strLt, $intLen = 39)
    {
        $floatLg = floatval($strLg); 
        $floatLt = floatval($strLt);  
        $intLen = intval($intLen);
        
        // 经纬度分别编码, 计算编码长度
        $intBinLen = intval($intLen / 2);  
        if ($intLen % 2 == 1) $intBinLen += 1;
        
        // 经纬度分别计算二进制编码
        $this->_strLng = self::_hashone($floatLg, $intBinLen, -180, 180);
        $this->_strLat = self::_hashone($floatLt, $intBinLen, -90, 90);
        
        // 合并经纬度二进制编码
        $strCode = $this->_merge($this->_strLng, $this->_strLat);
        
        // 截断保留所需长度
        $this->_strCode = substr($strCode, 0, $intLen);
        
        // 计算十进制编码值
        $this->_arrCode = array();	// 清空之前可能的编码
        $intCode = 0;
    	for ($i=0; $i<$intLen; ) {
            $intCode = $intCode * 2 + intval($this->_strCode[$i]);
            $i ++;
            //if (in_array($i, self::$_arrCodeLen, true)) {
            	$this->_arrCode[$i] = $intCode;
            //}
        }
	var_dump($this->_arrCode);
    
        return $this->_strCode;
    }

    /**
     * 获取当前坐标的编码（已转换为十进制数）
     * @param $intLen 二进制编码长度 
     */
    public function getIntCode($intLen) {
    	if (! isset($this->_arrCode[$intLen])) {
    		$strCode = substr($this->_strCode, 0, $intLen);
    		$this->_arrCode[$intLen] = self::_bin2int($strCode);
    	}
       	return $this->_arrCode[$intLen];
    }
    
    /**
     * 获取邻居区域的编码
     * @param $intLen
     * @return 邻居编码，数组长度{1,3}
     */
    public function getNeighbors($intLen) {
    	$arrNeighbors = array();
    	
    	$intLenLng = $intLenLat = intval($intLen/2);
    	if ($intLen % 2 == 1) {
    		$intLenLng += 1;
    	}
    	$strLng = substr($this->_strLng, 0, $intLenLng); 
    	$strLat = substr($this->_strLat, 0, $intLenLat);
    	$intLeftLng = $this->_nearEdge($strLng); 
    	$intLeftLat = $this->_nearEdge($strLat); 
    	$strNLng = '';
    	$strNLat = '';
    	
		if (($intLeftLng == 0) && ($intLeftLat == 0)) {
			return $arrNeighbors;
		}
    	if ($intLeftLng != 0) { // 经度靠近边界
    		 $intLngCode = self::_bin2int($strLng); // 获取十进制值
    		 $intLngCode += $intLeftLng;   // 邻居经度值
    		 $strNLng = self::_int2bin($intLngCode);  // 邻居经度二进制编码
    		 $strTmp = $this->_merge($strNLng, $strLat);
    		 $strCode = substr($strTmp, 0, $intLen);
    		 $intCode = self::_bin2int($strCode);
    		 $strCode15 = substr($strTmp, 0, self::$_arrCodeLen[0]);
    		 $intCode15 = self::_bin2int($strCode15);
    		 $arrBlock = array(
    		 	'strlng' => $strNLng,
    		 	'strlat' => $strLat,
    		 	'intcode' => $intCode,
    		 	'code15' => $intCode15,
    		 );
    		 $arrNeighbors[] = $arrBlock;
    	}
    	if ($intLeftLat != 0) { // 纬度靠近边界
    		$intLatCode = self::_bin2int($strLat); // 获取十进制值
    		 $intLatCode += $intLeftLat;   // 邻居纬度值
    		 $strNLat = self::_int2bin($intLatCode);  // 邻居纬度二进制编码
    		 $strTmp = $this->_merge($strLng, $strNLat);
    		 $strCode = substr($strTmp, 0, $intLen);
    		 $intCode = self::_bin2int($strCode);
    		 $strCode15 = substr($strTmp, 0, self::$_arrCodeLen[0]);
    		 $intCode15 = self::_bin2int($strCode15);
    		 $arrBlock = array(
    		 	'strlng' => $strLng,
    		 	'strlat' => $strNLat,
    		 	'intcode' => $intCode,
    		 	'code15' => $intCode15,
    		 );
    		 $arrNeighbors[] = $arrBlock;
    	}
    	if (! empty($strNLng) && ! empty($strNLat)) {
    		$strTmp = $this->_merge($strNLng, $strNLat);
    		$strCode = substr($strTmp, 0, $intLen);
    		$intCode = self::_bin2int($strCode);
    		$strCode15 = substr($strTmp, 0, self::$_arrCodeLen[0]);
    		$intCode15 = self::_bin2int($strCode15);
    		$arrBlock = array(
    		 	'strlng' => $strNLng,
    		 	'strlat' => $strNLat,
    		 	'intcode' => $intCode,
    			'code15' => $intCode15,
    		 );
    		$arrNeighbors[] = $arrBlock;
    	}
    	return $arrNeighbors;
    }
    
    /**
     * 判断当前编码是否靠近边界
     * 
     * @param string $strSCode
     * @return 0 - 不靠近边界
     * 		   -1  靠近左边界
     * 			1 靠近右边界
     */
   
	/*changed by chentianyu*/
    private function _nearEdge($strSCode) {
    	$intLen = strlen($strSCode);
    	$intBLen = $intLen - Geohash::CODE_INTERVAL_LEN;
    	if ($intBLen <= 0) {
    		return 0;
    	}
    	$strLeft = substr($strSCode, $intBLen);
    	$intLeft = self::_bin2int($strLeft);
    	//00,01,10,11，前两个是左边界，后两个是右边界
    	if ($intLeft == 0 || $intLeft == 1) {
    		return -1;
    	} else if ($intLeft == 2 || $intLeft == 3) {
    		return 1;
    	}
    	return 0;
    }
    
    /**
     * 二进制（字符串）转换为十进制整数
     * @param string $strBin
     */
    private static function _bin2int($strBin) {
    	$intRawLen = strlen($strBin);
    	$intCode = 0;
    	
    	for ($i=0; $i<$intRawLen; $i ++) {
            $intCode = $intCode * 2 + intval($strBin[$i]);
        }
        return $intCode;
    }
    
    /**
     * 给定十进制整数$intN，返回长度为$intLen的二进制字符串
     * @param int $intN
     * @param int $intLen
     */
    private static function _int2bin($intN, $intLen) {
    	if (($intN > 0) || ($intLen > 0)) {
    		return self::_int2bin(intval($intN/2), $intLen - 1) . "" . intval($intN % 2);
    	}
		return "";
    }

}

?>
