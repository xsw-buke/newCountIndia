<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class UpdateEvent
{

	/**
	 * 更新事件
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
                ->table('tp_update_event')
                ->select();

            $data = [];
            if($list) foreach ($list as $k=>$v){
                if($v['event_name'] == 'gameList'){
                     $data['gameList'] = (int)$v['event_value'];
                }
                if($v['event_name'] == 'event'){
                    $data['event'] = (int)$v['event_value'];
                }
                if($v['event_name'] == 'notice'){
                    $data['notice'] = (int)$v['event_value'];
                }
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
