<?php

function bit_string_to_hex($bits){
	// $bits = "10110111";
	// outputs "b7"

	$num_of_bits = strlen($bits);

	$c = $num_of_bits % 8;
	if ($c > 0){
		for ($i=0; $i<8-$c; $i++)
			$bits = "0" . $bits;
		$num_of_bits = strlen($bits);
	}

	$hex_str = "";
	for ($i=$num_of_bits-1; $i>=0; $i-=8){
		$n = 0;
    
		$n += $bits[$i-0] << 0;
		$n += $bits[$i-1] << 1;
		$n += $bits[$i-2] << 2;
		$n += $bits[$i-3] << 3;
		$n += $bits[$i-4] << 4;
		$n += $bits[$i-5] << 5;
		$n += $bits[$i-6] << 6;
		$n += $bits[$i-7] << 7;
    
		$temp = dechex($n);

		$hex_str = (strlen($temp) == 2 ? $temp : "0" . $temp) . $hex_str;
	}

	return $hex_str;
}

function hex_to_bit_string($hex_str){
	$bits = "";
	$hex_count = strlen($hex_str);
	for ($i=$hex_count-1; $i>=0; $i--){

		$val = base_convert($hex_str[$i], 16, 2) . "";

		$c = 4 - strlen($val);
		if ($c == 1)
			$val = "0" . $val; 
		elseif ($c == 2)
			$val = "00" . $val;
		elseif ($c == 3)
			$val = "000" . $val;

		$bits = $val . $bits;
	}

	return $bits;
}

function get_bit_id_from_hex($hex_str, $bit_id){
		
	// bit_id starts from 0
	// returns -1 if bit_id is out of range
	// otherwise returns 0 or 1

	/*
		example:
			$hex_str = "b7";
			$bit_id = 5;

			$hex_str in bit string is "10110111"
			bit id 0 from the right is 1
			...
			bit id 5 from the right is 1
	*/

	$hex_id = floor($bit_id / 4);
	$hex_count = strlen($hex_str);
    
	if ($hex_count > $hex_id){
        
		// extract only relevant hex letter (4 bits)
		$val = base_convert($hex_str[intval($hex_count - 1 - $hex_id)], 16, 2) . "";
    
		// get bit_id relative to $val, bit_id is between 0 and 3 inclusive
		if ($bit_id > 3)
			$bit_id = $bit_id % ($hex_id * 4);
    
		if ($bit_id >= strlen($val))
			return 0;
    
		return $val[strlen($val) - 1 - $bit_id];
	} else {
		return -1;
	}
}

/**
 * Copyright (c) 2016 rryqszq4
 *
 *
 *   TEA coder encrypt 64 bits value, by 128 bits key,
 *   QQ do 16 round TEA.
 *
 *   TEA ??,  64????, 128????, qq?TEA????16???
 *
 *
 */
