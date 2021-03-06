<?php
/**
 * 订单
 *
 *
 *
 *
 * @copyright  Copyright (c) 2016-2017 MoJiKeJi Inc. (http://www.fashop.cn)
 * @license    http://www.fashop.cn
 * @link       http://www.fashop.cn
 * @since      File available since Release v1.1
 */

namespace App\HttpController\Server;

use App\Logic\Order as OrderLogic;
use App\Utils\Code;

class Order extends Server
{

	/**
	 * 订单数量
	 * @method GET | POST
	 * @param array  $create_time    [开始时间,结束时间]
	 * @param string $feedback_state 维权状态：退款处理中 todo、退款结束 closed
	 * @param array  $user_ids       用户id
	 * @param int    $is_print       1打印 0未打印
	 * @param string $keywords_type  商品名称goods_name 、订单号order_no、收货人姓名 receiver_name、收货人电话 receiver_phone、 快递单号courier_number
	 * @param string $keywords       关键词
	 * @param array  $state_types    状态集合，不需要的可以不填，减少浪费
	 * @param
	 */
	public function stateNum()
	{
		$prefix            = config( 'database.prefix' );
		$table_order       = $prefix."order";
		$table_order_goods = $prefix."order_goods";
		$param             = [];

		if( isset( $this->post ) ){
			$param = $this->post;
		}

		if( isset( $this->get ) ){
			$param = $this->get;

		}
		try{
			$orderLogic = new OrderLogic();

			if( isset( $param['create_time'] ) ){
				$orderLogic->createTime( $param['create_time'] );
			}

			if( isset( $param['user_ids'] ) ){
				$orderLogic->users( 'id', $param['user_ids'] );
			}

			if( isset( $param['is_print'] ) ){
				$orderLogic->print( $param['is_print'] );
			}

			if( isset( $param['keywords'] ) && isset( $param['keywords_type'] ) ){
				$orderLogic->keywords( $param['keywords_type'], $param['keywords'] );
			}

			if( isset( $param['feedback_state'] ) ){
				$orderLogic->feedback( $param['feedback_state'] );
			}

			$result['state_new']        = 0;
			$result['state_send']       = 0;
			$result['state_success']    = 0;
			$result['state_close']      = 0;
			$result['state_unevaluate'] = 0;
			$result['state_refund']     = 0;

			$user = $this->getRequestUser();
			$orderLogic->users( 'id', model( 'User' )->getUserAllIds( $user['id'] ) );

			$result['state_new'] = $orderLogic->stateType( 'state_new' )->count();

			$result['state_send'] = $orderLogic->stateType( 'state_send' )->count();

			$result['state_success'] = $orderLogic->stateType( 'state_success' )->count();

			$result['state_unevaluate'] = model( 'OrderGoods' )->getOrderGoodsCount( ['user_id' => ['in', model( 'User' )->getUserAllIds( $user['id'] )], 'evaluate_state' => 0, "(SElECT state FROM $table_order where id =$table_order_goods.order_id)" => 40] );

			$result['state_refund'] = model( 'OrderRefund' )->getOrderRefundCount( ['user_id' => ['in', model( 'User' )->getUserAllIds( $user['id'] )], 'handle_state' => 0] );

			return $this->send( Code::success, $result );
		} catch( \Exception $e ){
			$this->send( Code::server_error, [], $e->getTraceAsString() );
		}

	}

	/**
	 * 订单列表
	 * @method GET | POST
	 * @param string $state_type     未付款'state_new', 已付款'state_pay', 已发货'state_send', 已完成'state_success', 已取消'state_cancel'  未评价'state_unevaluate'
	 * @param array  $create_time    [开始时间,结束时间]
	 * @param string $feedback_state 维权状态：退款处理中 todo、退款结束 closed
	 * @param int    $is_print       1打印 0未打印
	 * @param string $keywords_type  商品名称goods_name 、订单号order_no、收货人姓名 receiver_name、收货人电话 receiver_phone、 快递单号courier_number
	 * @param string $keywords       关键词
	 * @param
	 */
	public function list()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			$user = $this->getRequestUser();

			$param = [];

			if( isset( $this->post ) ){
				$param = $this->post;
			}

