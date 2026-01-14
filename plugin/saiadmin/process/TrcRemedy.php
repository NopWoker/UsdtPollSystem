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



class TrcRemedy
{
    /**
     * 修复自动修复其他因素导致的区块遗漏
     * @return string
     */
    public function run($data): string
    {

        echo '任务[TrcRemedy]调用:'.date('Y-m-d H:i:s')."\n";
        $allBlockIDs = TrcBlocks::order('id desc')->limit(1000)->column('block_number');
        if (empty($allBlockIDs)) {
            return '暂无区块数据';
        }
        $maxBlockID = max($allBlockIDs);
        $firstMissingBlockID = null;
        $currentBlockID = $maxBlockID - 1; // 从最大值的前一个值开始排查
        while ($currentBlockID >= 1) {
            // 直接在内存数组中判断，无数据库IO开销
            if (!in_array($currentBlockID, $allBlockIDs)) {
                $firstMissingBlockID = $currentBlockID;
                break; // 找到第一个缺失值，立即终止循环
            }
            $currentBlockID--;
        }
        if (is_null($firstMissingBlockID)) {
            return '无缺失区块';
        }
        $blockNumber = $firstMissingBlockID ?? null;
        if(empty($blockNumber)){
            return '参数错误：blockNumber不能为空';
        }
        try {
            $apiUrl = 'https://api.trongrid.io';
            $api = new API($apiUrl);
            $sender = new Requests($apiUrl);
            // 获取指定区块高度的区块信息
            $assignblock = $api->getBlockByNum((int)$blockNumber);
            if (!is_object($assignblock)) {
               return '获取区块失败：响应格式异常 blockNumber:'.$blockNumber;
            }
            

            $blockNum = $assignblock->block_header->raw_data->number ?? null;
            $blockId = $assignblock->blockID ?? null;
            $timestamp = $assignblock->block_header->raw_data->timestamp ?? null;
            // 如果 latest block 未包含交易，尝试按区块高度再获取详细信息
            if (empty($assignblock->transactions) && isset($blockNum)) {
                $detailed = $api->getBlockByNum((int)$blockNum);
                if (is_object($detailed) && !empty($detailed->transactions)) {
                    $assignblock = $detailed;
                }
            }
            $save = new TrcBlocks();
            $save->block_number = $assignblock->block_header->raw_data->number ?? null;
            $save->block_hash = $assignblock->blockID ?? ''; //区块哈希
            $save->parent_hash = $assignblock->block_header->raw_data->parentHash ?? ''; //父区块哈希
            $save->transaction_count = count($assignblock->transactions ?? []);  //交易数量
            $save->block_time = isset($assignblock->block_header->raw_data->timestamp) ? date('Y-m-d H:i:s', $assignblock->block_header->raw_data->timestamp / 1000) : null; //区块时间
            $save->raw_data = json_encode($assignblock); //原始数据
            $save->size = strlen(json_encode($assignblock)); //区块大小(估算)
            
            $witnessHex = $assignblock->block_header->raw_data->witness_address ?? '';
            $save->witness_address = $witnessHex ? $api->hex2address($witnessHex) : ''; //见证人地址
            $save->witness_signature = $assignblock->block_header->witness_signature ?? ''; //见证人签名
            $save->energy_used = 0; //已用能量(API通常不直接返回区块级总消耗，需遍历交易累加或忽略)
            $save->energy_limit = 0; //能量限制
            $save->merkle_root = $assignblock->block_header->raw_data->txTrieRoot ?? ''; //Merkle根
            $save->save();
            $rawTxs = is_array($assignblock->transactions ?? null) ? $assignblock->transactions : [];
            
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
        return '处理完成遗失区块：'.$firstMissingBlockID;
        

            //return json($result);
        } catch (\Throwable $e) {
            return  '处理异常：'.$e->getMessage();
            
        }
    }
        
}
