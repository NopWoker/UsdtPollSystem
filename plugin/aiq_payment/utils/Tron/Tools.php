<?php

declare(strict_types = 1);

namespace Plugin\aiq_payment\Utils\Tron;

use Plugin\aiq_payment\Utils\Tron\Crypto\Base58;
use Exception;

abstract class Tools {
	public function address2hex(string $address) : string {
		if(strlen($address) == 42 && str_starts_with($address, strval(41))) {
			return $address;
		} else {
			return Base58::decodeAddress($address);
		}
	}
	public function hex2address(string $address) : string {
		if(ctype_xdigit($address)) {
			return Base58::encodeAddress($address);
		} else {
			return $address;
		}
	}
	public function dec2hex(string $dec, int $base = 16) : string {
		if(extension_loaded('bcmath')) {
			if(bccomp($dec, strval(0)) == 0) return strval(0);
			$hex = '';
			while(bccomp($dec, strval(0)) > 0) {
				$mod = bcmod($dec, strval($base));
				$hex = dechex(intval($mod)) . $hex;
				$dec = bcdiv($dec, strval($base), 0);
			}
			return $hex;
		} else {
			throw new Exception('bc extension is needed !');
		}
	}
	public function hex2dec(string $hex, int $base = 16) : string {
		if(extension_loaded('bcmath')) {
			$hex = ltrim($hex, '0x');
			$dec = strval(0);
			$len = strlen($hex);
			for($i = 0; $i < $len; $i++) {
				$current = hexdec($hex[$i]);
				$dec = bcmul($dec, strval($base));
				$dec = bcadd($dec, strval($current));
			}
			return $dec;
		} else {
			throw new Exception('bc extension is needed !');
		}
	}
	public function validation(string $address) : bool {
		if(preg_match('/^T[A-HJ-NP-Za-km-z1-9]{33}$/', $address)) {
			$hex = $this->address2hex($address);
			$wallet = $this->hex2address($hex);
			return $wallet === $address;
		} else {
			return false;
		}
	}
}

?>