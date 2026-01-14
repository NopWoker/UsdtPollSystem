<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\aiq_payment\app\controller\trc;

use plugin\saiadmin\basic\BaseController;
use plugin\aiq_payment\app\logic\trc\TrcWalletsLogic;
use plugin\aiq_payment\app\validate\trc\TrcWalletsValidate;
use support\Request;
use support\Response;

/**
 * 钱包列表控制器
 */
class TrcWalletsController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logic = new TrcWalletsLogic();
        $this->validate = new TrcWalletsValidate;
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
            ['id', ''],
            ['address', ''],
            ['remark', ''],
            ['notify_utl', ''],
            ['status', ''],
            ['create_time', ''],
        ]);
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

}
