<?php
/**
 *  处理订单
 */
require_once dirname(__FILE__).'/global.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'add';

switch($action){
	case 'add':
		if(IS_POST){
			if($wap_user['uid']){
				$data_user_address['uid'] = $wap_user['uid'];
			}else{
				$data_user_address['session_id'] = session_id();
			}
			change
			$data_user_address['name'] = !empty($_POST['name']) ? $_POST['name'] : json_return(1000,'请填写名字');
			$data_user_address['tel'] = !empty($_POST['tel']) ? $_POST['tel'] : json_return(1001,'请填写联系电话');
			$data_user_address['province'] = !empty($_POST['province']) ? $_POST['province'] : json_return(1002,'请选择地区');
			$data_user_address['city'] = !empty($_POST['city']) ? $_POST['city'] : json_return(1003,'请选择地区');
			$data_user_address['area'] = !empty($_POST['area']) ? $_POST['area'] : json_return(1004,'请选择地区');
			$data_user_address['address'] = !empty($_POST['address']) ? $_POST['address'] : json_return(1005,'请填写详细地址');
			$data_user_address['zipcode'] = !empty($_POST['zipcode']) ? $_POST['zipcode'] : 0;
			$data_user_address['add_time'] = $_SERVER['REQUEST_TIME'];
			if($data_user_address['address_id'] = D('User_adasdasddress')->data($data_user_address)->add()){
				json_return(0,$data_user_address);
			}else{
				json_return(1006,'添加地址失败,请重试');
			}
		}
		break;
	case 'edit':
		if(IS_POST){
			$condition_user_address['address_id'] = $_POST['address_id'];
			if($wap_user['uid']){
				$condition_user_address['uid'] = $wap_user['uid'];
			}else{
				$condition_user_address['session_id'] = session_id();
			}
			$data_user_address['name'] = !empty($_POST['name']) ? $_POST['name'] : json_return(1000,'请填写名字');
			$data_user_address['tel'] = !empty($_POST['tel']) ? $_POST['tel'] : json_return(1001,'请填写联系电话');
			$data_user_address['province'] = !empty($_POST['province']) ? $_POST['province'] : json_return(1002,'请选择地区');
			$data_user_address['city'] = !empty($_POST['city']) ? $_POST['city'] : json_return(1003,'请选择地区');
			$data_user_address['area'] = !empty($_POST['area']) ? $_POST['area'] : json_return(1004,'请选择地区');
			$data_user_address['address'] = !empty($_POST['address']) ? $_POST['address'] : json_return(1005,'请填写详细地址');
			$data_user_address['zipcode'] = !empty($_POST['zipcode']) ? $_POST['zipcode'] : 0;
			$data_user_address['add_time'] = $_SERVER['REQUEST_TIME'];
			if(D('User_address')->where($condition_user_address)->data($data_user_address)->save()){
				$data_user_address['address_id'] = $_POST['address_id'];
				json_return(0,$data_user_address);
			}else{
				json_return(1006,'添加地址失败,请重试');
			}
		}
		break;
	case 'postage':
		if(IS_POST){
			$nowOrder = M('Order')->find($_POST['orderNo']);

			if ($_SESSION['float_postage']) {
				unset($_SESSION['float_postage']);
				json_return(0, $nowOrder['postage']);
			}
			
			$address_id = $_POST['address_id'];
			$province_id = $_POST['province_id'];
			$pay_type = $_POST['pay_type'];
			$send_other_type = $_POST['send_other_type'];
			$send_other_number = $_POST['send_other_number'];

			if (!empty($address_id)) {
				// 判断是否是送他人
				if ($pay_type == 'send_other' && $send_other_type == 2) {
					$store = M('Store')->getStore($order['store_id'], true);
					$top_store_id = $store['top_supplier_id'] ? $store['top_supplier_id'] : $store['store_id'];
					
					$nowAddress = D('Commonweal_address')->field('province')->where(array('id' => $address_id))->find();
				} else {
					$nowAddress = D('User_address')->field('`province`')->where(array('address_id' => $_POST['address_id']))->find();
				}
				if(empty($nowAddress)) json_return(1007,'该地址不存在');
			} else if (!empty($province_id)) {
				import('area');
				$areaClass = new area();
				$province_txt = $areaClass->get_name($province_id);
				if (empty($province_txt)) {
					json_return(1007,'该地址不存在');
				}
				$nowAddress['province'] = $province_id;
			} else {
				json_return(1007,'该地址不存在');
			}

			if(empty($nowOrder)) json_return(1008,'该订单不存在');
            if (empty($nowOrder['address'])) {
                //计算运费
                $postage_arr = array();
                $hasTplPostage = false;
                $order_products = array();
                $postage_details = array();
				// 送他人运费计算
				$send_other_postage_arr = array();
				
				// 当前供货商store_id
				$supplier_store_id = 0;

                // 有无运费模板
                $has_tpl_postage_arr = array();
                $hast_tpl_postage_arr = array();

                $postage_template_model = M('Postage_template');
                foreach ($nowOrder['proList'] as $key => $product) {
                    if (!empty($product['wholesale_supplier_id']) && !empty($product['wholesale_product_id'])) {
                    	// 使用供货商的运费模板、库存重量
                    	$supplier_product = D('Product')->where(array('product_id' => $product['wholesale_product_id']))->find();
                    	if (!empty($supplier_product)) {
                    		$product['postage_type'] = $supplier_product['postage_type'];
                    		$product['postage_template_id'] = $supplier_product['postage_template_id'];
                    		$product['postage'] = $supplier_product['postage'];
                    		$product['pro_weight'] = $supplier_product['weight'];
                    		
                    		if ($supplier_product['has_property'] && $product['sku_data']) {
                    			$sku_data_arr = unserialize($product['sku_data']);
                    			
                    			$properties = '';
                    			if (is_array($sku_data_arr)) {
                    				foreach ($sku_data_arr as $sku_data) {
                    					$properties .= ';' . $sku_data['pid'] . ':' . $sku_data['vid'];
                    				}
                    				$properties = trim($properties, ';');
                    			}
                    			
                    			if (!empty($properties)) {
                    				$product_sku = D('Product_sku')->where(array('product_id' => $product['wholesale_product_id'], 'properties' => $properties))->find();
                    				if (!empty($product_sku)) {
                    					$product['pro_weight'] = $product_sku['weight'];
                    				}
                    			}
                    		}
                    	}
                        $product['supplier_id'] = $product['wholesale_supplier_id'];
                    } else {
                        $product['supplier_id'] = $product['store_id'];
                        $supplier_store_id = $product['store_id'];
                    }

                    // // 店铺开启门店配送 设置运费为 0
                    // $tmp_store = M('Store')->getStore($product['supplier_id']);
                    // if ($tmp_store['open_local_logistics'] == 1) {
                    //     $hast_tpl_postage_arr[$product['supplier_id']] += 0;
                    //     continue;
                    // }

                    if ($product['postage_template_id'] && $product['postage_type'] == '1') {
                        $postage_template = $postage_template_model->get_tpl($product['postage_template_id'], $product['supplier_id']);

                        // 没有相应运费模板，直接跳出
                        if (empty($postage_template)) {
                        	continue;
                            json_return(1009, '');
                        }

                        $has_tpl = false;
                        foreach ($postage_template['area_list'] as $area) {
                            $has_tpl = false;
                            if (in_array($nowAddress['province'], explode('&', $area[0]))) {
                                if (isset($has_tpl_postage_arr[$product['supplier_id'] . '_' . $product['postage_template_id']])) {
                                    $has_tpl_postage_arr[$product['supplier_id'] . '_' . $product['postage_template_id']]['weight'] += $product['pro_num'] * $product['pro_weight'];
                                } else {
                                    $has_tpl_postage_arr[$product['supplier_id'] . '_' . $product['postage_template_id']]['weight'] = $product['pro_num'] * $product['pro_weight'];
                                    $has_tpl_postage_arr[$product['supplier_id'] . '_' . $product['postage_template_id']]['area'] = $area;
                                }

                                $has_tpl = true;
                                break;
                            }
                        }

                        // 没有相应运费模板，直接跳出
                        if (!$has_tpl) {
                            json_return(1009, '');
                        }
                    } else {
                        $hast_tpl_postage_arr[$product['supplier_id']] += $product['postage'];
                    }
					// 送他人统一运费，不考虑是否支持，不重复计数量
					$send_other_postage_arr[$product['supplier_id']] += $product['send_other_postage'];
                }
				import('source.class.Order');
				$order_data = new Order($nowOrder['proList']);
				$order_data->discount();
				$postage_free_list = $order_data->postage_free_list;

                $supplier_postage_arr = array();
                $supplier_postage_nofree_arr = array();
                $postageCount = 0;
                foreach ($has_tpl_postage_arr as $key => $postage_detail) {
                    list($supplier_id, $tpl_id) = explode('_', $key);
                    
                    $supplier_postage_nofree_arr[$supplier_id] += $postage_detail['area'][2];
                    if ($postage_detail['weight'] > $postage_detail['area']['1'] && $postage_detail['area'][3] > 0 && $postage_detail['area'][4] > 0) {
                    	$supplier_postage_nofree_arr[$supplier_id] += ceil(($postage_detail['weight'] - $postage_detail['area']['1']) / $postage_detail['area'][3]) * $postage_detail['area']['4'];
                    }
                    
                    if ($postage_free_list[$supplier_id]) {
                    	continue;
                    }
                    
                    $supplier_postage_arr[$supplier_id] += $postage_detail['area'][2];
                    $postageCount += $postage_detail['area'][2];
                    if ($postage_detail['weight'] > $postage_detail['area']['1'] && $postage_detail['area'][3] > 0 && $postage_detail['area'][4] > 0) {
                        $supplier_postage_arr[$supplier_id] += ceil(($postage_detail['weight'] - $postage_detail['area']['1']) / $postage_detail['area'][3]) * $postage_detail['area']['4'];
                        $postageCount += ceil(($postage_detail['weight'] - $postage_detail['area']['1']) / $postage_detail['area'][3]) * $postage_detail['area']['4'];
                    }
                }
				
				// 无运费模板运费计算
				foreach ($hast_tpl_postage_arr as $key => $postage) {
					$supplier_postage_nofree_arr[$key] += $postage;
					if ($postage_free_list[$key]) {
						continue;
					}
					$supplier_postage_arr[$key] += $postage;
					$postageCount += $postage;
				}
				
				// 送他人用统一运费重新计算
				if ($pay_type == 'send_other') {
					// 送他人计算邮费，重新计算
					$supplier_postage_arr = array();
					$supplier_postage_nofree_arr = array();
					$postageCount = 0;
					
					foreach ($send_other_postage_arr as $key => $postage) {
						$supplier_postage_nofree_arr[$key] += $postage;
						if ($postage_free_list[$key]) {
							continue;
						}
						
						$supplier_postage_arr[$key] += $postage * $send_other_number;
						$postageCount += $postage * $send_other_number;
					}
				}

				$fx_postage = '';
				if (!empty($supplier_postage_arr)) {
					$fx_postage = serialize($supplier_postage_arr);
				}
				
				$condition_order['order_id'] = $nowOrder['order_id'];
				$data_order['postage'] = $postageCount;
				$data_order['total'] = $nowOrder['sub_total'] + $postageCount;
				$data_order['fx_postage'] = $fx_postage;
				D('Order')->where($condition_order)->data($data_order)->save();

//				返回数据postaage_list、supllier_postage
				json_return(0,$postageCount, array('postaage_list' => serialize($supplier_postage_nofree_arr), 'supllier_postage' => $supplier_postage_nofree_arr[$supplier_store_id]));
			} else {
				json_return(1001, '刷新订单');
			}
		}
		break;
	case 'list':
		if(IS_POST){
			$userAddress = M('User_address')->select(session_id(),$wap_user['uid']);
			foreach($userAddress as $value){
				$returnAddress[$value['address_id']] = $value;
			}

//			返回数据$returnAddress
			json_return(0,$returnAddress);
		}
		break;
	case 'friend_share_postage':
			if(IS_POST){
				$nowOrder = M('Order')->find($_POST['orderNo']);
				$province_id = $_POST['province_id'];
				import('area');
				$areaClass = new area();
				$province_txt = $areaClass->get_name($province_id);
				if (empty($province_txt)) {
					json_return(1007,'该地址不存在');
				}
				
				if(empty($nowOrder)) {
					json_return(1008,'该订单不存在');
				}
				
				if ($nowOrder['status'] <= 1) {
					json_return(1000, '订单未支付,不能领取');
				}
				
				$postage_template_model = M('Postage_template');
				foreach ($nowOrder['proList'] as $key => $product) {
					if (!empty($product['wholesale_supplier_id']) && !empty($product['wholesale_product_id'])) {
						$product['supplier_id'] = $product['wholesale_supplier_id'];
					} else {
						$product['supplier_id'] = $product['store_id'];
					}
					
					if ($product['postage_template_id'] && $product['postage_type'] == '1') {
						$postage_template = $postage_template_model->get_tpl($product['postage_template_id'], $product['supplier_id']);
						
						if (empty($postage_template)) {
							json_return(1009, '');
						}
						
						$has_tpl = false;
						foreach ($postage_template['area_list'] as $area) {
							$has_tpl = false;
							if (in_array($province_id, explode('&', $area[0]))) {
								$has_tpl = true;
								break;
							}
						}
						
						if (!$has_tpl) {
							json_return(1009, '');
						}
					}
				}
				
				json_return(0, 'ok');
			}
			break;
}
?>