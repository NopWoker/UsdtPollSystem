<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\validate\trc;

use think\Validate;

/**
 * 区块扫描记录验证器
 */
class TrcBlocksValidate extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule =   [
        'block_number' => 'require',
        'block_hash' => 'require',
        'transaction_count' => 'require',
        'block_time' => 'require',
        'size' => 'require',
        'energy_used' => 'require',
        'energy_limit' => 'require',
        'create_time' => 'require',
        'update_time' => 'require',
    ];

    /**
     * 定义错误信息
     */
    protected $message  =   [
        'block_number' => '区块高度必须填写',
        'block_hash' => '区块哈希必须填写',
        'transaction_count' => '区块交易数目必须填写',
        'block_time' => '区块时间必须填写',
        'size' => '区块大小必须填写',
        'energy_used' => '消耗的能量必须填写',
        'energy_limit' => '能量限制必须填写',
        'create_time' => '创建时间必须填写',
        'update_time' => '更新时间必须填写',
    ];

    /**
     * 定义场景
     */
    protected $scene = [
        'save' => [
            'block_number',
            'block_hash',
            'transaction_count',
            'block_time',
            'size',
            'energy_used',
            'energy_limit',
            'create_time',
            'update_time',
        ],
        'update' => [
            'block_number',
            'block_hash',
            'transaction_count',
            'block_time',
            'size',
            'energy_used',
            'energy_limit',
            'create_time',
            'update_time',
        ],
    ];

}
