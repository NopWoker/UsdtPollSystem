<?php

namespace app\controller;

use support\Response;

class BaseController
{
    /**
     * 成功返回
     * @param mixed $data
     * @param string $msg
     * @param int $code
     * @return Response
     */
    protected function success($data = [], string $msg = 'ok', int $code = 200): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * 失败返回
     * @param string $msg
     * @param int $code
     * @param mixed $data
     * @return Response
     */
    protected function fail(string $msg = 'fail', int $code = 500, $data = []): Response
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }
}
