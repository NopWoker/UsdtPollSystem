<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\process;


use Plugin\aiq_payment\Utils\Tron\API;
use Plugin\aiq_payment\Utils\Tron\Requests;
use Plugin\aiq_payment\app\model\trc\TrcWallets;
use Plugin\aiq_payment\app\model\trc\TrcTransactions;
use Plugin\aiq_payment\app\model\trc\TrcBlocks;



class TrcBlock
{
    public function run(): string
    {
        echo '任务[TrcBlock]调用:'.date('Y-m-d H:i:s')."\n";
        try {
            $apiUrl = 'https://api.trongrid.io';
            $api = new API($apiUrl);
            $sender = new Requests($apiUrl);

            $nowBlock = $sender->request('POST', 'wallet/getnowblock');

            if (!is_object($nowBlock)) {
               return '获取最新区块失败：响应格式异常';
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
            $save = new TrcBlocks();
            $save->block_number = $nowBlock->block_header->raw_data->number ?? null;
            $save->block_hash = $nowBlock->blockID ?? ''; //区块哈希
            $save->parent_hash = $nowBlock->block_header->raw_data->parentHash ?? ''; //父区块哈希
            $save->transaction_count = count($nowBlock->transactions ?? []);  //交易数量
            $save->block_time = isset($nowBlock->block_header->raw_data->timestamp) ? date('Y-m-d H:i:s', $nowBlock->block_header->raw_data->timestamp / 1000) : null; //区块时间
            $save->raw_data = json_encode($nowBlock); //原始数据
            $save->size = strlen(json_encode($nowBlock)); //区块大小(估算)
            
            $witnessHex = $nowBlock->block_header->raw_data->witness_address ?? '';
            $save->witness_address = $witnessHex ? $api->hex2address($witnessHex) : ''; //见证人地址
            
            $save->witness_signature = $nowBlock->block_header->witness_signature ?? ''; //见证人签名
            $save->energy_used = 0; //已用能量(API通常不直接返回区块级总消耗，需遍历交易累加或忽略)
            $save->energy_limit = 0; //能量限制
            $save->merkle_root = $nowBlock->block_header->raw_data->txTrieRoot ?? ''; //Merkle根
            $save->save();
            $rawTxs = is_array($nowBlock->transactions ?? null) ? $nowBlock->transactions : [];
            
            foreach ($rawTxs as $tx) {
                $txID = $tx->txID ?? null;
                $ret = isset($tx->ret) && is_array($tx->ret) ? ($tx->ret[0] ?? null) : null;
                $status = is_object($ret) ? ($ret->contractRet ?? null) : null;
                $fee = is_object($ret) ? ($ret->fee ?? null) : null;
                $contracts = is_array($tx->raw_data->contract ?? null) ? $tx->raw_data->contract : [];
                $contract = isset($contracts[0]) ? $contracts[0] : null;

               
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
               
            }

        }
        return '处理完成';
        

            //return json($result);
        } catch (\Throwable $e) {
            return  '处理异常：'.$e->getMessage();
            
        }
    }
        
}
