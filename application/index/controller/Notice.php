<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class Notice
{

	/**
	 * 获取热更新列表
	 *
	 * @param Request $request
	 *
	 * @return mixed
	 */
	public function index(Request $request)
	{
		$cid = $request->param('cid',29001);
        try {
            $where = [
                'start_time' => [ '<=', date("Y-m-d H:i:s",time()) ],
                'end_time' => [ '>', date("Y-m-d H:i:s",time()) ],
            ];
            //游戏列表的获取
            $list = Db::connect('database.portal')
                ->table('tp_notice')
                ->where($where)
                ->select();

            $data = [];
            if($list) foreach ($list as $k=>$v){
                $data[$k]['name'] = (string)$v['headline'];
                $data[$k]['title'] = (string)$v['title'];
                $data[$k]['content'] = (string)$v['content'];
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
