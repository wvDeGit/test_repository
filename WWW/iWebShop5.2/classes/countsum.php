<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file countsum.php
 * @brief 计算购物车中的商品价格
 * @author chendeshan
 * @date 2011-02-24
 * @version 0.6
 */
class CountSum
{
	//用户ID
	public $user_id = 0;

	//用户组ID
	public $group_id = '';

	//用户组折扣
	public $group_discount = '';

	//错误信息
	public $error = '';

	/**
	 * 构造函数
	 */
	public function __construct($user_id = 0)
	{
		if($user_id)
		{
			$this->user_id = $user_id;
		}
		else
		{
			$userCheckRights = IWeb::$app->getController()->user;
			$this->user_id = ( isset($userCheckRights['user_id']) && $userCheckRights['user_id'] ) ? $userCheckRights['user_id'] : 0;
		}

		//获取用户组ID及组的折扣率
		if($this->user_id)
		{
			$groupObj = new IModel('member as m , user_group as g');
			$groupRow = $groupObj->getObj('m.user_id = '.$this->user_id.' and m.group_id = g.id','g.*');
			if($groupRow)
			{
				$this->group_id       = $groupRow['id'];
				$this->group_discount = $groupRow['discount'] * 0.01;
			}
		}
	}

	/**
	 * 获取会员组价格
	 * @param $id   int    商品或货品ID
	 * @param $type string goods:商品; product:货品
	 * @return float 价格
	 */
	public function getGroupPrice($id,$type = 'goods')
	{
		if(!$this->group_id)
		{
			return null;
		}

		//1,查询特定商品的组价格
		$groupPriceDB = new IModel('group_price');
		if($type == 'goods')
		{
			$discountRow = $groupPriceDB->getObj('goods_id = '.$id.' and group_id = '.$this->group_id,'price');
		}
		else
		{
			$discountRow = $groupPriceDB->getObj('product_id = '.$id.' and group_id = '.$this->group_id,'price');
		}

		if($discountRow)
		{
			return $discountRow['price'];
		}

		//2,根据会员折扣率计算商品折扣
		if($this->group_discount)
		{
			if($type == 'goods')
			{
				$goodsDB  = new IModel('goods');
				$goodsRow = $goodsDB->getObj('id = '.$id,'sell_price');
				return $goodsRow ? Util::priceFormat($goodsRow['sell_price'] * $this->group_discount) : null;
			}
			else
			{
				$productDB  = new IModel('products');
				$productRow = $productDB->getObj('id = '.$id,'sell_price');
				return $productRow ? Util::priceFormat($productRow['sell_price'] * $this->group_discount) : null;
			}
		}
		return null;
	}

	/**
	 * 获取会员组价格区间,(1)手动定义商品价格; (2)商品自动折扣
	 * @param  $id   int 商品ID
	 * @return float 价格
	 */
	public function groupPriceRange($id)
	{
		if(!$this->group_id)
		{
			return null;
		}

		//查询商品会员价格
		$goodsDB = new IModel('goods');
		$goodsRow= $goodsDB->getObj('id = '.$id,'sell_price');
		if(!$goodsRow)
		{
			return null;
		}

		//默认商品折扣价格
		$resultPrice = array(
			'minSellPrice' => Util::priceFormat($goodsRow['sell_price'] * $this->group_discount)
		);

		//查询会员价格设定表
		$groupPriceDB = new IModel('group_price');
		$discountRow  = $groupPriceDB->getObj('goods_id = '.$id.' and group_id = '.$this->group_id,'min(price) as minGroupPrice,max(price) as maxGroupPrice');
		if(isset($discountRow['minGroupPrice']) && $discountRow['minGroupPrice'])
		{
			$resultPrice['minGroupPrice'] = $discountRow['minGroupPrice'];
		}
		if(isset($discountRow['maxGroupPrice']) && $discountRow['maxGroupPrice'])
		{
			$resultPrice['maxGroupPrice'] = $discountRow['maxGroupPrice'];
		}

		//如果设置了指定的会员价格，那么就移除默认的折扣价格
		if($discountRow && current($discountRow))
		{
			unset($resultPrice['minSellPrice']);
		}

		//查询货品默认价格
		$tb_product   = new IModel('products');
		$product_info = $tb_product->getObj('goods_id='.$id,'max(sell_price) as maxSellPrice');
		if(isset($product_info['maxSellPrice']) && $product_info['maxSellPrice'])
		{
			$resultPrice['maxSellPrice'] = Util::priceFormat($product_info['maxSellPrice'] * $this->group_discount);
		}

		$minPrice = min($resultPrice);
		$maxPrice = max($resultPrice);
		return $minPrice == $maxPrice ? $minPrice : join('-',array($minPrice,$maxPrice));
	}

