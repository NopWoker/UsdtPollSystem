<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\validate\trc;

use think\Validate;

/**
 * 钱包列表验证器
 */
class TrcWalletsValidate extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule =   [
        'address' => 'require',
        'trx_balance' => 'require',
        'usdt_balance' => 'require',
        'energy' => 'require',
        'bandwidth' => 'require',
        'create_time' => 'require',
        'update_time' => 'require',
    ];

    /**
     * 定义错误信息
     */
    protected $message  =   [
        'address' => '钱包地址必须填写',
        'trx_balance' => 'TRX余额必须填写',
        'usdt_balance' => 'USDT余额必须填写',
        'energy' => '能量值必须填写',
        'bandwidth' => '带宽值必须填写',
        'create_time' => '创建时间必须填写',
        'update_time' => '更新时间必须填写',
    ];

    /**
     * 定义场景
     */
    protected $scene = [
        'save' => [
            'address',
            'trx_balance',
            'usdt_balance',
            'energy',
            'bandwidth',
            'create_time',
            'update_time',
        ],
        'update' => [
            'address',
            'trx_balance',
            'usdt_balance',
            'energy',
            'bandwidth',
            'create_time',
            'update_time',
        ],
    ];

}
