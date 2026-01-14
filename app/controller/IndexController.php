<?php

namespace app\controller;

use support\Request;
use Plugin\aiq_payment\Utils\Tron\API;
use Plugin\aiq_payment\app\model\trc\TrcWallets;

class IndexController extends BaseController
{
    public function createWallet(Request $request)
    {
        $rsa2public="MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhKW22lmQzlEf3kNBGEBNyESUCPVsWla93vEEEhMplJ9Ijh0cajSuNkUxpSzZDQZes3UycpWutswBmgv20X4TgiLan3lbjJEBzT6Q26EIywkKn3dV3NgfXuNsmFucE+GkL3m1PU1I5r0by5SCjGXWs0h2JXrGQpZcMyIvLSZBYKXlu7cm7oOmPDHaN1AHqW2YgeM+5ZmU+yORy1GXIQMj5DTFrIEV007LPr2gLaFoCX33FvDvsD8Qkbn5LQiB4h133cyEo6Q2t/1Yhe49MQMgusZ8h3sErGzHoGmtdnjbk+qVy87byaufqyXqFPQmx28jVUJ3YC4RpsLTHDOhG3Q39wIDAQAB";
        try {
            $notifyUrl = $request->input('notifyUrl',null);
            $remark = $request->input('remark',null);
            $tronApi = new API();
            $newWallet = $tronApi->generateAddress();
            $mnemonic = $tronApi->getPhraseFromPrivateKey($newWallet->privatekey);
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($rsa2public, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $encryptedPrivateKey = '';
            openssl_public_encrypt($newWallet->privatekey, $encryptedPrivateKey, $publicKey);
            $encryptedMnemonic = '';
            openssl_public_encrypt($mnemonic, $encryptedMnemonic, $publicKey);
            $save = new TrcWallets();
            $save->address = $newWallet->wallet;
            $save->private_key = base64_encode($encryptedPrivateKey);
            $save->mnemonic = base64_encode($encryptedMnemonic);
            if(!is_null($notifyUrl)) {
                $save->notify_url = $notifyUrl;
            }
            if(!is_null($remark)) {
                $save->remark = $remark;
            }
            $save->save();
            return $this->success(['address' => $newWallet->wallet]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