	/**
	 * @brief 计算商品价格
	 * @param Array $buyInfo ,购物车格式
	 * @return array or bool
	 */
	public function goodsCount($buyInfo)
	{
		$this->sum           = 0;       //原始总额(优惠前)
		$this->final_sum     = 0;       //应付总额(优惠后)
    	$this->weight        = 0;       //总重量
    	$this->reduce        = 0;       //减少总额
    	$this->count         = 0;       //总数量
    	$this->promotion     = array(); //促销活动规则文本
    	$this->proReduce     = 0;       //促销活动规则优惠额
    	$this->point         = 0;       //增加积分
    	$this->exp           = 0;       //增加经验
    	$this->freeFreight   = array(); //商家免运费,免运费的商家ID,自营ID为0
    	$this->tax           = 0;       //商品税金
    	$this->seller        = array(); //商家商品总额统计, 商家ID => 商品金额
    	$this->spend_point   = 0;       //商品所需积分

		$user_id      = $this->user_id;
		$group_id     = $this->group_id;
    	$goodsList    = array();
    	$productList  = array();
    	$giftIds      = array();//奖励性促销规则IDS

        //判断商品是否存在活动情况
        $promo      = "";
        $active_id  = 0;
        $goodsCount = 0;
        foreach($buyInfo as $key => $val)
        {
            if(isset($val['id']) && is_array($val['id']))
            {
                $goodsCount += count($val['id']);
            }
        }

    	if($goodsCount === 1)
    	{
    	    $goodsInfo = current($buyInfo);
    	    if(count($goodsInfo['id']) == 1)
    	    {
    	        $goodsType = key($buyInfo);
    	        $infoId    = current($goodsInfo['id']);
    	        $infoTemp  = $goodsType == 'goods' ? Api::run('getGoodsInfo',array("id" => $infoId)) : Api::run('getProductInfo',array("id" => $infoId));
    	        if($infoTemp)
    	        {
    	            $promo     = $infoTemp['promo'];
    	            $active_id = $infoTemp['active_id'];
    	        }
    	    }
    	}

		//活动购买情况
    	if($promo && $active_id)
    	{
    		$ac_type    = isset($buyInfo['goods']) && $buyInfo['goods']['id'] ? "goods" : "product";
    		$ac_id      = current($buyInfo[$ac_type]['id']);
    		$ac_buy_num = $buyInfo[$ac_type]['data'][$ac_id]['count'];

			//开启促销活动
	    	$activeObject      = new Active($promo,$active_id,$user_id,$ac_id,$ac_type,$ac_buy_num);
	    	$activeResult      = $activeObject->checkValid();
            $this->spend_point = $activeObject->spendPoint;
	    	if($activeResult === true)
	    	{
	    		$typeRow  = $activeObject->originalGoodsInfo;
	    		$disPrice = $activeObject->activePrice;

				//设置优惠价格，如果不存在则优惠价等于商品原价
				$typeRow['reduce'] = round($typeRow['sell_price'] - $disPrice,2);
				$typeRow['count']  = $ac_buy_num;
    			$current_sum_all   = $typeRow['sell_price'] * $ac_buy_num;
    			$current_reduce_all= $typeRow['reduce']     * $ac_buy_num;
				$typeRow['sum']    = $current_sum_all - $current_reduce_all;

    			if(!isset($this->seller[$typeRow['seller_id']]))
    			{
    				$this->seller[$typeRow['seller_id']] = 0;
    			}
    			$this->seller[$typeRow['seller_id']] += $typeRow['sum'];

    			//全局统计
		    	$this->weight += $typeRow['weight'] * $ac_buy_num;
		    	$this->point  += $typeRow['point']  * $ac_buy_num;
		    	$this->exp    += $typeRow['exp']    * $ac_buy_num;
		    	$this->sum    += $current_sum_all;
		    	$this->reduce += $current_reduce_all;
		    	$this->count  += $ac_buy_num;
		    	$this->tax    += self::getGoodsTax($typeRow['sum'],$typeRow['seller_id']);
		    	$typeRow == "goods" ? ($goodsList[] = $typeRow) : ($productList[] = $typeRow);
	    	}
	    	else
	    	{
	    		$this->error .= $activeResult;
	    		return $activeResult;
	    	}
    	}
    	else
    	{
			/*开始计算goods和product的优惠信息 , 会根据条件分析出执行以下哪一种情况:
			 *(1)查看此商品(货品)是否已经根据不同会员组设定了优惠价格;
			 *(2)当前用户是否属于某个用户组中的成员，并且此用户组享受折扣率;
			 *(3)优惠价等于商品(货品)原价;
			 */

			//获取商品或货品数据
			/*Goods 拼装商品优惠价的数据*/
	    	if(isset($buyInfo['goods']['id']) && $buyInfo['goods']['id'])
	    	{
	    		//购物车中的商品数据
	    		$goodsIdStr = join(',',$buyInfo['goods']['id']);
	    		$goodsObj   = new IQuery('goods as go');
	    		$goodsObj->where = 'go.id in ('.$goodsIdStr.') and go.is_del = 0';
	    		$goodsObj->fields= 'go.name,go.cost_price,go.id as goods_id,go.img,go.sell_price,go.point,go.weight,go.store_nums,go.exp,go.goods_no,0 as product_id,go.seller_id,go.type';
	    		$goodsList       = $goodsObj->find();

	    		//开始优惠情况判断
	    		foreach($goodsList as $key => $val)
	    		{
	    			//检查库存
	    			if($buyInfo['goods']['data'][$val['goods_id']]['count'] <= 0 || $buyInfo['goods']['data'][$val['goods_id']]['count'] > $val['store_nums'])
	    			{
	    				$goodsList[$key]['name'] .= "【无库存】";
	    				$this->error .= "<商品：".$val['name']."> 购买数量超出库存，请重新调整购买数量。";
	    			}

	    			$groupPrice                = $this->getGroupPrice($val['goods_id'],'goods');
	    			$goodsList[$key]['reduce'] = $groupPrice === null ? 0 : round($val['sell_price'] - $groupPrice,2);
	    			$goodsList[$key]['count']  = $buyInfo['goods']['data'][$val['goods_id']]['count'];
	    			$current_sum_all           = $goodsList[$key]['sell_price'] * $goodsList[$key]['count'];
	    			$current_reduce_all        = $goodsList[$key]['reduce']     * $goodsList[$key]['count'];
	    			$goodsList[$key]['sum']    = $current_sum_all - $current_reduce_all;
	    			if(!isset($this->seller[$val['seller_id']]))
	    			{
	    				$this->seller[$val['seller_id']] = 0;
	    			}
	    			$this->seller[$val['seller_id']] += $goodsList[$key]['sum'];

	    			//全局统计
			    	$this->weight += $val['weight'] * $goodsList[$key]['count'];
			    	$this->point  += $val['point']  * $goodsList[$key]['count'];
			    	$this->exp    += $val['exp']    * $goodsList[$key]['count'];
			    	$this->sum    += $current_sum_all;
			    	$this->reduce += $current_reduce_all;
			    	$this->count  += $goodsList[$key]['count'];
			    	$this->tax    += self::getGoodsTax($goodsList[$key]['sum'],$val['seller_id']);
			    }
	    	}

			/*Product 拼装商品优惠价的数据*/
	    	if(isset($buyInfo['product']['id']) && $buyInfo['product']['id'])
	    	{
	    		//购物车中的货品数据
	    		$productIdStr = join(',',$buyInfo['product']['id']);
	    		$productObj   = new IQuery('products as pro,goods as go');
	    		$productObj->where  = 'pro.id in ('.$productIdStr.') and go.id = pro.goods_id';
	    		$productObj->fields = 'pro.sell_price,pro.cost_price,pro.weight,pro.id as product_id,pro.spec_array,pro.goods_id,pro.store_nums,pro.products_no as goods_no,go.name,go.point,go.exp,go.img,go.seller_id,go.type';
	    		$productList  = $productObj->find();

	    		//开始优惠情况判断
	    		foreach($productList as $key => $val)
	    		{
	    			//检查库存
	    			if($buyInfo['product']['data'][$val['product_id']]['count'] <= 0 || $buyInfo['product']['data'][$val['product_id']]['count'] > $val['store_nums'])
	    			{
	    				$productList[$key]['name'] .= "【无库存】";
	    				$this->error .= "<货品：".$val['name']."> 购买数量超出库存，请重新调整购买数量。";
	    			}

	    			$groupPrice                  = $this->getGroupPrice($val['product_id'],'product');
					$productList[$key]['reduce'] = $groupPrice === null ? 0 : round($val['sell_price'] - $groupPrice,2);
	    			$productList[$key]['count']  = $buyInfo['product']['data'][$val['product_id']]['count'];
	    			$current_sum_all             = $productList[$key]['sell_price']  * $productList[$key]['count'];
	    			$current_reduce_all          = $productList[$key]['reduce']      * $productList[$key]['count'];
	    			$productList[$key]['sum']    = $current_sum_all - $current_reduce_all;
	    			if(!isset($this->seller[$val['seller_id']]))
	    			{
	    				$this->seller[$val['seller_id']] = 0;
	    			}
	    			$this->seller[$val['seller_id']] += $productList[$key]['sum'];

	    			//全局统计
			    	$this->weight += $val['weight'] * $productList[$key]['count'];
			    	$this->point  += $val['point']  * $productList[$key]['count'];
			    	$this->exp    += $val['exp']    * $productList[$key]['count'];
			    	$this->sum    += $current_sum_all;
			    	$this->reduce += $current_reduce_all;
			    	$this->count  += $productList[$key]['count'];
			    	$this->tax    += self::getGoodsTax($productList[$key]['sum'],$val['seller_id']);
			    }
	    	}

	    	//总金额满足的促销规则
	    	if($user_id)
	    	{
	    		//计算每个商家促销规则
	    		foreach($this->seller as $seller_id => $sum)
	    		{
			    	$proObj = new ProRule($sum,$seller_id);
			    	$proObj->setUserGroup($group_id);
			    	if($proObj->isFreeFreight() == true)
			    	{
			    		$this->freeFreight[] = $seller_id;
			    	}
			    	//获取奖励型促销规则
			    	$giftIds[$seller_id] = $proObj->getAwardIds();
			    	$this->promotion = array_merge($proObj->getInfo(),$this->promotion);
			    	$this->proReduce += $sum - $proObj->getSum();
	    		}
	    	}
    	}

    	$this->final_sum = $this->sum - $this->reduce - $this->proReduce;
    	$this->final_sum = $this->final_sum <= 0 ? 0 : $this->final_sum;
    	$resultList      = array_merge($goodsList,$productList);
    	if(!$resultList)
    	{
    		$this->error .= "当前没有选购商品，请重新选择商品下单";
    	}

    	$goodsRow = current($resultList);
    	return array(
    	    'active_id'  => $active_id,
    	    'promo'      => $promo,
    		'final_sum'  => $this->final_sum,
    		'promotion'  => $this->promotion,
    		'proReduce'  => $this->proReduce,
    		'sum'        => $this->sum,
    		'goodsList'  => $resultList,
    		'count'      => $this->count,
    		'reduce'     => $this->reduce,
    		'weight'     => common::formatWeight($this->weight),
    		'point'      => $this->point,
    		'exp'        => $this->exp,
    		'tax'        => $this->tax,
    		'freeFreight'=> $this->freeFreight,
    		'seller'     => $this->seller,
    		'giftIds'    => $giftIds,
    		'spend_point'=> $this->spend_point,
    		'goodsType'  => $goodsRow['type'],
    	);
	}

