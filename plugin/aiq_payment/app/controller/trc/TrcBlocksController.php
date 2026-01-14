<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\controller\trc;

use plugin\saiadmin\basic\BaseController;
use plugin\aiq_payment\app\logic\trc\TrcBlocksLogic;
use plugin\aiq_payment\app\validate\trc\TrcBlocksValidate;
use support\Request;
use support\Response;

/**
 * 区块扫描记录控制器
 */
class TrcBlocksController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logic = new TrcBlocksLogic();
        $this->validate = new TrcBlocksValidate;
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
            ['block_number', ''],
            ['block_hash', ''],
            ['create_time', ''],
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

}
