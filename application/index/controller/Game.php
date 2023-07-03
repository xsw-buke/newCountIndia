<?php

namespace app\index\controller;

use app\index\controller\Application;
use app\index\model\App;
use think\Db;
use think\Exception;
use think\Request;

class Game
{

	/**
	 * 获取游戏列表
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
            $game_list = Db::connect('database.portal')
                ->table('tp_game')
                ->select();
           $data['channel_id'] = (string)$cid;
         /*  $data['channel_version'] = "1.0.0";
           $data['hid'] = "212";
           $data['id'] = "212";
           $data['hall_id'] = "29001";
           $data['hall_version'] = "100";
           $data['download_url'] = "https://moantech.oss-cn-shenzhen.aliyuncs.com/lhj_Finegold/luckyGame/hall/v100";*/
           $data['NewSlots'] = array();
           $data['HotSlots'] = array();
           $data['AllSlots'] = array();
           if($game_list) foreach ($game_list as $k=>$v){
              if($v['default_hot_sign'] == 1){//最火
                  $HotSlots['name'] = (string)$v['name'];
                  $HotSlots['show_name'] = (string)$v['show_name'];
                  $HotSlots['game_id'] = (string)$v['game_id'];
                  $HotSlots['version'] = (string)$v['version'];
                  $HotSlots['img_url'] = (string)$v['img_url'];
                  $HotSlots['img_url_md5'] = (string)$v['img_url_md5'];
                  $HotSlots['thumbnail_url'] = (string)$v['thumbnail_url'];
                  $HotSlots['thumbnail_url_md5'] = (string)$v['thumbnail_url_md5'];
                  $HotSlots['jackpot_value'] = (string)$v['jackpot_value'];
                  $HotSlots['download_url'] = (string)$v['download_url'];
                  $HotSlots['download_url_md5'] = (string)$v['download_url_md5'];
                  $HotSlots['sort'] = (string)$v['default_sort'];
                  $HotSlots['unlock_level'] = (string)$v['default_unlock_level'];
                  $HotSlots['user_type'] = (string)$v['default_user_type'];
                  $HotSlots['hot_sign'] = (string)$v['default_hot_sign'];
                  $HotSlots['jackpot'] = (string)$v['default_jackpot'];
                  $HotSlots['status'] = (string)$v['default_status'];
                  $HotSlots['bundleName'] = (string)$v['bundleName'];
                  $HotSlots['startScene'] = (string)$v['startScene'];
                  array_push($data['HotSlots'],$HotSlots);
              }elseif($v['default_hot_sign'] == 2){//最新
                  $NewSlots['name'] = (string)$v['name'];
                  $NewSlots['show_name'] = (string)$v['show_name'];
                  $NewSlots['game_id'] = (string)$v['game_id'];
                  $NewSlots['version'] = (string)$v['version'];
                  $NewSlots['img_url'] = (string)$v['img_url'];
                  $NewSlots['img_url_md5'] = (string)$v['img_url_md5'];
                  $NewSlots['thumbnail_url'] = (string)$v['thumbnail_url'];
                  $NewSlots['thumbnail_url_md5'] = (string)$v['thumbnail_url_md5'];
                  $NewSlots['jackpot_value'] = (string)$v['jackpot_value'];
                  $NewSlots['download_url'] = (string)$v['download_url'];
                  $NewSlots['download_url_md5'] = (string)$v['download_url_md5'];
                  $NewSlots['sort'] = (string)$v['default_sort'];
                  $NewSlots['unlock_level'] = (string)$v['default_unlock_level'];
                  $NewSlots['user_type'] = (string)$v['default_user_type'];
                  $NewSlots['hot_sign'] = (string)$v['default_hot_sign'];
                  $NewSlots['jackpot'] = (string)$v['default_jackpot'];
                  $NewSlots['status'] = (string)$v['default_status'];
                  $NewSlots['bundleName'] = (string)$v['bundleName'];
                  $NewSlots['startScene'] = (string)$v['startScene'];
                  array_push($data['NewSlots'],$NewSlots);
              }else{
                  $AllSlots['name'] = (string)$v['name'];
                  $AllSlots['show_name'] = (string)$v['show_name'];
                  $AllSlots['game_id'] = (string)$v['game_id'];
                  $AllSlots['version'] = (string)$v['version'];
                  $AllSlots['img_url'] = (string)$v['img_url'];
                  $AllSlots['img_url_md5'] = (string)$v['img_url_md5'];
                  $AllSlots['thumbnail_url'] = (string)$v['thumbnail_url'];
                  $AllSlots['thumbnail_url_md5'] = (string)$v['thumbnail_url_md5'];
                  $AllSlots['jackpot_value'] = (string)$v['jackpot_value'];
                  $AllSlots['download_url'] = (string)$v['download_url'];
                  $AllSlots['download_url_md5'] = (string)$v['download_url_md5'];
                  $AllSlots['sort'] = (string)$v['default_sort'];
                  $AllSlots['unlock_level'] = (string)$v['default_unlock_level'];
                  $AllSlots['user_type'] = (string)$v['default_user_type'];
                  $AllSlots['hot_sign'] = (string)$v['default_hot_sign'];
                  $AllSlots['jackpot'] = (string)$v['default_jackpot'];
                  $AllSlots['status'] = (string)$v['default_status'];
                  $AllSlots['bundleName'] = (string)$v['bundleName'];
                  $AllSlots['startScene'] = (string)$v['startScene'];
                  array_push($data['AllSlots'],$AllSlots);
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
