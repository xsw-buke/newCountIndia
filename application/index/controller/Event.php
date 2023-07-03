<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class Event
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
                'dalete' => 0
            ];
            //游戏列表的获取
            $list = Db::connect('database.portal')
                ->table('tp_event')
                ->where($where)
                ->order('sort desc')
                ->select();

            $data = [];
            if($list) foreach ($list as $k=>$v){
                $data[$k]['name'] = (string)$v['name'];
                $data[$k]['clickType'] = (string)$v['clickType'];
                $data[$k]['clickValue'] = (string)$v['clickValue'];
                $data[$k]['imgMd5'] = (string)$v['imgMd5'];
                $data[$k]['imgUrl'] = (string)$v['imgUrl'];
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
