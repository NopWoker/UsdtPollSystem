<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\model\trc;

use plugin\saiadmin\basic\BaseModel;

/**
 * 钱包列表模型
 *
 * sa_trc_wallets 虚拟货币钱包表
 *
 * @property  $id 主键
 * @property  $address 钱包地址
 * @property  $private_key 私钥
 * @property  $mnemonic 助记词
 * @property  $trx_balance TRX余额
 * @property  $usdt_balance USDT余额
 * @property  $energy 能量值
 * @property  $bandwidth 带宽值
 * @property  $remark 备注
 * @property  $notify_utl 回调地址
 * @property  $status 是否启用
 * @property  $create_time 创建时间
 * @property  $update_time 更新时间
 */
class TrcWallets extends BaseModel
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
    protected $table = 'sa_trc_wallets';

    /**
     * 钱包地址 搜索
     */
    public function searchAddressAttr($query, $value)
    {
        $query->where('address', 'like', '%'.$value.'%');
    }

    /**
     * 备注 搜索
     */
    public function searchRemarkAttr($query, $value)
    {
        $query->where('remark', 'like', '%'.$value.'%');
    }

    /**
     * 回调地址 搜索
     */
    public function searchNotifyUtlAttr($query, $value)
    {
        $query->where('notify_utl', 'like', '%'.$value.'%');
    }

    /**
     * 创建时间 搜索
     */
    public function searchCreateTimeAttr($query, $value)
    {
        $query->whereTime('create_time', 'between', $value);
    }

}
