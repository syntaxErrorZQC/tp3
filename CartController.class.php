<?php
namespace Pc\Controller;
class CartController extends PcController {
    public function index(){
        //输出模版内容
        $this->inputContent($this->tpl_id,4);

        //购物车商品
        $Item = M('Item');$Sku = M('Sku');
        $lists = array();
        $is_discount_price_round = config('is_discount_price_round'); // 折扣价是否取整
        if(UID){
            $Cart = M('Cart');
            $where = array(
                'user_id'=>UID,
                'shop_id'=>SHOP_ID,
            );
            //会员折扣
            $discount = D('Mobile/RankPrivilege')->get_rank_discount(UID);
            $this->assign('discount',$discount['discount']/10);
            //会员等级
            $rank_id = D('Mobile/User')->getUserRank(UID);
            $join = 'right join __ITEM__ i on i.item_id = c.item_id';
            $lists = $Cart->alias('c')->join($join)->field('cart_id,c.item_id,sku_id,c.num,title,quota,original_price,price,i.status,join_level_discount')->where($where)->select();
            foreach($lists as $k=>$v){
                //会员价
                if($v['sku_id']){
                    $sku_info = D('Sku')->where('sku_id = '.$v['sku_id'])->field('price,o_price')->find();
                    $price = $sku_info['price'];
                    if($sku_info['o_price']) {
                        $lists[$k]['original_price'] = $sku_info['o_price'];
                    }
                    $lists[$k]['props'] = D('Mobile/Sku')->getSkuInfo($v['sku_id']);
                    $rank_price = D('Mobile/Sku')->getSkuRankPrice($v['sku_id'],$v['item_id'],$rank_id,UID,$v['join_level_discount']);
                    if($rank_price){
                        $price = $rank_price;
                    } elseif($v['join_level_discount']) {
                        $price = item_discount_price($price, $discount['discount'], $is_discount_price_round);
                    }
                }else{
                    $lists[$k]['sku_id'] = 0;
                    $price = $v['price'];
                    $rank_price = D('Mobile/Item')->getItemRankPrice($v['item_id'],$rank_id,UID,$v['join_level_discount']);
                    if($rank_price){
                        $price = $rank_price;
                    } elseif($v['join_level_discount']) {
                        $price = item_discount_price($price, $discount['discount'], $is_discount_price_round);
                    }
                }
                $lists[$k]['price'] = $price;
                $lists[$k]['link'] = U('Detail/index/id/'.$v['item_id']);
                $img_type = D('Item')->where('item_id = '.$v['item_id'])->getField('type');
                $img = D('Mobile/File')->get_small_img($v['item_id'],$v['sku_id']);
                $lists[$k]['pic'] = $img['img_path'];
                $lists[$k]['is_compress'] = $img['is_compress'];
                $lists[$k]['total_price'] = $price*$v['num'];
                if($v['join_level_discount'] && !$rank_price){
                    $lists[$k]['discount'] = $discount['discount'];
                    $lists[$k]['dis'] = $discount['discount']/10;
                }else{
                    $lists[$k]['dis'] = 1;
                }
                if ($v['status'] != 1) {
                    $Cart->where('cart_id = '.$v['cart_id'])->delete();
                    $failed_lists[] = $lists[$k];
                    unset($lists[$k]);
                }
            }
        }else{
            $arr = unserialize(stripslashes(cookie('shop_cart_info')));
            foreach($arr as $k=>$v){
                $arr_where = array(
                    'item_id'=>$v['item_id'],
                    'shop_id'=>SHOP_ID,
                );
                $item = $Item->where($arr_where)->field('title,price,type,status')->find();
                if($item){
                    if($v['sku_id']){
                        $price = $Sku->where('sku_id = '.$v['sku_id'])->getField('price');
                        $lists[$k]['props'] = D('Mobile/Sku')->getSkuInfo($v['sku_id']);
                    }else{
                        $lists[$k]['sku_id'] = 0;
                        $price = $item['price'];
                    }
                    isset($v['sku_id'])&&$v['sku_id']>0?$v['sku_id']:0;
                    $lists[$k]['item_id'] = $v['item_id'];
                    $lists[$k]['num'] = $v['num'];
                    $lists[$k]['title'] = $item['title'];
                    $lists[$k]['sku_id'] = $v['sku_id'];
                    $lists[$k]['price'] = $price;
                    $lists[$k]['link'] = U('Detail/index/id/'.$v['item_id']);
                    $img = D('Mobile/File')->get_small_img($v['item_id'],$v['sku_id']);
                    $lists[$k]['pic'] = $img['img_path'];
                    $lists[$k]['is_compress'] = $img['is_compress'];
                    $lists[$k]['total_price'] = $price*$v['num'];
                    $lists[$k]['quota'] = $Item->where('item_id = '.$v['item_id'])->getField('quota');
                    $lists[$k]['dis'] =1;
                    if ($item['status'] != 1) {
                        $failed_lists[] = $lists[$k];
                        unset($lists[$k]);
                    }
                }
            }
            cookie("shop_cart_info",null);
            cookie("shop_cart_info",serialize($lists),24*3600);
        }
       $this->assign('lists',$lists);

        //是否显示原价
        $is_display_original_price = config('is_display_original_price');
        $is_display_original_price = is_null($is_display_original_price) ? 1 : $is_display_original_price;
        $this->assign('is_display_original_price', $is_display_original_price);
        //猜你喜欢
        $like_list = D('Item')->getItemShow('is_recommend desc ',4);
        $this->assign('like_list',$like_list);
        $this->page_title = '购物车页';
        //是否开启扫码支付
        $this->is_wx_pay = $this->shop_info['is_wx_pay'];
        //是否开启支付宝支付
        $this->is_alipay = $this->shop_info['is_alipay'];

        $this->display();
    }

