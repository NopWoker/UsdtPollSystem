<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\model\trc;

use plugin\saiadmin\basic\BaseModel;

/**
 * 区块扫描记录模型
 *
 * sa_trc_blocks Tron区块记录表
 *
 * @property  $id 记录ID
 * @property  $block_number 区块高度
 * @property  $block_hash 区块哈希
 * @property  $parent_hash 父区块哈希
 * @property  $transaction_count 区块交易数目
 * @property  $block_time 区块时间
 * @property  $raw_data 区块原始数据
 * @property  $size 区块大小
 * @property  $witness_address 见证人地址
 * @property  $witness_signature 见证人签名
 * @property  $energy_used 消耗的能量
 * @property  $energy_limit 能量限制
 * @property  $merkle_root 默克尔根
 * @property  $create_time 创建时间
 * @property  $update_time 更新时间
 */
class TrcBlocks extends BaseModel
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
    protected $table = 'sa_trc_blocks';

    /**
     * 创建时间 搜索
     */
    public function searchCreateTimeAttr($query, $value)
    {
        $query->whereTime('create_time', 'between', $value);
    }

}