	//购物车计算
	public function cart_count($id = '',$type = '',$buy_num = 1)
	{
		//单品购买
		if($id && $type)
		{
			$type = ($type == "goods") ? "goods" : "product";

			//规格必填
			if($type == "goods")
			{
				$productsDB = new IModel('products');
				if($productsDB->getObj('goods_id = '.$id))
				{
					$this->error .= '请先选择商品的规格';
					return $this->error;
				}
			}

    		$buyInfo = array(
    			$type => array('id' => array($id),'data' => array($id => array('count' => $buy_num)),'count' => $buy_num)
    		);
		}
		else
		{
			//获取购物车中的商品和货品信息
	    	$cartObj = new Cart();
	    	$buyInfo = $cartObj->getMyCart(false);
		}
    	return $this->goodsCount($buyInfo);
    }

    /**
     * 计算订单信息,其中部分计算都是以商品原总价格计算的$goodsSum
     * @param $goodsResult array CountSum结果集
     * @param $province_id int 省份ID
     * @param $delievery_id int 配送方式ID
     * @param $payment_id int 支付ID
     * @param $is_invoice int 是否要发票
     * @param $discount float 订单的加价或者减价
     * @return $result 最终的返回数组
     */
    public function countOrderFee($goodsResult,$province_id,$delivery_id,$payment_id,$is_invoice,$discount = 0)
    {
    	//根据商家进行商品分组
    	$sellerGoods = array();
    	foreach($goodsResult['goodsList'] as $key => $val)
    	{
    		if(!isset($sellerGoods[$val['seller_id']]))
    		{
    			$sellerGoods[$val['seller_id']] = array();
    		}
    		$sellerGoods[$val['seller_id']][] = $val;
    	}

        $cartObj = new Cart();
    	foreach($sellerGoods as $seller_id => $item)
    	{
    		$num          = array();
    		$productID    = array();
    		$goodsID      = array();
    		$goodsArray   = array();
    		$productArray = array();
    		foreach($item as $key => $val)
    		{
    			$goodsID[]   = $val['goods_id'];
    			$productID[] = $val['product_id'];
    			$num[]       = $val['count'];

	    		if($val['product_id'] > 0)
	    		{
	    			$productArray[$val['product_id']] = $val['count'];
	    		}
	    		else
	    		{
	    			$goodsArray[$val['goods_id']] = $val['count'];
	    		}
    		}

    		$sellerData = $this->goodsCount($cartObj->cartFormat(array("goods" => $goodsArray,"product" => $productArray)));
	    	if(is_string($sellerData))
	    	{
				return $sellerData;
	    	}

            //配送方式和运费是否存在(虚拟商品不需要配送)
    		if($sellerData['goodsType'] == 'default')
    		{
    	    	$deliveryList = Delivery::getDelivery($province_id,$delivery_id,$goodsID,$productID,$num);
    	    	if(is_string($deliveryList))
    	    	{
    				return $deliveryList;
    	    	}

    			//物流无法送达
    	    	if($deliveryList['if_delivery'] == 1)
    	    	{
    	    		return '所选物流方式无法送达至您所在地';
    	    	}

    			//有促销免运费活动
    			if(isset($sellerData['freeFreight']) && $sellerData['freeFreight'])
    			{
    				foreach($sellerData['freeFreight'] as $sid)
    				{
    					if(isset($deliveryList['seller_price'][$sid]))
    					{
    						$deliveryList['price'] -= $deliveryList['seller_price'][$sid];
    						$deliveryList['seller_price'][$sid] = 0;
    					}
    				}
    			}
    		}

	    	$extendArray = array(
	    		'deliveryOrigPrice' => isset($deliveryList['org_price']) ? $deliveryList['org_price'] : 0,
	    		'deliveryPrice'     => isset($deliveryList['price']) ? ($deliveryList['price'] <= 0 ? 0 : $deliveryList['price']) : 0,
	    		'insuredPrice'      => isset($deliveryList['protect_price']) ? $deliveryList['protect_price'] : 0,
	    		'taxPrice'          => $is_invoice == true ? $sellerData['tax'] : 0,
	    		'paymentPrice'      => $payment_id != 0 ? self::getGoodsPaymentPrice($payment_id,$sellerData['final_sum']) : 0,
	    		'goodsResult'       => $sellerData,
	    		'orderAmountPrice'  => 0,
	    		'giftIds'           => isset($sellerData['giftIds'][$seller_id]) ? $sellerData['giftIds'][$seller_id] : '',
	    	);
	    	$orderAmountPrice = array_sum(array(
		    	$sellerData['final_sum'],
		    	$extendArray['deliveryPrice'],
		    	$extendArray['insuredPrice'],
		    	$extendArray['taxPrice'],
		    	$extendArray['paymentPrice'],
	    	));

			//订单减价折扣不能高于订单总额,禁止0元订单
	    	if($discount < 0 && $orderAmountPrice <= abs($discount))
	    	{
	    		return '订单减价折扣不能低于订单总额';
	    	}
			$orderAmountPrice               += $discount;
			$extendArray['orderAmountPrice'] = $orderAmountPrice <= 0 ? 0 : round($orderAmountPrice,2);
			$sellerGoods[$val['seller_id']]  = array_merge($sellerData,$extendArray);
    	}
    	return $sellerGoods;
    }