			if( isset( $this->get ) ){
				$param = $this->get;

			}
			try{
				$orderLogic = new OrderLogic();
				if( isset( $param['create_time'] ) ){
					$orderLogic->createTime( $param['create_time'] );
				}
				if( isset( $param['state_type'] ) ){
					$orderLogic->stateType( $param['state_type'] );
				}

				$orderLogic->users( 'id', model( 'User' )->getUserAllIds( $user['id'] ) );

				if( isset( $param['is_print'] ) ){
					$orderLogic->print( $param['is_print'] );
				}
				if( isset( $param['keywords'] ) && isset( $param['keywords_type'] ) ){
					$orderLogic->keywords( $param['keywords_type'], $param['keywords'] );
				}
				if( isset( $param['feedback_state'] ) ){
					$orderLogic->feedback( $param['feedback_state'] );
				}
				$orderLogic->page( $this->getPageLimit() )->extend( [
					'order_goods',
					'order_extend',
				] );

				$count       = $orderLogic->count();
				$list        = $orderLogic->list();
				$order_model = model( 'Order' );
				foreach( $list as $key => $order_info ){
					//显示取消订单
					$list[$key]['if_cancel'] = $order_model->getOrderOperateState( 'user_cancel', $order_info );
					//显示是否需能支付（todo 计算后台过期时间）
					$list[$key]['if_pay'] = $order_model->getOrderOperateState( 'user_pay', $order_info );
					//显示退款取消订单
					$list[$key]['if_refund_cancel'] = $order_model->getOrderOperateState( 'refund_cancel', $order_info );
					//显示投诉
					$list[$key]['if_complain'] = $order_model->getOrderOperateState( 'complain', $order_info );
					//显示收货
					$list[$key]['if_receive'] = $order_model->getOrderOperateState( 'receive', $order_info );
					//显示锁定中
					$list[$key]['if_lock'] = $order_model->getOrderOperateState( 'lock', $order_info );
					//显示物流跟踪
					$list[$key]['if_deliver'] = $order_model->getOrderOperateState( 'deliver', $order_info );
					//显示评价
					$list[$key]['if_evaluate'] = $order_model->getOrderOperateState( 'evaluate', $order_info );

				}
				$this->send( Code::success, [
					'total_number' => $count,
					'list'         => $list,
				] );
			} catch( \Exception $e ){
				$this->send( Code::server_error, [], $e->getTraceAsString() );
			}

		}
	}

	/**
	 * 订单详细
	 * @method GET
	 * @param int $id 订单id
	 * @author 韩文博
	 */
	public function info()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $this->get, 'Server/Order.info' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$user     = $this->getRequestUser();
				$order_id = $this->get['id'];
				/**
				 * @var $order_model \App\Model\Order
				 */
				$order_model          = model( 'Order' );
				$condition['id']      = $order_id;
				$condition['user_id'] = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$order_info           = $order_model->getOrderInfo( $condition, '', '*', [
					'order_extend',
					'order_goods',
				] );
				if( empty( $order_info ) ){
					$this->send( Code::error, [], '没有该订单' );
				} else{
					//显示取消订单
					$order_info['if_cancel'] = $order_model->getOrderOperateState( 'user_cancel', $order_info );
					//显示是否需能支付（todo 计算后台过期时间）
					$order_info['if_pay'] = $order_model->getOrderOperateState( 'user_pay', $order_info );
					//显示退款取消订单
					$order_info['if_refund_cancel'] = $order_model->getOrderOperateState( 'refund_cancel', $order_info );
					//显示投诉
					$order_info['if_complain'] = $order_model->getOrderOperateState( 'complain', $order_info );
					//显示收货
					$order_info['if_receive'] = $order_model->getOrderOperateState( 'receive', $order_info );
					//显示锁定中
					$order_info['if_lock'] = $order_model->getOrderOperateState( 'lock', $order_info );
					//显示物流跟踪
					$order_info['if_deliver'] = $order_model->getOrderOperateState( 'deliver', $order_info );
					//显示评价
					$order_info['if_evaluate'] = $order_model->getOrderOperateState( 'evaluate', $order_info );

					$log_list     = model( 'OrderLog' )->getOrderLogList( ['order_id' => $order_id] );
					$refund_model = model( 'OrderRefund' );
					$return_list  = $refund_model->getOrderRefundList( ['order_id' => $order_id, 'refund_type' => 2] );
					$refund_list  = $refund_model->getOrderRefundList( ['order_id' => $order_id, 'refund_type' => 1] );
					$this->send( Code::success, [
						'info'        => $order_info,
						'order_log'   => $log_list,
						'return_list' => $return_list,
						'refund_list' => $refund_list,
					] );
				}
			}
		}
	}

	/**
	 * 取消未付款订单
	 * @method POST
	 * @param int    $id           订单id
	 * @param string $state_remark 状态备注，如：取消原因（改买其他商品、改配送方式、其他原因等等）
	 * @author 韩文博
	 */
	public function cancel()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $this->post, 'Server/Order.cancel' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$user                 = $this->getRequestUser();
				$order_model          = model( 'Order' );
				$condition['id']      = $this->post['id'];
				$condition['user_id'] = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$order_info           = $order_model->getOrderInfo( $condition );
				$extend_msg           = isset( $this->post['state_remark'] ) ? $this->post['state_remark'] : null;
				$result               = $order_model->userChangeState( 'order_cancel', $order_info, $user['id'], $user['username'], $extend_msg );
				if( $result === true ){
					$this->send( Code::success );
				} else{
					$this->send( Code::error );
				}
			}
		}
	}

	/**
	 * 确认收货
	 * @method POST
	 * @param int $id 订单id
	 * @author 韩文博
	 */
	public function confirmReceipt()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $this->post, 'Server/Order.confirmReceipt' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$user                 = $this->getRequestUser();
				$order_model          = model( 'Order' );
				$condition['id']      = $this->post['id'];
				$condition['user_id'] = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$order_info           = $order_model->getOrderInfo( $condition );
				$extend_msg           = isset( $this->post['state_remark'] ) ? $this->post['state_remark'] : null;
				$result               = $order_model->userChangeState( 'order_receive', $order_info, $user['id'], $user['username'], $extend_msg );
				if( $result === true ){
					$this->send( Code::success );
				} else{
					$this->send( Code::error );
				}
			}
		}
	}

	/**
	 * 订单商品列表
	 * @method     GET
	 * @param int $id 订单id
	 * @author  韩文博
	 */
	public function goodsList()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $this->get, 'Server/Order.goodsList' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$user                  = $this->getRequestUser();
				$condition['order_id'] = $this->get['id'];
				$list                  = model( 'Order' )->getOrderGoodsList( [
					'order_id' => $this->get['id'],
					'user_id'  => ['in', model( 'User' )->getUserAllIds( $user['id'] )],
				], '*', 'id asc', '1,1000' );
				$this->send( Code::success, ['list' => $list] );
			}
		}
	}

	/**
	 * 订单商品信息
	 * @method     GET
	 * @param int $id 订单id
	 * @author          韩文博
	 *
	 */
	public function goodsInfo()
	{
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $this->get, 'Server/Order.goodsInfo' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$user                 = $this->getRequestUser();
				$condition['id']      = $this->get['id'];
				$condition['user_id'] = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$result['info']       = model( 'Order' )->getOrderGoodsInfo( $condition );
				$this->send( Code::success, $result );
			}
		}
	}

	/**
	 * 拼团订单团信息[适用于订单详情]
	 * @method GET
	 * @param int $id 订单id
	 * @author 孙泉
	 * @return group_identity 1 团长 2 团员
	 */
	public function groupInfo()
	{
		$get = $this->get;

		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $get, 'Server/Order.groupInfo' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$order_id                = $get['id'];
				$user                    = $this->getRequestUser();
				$order_model             = model( 'Order' );
				$condition['id']         = $order_id;
				$condition['state']      = ['egt', 20];
				$condition['goods_type'] = 2;
				$condition['user_id']    = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$order_info              = $order_model->getOrderInfo( $condition );
				if( !$order_info ){
					return $this->send( Code::param_error, [], '参数错误' );
				} else{
					$prefix             = config( 'database.prefix' );
					$table_order        = $prefix."order";
					$table_user_profile = $prefix."user_profile";
					$field              = 'group_identity,user_id'.",(SELECT avatar FROM $table_user_profile WHERE user_id=$table_order.user_id) AS user_avatar";
					$group_list         = $order_model->getOrderCommonList( ['group_sign' => $order_info['group_sign']], '', $field, 'group_identity asc', '' );
					$this->send( Code::success, [
						'list' => $group_list,
					] );
				}
			}
		}
	}

	/**
	 * 拼团订单列表自动过期 设置拼团失败
	 * @method GET
	 */
	public function antoSetOrderGroup()
	{
		$order_model                  = model( 'Order' );
		$condition['state']           = 20;              //已付款
		$condition['group_end_time']  = ['lt', time()];  //拼团时限已过期
		$condition['group_state']     = 1;               //正在进行中(待开团)
		$condition['goods_type']      = 2;               //拼团订单
		$condition['group_identity']  = 1;               //团长
		$condition['group_fail_time'] = 0;               //拼团失败时间
		$condition_string             = 'group_men_num<group_people_num';
		$group                        = 'group_sign';
		$list                         = $order_model->getOrderCommonList( $condition, $condition_string, '*', 'id desc', '', $group );
		if( $list ){
			$group_sign_arr = array_column( $list, 'group_sign' );
			if( $group_sign_arr && is_array( $group_sign_arr ) ){
				//未付款的已经被关闭的跟团的拼团订单 也会更改他的拼团状态为失败
				$result = $order_model->editOrder( ['group_sign' => ['in', $group_sign_arr]], ['group_state' => 3, 'group_fail_time' => time()] );
				if( !$result ){
					return $this->send( Code::param_error, [], '更改拼团订单状态失败' );
				} else{
					return $this->send( Code::success );
				}
			}
		} else{
			return $this->send( Code::success, [], '暂无数据' );
		}
	}

	/**
	 * 物流查询
	 * @method GET
	 * @param int $id 订单id
	 * @author 孙泉
	 */
	public function logisticsQuery()
	{
		$get = $this->get;
		if( $this->verifyResourceRequest() !== true ){
			$this->send( Code::user_access_token_error );
		} else{
			if( $this->validate( $get, 'Server/Order.groupInfo' ) !== true ){
				$this->send( Code::param_error, [], $this->getValidate()->getError() );
			} else{
				$order_id             = $get['id'];
				$user                 = $this->getRequestUser();
				$order_model          = model( 'Order' );
				$condition['id']      = $order_id;
				$condition['state']   = ['egt', 30];
				$condition['user_id'] = ['in', model( 'User' )->getUserAllIds( $user['id'] )];
				$order_info           = $order_model->getOrderInfo( $condition );
				if( !$order_info ){
					return $this->send( Code::param_error, [], '参数错误' );
				} else{
					$order_extend_model = model( 'OrderExtend' );
					$extend_info        = $order_extend_model->getOrderExtendInfo( ['id' => $order_info['id']], '', '*' );
					if( !$extend_info ){
						return $this->send( Code::param_error, [], '参数错误' );
					} else{
						if( isset( $extend_info['tracking_no'] ) && intval( $extend_info['shipper_id'] ) > 0 && intval( $extend_info['express_id'] ) > 0 ){
							$kuaidi100_code = model( 'Express' )->getExpressValue( ['id' => $extend_info['express_id']], $field = 'kuaidi100_code' );
							if( $kuaidi100_code ){
								$info['url'] = 'https://m.kuaidi100.com/index_all.html?type='.$kuaidi100_code.'&postid='.$extend_info['tracking_no'];
							} else{
								$info = [];
							}
						} else{
							$info = [];
						}
						$this->send( Code::success, [
							'info' => $info,
						] );
					}
				}
			}
		}
	}
}
// /**
//  * 物流跟踪
//  * @method GET
//  * @param string $id 订单id
//  * @author 韩文博
//  */
// public function deliver()
// {
// 	if( $this->verifyResourceRequest() !== true ){
// 		$this->send( Code::user_access_token_error );
// 	} else{
// 		$user = $this->getRequestUser();
// 		if( $this->validate( $this->get, 'Server/Order.info' ) !== true ){
// 			$this->send( Code::param_error, [], $this->getValidate()->getError() );
// 		} else{
// 			$order_model          = model( 'Order' );
// 			$condition['id']      = $this->get['id'];
// 			$condition['user_id'] = $this->user['id'];
// 			$order_info           = $order_model->getOrderInfo( $condition, '', '*', ['order_extend', 'order_goods'] );
// 			if(
// 				empty( $order_info ) || !in_array( $order_info['state'], [
// 					OrderLogic::state_send,
// 					OrderLogic::state_success,
// 				] )
// 			){
// 				return $this->send( Code::error, [], '未找到信息' );
// 			}
// 			$row['order_info'] = $order_info;
// 			//卖家发货信息
// 			$deliver_address_info        = model( 'DeliverAddress' )->getAddressInfo( ['id' => $order_info['extend_order_extend']['deliver_address_id']] );
// 			$row['deliver_address_info'] = $deliver_address_info;

// 			//取得配送公司代码
// 			$express            = expressCache();
// 			$row['code']        = $express[$order_info['extend_order_extend']['shipping_express_id']]['code'];
// 			$row['title']       = $express[$order_info['extend_order_extend']['shipping_express_id']]['title'];
// 			$row['url']         = $express[$order_info['extend_order_extend']['shipping_express_id']]['url'];
// 			$row['tracking_no'] = $order_info['tracking_no'];

// 			$result         = [];
// 			$result['info'] = $row;
// 			$this->send( Code::success, $result );
// 		}
// 	}
// }