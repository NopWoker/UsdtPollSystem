<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\validate\trc;

use think\Validate;

/**
 * 交易记录验证器
 */
class TrcTransactionsValidate extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule =   [
        'wallet_id' => 'require',
        'transaction_type' => 'require',
        'amount' => 'require',
        'fee' => 'require',
        'create_time' => 'require',
        'update_time' => 'require',
    ];

    /**
     * 定义错误信息
     */
    protected $message  =   [
        'wallet_id' => '关联钱包ID必须填写',
        'transaction_type' => '动账类型必须填写',
        'amount' => '动账金额必须填写',
        'fee' => '手续费必须填写',
        'create_time' => '创建时间必须填写',
        'update_time' => '更新时间必须填写',
    ];

    /**
     * 定义场景
     */
    protected $scene = [
        'save' => [
            'wallet_id',
            'transaction_type',
            'amount',
            'fee',
            'create_time',
            'update_time',
        ],
        'update' => [
            'wallet_id',
            'transaction_type',
            'amount',
            'fee',
            'create_time',
            'update_time',
        ],
    ];

}
