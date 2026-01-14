<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\model\trc;

use plugin\saiadmin\basic\BaseModel;

/**
 * 交易记录模型
 *
 * sa_trc_transactions 钱包动账记录表
 *
 * @property  $id 主键
 * @property  $wallet_id 关联钱包ID
 * @property  $transaction_type 动账类型
 * @property  $amount 动账金额
 * @property  $fee 手续费
 * @property  $transaction_hash 交易哈希
 * @property  $from_address 发送地址
 * @property  $to_address 接收地址
 * @property  $block_number 所属区块高度
 * @property  $transaction_time 交易时间
 * @property  $remark 备注
 * @property  $create_time 创建时间
 * @property  $update_time 更新时间
 */
class TrcTransactions extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 数据库表名称
     * @var string
     */
    protected $table = 'sa_trc_transactions';

    /**
     * 创建时间 搜索
     */
    public function searchCreateTimeAttr($query, $value)
    {
        $query->whereTime('create_time', 'between', $value);
    }

}