    /**
     * 获取商品的税金
     * @param $goodsSum float 商品总价格
     * @param $seller_id int 商家ID
     * @return $goodsTaxPrice float 商品的税金
     */
    public static function getGoodsTax($goodsSum,$seller_id = 0)
    {
    	if($seller_id)
    	{
    		$sellerDB = new IModel('seller');
    		$sellerRow= $sellerDB->getObj('id = '.$seller_id);
    		$tax_per  = $sellerRow['tax'];
    	}
    	else
    	{
			$siteConfigObj = new Config("site_config");
			$site_config   = $siteConfigObj->getInfo();
			$tax_per       = isset($site_config['tax']) ? $site_config['tax'] : 0;
    	}
		$goodsTaxPrice = $goodsSum * ($tax_per * 0.01);
		return round($goodsTaxPrice,2);
    }

    /**
     * 获取商品金额的支付费用
     * @param $payment_id int 支付方式ID
     * @param $goodsSum float 商品总价格
     * @return $goodsPayPrice
     */
    public static function getGoodsPaymentPrice($payment_id,$goodsSum)
    {
		$paymentObj = new IModel('payment');
		$paymentRow = $paymentObj->getObj('id = '.$payment_id,'poundage,poundage_type');

		if($paymentRow)
		{
			if($paymentRow['poundage_type'] == 1)
			{
				//按照百分比
				return $goodsSum * ($paymentRow['poundage'] * 0.01);
			}
			//按照固定金额
			return $paymentRow['poundage'];
		}
		return 0;
    }

