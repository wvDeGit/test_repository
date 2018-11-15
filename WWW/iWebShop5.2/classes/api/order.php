<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file order.php
 * @brief 订单API
 * @author nswe
 * @date 2018/4/17 8:43:13
 * @version 5.1
 */
class APIOrder
{
	/**
	 * @brief 获取带有地域文字信息的订单数据
	 * @param array $idArray 订单ID数组
	 * @return array 订单列表数据
	 */
	public function getOrderListWithArea($idArray)
	{
		$idArray     = IFilter::act($idArray,'int');
		$orderObj    = new IModel('order');
		$areaIdArray = array();
		$where       = 'id in ('.join(',',$idArray).')';

		//如果不是管理员的权限，则强制增加seller_id的判断条件，防止越权查看订单信息
		if(!IWeb::$app->getController()->admin['admin_id'])
		{
			$where .= ' and seller_id = '.IWeb::$app->getController()->seller['seller_id'];
		}
		$orderList = $orderObj->query($where);

		if(!$orderList)
		{
			IError::show(403,"无查阅订单权限");
		}

		foreach($orderList as $key => $val)
		{
			$temp = area::name($val['province'],$val['city'],$val['area']);
			$orderList[$key]['province_str'] = $temp[$val['province']];
			$orderList[$key]['city_str']     = $temp[$val['city']];
			$orderList[$key]['area_str']     = $temp[$val['area']];
		}
		return $orderList;
	}

	//获取消费码信息
	public function getCodeInfo($code)
	{
	    $code = IFilter::act($code);
	    if($code == '')
	    {
	        return array('success' => false,'msg' => '消费码不能为空');
	    }

        $goodsCodeRelationDB = new IModel('order_code_relation');
        $data = $goodsCodeRelationDB->getObj("code = '{$code}'");

        if($data)
        {
            if($data['is_used'] == 1)
            {
                return array('success' => false,'msg' => '消费码已使用过！使用时间:'.$data['use_time']);
            }
            else
            {
                $orderGoodsDB = new IModel('order_goods');
                $orderGoodsRow= $orderGoodsDB->getObj('order_id = '.$data['order_id'].' and goods_id = '.$data['goods_id']);
                if($orderGoodsRow)
                {
                    return array('success' => true,'data' => JSON::decode($orderGoodsRow['goods_array']));
                }
                else
                {
                    return array('success' => false,'msg' => '商品信息不存在');
                }
            }
        }
        return array('success' => false,'msg' => '未找到此消费码');
	}
}