class Tea
{
    private static $op = 0xffffffff;
    public static function encrypt($v, $k)
    {
        $vl = strlen($v);
        $filln = (8 - ($vl + 2)) % 8 + 2;
        if ($filln >= 2 && $filln < 9) {
        } else {
            $filln += 8;
        }
        $fills = '';
        for ($i = 0; $i < $filln; $i++) {
            $fills .= chr(rand(0, 0xff));
        }
        $v = chr(($filln - 2) | 0xF8) . $fills . $v;
        $tmp_l = strlen($v) + 7;
        $v = pack("a{$tmp_l}", $v);
        $tr = pack("a8", '');
        $to = pack("a8", '');
        $r = '';
        $o = pack("a8", '');
        for ($i = 0; $i < strlen($v); $i = $i + 8) {
            $o = self::_xor(substr($v, $i, 8), $tr);
            $tr = self::_xor(self::block_encrypt($o, $k), $to);
            $to = $o;
            $r .= $tr;
        }
        return $r;
    }
    public static function decrypt($v, $k)
    {
        $l = strlen($v);
        $prePlain = self::block_decrypt($v, $k);
        $pos = (ord($prePlain[0]) & 0x07) + 2;
        $r = $prePlain;
        $preCrypt = substr($v, 0, 8);
        for ($i = 8; $i < $l; $i = $i + 8) {
            $x = self::_xor(
                self::block_decrypt(self::_xor(
                    substr($v, $i, $i + 8), $prePlain),
                    $k),
                $preCrypt);
            $prePlain = self::_xor($x, $preCrypt);
            $preCrypt = substr($v, $i, $i + 8);
            $r .= $x;
        }
        if (substr($r, -7) != pack("a7", '')) {
            return "";
        }
        return substr($r, $pos + 1, -7);
    }
    private static function _xor($a, $b)
    {
        $a = self::_str2long($a);
        $a1 = $a[0];
        $a2 = $a[1];
        $b = self::_str2long($b);
        $b1 = $b[0];
        $b2 = $b[1];
        return self::_long2str(($a1 ^ $b1) & self::$op) .
        self::_long2str(($a2 ^ $b2) & self::$op);
    }
    public static function block_encrypt($v, $k)
    {
        $s = 0;
        $delta = 0x9e3779b9;
        $n = 16;
        $k = self::_str2long($k);
        $v = self::_str2long($v);
        $z = $v[1];
        $y = $v[0];
        /* start cycle */
        for ($i = 0; $i < $n; $i++) {
            $s += $delta;
            $y += (self::$op & ($z << 4)) + $k[0] ^ $z + $s ^ (self::$op & ($z >> 5)) + $k[1];
            $y &= self::$op;
            $z += (self::$op & ($y << 4)) + $k[2] ^ $y + $s ^ (self::$op & ($y >> 5)) + $k[3];
            $z &= self::$op;
        }
        /* end cycle */
        return self::_long2str($y) . self::_long2str($z);
    }
    public static function block_decrypt($v, $k)
    {
        $delta = 0x9e3779b9;
        $s = ($delta << 4) & self::$op;
        #$s=0xC6EF3720;
        $n = 16;
        $v = self::_str2long($v);
        $k = self::_str2long($k);
        $y = $v[0];
        $z = $v[1];
        $a = $k[0];
        $b = $k[1];
        $c = $k[2];
        $d = $k[3];
        for ($i = 0; $i < $n; $i++) {
            $z -= (($y << 4) + $c) ^ ($y + $s) ^ (($y >> 5) + $d);
            $z &= self::$op;
            $y -= (($z << 4) + $a) ^ ($z + $s) ^ (($z >> 5) + $b);
            $y &= self::$op;
            $s -= $delta;
            $s &= self::$op;
        }
        return self::_long2str($y) . self::_long2str($z);
    }
    private static function _str2long($data)
    {
        $n = strlen($data);
        $tmp = unpack('N*', $data);
        $data_long = array();
        $j = 0;
        foreach ($tmp as $value) {
            $data_long[$j++] = $value;
            //if ($j >= 4) break;
        }
        return $data_long;
    }
    private static function _long2str($l)
    {
        return pack('N', $l);
    }
    public static function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/=', '*-_');
    }
    public static function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '*-_', '+/='));
    }
	/*
    public static function test()
    {
        $v = 'a';
        $k = '13404B5427A13C45E69BF78147E748D8';
        $data = self::encrypt($v, pack("H*", $k));
        $data = self::decrypt($data, pack("H*", $k));
        var_dump($data);
        $v = '008022a6785ec57392824936e439cd97' .
            '1d740c2ac0ca0d94e8c258aa2da8498a' .
            '0576c330828534efad26ca206eef39c3' .
            '994ca5265c719ef3289487893d2ef4e6' .
            'ac72f35adfa3b02ab701673472bb6c88' .
            'ba871b7bfe3bbe6f17c23b88d5a77e44' .
            '4b828456634aba70a3f36eb6763c51ca' .
            '5268ef461f20c73d45b71976a7bf347a' .
            'd75b00000000a9884e910004214b4650';
        $data = self::encrypt($v, pack("H*", $k));
        $data = self::decrypt($data, pack("H*", $k));
        var_dump($data);
        $v = '008022a6785ec57392824936e439cd97' .
            '1d740c2ac0ca0d94e8c258aa2da8498a' .
            '0576c330828534efad26ca206eef39c3' .
            '994ca5265c719ef3289487893d2ef4e6' .
            'ac72f35adfa3b02ab701673472bb6c88' .
            'ba871b7bfe3bbe6f17c23b88d5a77e44' .
            '4b828456634aba70a3f36eb6763c51ca' .
            '5268ef461f20c73d45b71976a7bf347a' .
            'd75b00000000a9884e910004214b4650';
        $data = self::encrypt(pack("H*", $v), pack("H*", $k));
        $data = self::decrypt($data, pack("H*", $k));
        var_dump(bin2hex($data));
    }
	*/
}