	/**
	 * @brief 获取商户订单货款结算
	 * @param int $seller_id 商户ID
	 * @param datetime $start_time 订单开始时间
	 * @param datetime $end_time 订单结束时间
	 * @param string $is_checkout 是否已经结算 0:未结算; 1:已结算; null:不限
	 * @param IQuery 结果集对象
	 */
    public static function getSellerGoodsFeeQuery($seller_id = '',$start_time = '',$end_time = '',$is_checkout = '')
    {
    	$where  = "status in (5,6,7) and pay_type != 0 and pay_status = 1 and distribution_status in (0,1,2)";
    	$where .= $is_checkout !== '' ? " and is_checkout = ".$is_checkout : "";
    	$where .= $seller_id          ? " and seller_id = ".$seller_id : " and seller_id > 0";
    	$where .= $start_time         ? " and create_time >= '{$start_time}' " : "";
    	$where .= $end_time           ? " and create_time <= '{$end_time}' "   : "";

    	$orderGoodsDB = new IQuery('order');
    	$orderGoodsDB->order = "id desc";
    	$orderGoodsDB->where = $where;
    	return $orderGoodsDB;
    }

	/**
	 * @brief 计算商户货款及其他费用
	 * @param array $orderList 订单数据关联
	 * @return array(
	 * 'orderAmountPrice' => 订单金额（去掉pay_fee支付手续费）,'refundFee' => 退款金额, 'commissionFee' => 分销佣金金额, 'orgCountFee' => 原始结算金额,
	 * 'countFee' => 实际结算金额, 'platformFee' => 平台促销活动金额(优惠券等平台补贴给商家),'commission' => '手续费' ,
	 * 'orderNum' => 订单数量, 'order_ids' => 订单IDS,'orderNoList' => 订单编号
	 * ),
	 */
    public static function countSellerOrderFee($orderList)
    {
    	$result = array(
			'orderAmountPrice' => 0,
			'refundFee'        => 0,
			'commissionFee'    => 0,
			'orgCountFee'      => 0,
			'countFee'         => 0,
			'platformFee'      => 0,
			'commission'       => 0,
			'orderNum'         => count($orderList),
			'order_ids'        => array(),
			'orderNoList'      => array(),
    	);

    	if($orderList && is_array($orderList))
    	{
    		$refundObj = new IModel("refundment_doc");
    		$propObj   = new IModel("prop");
    		foreach($orderList as $key => $item)
    		{
    			//检查订单未完全退款下的平台促销活动
    			//1,优惠券
    			if($item['prop'] && $item['status'] != 6)
    			{
    				$propRow = $propObj->getObj('id = '.$item['prop'].' and type = 0');
    				if($propRow && $propRow['seller_id'] == 0)
    				{
    					$propRow['value'] = min($item['real_amount'],$propRow['value']);
    					$result['platformFee'] += $propRow['value'];
    				}
    			}

    			$result['orderAmountPrice'] += $item['order_amount'] - $item['pay_fee'];
    			$result['order_ids'][]       = $item['id'];
    			$result['orderNoList'][]     = $item['order_no'];

    			//是否存在退款
    			$refundList = $refundObj->query("order_id = ".$item['id'].' and pay_status = 2');
    			foreach($refundList as $k => $val)
    			{
    				$result['refundFee'] += $val['amount'];
    			}

    			//是否存在订单佣金
    			$itemCommissionFee = plugin::trigger('getCommissionFeeByOrderId',$item['id']);
    			if($itemCommissionFee)
    			{
    				$result['commissionFee'] += $itemCommissionFee;
    			}

    			//订单手续费总金额
    			$result['commission'] += $item['servicefee_amount'];
    		}
    	}

		//应该结算金额
		$result['orgCountFee'] = $result['orderAmountPrice'] - $result['refundFee'] - $result['commissionFee'] + $result['platformFee'];

		//最终结算金额
		$result['countFee'] = $result['orgCountFee'] - $result['commission'];

    	return $result;
    }

