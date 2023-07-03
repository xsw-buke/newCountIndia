<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class GameConfig
{

	/**
	 * 获取游戏通用配置列表
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
                ->table('tp_game_config')
                ->select();

            if($list){
                $data['telegramUrl'] = urlencode($list[0]['telegramUrl']);
                $data['whatsappUrl'] = urlencode($list[0]['whatsappUrl']);
                $data['fbIsOpen'] = urlencode((int)$list[0]['fbIsOpen']);
                $data['modules'] = $list[0]['modules'];
                $data['inviteFriendsDesc'] = urlencode($list[0]['inviteFriendsDesc']);
                $data['errorCodeUrl'] = urlencode($list[0]['errorCodeUrl']);
            }else{
                $data['telegramUrl'] = '';
                $data['whatsappUrl'] ='';
                $data['fbIsOpen'] = 0;
                $data['modules'] = '';
                $data['inviteFriendsDesc'] = '';
                $data['errorCodeUrl'] = '';
            }

           $datas['data'] = $data;
           $datas['errCode'] = 200;
           return urldecode(json_encode($datas));
        } catch (Exception $e) {
            $datas['data'] = [];
            $datas['errCode'] = 500;
            return json_encode($datas);
        }
	}


}
