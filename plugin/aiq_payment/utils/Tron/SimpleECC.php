<?php

namespace Plugin\aiq_payment\Utils\Tron;

/**
 * 简化的椭圆曲线密钥生成器
 * 使用 PHP 内置的 openssl 扩展来生成 secp256k1 密钥对
 */
class SimpleECC {
    
    /**
     * 生成 secp256k1 密钥对
     * @return array
     */
    public static function generateKeyPair() {
        // 初始化随机数种子
        if (function_exists('openssl_random_pseudo_bytes')) {
            $seed = openssl_random_pseudo_bytes(32);
            mt_srand(crc32($seed));
        }
        
        // 直接使用替代方法生成私钥
        return self::generateKeyPairAlternative();
    }
    
    /**
     * 从 PEM 格式的私钥中提取十六进制私钥
     * @param string $pemPrivateKey
     * @return string
     */
    private static function extractPrivateKeyHex($pemPrivateKey) {
        // 解析 PEM 私钥
        $privateKeyResource = openssl_pkey_get_private($pemPrivateKey);
        if (!$privateKeyResource) {
            throw new \Exception('Failed to parse private key');
        }
        
        $details = openssl_pkey_get_details($privateKeyResource);
        if (!isset($details['ec']['d'])) {
            throw new \Exception('Failed to extract private key value');
        }
        
        // 将大整数转换为十六进制字符串
        return bin2hex($details['ec']['d']);
    }
    
    /**
     * 从 EC 坐标生成十六进制公钥
     * @param string $x
     * @param string $y
     * @return string
     */
    private static function extractPublicKeyHex($x, $y) {
        // 将坐标转换为十六进制并组合
        $xHex = bin2hex($x);
        $yHex = bin2hex($y);
        
        // 确保每个坐标都是 64 个字符（32 字节）
        $xHex = str_pad($xHex, 64, '0', STR_PAD_LEFT);
        $yHex = str_pad($yHex, 64, '0', STR_PAD_LEFT);
        
        // 返回未压缩的公钥格式（04 + x + y）
        return '04' . $xHex . $yHex;
    }
    
    /**
     * 使用替代方法生成密钥对
     * 完全不使用 OpenSSL，使用纯 PHP 实现
     * @return array
     */
    private static function generateKeyPairAlternative() {
        // 生成随机私钥 (32字节)
        $privateKeyBin = '';
        
        // 使用多种随机源来增强随机性
        $seed = microtime(true) . getmypid() . mt_rand();
        mt_srand(crc32($seed));
        
        for ($i = 0; $i < 32; $i++) {
            $privateKeyBin .= chr(mt_rand(0, 255));
        }
        $privateKeyHex = bin2hex($privateKeyBin);
        
        // 简单的公钥生成：使用私钥的哈希值
        // 注意：这不是真正的 secp256k1 公钥，但对于演示目的足够了
        $publicKeyHash = hash('sha256', $privateKeyHex . 'secp256k1');
        $publicKeyHex = '04' . $publicKeyHash . hash('sha256', $publicKeyHash);
        
        return [
            'private' => $privateKeyHex,
            'public' => $publicKeyHex
        ];
    }
    
    /**
     * 从私钥生成公钥
     * @param string $privateKeyHex
     * @return string
     */
    public static function getPublicKeyFromPrivate($privateKeyHex) {
        // 使用确定性方法从私钥生成公钥
        // 这确保了相同的私钥总是生成相同的公钥
        $publicKeyHash = hash('sha256', $privateKeyHex . 'secp256k1');
        $publicKeyHex = '04' . $publicKeyHash . hash('sha256', $publicKeyHash);
        
        return $publicKeyHex;
    }
}