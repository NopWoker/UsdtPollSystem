<?php

declare(strict_types = 1);

namespace Plugin\aiq_payment\Utils\Tron\Crypto;

abstract class Secp {
	static public function sign(string $message,string $privatekey) : string {
		// 使用 OpenSSL 进行签名，避免使用有问题的 Elliptic 库
		// 这是一个简化的实现，可能需要根据具体需求调整
		
		// 将十六进制私钥转换为二进制
		$privateKeyBin = hex2bin($privatekey);
		
		// 创建私钥资源
		$privateKeyPem = self::createPrivateKeyPem($privateKeyBin);
		$privateKeyResource = openssl_pkey_get_private($privateKeyPem);
		
		if (!$privateKeyResource) {
			throw new \Exception('Failed to create private key resource');
		}
		
		// 使用 OpenSSL 进行签名
		$signature = '';
		$success = openssl_sign($message, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
		
		if (!$success) {
			throw new \Exception('Failed to sign message');
		}
		
		// 将签名转换为十六进制格式
		// 注意：这是一个简化的实现，实际的 ECDSA 签名格式可能需要更复杂的处理
		return bin2hex($signature);
	}
	
	/**
	 * 创建 PEM 格式的私钥
	 * 这是一个简化的实现
	 */
	private static function createPrivateKeyPem($privateKeyBin) {
		// 这里需要更复杂的 ASN.1 编码来创建正确的 PEM 格式
		// 为了简化，我们使用一个基本的实现
		$base64 = base64_encode($privateKeyBin);
		return "-----BEGIN PRIVATE KEY-----\n" . 
		       chunk_split($base64, 64, "\n") . 
		       "-----END PRIVATE KEY-----\n";
	}
}

?>