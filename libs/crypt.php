<?php

function bin_string_to_offset_arr(&$bin_string, $highest_bit_offset, $bit_val = true){

    $byte_string = unpack("C*", $bin_string);

    $offset = 0;
    $byte_id = 1;   // unpack's byte array starts from id 1
    $i = 7;
    $arr = array();

    if ($bit_val){

        while ($offset <= $highest_bit_offset){

            if ( isset($byte_string[$byte_id]) && (($byte_string[$byte_id] & (1 << $i)) > 0) )
                $arr[] = $offset;

            $offset++;
            $i--;

            if ($i == -1){
                $i = 7;
                $byte_id++;
            }
        }

    } else {

        while ($offset <= $highest_bit_offset){

            if ( isset($byte_string[$byte_id]) && (($byte_string[$byte_id] & (1 << $i)) == 0) )
                $arr[] = $offset;

            $offset++;
            $i--;

            if ($i == -1){
                $i = 7;
                $byte_id++;
            }
        }

    }

    return $arr;    
}

function offset_arr_to_bin_string(&$offset_arr, $highest_bit_offset){
    
    $bytes = array();
    
    $highest_byte_id = floor($highest_bit_offset/8);
    for ($i=0; $i<=$highest_byte_id; $i++)
        $bytes[$i] = 0;
    
    foreach ($offset_arr as $k => $offset){
        $byte_id = floor($offset/8);
        $i = 7 - ($offset - $byte_id*8);
        $byte = 1 << $i;
        
        $bytes[$byte_id] += $byte;
    }
    
    $packed = "";
    foreach($bytes as $k => $byte){
        $packed .= pack("C*", $byte);
    }
    
    return $packed;
}

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


if (function_exists("openssl_encrypt")){
    /**

        Aes encryption (php7)

        use block size of 256 bits for a 32 chars key

        example:

        $key = "AD499352A1CA050523CFA17BC6793385";
        
        $raw = "encrypt me text";

        $aes = new AES($raw, $key, 256);
        $enc = $aes->encrypt();

        echo base64_encode($enc);   // can also do bin2hex()

        $aes2 = new AES($enc, $key, 256);
        $raw2 = $aes2->decrypt();

        var_dump($raw === $raw2);
    */
    class AES {
       
        protected $key;
        protected $data;
        protected $method;
        /**
         * Available OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
         *
         * @var type $options
         */
        protected $options = 0;
        /**
         * 
         * @param type $data
         * @param type $key
         * @param type $blockSize
         * @param type $mode
         */
        function __construct($data = null, $key = null, $blockSize = null, $mode = 'CBC') {
            $this->setData($data);
            $this->setKey($key);
            $this->setMethode($blockSize, $mode);
        }
        /**
         * 
         * @param type $data
         */
        public function setData($data) {
            $this->data = $data;
        }
        /**
         * 
         * @param type $key
         */
        public function setKey($key) {
            $this->key = $key;
        }
        /**
         * CBC 128 192 256 
          CBC-HMAC-SHA1 128 256
          CBC-HMAC-SHA256 128 256
          CFB 128 192 256
          CFB1 128 192 256
          CFB8 128 192 256
          CTR 128 192 256
          ECB 128 192 256
          OFB 128 192 256
          XTS 128 256
         * @param type $blockSize
         * @param type $mode
         */
        public function setMethode($blockSize, $mode = 'CBC') {
            if($blockSize==192 && in_array('', array('CBC-HMAC-SHA1','CBC-HMAC-SHA256','XTS'))){
                $this->method=null;
                 throw new Exception('Invlid block size and mode combination!');
            }
            $this->method = 'AES-' . $blockSize . '-' . $mode;
        }
        /**
         * 
         * @return boolean
         */
        public function validateParams() {
            if ($this->data != null &&
                    $this->method != null ) {
                return true;
            } else {
                return FALSE;
            }
        }
    //it must be the same when you encrypt and decrypt
         protected function getIV() {
            return '1234567890123456';
             //return mcrypt_create_iv(mcrypt_get_iv_size($this->cipher, $this->mode), MCRYPT_RAND);
             return openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
         }
        /**
         * @return type
         * @throws Exception
         */
        public function encrypt() {
            if ($this->validateParams()) { 
                return trim(openssl_encrypt($this->data, $this->method, $this->key, $this->options,$this->getIV()));
            } else {
                throw new Exception('Invlid params!');
            }
        }
        /**
         * 
         * @return type
         * @throws Exception
         */
        public function decrypt() {
            if ($this->validateParams()) {
               $ret=openssl_decrypt($this->data, $this->method, $this->key, $this->options,$this->getIV());
              
               return   trim($ret); 
            } else {
                throw new Exception('Invlid params!');
            }
        }
    }
}