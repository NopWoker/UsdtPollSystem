<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\controller\trc;

use plugin\saiadmin\basic\BaseController;
use plugin\aiq_payment\app\logic\trc\TrcTransactionsLogic;
use plugin\aiq_payment\app\validate\trc\TrcTransactionsValidate;
use support\Request;
use support\Response;

/**
 * 交易记录控制器
 */
class TrcTransactionsController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logic = new TrcTransactionsLogic();
        $this->validate = new TrcTransactionsValidate;
        parent::__construct();
    }

    /**
     * 数据列表
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['wallet_id', ''],
            ['transaction_type', ''],
            ['transaction_hash', ''],
            ['from_address', ''],
            ['to_address', ''],
            ['block_number', ''],
            ['create_time', ''],
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

}
