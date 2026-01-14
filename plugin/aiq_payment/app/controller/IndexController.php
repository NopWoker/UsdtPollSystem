<?php

namespace plugin\aiq_payment\app\controller;

use support\Request;

class IndexController
{

    public function index()
    {
        return view('index/index', ['name' => 'aiq_payment']);
    }

}
