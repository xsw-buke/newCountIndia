<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class HallVersion
{

	/**
	 * 获取大厅列表
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 */
	public function index(Request $request)
	{
		$cid = $request->param('cid',29001);
        try {
            //游戏列表的获取
            $list = Db::connect('database.portal')
                ->table('tp_hall_version')
                ->select();

            if($list){
                $data['title'] = $list[0]['hall_name'];
                $data['content'] = $list[0]['download_url'];
                $data['update_type'] = (int)$list[0]['delete'];
            }else{
                $data['title'] = '';
                $data['content'] = '';
                $data['update_type'] = 0;
            }
           $datas['data'] = $data;
           $datas['errCode'] = 200;
           return json_encode($datas);
        } catch (Exception $e) {
            $datas['data'] = [];
            $datas['errCode'] = 500;
            return json_encode($datas);
        }
	}


}
