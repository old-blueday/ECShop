<?php

/**
 * ECSHOP 报价单
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liuhui $
 * $Id: quotation.php 17063 2010-03-25 06:35:46Z liuhui $
*/
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

$action  = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'default';
if ($action == 'print_quotation')
{
    $smarty->template_dir = DATA_DIR;
    $smarty->assign('shop_name', $_CFG['shop_title']);
    $smarty->assign('cfg',       $_CFG);
    $where = get_quotation_where($_POST);
    $sql = "SELECT g.goods_id, g.goods_name, g.shop_price, g.goods_number, c.cat_name AS goods_category".
    " FROM " . $ecs->table('goods') . " AS g LEFT JOIN " . $ecs->table('category') . " AS c ON g.cat_id = c.cat_id " . $where . " AND is_on_sale = 1 AND is_alone_sale = 1";
    $goods_list = $db->getAll($sql);
    $user_rank = $db->getAll("SELECT * FROM " .$ecs->table('user_rank') . "WHERE show_price = 1 OR rank_id = '$_SESSION[user_rank]'");
    $rank_point = 0;
    if (!empty($_SESSION['user_id']))
    {
        $rank_point = $db->getOne("SELECT rank_points FROM " . $ecs->table('users') . " WHERE user_id = '$_SESSION[user_id]'");
    }
    $user_rank = calc_user_rank($user_rank, $rank_point);
    $user_men = serve_user($goods_list);
    $smarty->assign('extend_price', $user_rank['ext_price']);
    $smarty->assign('extend_rank', $user_men);
    $smarty->assign('goods_list', $goods_list);

    $html = $smarty->fetch('quotation_print.html');
    exit($html);
}

assign_template();

$position = assign_ur_here(0, $_LANG['quotation']);
$smarty->assign('page_title', $position['title']);   // 页面标题
$smarty->assign('ur_here',    $position['ur_here']); // 当前位置

$smarty->assign('cat_list', cat_list());
$smarty->assign('brand_list',   get_brand_list());

if (is_null($smarty->get_template_vars('helps')))
{
    $smarty->assign('helps', get_shop_help()); // 网店帮助
}

$smarty->display('quotation.dwt');

function get_quotation_where($filter)
{
    include_once(ROOT_PATH . ADMIN_PATH . '/includes/lib_main.php');
    $_filter = new StdClass();
    $_filter->cat_id = $filter['cat_id'];
    $_filter->brand_id = $filter['brand_id'];
    $_filter->keyword = $filter['keyword'];
    $where = get_where_sql($_filter);
    return $where;
}

function calc_user_rank($rank, $rank_point)
{
    $_tmprank = array();
    foreach($rank as $_rank)
    {
        if ($_rank['show_price'])
        {
            $_tmprank['ext_price'][] = $_rank['rank_name'];
            $_tmprank['ext_rank'][] = $_rank['discount'];
        }
        else
        {
            if (!empty($_SESSION['user_id']) && ($rank_point >= $_rank['min_points']))
            {
                $_tmprank['ext_price'][] = $_rank['rank_name'];
                $_tmprank['ext_rank'][] = $_rank['discount'];
            }
        }
    }
    return $_tmprank;
}

function serve_user($goods_list)
{
    foreach ( $goods_list as $all_list )
    {
        $goods_id = $all_list['goods_id'];
        $price = $all_list['shop_price'];
        $sql = "SELECT rank_id, IFNULL(mp.user_price, r.discount * $price / 100) AS price, r.rank_name, r.discount " .
                'FROM ' . $GLOBALS['ecs']->table('user_rank') . ' AS r ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . " AS mp ".
                "ON mp.goods_id = '$goods_id' AND mp.user_rank = r.rank_id " .
                "WHERE r.show_price = 1 OR r.rank_id = '$_SESSION[user_rank]'";
        $res = $GLOBALS['db']->getAll($sql);

        foreach ( $res as $row )
        {
            $arr[$row['rank_id']] = array(
                      'rank_name' => htmlspecialchars($row['rank_name']),
                      'price'     => price_format($row['price']));

        }
        $arr_list[$goods_id] = $arr;
    }
    return $arr_list;
}
?>