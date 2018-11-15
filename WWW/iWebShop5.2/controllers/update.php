<?php
/**
 * @brief 升级更新控制器
 */
class Update extends IController
{
	/**
	 * @brief iwebshop5.2 版本升级更新
	 */
	public function index()
	{
		set_time_limit(0);

		//更新订单类型
		$modelObj = new IModel('order');
        $modelObj->setData(array('type' => ''));
        $modelObj->update('type = 0');

        $modelObj->setData(array('type' => 'groupon'));
        $modelObj->update('type = 1');

        $modelObj->setData(array('type' => 'time'));
        $modelObj->update('type = 2');

        $modelObj->setData(array('type' => 'costpoint'));
        $modelObj->update('type = 3');

		$sql = array(
		    "ALTER TABLE `{pre}marketing_sms` add `rev_info` text COMMENT '收件人信息';",
		    "ALTER TABLE `{pre}message` add `rev_info` text COMMENT '收件人信息';",
		    "ALTER TABLE `{pre}seller_message` add `rev_info` text COMMENT '收件人信息';",

            "ALTER TABLE `{pre}order` CHANGE `type` `type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '默认:普通,groupon:团购,time:限时抢购,costpoint:积分兑换';",
		    "ALTER TABLE `{pre}order` ADD `servicefee_amount` DECIMAL(15,2) NOT NULL DEFAULT '0.00' COMMENT '订单手续费总金额' AFTER `spend_point`;",
		    "ALTER TABLE `{pre}order` ADD `goods_type` VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT 'default:实体,code:到店服务,download:知识付费下载';",

            "ALTER TABLE `{pre}goods` ADD COLUMN `promo` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '默认:普通,groupon:团购,time:限时抢购,costpoint:积分兑换';",
            "ALTER TABLE `{pre}goods` ADD COLUMN `active_id` int(11) NOT NULL DEFAULT '0' COMMENT '活动ID主键';",
		    "ALTER TABLE `{pre}goods` ADD COLUMN `type` varchar(20) NOT NULL DEFAULT 'default' COMMENT 'default:实体,code:到店服务,download:知识付费下载';",
		    "ALTER TABLE `{pre}comment` add `img_list` text COMMENT '评价图片';",
            "ALTER TABLE `{pre}refundment_doc` add `img_list` text COMMENT '退款图片';",

			"ALTER TABLE `{pre}delivery_doc` ADD `express_template` mediumtext default NULL COMMENT '快递单模板';",

		    "ALTER TABLE `{pre}promotion` CHANGE `condition` `condition` TEXT NOT NULL COMMENT '活动生效条件 当type=0<促销规则消费额度>,当type=1<限时抢购商品ID>,type=2<特价商品分类ID>,type=3<特价商品ID>,type=4<特价商品品牌ID>,type=5<无意义>,type=6<在线充值金额>';",
		    "ALTER TABLE `{pre}promotion` CHANGE `type` `type` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '活动类型 0:购物车促销规则 1:商品限时抢购 2:商品分类特价 3:商品单品特价 4:商品品牌特价 5:新用户注册促销规则 6:在线充值赠送余额规则';",
		    "ALTER TABLE `{pre}promotion` CHANGE `award_value` `award_value` VARCHAR(255) NULL DEFAULT NULL COMMENT '奖励值 type=0,5,6<奖励值>,type=1<抢购价格>,type=2,3,4<特价折扣>';",
		    "ALTER TABLE `{pre}promotion` CHANGE `award_type` `award_type` TINYINT(3) NOT NULL DEFAULT '0' COMMENT '奖励方式:0商品限时抢购 1减金额 2奖励折扣 3赠送积分 4赠送代金券 5赠送赠品 6免运费 7商品特价 8赠送经验 10赠送余额';",

		    "ALTER TABLE `{pre}seller` ADD `discount` DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT '100.00' COMMENT '商户结算折扣率' AFTER `logo`;",

			"DROP TABLE IF EXISTS `{pre}expresswaybill`;",
			"CREATE TABLE `{pre}expresswaybill` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `freight_type` varchar(255) NOT NULL COMMENT '货运代号',
			  `freight_name` varchar(255) NOT NULL COMMENT '货运公司名称',
			  `url` varchar(255) NOT NULL COMMENT '网址',
			  `config` text COMMENT '快递单打印配置JSON',
			  `description` varchar(255) default NULL COMMENT '描述',
			  `is_open` tinyint(1) NOT NULL default 0 COMMENT '是否开启:0关闭;1:开启;',
			  `seller_id` int(11) unsigned NOT NULL default '0' COMMENT '商家ID',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='快递单打印物流公司';",

		    "DROP TABLE IF EXISTS `{pre}goods_rate`;",
		    "CREATE TABLE `{pre}goods_rate` (
		        `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
		        `goods_rate` decimal(15,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '单品手续费',
		        PRIMARY KEY (`goods_id`)
	        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='单个商品手续费设置表';",

		    "DROP TABLE IF EXISTS `{pre}category_rate`;",
		    "CREATE TABLE `{pre}category_rate` (
		        `category_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品分类ID',
		        `category_rate` decimal(15,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '商品分类手续费',
		        PRIMARY KEY (`category_id`)
	        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商品分类手续费设置表';",

		    "DROP TABLE IF EXISTS `{pre}order_goods_servicefee`;",
		    "CREATE TABLE `{pre}order_goods_servicefee` (
              `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `order_id` int(11) UNSIGNED NOT NULL COMMENT '订单ID',
              `order_goods_id` int(11) UNSIGNED NOT NULL COMMENT '订单商品ID',
              `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '手续费类型 0:默认;1:单品;2:分类',
              `rate` decimal(15,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '手续费率',
              `discount` decimal(15,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '商户结算折扣率',
              `amount` decimal(15,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '手续费总额',
              PRIMARY KEY  (`id`),
              index (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单商品手续费表';",

		    "DROP TABLE IF EXISTS `{pre}order_code_relation`;",
		    "CREATE TABLE `{pre}order_code_relation` (
		        `id` int(11) unsigned NOT NULL auto_increment,
		        `order_id` int(11) NOT NULL COMMENT '订单ID',
		        `goods_id` int(11) NOT NULL COMMENT '商品ID',
		        `code` varchar(50) NOT NULL COMMENT '验证码字符串',
		        `seller_id` int(11) unsigned default '0' COMMENT '商家ID',
		        `user_id` int(11) NOT NULL COMMENT '用户ID',
		        `create_time` datetime DEFAULT NULL COMMENT '生成时间',
		        `use_time` datetime DEFAULT NULL COMMENT '使用时间',
		        `is_used` tinyint(1) NOT NULL default '0' COMMENT '使用状态 0未用 1已用',
		        PRIMARY KEY  (`id`),
		        index (`order_id`),
		        index (`code`)
		    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='虚拟商品到店服务验证码关系';",

		    "DROP TABLE IF EXISTS `{pre}order_download_relation`;",
		    "CREATE TABLE `{pre}order_download_relation` (
		        `id` int(11) unsigned NOT NULL auto_increment,
		        `order_id` int(11) NOT NULL COMMENT '订单ID',
		        `goods_id` int(11) NOT NULL COMMENT '商品ID',
		        `seller_id` int(11) unsigned default '0' COMMENT '商家ID',
		        `user_id` int(11) NOT NULL COMMENT '用户ID',
		        `create_time` datetime DEFAULT NULL COMMENT '生成时间',
		        `num` smallint(6) NOT NULL default '0' COMMENT '下载次数',
		        PRIMARY KEY  (`id`),
		        index (`order_id`)
		        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='虚拟商品知识付费下载关系';",

		    "DROP TABLE IF EXISTS `{pre}goods_extend_download`;",
		    "CREATE TABLE `{pre}goods_extend_download` (
		        `id` int(11) unsigned NOT NULL auto_increment,
		        `goods_id` int(11) NOT NULL COMMENT '商品ID',
		        `url` varchar(255) NOT NULL COMMENT '下载地址',
		        `seller_id` int(11) unsigned default '0' COMMENT '商家ID',
		        `end_time` date DEFAULT NULL COMMENT '截至时间',
		        `limit_num` smallint(6) DEFAULT '0' COMMENT '限制下载次数',
		        PRIMARY KEY  (`id`),
		        index (`goods_id`)
		    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='商品下载地址扩展';",

		    "INSERT INTO `{pre}expresswaybill` VALUES (NULL,'SF','顺丰速运','http://www.sf-express.com','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'FAST','快捷快递','http://www.kjkd.com/','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'ZTKY','中铁快运','http://www.cre.cn','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'YZBK','邮政国内标快','http://www.ems.com.cn','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'YZPY','邮政快递包裹','http://www.ems.com.cn','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'ZJS','宅急送','http://www.zjs.com.cn','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'UAPEX','全一快递','http://www.unitop-apex.com','','',1,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'ZTO','中通快递','http://www.zto.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\"}','需要去营业网点申请<商家ID>，<商家密码>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'STO','申通快递','http://www.sto.cn','{\"CustomerName\":\"\",\"CustomerPwd\":\"\",\"SendSite\":\"\"}','需要去营业网点申请<客户简称>，<客户密码>，<所属网点>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'DBL','德邦快递','https://www.deppon.com','{\"CustomerName\":\"\"}','需要去营业网点申请<月结编码>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'HHTT','天天快递','http://www.ttkdex.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\",\"SendSite\":\"\"}','需要去营业网点申请<客户账号>，<客户密码>，<网点名称>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'GTO','国通快递','http://www.gto365.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\",\"SendSite\":\"\"}','需要去营业网点申请<客户简称>，<客户密码>，<网点名称>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'KYSY','跨越速运','http://www.ky-express.com','{\"CustomerName\":\"\"}','需要去营业网点申请<客户号>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'YD','韵达速递','http://www.yundaex.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\"}','需要去营业网点申请<客户ID>，<接口联调密码>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'HTKY','百世快递','http://www.800bestex.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\"}','需要去营业网点申请<操作编码>，<ERP秘钥>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'YTO','圆通速递','http://www.yto.net.cn','{\"CustomerName\":\"\",\"MonthCode\":\"\"}','需要去营业网点申请<商家代码>，<密钥串>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'YCWL','远成快运','http://www.ycgky.com','{\"CustomerName\":\"\",\"SendSite\":\"\"}','需要去营业网点申请<商户代码>，<网点名称>',0,0);",
			"INSERT INTO `{pre}expresswaybill` VALUES (NULL,'UC','优速快递','http://www.uc56.com','{\"CustomerName\":\"\",\"CustomerPwd\":\"\"}','需要去营业网点申请<客户编号>，<密钥>',0,0);",

			"INSERT INTO `{pre}freight_company` VALUES (NULL,'FAST','快捷快递','http://www.kjkd.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'YZBK','邮政国内标快','http://www.ems.com.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'YZPY','邮政快递包裹','http://www.deppon.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'GTO','国通快递','http://www.gto365.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'KYSY','跨越速运','http://www.ky-express.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'HTKY','百世快递','http://www.800bestex.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'YCWL','远成快运','http://www.ycgky.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'UC','优速快递','http://www.uc56.com',0,0);",

		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]单品手续费列表','goods@goods_rate_list','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]单品手续费添加修改','goods@goods_rate_edit,goods@goods_rate_save','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]单品手续费删除','goods@goods_rate_del','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]分类手续费列表','goods@category_rate_list','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]分类手续费添加修改','goods@category_rate_edit,goods@category_rate_save','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[会员]分类手续费删除','goods@category_rate_del','0');",
		    "INSERT INTO `{pre}right`(`id`, `name`, `right`, `is_del`) VALUES (NULL,'[订单]验证消费码','order@check_code_ajax,order@get_code_info_ajax', 0);",
		);

		foreach($sql as $key => $val)
		{
		    IDBFactory::getDB()->query( $this->_c($val) );
		}

		$rightDB = new IModel('right');
		$rightDB->setData(array('right' => 'order@expresswaybill_print,order@expresswaybill_ajax,order@expresswaybill_template,order@expresswaybill_edit,order@expresswaybill_update,order@merge_template,order@pick_template,order@shop_template,order@print_template_update,order@print_template'));
		$rightDB->update('name="[订单]订单打印"');
		$rightDB->setData(array('right' => 'market@order_goods_list,market@order_goods_servicefee_list'));
		$rightDB->update('name="[统计]货款明细列表"');

		$shipDB = new IModel('merch_ship_info');
		$shipDB->setData(array('is_del' => 0));
		$shipDB->update('seller_id > 0');

		//促销活动信息同步
		$goodsDB  = new IModel('goods');

		$modelObj = new IModel('regiment');
		$items    = $modelObj->query();
		foreach($items as $key => $item)
		{
		    $goodsDB->setData(array('promo' => 'groupon','active_id' => $item['id']));
            $goodsDB->update('id = '.$item['goods_id']);
		}

		$modelObj = new IModel('promotion');
		$items    = $modelObj->query('type = 1');
		foreach($items as $key => $item)
		{
		    $goodsDB->setData(array('promo' => 'time','active_id' => $item['id']));
            $goodsDB->update('id = '.$item['condition']);
		}

		$modelObj = new IModel('cost_point');
		$items    = $modelObj->query();
		foreach($items as $key => $item)
		{
		    $goodsDB->setData(array('promo' => 'point','active_id' => $item['id']));
            $goodsDB->update('id = '.$item['goods_id']);
		}


        //清空runtime缓存
		$runtimePath = IWeb::$app->getBasePath().'runtime';
		$result      = IFile::clearDir($runtimePath);
		die("升级成功!! V5.2版本");
	}

	public function _c($sql)
	{
		return str_replace('{pre}',IWeb::$app->config['DB']['tablePre'],$sql);
	}
}