	//获取发票类型的文字显示
    public static function invoiceTypeText($type)
    {
    	$config = array('1' => '普通发票' , '2' => '增值税专用票');
    	return isset($config[$type]) ? $config[$type] : "";
    }

	//发票信息数据可读性的
    public static function invoiceText($invoiceJSON)
    {
    	$result = array(
    		"type"         => "发票类型",
    		"company_name" => "公司名称",
    		"taxcode"      => "纳税人识别码",
    		"address"      => "注册地址",
    		"telphone"     => "注册电话",
    		"bankname"     => "开户银行",
    		"bankno"       => "银行账号",
    	);

    	$resultArray  = array();
    	$invoiceArray = JSON::decode($invoiceJSON);
    	if(!$invoiceArray)
    	{
    		return '';
    	}
    	foreach($invoiceArray as $key => $val)
    	{
    		if(isset($result[$key]))
    		{
    			if($key == 'type')
    			{
    				$val = self::invoiceTypeText($val);
    			}
    			$resultArray[$result[$key]] = $val;
    		}
    	}
    	return join("  ",$resultArray);
    }

    /**
     * @brief 计算商户订单手续费总金额
     * @param array $orderList 订单数据关联
     * @return array 更新商户订单手续费总金额后，返回最新的结果集
     */
    public static function countSellerOrderServicefee($orderList)
    {
        if (!$orderList)
        {
            //无订单数据则立即结束
            return $orderList;
        }
        //获取商品手续费率
        $goodsRateObj = new IModel('goods_rate');
        $goods_rate_list = $goodsRateObj->query(false, 'goods_id,goods_rate', '', 'all');

        $goods_rate_array = array();
        foreach ($goods_rate_list as $val)
        {
            $goods_rate_array[$val['goods_id']] = $val['goods_rate'];
        }
        unset($goods_rate_list);

        //获取分类手续费率
        $categoryRateObj = new IModel('category_rate');
        $category_rate_list = $categoryRateObj->query(false, 'category_id,category_rate', '', 'all');

        $category_rate_array = array();
        foreach ($category_rate_list as $val)
        {
            $category_rate_array[$val['category_id']] = $val['category_rate'];
        }
        unset($category_rate_list);

        //平台设置的商家结算手续费率
        $siteConfigData = new Config('site_config');
        $seller_rate = $siteConfigData->commission ? $siteConfigData->commission : 0;

        //获取商户结算折扣率
        $sellerObj = new IModel('seller');
        $seller_discount_list = $sellerObj->query(false, 'id,discount', '', 'all');

        $seller_discount_array = array();
        foreach ($seller_discount_list as $val)
        {
            $seller_discount_array[$val['id']] = $val['discount'];
        }
        unset($seller_discount_list);

        // 商品分类
        $category_class = new category_class();

        // 订单id数组
        $order_ids = array();
        foreach ($orderList as $val)
        {
            $order_ids[] = $val['id'];
        }
        $order_goods_where = $servicefee_where = 'order_id in (' .implode(',', $order_ids). ')';
        // 订单商品手续费
        $servicefee_array = array();

        //商品分类关系
        $categoryExtendObj = new IModel('category_extend');
        //订单商品手续费
        $servicefeeObj = new IModel('order_goods_servicefee');
        //依据订单id删除已存在的订单商品手续费记录
        $servicefeeObj->del($servicefee_where);

        //批量获取订单的商品
        $orderGoodsObj = new IModel('order_goods');
        $order_goods_list = $orderGoodsObj->query($order_goods_where, 'id,order_id,goods_id,real_price,goods_nums,is_send,seller_id', '', 'all');
        foreach ($order_goods_list as $val)
        {
            //是否已发货 0:未发货;1:已发货;2:已经退货
            if ('1' != $val['is_send'])
            {
                //不是“已发货”状态的，则跳过
                continue;
            }
            //商户ID，非商户的商品则跳过
            if (!$val['seller_id'])
            {
                continue;
            }

            $temp_servicefee = array();
            $temp_servicefee['order_id'] = $val['order_id'];
            $temp_servicefee['order_goods_id'] = $val['id'];

            $temp_goods_id = $val['goods_id'];
            //是否存在单品手续费率
            if (isset($goods_rate_array[$temp_goods_id]))
            {
                $temp_servicefee['type'] = 1;
                $temp_servicefee['rate'] = $goods_rate_array[$temp_goods_id];
            }
            else
            {
                //该商品所属的且已设置手续费率的分类id
                $category_array = array();
                //查询商品所属分类
                $category_extend_where = 'goods_id =' . $temp_goods_id;
                $goods_category_list = $categoryExtendObj->query($category_extend_where, 'category_id', '', 'all');
                //查询是否设置了分类手续费率
                foreach ($goods_category_list as $v)
                {
                    if (isset($category_rate_array[$v['category_id']]))
                    {
                        $category_array[] = $v['category_id'];
                    }
                }
                unset($goods_category_list);

                $category_num = count($category_array);
                if (0 == $category_num)
                {
                    //平台设置的商家结算手续费率
                    $temp_servicefee['type'] = 0;
                    $temp_servicefee['rate'] = $seller_rate;
                }
                elseif (1 == $category_num)
                {
                    //唯一分类，无需追溯分类路径
                    $temp_servicefee['type'] = 2;
                    $temp_servicefee['rate'] = $category_rate_array[$category_array[0]];
                }
                else
                {
                    //多个分类，取末级分类的最大值
                    // 父级分类id数组
                    $parent_id_array = array();
                    foreach ($category_array as $v)
                    {
                        // 获取本分类的父级分类数组
                        $path = $category_class->get_path($v);
                        // 合并所有的父级分类id
                        $parent_id_array = array_merge($parent_id_array, $path);
                    }
                    //需要参与比较的分类，实质是去掉所有的父级分类id
                    $category_array = array_diff($category_array, $parent_id_array);
                    //最大的分类手续费率
                    $max_rate = 0;
                    foreach ($category_array as $v)
                    {
                        if ($max_rate <= $category_rate_array[$v])
                        {
                            $max_rate = $category_rate_array[$v];
                        }
                    }
                    $temp_servicefee['type'] = 2;
                    $temp_servicefee['rate'] = $max_rate;
                }
            }
            //商户结算折扣率
            if (isset($seller_discount_array[$val['seller_id']]))
            {
                $temp_servicefee['discount'] = $seller_discount_array[$val['seller_id']];
            }
            else
            {
                $temp_servicefee['discount'] = 100.00;
            }
            //手续费总额 = 实付金额 * 商品数量 * 手续费率 * 商户结算折扣率;
            $temp_servicefee['amount'] = round($val['real_price'] * $val['goods_nums'] * $temp_servicefee['rate'] * 0.01 * $temp_servicefee['discount'] * 0.01, 2);

            //订单商品手续费，二维数组，因为一个订单可能同时存在多个商品
            $servicefee_array[$temp_servicefee['order_id']][] = $temp_servicefee['amount'];

            //写入数据表：order_goods_servicefee
            $servicefeeObj->setData($temp_servicefee);
            $servicefeeObj->add();
        }
        unset($order_goods_list);

        //订单手续费总金额，array_map — 为数组的每个元素应用回调函数；array_sum — 对数组中所有值求和
        $servicefee_amount_array = array_map('array_sum', $servicefee_array);

        if ($servicefee_amount_array)
        {
            $orderObj = new IModel('order');
            //更新订单表的订单手续费总金额：servicefee_amount
            foreach ($servicefee_amount_array as $key=>$val)
            {
                $tempData = array(
                    'servicefee_amount' => $val,
                );
                $orderObj->setData($tempData);
                $tempWhere = 'id='.$key;
                $orderObj->update($tempWhere);
            }
            //更新$orderList数组的订单手续费总金额
            foreach ($orderList as $k=>$v)
            {
                if (isset($servicefee_amount_array[$v['id']]))
                {
                    $orderList[$k]['servicefee_amount'] = $servicefee_amount_array[$v['id']];
                }
            }
        }
        return $orderList;
    }

    /**
     * @brief 获取手续费类型的文字显示
     * @param integer $type 手续费类型
     */
    public static function servicefeeTypeText($type)
    {
        $config = array(
            '0' => '默认',
            '1' => '单品',
            '2' => '分类',
        );
        return isset($config[$type]) ? $config[$type] : '';
    }
}