<?php

namespace app\controller;

use support\Request;
use Plugin\aiq_payment\Utils\Tron\API;
use Plugin\aiq_payment\app\model\trc\TrcWallets;

class ApiController extends BaseController
{
    public function createWallet(Request $request)
    {
        try {
            
            return $this->success(['address' => '']);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
