<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [

	'log'    => [ // 数据库类型
	              'type'            => 'mysql',
	              // 服务器地址
	              'hostname'        => '120.79.178.127',
	              // 数据库名
	              'database'        => 'log',
	              // 用户名
	              'username'        => 'root',
	              // 密码
	              'password'        => 'fdrEddasOp0mj3sd',
                  'hostport'        => '3306',
	              // 连接dsn
	              'dsn'             => '',
	              // 数据库连接参数
	              'params'          => [],
	              // 数据库编码默认采用utf8
	              'charset'         => 'utf8',
	              // 数据库表前缀
	              'prefix'          => 'tbl_',
	              // 数据库调试模式
	              'debug'           => TRUE,
	              // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
	              'deploy'          => 0,
	              // 数据库读写是否分离 主从式有效
	              'rw_separate'     => FALSE,
	              // 读写分离后 主服务器数量
	              'master_num'      => 1,
	              // 指定从服务器序号
	              'slave_no'        => '',
	              // 自动读取主库数据
	              'read_master'     => FALSE,
	              // 是否严格检查字段是否存在
	              'fields_strict'   => TRUE,
	              // 数据集返回类型
	              'resultset_type'  => 'array',
	              // 自动写入时间戳字段
	              'auto_timestamp'  => FALSE,
	              // 时间字段取出后的默认时间格式
	              'datetime_format' => 'Y-m-d H:i:s',
	              // 是否需要进行SQL性能分析
	              'sql_explain'     => FALSE,
	],
	'report' => [ // 数据库类型
	              'type'            => 'mysql',
	              // 服务器地址
	              'hostname'        => '120.79.178.127',
	              // 数据库名
	              'database'        => 'report',
	              // 用户名
	              'username'        => 'root',
	              // 密码
	              'password'        => 'fdrEddasOp0mj3sd',
                  'hostport'        => '3306',
	              // 连接dsn
	              'dsn'             => '',
	              // 数据库连接参数
	              'params'          => [],
	              // 数据库编码默认采用utf8
	              'charset'         => 'utf8',
	              // 数据库表前缀
	              'prefix'          => 'tbl_',
	              // 数据库调试模式
	              'debug'           => TRUE,
	              // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
	              'deploy'          => 0,
	              // 数据库读写是否分离 主从式有效
	              'rw_separate'     => FALSE,
	              // 读写分离后 主服务器数量
	              'master_num'      => 1,
	              // 指定从服务器序号
	              'slave_no'        => '',
	              // 自动读取主库数据
	              'read_master'     => FALSE,
	              // 是否严格检查字段是否存在
	              'fields_strict'   => TRUE,
	              // 数据集返回类型
	              'resultset_type'  => 'array',
	              // 自动写入时间戳字段
	              'auto_timestamp'  => FALSE,
	              // 时间字段取出后的默认时间格式
	              'datetime_format' => 'Y-m-d H:i:s',
	              // 是否需要进行SQL性能分析
	              'sql_explain'     => FALSE,
	],

	'slotdatacenter' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'slotdatacenter',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],

	'slotdatatest' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'slotdatatest',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],

	'portal' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'newPortal',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],
	'center' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'newCenter',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],


	'pay' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'pay',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],
	'datacenter' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'datacenter',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],
	'uwinslot' => [
		//线上服的连接数据
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'uwinslot',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],
	'slotdatacenterNew' => [
		//数据库类型
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '120.79.178.127',
		// 数据库名
		'database'        => 'slotdatacenter',
		// 用户名
		'username'        => 'root',
		// 密码
		'password'        => 'fdrEddasOp0mj3sd',
		// 端口
		'hostport'        => '3306',
		// 连接dsn
		'dsn'             => '',
		// 数据库连接参数
		'params'          => [],
		// 数据库编码默认采用utf8
		'charset'         => 'utf8',
		// 数据库表前缀
		'prefix'          => 'tbl_',
		// 数据库调试模式
		'debug'           => TRUE,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 0,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => FALSE,
		// 读写分离后 主服务器数量
		'master_num'      => 1,
		// 指定从服务器序号
		'slave_no'        => '',
		// 自动读取主库数据
		'read_master'     => FALSE,
		// 是否严格检查字段是否存在
		'fields_strict'   => TRUE,
		// 数据集返回类型
		'resultset_type'  => 'array',
		// 自动写入时间戳字段
		'auto_timestamp'  => FALSE,
		// 时间字段取出后的默认时间格式
		'datetime_format' => 'Y-m-d H:i:s',
		// 是否需要进行SQL性能分析
		'sql_explain'     => FALSE,
	],
];