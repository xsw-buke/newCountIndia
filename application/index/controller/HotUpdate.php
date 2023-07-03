<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class HotUpdate
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
            //游戏列表的获取
            $list = Db::connect('database.portal')
                ->table('tp_hot_update')
                ->select();

            if($list){
                $data['packageUrl'] = $list[0]['apk_url'];
                $data['minVersion'] = $list[0]['min_version'];
                $data['whiteConfig'] = [
                    'users' => $list[0]['white_users'],
                    'config'=>[
                        'ver' => $list[0]['white_ver'],
                        'switch' => (int)$list[0]['white_switch'],
                        'mode' => (int)$list[0]['white_mode'],
                        'force' => (int)$list[0]['white_force'],
                    ],
                ];
                $data['commonConfig'] = [
                    'ver' => $list[0]['common_ver'],
                    'switch' => (int)$list[0]['common_switch'],
                    'mode' => (int)$list[0]['common_mode'],
                    'force' => (int)$list[0]['common_force'],
                ];
            }else{
                $data['packageUrl'] = '';
                $data['minVersion'] = '';
                $data['whiteConfig'] = [
                    'users' => '',
                    'config'=>[
                        'ver' => '',
                        'switch' => 0,
                        'mode' => 0,
                        'force' => 0,
                    ],
                ];
                $data['commonConfig'] = [
                    'ver' => '',
                    'switch' => 0,
                    'mode' => 0,
                    'force' => 0,
                ];
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
