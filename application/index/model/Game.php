<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/24
 * Time: 9:54
 */


namespace app\index\model;


use think\Cache;
use think\Db;
use think\Request;
use tp5redis\Redis;
use think\Exception;

class Game
{

    protected $db;

    public function __construct()
    {
        try {
            $this->db = Db::connect('database.portal');
        } catch (Exception $e) {
        }
    }

    /**
     * 获取bet表
     *
     * @return string
     */
/*    function getBetList(){
        $list = Redis::get('getBetList');
        if (empty($list)) {
            return jsonError('获取bet表失败');
        }
        return jsonSuccess(json_decode($list), '获取bet表成功');
    }*/

    /**
     * 获取游戏列表
     *
     * @return string
     */
    function getGameList(){
        $list = Redis::get('getGameList');
        if (empty($list)) {
            return jsonError('获取所有游戏失败');
        }
        return jsonSuccess(json_decode($list), '获取所有游戏成功');
    }

    /**
     * 通过渠道号获取所属游戏
     *
     * @param Request $request
     * @return string
     */
    function getGameByCid(Request $request){
        $str = htmlspecialchars($request->param('channel_id')).'-'.htmlspecialchars($request->param('channel_version'));


        $list = Redis::hGet('getChannelGameList' , $str);
        if (empty($list)) {
            return jsonError('渠道或版本异常');
        }
        $list = json_decode($list ,TRUE);

	    $betTable = Redis::get('betTable');
	    if (empty($betTable)) {
		    return jsonError('bet表异常');
	    }
	    $list['bet'] = json_decode($betTable ,true);
	    return jsonSuccess($list, '获取渠道下游戏成功');
    }


}