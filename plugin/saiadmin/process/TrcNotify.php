<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\process;

use Plugin\aiq_payment\app\model\trc\TrcTransactions;
use Plugin\aiq_payment\app\model\trc\TrcWallets;

class TrcNotify
{
    public function run(): string
    {
        echo '任务[TrcNotify]调用：'.date('Y-m-d H:i:s')."\n";
        $trcTransactions = TrcTransactions::where('transaction_status' ,'=', 'SUCCESS')
            ->where('notify_status' ,'=', 'notyet')
            ->join('sa_trc_wallets', 'sa_trc_transactions.wallet_id = sa_trc_wallets.id')
            ->select();
        $SuccessCount=0;
        $FailCount=0;
        if($trcTransactions){
            foreach($trcTransactions as $trcTransaction){
                $notify=$this->curl_post($trcTransaction->notify_url,['amount'=>$trcTransaction->amount,'transaction_hash'=>$trcTransaction->transaction_hash]);
                if($notify){
                    $trcTransaction->notify_status = 'done';
                    $trcTransaction->save();
                    $SuccessCount++;
                }else{
                    $FailCount++;
                }
            }
            return '异步回调成功：'.$SuccessCount.'条，失败'.$FailCount.'条';
        }else{
            return '暂无待处理交易';
        }
        
    }
    public function curl_post($url,$data){
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        if($response == 'success'){
            return true;
        }else{
            return false;
        }
        
    }
}