    /**
     * 删除购物车商品
     * @author zc
     */
    public function delCart(){
        if(IS_AJAX){
            $id = intval(I('id'));
            if(!$id){
                $this->ajaxReturn(array('status'=>0,'msg'=>'参数错误'));
            }
            if(UID){
                $where['cart_id'] = $id;
                $rs = D('Cart')->where($where)->delete();
                if($rs){
                    $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
                }else{
                    $this->ajaxReturn(array('status'=>0,'msg'=>'操作失败'));
                }
            }else{
                $sku_id = intval(I('sku_id'));
                $expire = 24*3600;
                $lists = unserialize(stripslashes(cookie('shop_cart_info')));
                foreach($lists as $k=>$v){
                    if($v['item_id'] == $id){
                        if($v['sku_id']){
                            if($v['sku_id'] == $sku_id){
                                unset($lists[$k]);
                            }
                        }else{
                            unset($lists[$k]);
                        }
                    }
                }
                cookie("shop_cart_info",null);
                cookie("shop_cart_info",serialize($lists),$expire);
                $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
            }
        }
    }

    /**
     * 删除购物车商品
     * @author zc
     */
    public function delAllCart(){
        if(IS_AJAX){
            $ids = I('ids');
            if(empty($ids)){
                $this->ajaxReturn(array('status'=>0,'msg'=>'参数错误'));
            }
            if(UID){
                $where['cart_id'] = array('in',$ids);
                $rs = D('Cart')->where($where)->delete();
                if($rs){
                    $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
                }else{
                    $this->ajaxReturn(array('status'=>0,'msg'=>'操作失败'));
                }
            }else{
                $sku_ids = I('sku_ids');
                $expire = 24*3600;
                $lists = unserialize(stripslashes(cookie('shop_cart_info')));
                foreach($lists as $k=>$v){
                    if(in_array($v['item_id'],$ids)){
                        if($v['sku_id']){
                            if(in_array($v['sku_id'],$sku_ids)){
                                unset($lists[$k]);
                            }
                        }else{
                            unset($lists[$k]);
                        }
                    }
                }
                cookie("shop_cart_info",null);
                cookie("shop_cart_info",serialize($lists),$expire);
                $this->ajaxReturn(array('status'=>1,'msg'=>'操作成功'));
            }
        }
    }

    /**
     * 购物车结算二维码
     * @author zc
     */
    public function cartBuy(){
        $ids = I('ids');
        $nums = I('post.nums');
        $sku_ids = I('post.sku_ids');
        if(empty($ids)){
            $this->ajaxReturn(array('status'=>0,'msg'=>'请选择商品!'));
        }
        foreach($ids as $k=>$id){
            $result = D('Item')->checkShopItem($id);
            if(!$result){
                $this->ajaxReturn(array('status'=>0,'msg'=>'非法访问!'));
            }
            if($nums[$k] <1){
                $this->ajaxReturn(array('status'=>0,'msg'=>'选择商品库存不能小于0!'));
            }
        }
        if(UID){
            //店铺二维码链接
            $link = urlencode(preview_url(U('Item/addPcCart')));
            $url = '/Modules/qrcode?link='.$link;
            $this->ajaxReturn(array('status'=>1,'url'=>$url));
        }else{
            $arr = array(
                'item_ids'=>$ids,
                'nums'=>$nums,
                'sku_ids'=>$sku_ids
            );
            $serialize_arr = serialize($arr);
            $data= array('content'=>$serialize_arr);
            $id = M('PcCart')->add($data);
            if($id){
                //店铺二维码链接
                $link = urlencode(preview_url(U('Item/addPcCart/id/'.$id)));
                $url = '/Modules/qrcode?link='.$link;
                $this->ajaxReturn(array('status'=>1,'url'=>$url));
            }else{
                $this->ajaxReturn(array('status'=>0,'msg'=>''));
            }
        }



    }
}