<?php

namespace app\controller;

use support\Request;
use support\Response;
use Plugin\aiq_payment\Utils\Tron\API;
use Plugin\aiq_payment\Utils\Tron\Requests;
use Plugin\aiq_payment\app\model\trc\TrcWallets;
use Plugin\aiq_payment\app\model\trc\TrcTransactions;
use Plugin\aiq_payment\app\model\trc\TrcBlocks;
class TestController
{
    /**
     * 获取最新区块，解析所有交易并返回JSON
     */
    public function index(Request $request)
    {
        try {
            $apiUrl = 'https://api.trongrid.io';
            $api = new API($apiUrl);
            $sender = new Requests($apiUrl);

            $nowBlock = $sender->request('POST', 'wallet/getnowblock');

            if (!is_object($nowBlock)) {
                throw new \Exception('获取最新区块失败：响应格式异常');
            }
            


            $blockNum = $nowBlock->block_header->raw_data->number ?? null;
            $blockId = $nowBlock->blockID ?? null;
            $timestamp = $nowBlock->block_header->raw_data->timestamp ?? null;
            // 如果 latest block 未包含交易，尝试按区块高度再获取详细信息
            if (empty($nowBlock->transactions) && isset($blockNum)) {
                $detailed = $api->getBlockByNum((int)$blockNum);
                if (is_object($detailed) && !empty($detailed->transactions)) {
                    $nowBlock = $detailed;
                }
            }
            
          




            $transactions = [];
            $rawTxs = is_array($nowBlock->transactions ?? null) ? $nowBlock->transactions : [];

            foreach ($rawTxs as $tx) {
                $txID = $tx->txID ?? null;
                $ret = isset($tx->ret) && is_array($tx->ret) ? ($tx->ret[0] ?? null) : null;
                $status = is_object($ret) ? ($ret->contractRet ?? null) : null;
                $fee = is_object($ret) ? ($ret->fee ?? null) : null;

                $contracts = is_array($tx->raw_data->contract ?? null) ? $tx->raw_data->contract : [];
                $contract = $contracts[0] ?? null;
                $type = is_object($contract) ? ($contract->type ?? null) : null;
                $value = is_object($contract) && isset($contract->parameter) ? ($contract->parameter->value ?? null) : null;

                $parsed = [
                    'txID' => $txID,
                    'type' => $type,
                    'info'=>$tx,
                    'status' => $status,
                    'fee' => $fee,
                    'timestamp' => $tx->raw_data->timestamp ?? $timestamp,
                ];

                if (is_object($value)) {
                    // 解析不同合约类型
                    $isWallet =false;
                    if ($type === 'TransferContract') {
                        $fromHex = $value->owner_address ?? null;
                        $toHex = $value->to_address ?? null;
                        $amount = $value->amount ?? null;
                        $parsed['from_address'] = $fromHex ? $api->hex2address($fromHex) : null;
                        $parsed['to_address'] = $toHex ? $api->hex2address($toHex) : null;
                        if($wallet=TrcWallets::where('address',$parsed['from_address'])->find()){
                            $isWallet =true;
                            $parsed['transaction_type'] = 'TRX_OUT';
                        }elseif($wallet=TrcWallets::where('address',$parsed['to_address'])->find()){
                            $isWallet =true;
                            $parsed['transaction_type'] = 'TRX_IN';
                        }
                        $parsed['amount'] = is_numeric($amount) ? ((float)$amount) / 1e6 : null; // TRX单位换算
                    } elseif ($type === 'TriggerSmartContract') {
                        $parsed['owner'] = isset($value->owner_address) ? $api->hex2address($value->owner_address) : null;
                        $contractAddress = isset($value->contract_address) ? $api->hex2address($value->contract_address) : null;
                        $parsed['contract_address'] = $contractAddress;
                        $parsed['call_value'] = $value->call_value ?? null;
                        $parsed['data'] = $value->data ?? null; 
                        if (isset($value->data) && str_starts_with($value->data, 'a9059cbb') && strlen($value->data) >= 136) {
                            $toHex = '41' . substr($value->data, 32, 40); 
                            $amountHex = substr($value->data, 72, 64);
                            $parsed['to'] = $api->hex2address($toHex);
                            $rawAmount = $api->hex2dec($amountHex);
                            if ($contractAddress === 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t') {
                                // 补充 USDT 交易的发送和接收地址
                                $parsed['from_address'] = $parsed['owner'];
                                $parsed['to_address'] = $parsed['to'];
                                
                                if($wallet=TrcWallets::where('address',$parsed['to'])->find()){
                                    $isWallet =true;
                                    $parsed['transaction_type'] = 'USDT_IN';
                                }elseif($wallet=TrcWallets::where('address',$parsed['owner'])->find()){
                                    $isWallet =true;
                                    $parsed['transaction_type'] = 'USDT_OUT';
                                }
                                $parsed['amount'] = bcdiv($rawAmount, '1000000', 6);
                            } 
                        }
                    }
                    if($isWallet){
                        $transaction = new TrcTransactions();
                        $transaction->wallet_id = $wallet->id;
                        $transaction->transaction_type = $parsed['transaction_type'];
                        $transaction->amount = $parsed['amount'];
                        $transaction->fee = 0;
                        $transaction->transaction_hash = $txID; // 交易哈希
                        $transaction->from_address = $parsed['from_address'] ?? ''; // 发送地址
                        $transaction->to_address = $parsed['to_address'] ?? ''; // 接收地址
                        $transaction->transaction_status = $status; // 交易状态
                        $transaction->notify_status = 'notyet'; // 通知状态
                        $transaction->block_number = $blockNum; // 区块高度
                        $transaction->save();
                    }
                $transactions[]=$parsed;
            }

        }
        return json([
            'success' => true,
            'block_number' => $blockNum,
            'data' => $transactions,
        ]);

            //return json($result);
        } catch (\Throwable $e) {
            return json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

