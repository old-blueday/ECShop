<?php

/**
 * ECSHOP 短信模块 之 模型（类库）
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: yehuaixiao $
 * $Id: cls_sms.php 17155 2010-05-06 06:29:05Z yehuaixiao $
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

require_once(ROOT_PATH . 'includes/cls_transport.php');
require_once(ROOT_PATH . 'includes/shopex_json.php');

/* 短信模块主类 */
class sms
{
    /**
     * 存放提供远程服务的URL。
     *
     * @access  private
     * @var     array       $api_urls
     */
    var $api_urls   = array('register'          =>      'http://sms.ecshop.com/register.php',
                            'auth'              =>      'http://sms.ecshop.com/user_auth.php',
                            'send'              =>      'http://idx.sms.shopex.cn/service.php ',
                            'charge'            =>      'http://sms.ecshop.com/charge.php?act=charge_form',
                            'balance'           =>      'http://sms.ecshop.com/get_balance.php',
                            'send_history'      =>      'http://sms.ecshop.com/send_history.php',
                            'charge_history'    =>      'http://sms.ecshop.com/charge_history.php');
    /**
     * 存放MYSQL对象
     *
     * @access  private
     * @var     object      $db
     */
    var $db         = null;

    /**
     * 存放ECS对象
     *
     * @access  private
     * @var     object      $ecs
     */
    var $ecs        = null;

    /**
     * 存放transport对象
     *
     * @access  private
     * @var     object      $t
     */
    var $t          = null;

    /**
     * 存放程序执行过程中的错误信息，这样做的一个好处是：程序可以支持多语言。
     * 程序在执行相关的操作时，error_no值将被改变，可能被赋为空或大等0的数字.
     * 为空或0表示动作成功；大于0的数字表示动作失败，该数字代表错误号。
     *
     * @access  public
     * @var     array       $errors
     */
    var $errors  = array('api_errors'       => array('error_no' => -1, 'error_msg' => ''),
                         'server_errors'    => array('error_no' => -1, 'error_msg' => ''));

    /**
     * 构造函数
     *
     * @access  public
     * @return  void
     */
    function __construct()
    {
        $this->sms();
    }

    /**
     * 构造函数
     *
     * @access  public
     * @return  void
     */
    function sms()
    {
        /* 由于要包含init.php，所以这两个对象一定是存在的，因此直接赋值 */
        $this->db = $GLOBALS['db'];
        $this->ecs = $GLOBALS['ecs'];

        /* 此处最好不要从$GLOBALS数组里引用，防止出错 */
        $this->t = new transport(-1, -1, -1, false);
    }

    /**
     * 检测是否已注册或启用短信服务
     *
     * @access  public
     * @return  boolean     已注册或启用短信服务返回true，否则返回false。
     */
    function has_registered()
    {
        $sql = 'SELECT `value`
                FROM ' . $this->ecs->table('shop_config') . "
                WHERE `code` = 'sms_shop_mobile'";

        $result = $this->db->getOne($sql);

        if (empty($result))
        {
            return false;
        }

        return true;
    }

    /**
     * 返回指定键名的URL
     *
     * @access  public
     * @param   string      $key        URL的名字，即数组的键名
     * @return  string or boolean       如果由形参指定的键名对应的URL值存在就返回该URL，否则返回false。
     */
    function get_url($key)
    {
        $url = $this->api_urls[$key];

        if (empty($url))
        {
            return false;
        }

        return $url;
    }

    /**
     * 获得短信特服信息
     *
     * @access  public
     * @return  1-dimensional-array or boolean      成功返回短信特服信息，否则返回false。
     */
    function get_my_info()
    {
        $sql = 'SELECT `code`, `value`
                FROM ' . $this->ecs->table('shop_config') . "
                WHERE `code` LIKE '%sms\_%'";
        $result = $this->db->query($sql);

        $retval = array();
        if (!empty($result))
        {
            while ($temp_arr = $this->db->fetchRow($result))
            {
                $retval[$temp_arr['code']] = $temp_arr['value'];
            }

            return $retval;
        }

        return false;
    }

    /**
     * 获得当前处于会话状态的管理员的邮箱
     *
     * @access  private
     * @return  string or boolean       成功返回管理员的邮箱，否则返回false。
     */
    function get_admin_email()
    {
        $sql = 'SELECT `email` FROM ' . $this->ecs->table('admin_user') . " WHERE `user_id` = '" . $_SESSION['admin_id'] . "'";
         $email = $this->db->getOne($sql);

         if (empty($email))
         {
            return false;
         }

         return $email;
    }

    /**
     * 取出管理员的邮箱及网店域名
     *
     * @access  public
     * @return  void
     */
    function get_site_info()
    {
        /* 获得当前处于会话状态的管理员的邮箱 */
        $email = $this->get_admin_email();
        $email = $email ? $email : '';
        /* 获得当前网店的域名 */
        $domain = $this->ecs->get_domain();
        $domain = $domain ? $domain : '';

        /* 赋给smarty模板 */
        $sms_site_info['email'] = $email;
        $sms_site_info['domain'] = $domain;

        return $sms_site_info;
    }

    /**
     * 注册短信服务
     *
     * @access  public
     * @param   string      $email          帐户信息
     * @param   string      $password       密码，未经MD5加密
     * @param   string      $domain         域名
     * @param   string      $phone          商家注册时绑定的手机号码
     * @return  boolean                     注册成功返回true，失败返回false。
     */
    function register($email, $password, $domain, $phone)
    {
        /* 检查注册信息 */
        if (!$this->check_register_info($email, $password, $domain, $phone))
        {
            $this->errors['server_errors']['error_no'] = 1;//注册信息无效

            return false;
        }

        /* 获取API URL */
        $url = $this->get_url('register');
        if (!$url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }

        $params = array('email' => $email,
                        'password' => $password,
                        'domain' => $domain);
        /* 发送HTTP请求 */
        $response = $this->t->request($url, $params);
        $http_body = $response['body'];
        if (!$response || !$http_body)
        {
            $this->errors['server_errors']['error_no'] = 7;//HTTP响应体为空

            return false;
        }

        /* 更新最后访问API的时间 */
        $this->update_sms_last_request();

        /* 解析XML文本串 */
        $xmlarr = $this->xml2array($http_body);
        if (empty($xmlarr))
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        $elems = &$xmlarr[0]['elements'];
        $count = count($elems);//如果data没有子元素，$count等于0
        if ($count === 0)
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        /* 提取信息 */
        $result = array();
        for ($i = 0; $i < $count; $i++)
        {
            $node_name = trim($elems[$i]['name']);
            switch ($node_name)
            {
                case 'user_name' :
                    $result['user_name'] = $elems[$i]['text'];
                    break;
                case 'password' :
                    $result['password'] = $elems[$i]['text'];
                    break;
                case 'auth_str' :
                    $result['auth_str'] = @$elems[$i]['text'];
                    break;
                case 'sms_num' :
                    $result['sms_num'] = @$elems[$i]['text'];
                    break;
                case 'error' :
                    $this->errors['api_errors']['error_no'] = @$elems[$i]['elements'][0]['text'];
                    break;
//                default :
//                    $this->errors['server_errors']['error_no'] = 9;//无效的节点名字
//
//                    return false;
            }
        }

        /* 如果API出错了 */
        if (intval($this->errors['api_errors']['error_no']) !== 0)
        {
            return false;
        }

        $my_info = array('sms_user_name' => $result['user_name'],
                        'sms_password' => $result['password'],
                        'sms_auth_str' => $result['auth_str'],
                        'sms_domain' => $domain,
                        'sms_count' => 0,
                        'sms_total_money' => 0,
                        'sms_balance' => 0,
                        'sms_last_request' => gmtime());

        /* 存储短信特服信息 */
        if (!$this->store_my_info($my_info))
        {
            $this->errors['server_errors']['error_no'] = 10;//存储失败

            return false;
        }

        return true;
    }

    /**
     * 重新存储短信特服信息
     *
     * @access  public
     * @param   string      $username       用户名
     * @param   string      $password       密码，已经用MD5加密
     * @return  boolean                     重新存储成功返回true，失败返回false。
     */
    function restore($username,  $password)
    {
        /* 检查启用服务时用户信息的合法性 */
        if (!$this->check_enable_info($username, $password))
        {
            $this->errors['server_errors']['error_no'] = 2;//启用信息无效

            return false;
        }

        /* 获取API URL */
        $url = $this->get_url('auth');
        if (!$url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }

        $params = array('username' => $username,
                        'password' => $password);

        /* 发送HTTP请求 */
        $response = $this->t->request($url, $params);
        $http_body = $response['body'];
        if (!$response || !$http_body)
        {
            $this->errors['server_errors']['error_no'] = 7;//HTTP响应体为空

            return false;
        }

        /* 更新最后请求的时间 */
        $this->update_sms_last_request();

        /* 解析XML文本串 */
        $xmlarr = $this->xml2array($http_body);
        if (empty($xmlarr))
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        $elems = &$xmlarr[0]['elements'];
        $count = count($elems);
        if ($count === 0)
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        /* 提取信息 */
        $result = array();
        for ($i = 0; $i < $count; $i++)
        {
            $node_name = trim($elems[$i]['name']);
            switch ($node_name)
            {
                case 'user_name' :
                    $result['user_name'] = $elems[$i]['text'];
                    break;
                case 'password' :
                    $result['password'] = $elems[$i]['text'];
                    break;
                case 'auth_str' :
                    $result['auth_str'] = @$elems[$i]['text'];
                    break;
                case 'domain' :
                    $result['domain'] = @$elems[$i]['text'];
                    break;
                case 'count' :
                    $result['count'] = empty($elems[$i]['text']) ? 0 : $elems[$i]['text'];
                    break;
                case 'total_money' :
                    $result['total_money'] = empty($elems[$i]['text']) ? 0 : $elems[$i]['text'];
                    break;
                case 'balance' :
                    $result['balance'] = empty($elems[$i]['text']) ? 0 : $elems[$i]['text'];
                    break;
                case 'error' :
                    $this->errors['api_errors']['error_no'] = @$elems[$i]['elements'][0]['text'];
                    break;
                default :
                    $this->errors['server_errors']['error_no'] = 9;//无效的节点名字

                    return false;
            }
        }

        /* 如果API出错了 */
        if (intval($this->errors['api_errors']['error_no']) !== 0)
        {
            return false;
        }

        $my_info = array('sms_user_name' => $result['user_name'],
                    'sms_password' => $result['password'],
                    'sms_auth_str' => $result['auth_str'],
                    'sms_domain' => $result['domain'],
                    'sms_count' => $result['count'],
                    'sms_total_money' => $result['total_money'],
                    'sms_balance' => $result['balance'],
                    'sms_last_request' => gmtime());

        /* 存储短信特服信息 */
        if (!$this->store_my_info($my_info))
        {
            $this->errors['server_errors']['error_no'] = 10;//存储失败

            return false;
        }

        return true;
    }

    /**
     * 清除短信特服信息
     *
     * @access  public
     * @return  boolean     清除成功返回true，失败返回false。
     */
    function clear_my_info()
    {
        $my_info = array('sms_user_name' => '',
            'sms_password' => '',
            'sms_auth_str' => '',
            'sms_domain' => '',
            'sms_count' => '',
            'sms_total_money' => '',
            'sms_balance' => '',
            'sms_last_request' => '');

        return $this->store_my_info($my_info);
    }

    /**
     * 发送短消息
     *
     * @access  public
     * @param   string  $phone          要发送到哪些个手机号码，多个号码用半角逗号隔开
     * @param   string  $msg            发送的消息内容
     * @param   string  $send_date      定时发送时间
     * @return  boolean                 发送成功返回true，失败返回false。
     */
    function send($phone, $msg, $send_date = '', $send_num = 1)
    {
        /* 检查发送信息的合法性 */
        if (!$this->check_send_sms($phone, $msg, $send_date))
        {
            $this->errors['server_errors']['error_no'] = 3;//发送的信息有误

            return false;
        }

        /* 获取身份验证信息 */
        $login_info = $this->get_login_info();
        if (!$login_info)
        {
            $this->errors['server_errors']['error_no'] = 5;//无效的身份信息

            return false;
        }

        /* 获取API URL */
        $url = $this->get_url('send');

        if (!$url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }

        $version = $GLOBALS['_CFG']['ecs_version'];
        $submit_str['certi_id'] = $GLOBALS['_CFG']['certificate_id'];
        $submit_str['ac'] = md5($GLOBALS['_CFG']['certificate_id'].$GLOBALS['_CFG']['token']);
        $submit_str['version']=$version;
        
        /* 发送HTTP请求 */
        $response = $this->t->request($url, $submit_str);
        $result = explode('|',$response['body']);

        if($result[0] == '0')
        {
            $sms_url = $result[1];
        }
        if($result[0] == '1')
        {
            $sms_url = '';
        }
        if($result[0] == '2'){
            $sms_url = '';
        }
        if (EC_CHARSET != 'utf-8')
        {
        $send_arr =    Array(
            0 => Array(
                    0 => $phone,    //发送的手机号码
                    1 => iconv('gb2312','utf-8',$msg),      //发送信息
                    2 => 'Now' //发送的时间
                )
        );
        }
        else
        {
            $send_arr =    Array(
            0 => Array(
                    0 => $phone,    //发送的手机号码
                    1 => $msg,      //发送信息
                    2 => 'Now' //发送的时间
                )
        );
        }
        $send_str['certi_id'] = $GLOBALS['_CFG']['certificate_id'];
        $send_str['ex_type'] = $send_num;
        $send_str['content'] = json_encode($send_arr);
        $send_str['encoding'] = 'utf8';
        $send_str['version'] = $version;
        $send_str['ac'] = md5($send_str['certi_id'].$send_str['ex_type'].$send_str['content'].$send_str['encoding'].$GLOBALS['_CFG']['token']);
        
        if (!$sms_url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }
        
        /* 发送HTTP请求 */
        $response = $this->t->request($sms_url, $send_str);

        $result = explode('|' ,$response['body']);

        if($result[0] == 'true')
        {
            //发送成功
            return true;
        }
        elseif($result[0] == 'false')
        {
            //发送失败
            return false;
        }
        
        
    }

    /**
     * 获取XML格式的消息发送历史记录
     *
     * @access  public
     * @param   string  $start_date     开始日期
     * @param   string  $end_date       结束日期
     * @param   string  $page_size      每页显示多少条记录，默认为20
     * @param   string  $page           显示多少页，默认为1页
     * @return  string or boolean       查询成功返回XML格式的文本串，失败返回false。
     */
    function get_send_history_by_xml($start_date, $end_date, $page_size = 20, $page = 1)
    {
        /* 检查查询条件 */
        if (!$this->check_history_query($start_date, $end_date, $page_size, $page))
        {
            $this->errors['server_errors']['error_no'] = 4;//填写的查询信息有误

            return false;
        }

        /* 获取身份验证信息 */
        $login_info = $this->get_login_info();
        if (!$login_info)
        {
            $this->errors['server_errors']['error_no'] = 5;//无效的身份信息

            return false;
        }

        /* 获取API URL */
        $url = $this->get_url('send_history');
        if (!$url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }

        $params = array('login_info' => $login_info,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'page_size' => $page_size,
                        'page' => $page);

        /* 发送HTTP请求 */
        $response = $this->t->request($url, $params);
        $http_body = $response['body'];
        if (!$response || !$http_body)
        {
            $this->errors['server_errors']['error_no'] = 7;//HTTP响应体为空

            return false;
        }

        /* 更新最后请求API的时间 */
        $this->update_sms_last_request();

        return $http_body;//返回xml文本串
    }

    /**
     * 获取解析后的消息发送历史记录
     *
     * @access  public
     * @param   string  $start_date                 开始日期
     * @param   string  $end_date                   结束日期
     * @param   string  $page_size                  每页显示多少条记录，默认为20
     * @param   string  $page                       显示多少页，默认为1页
     * @return  1-dimensional-array or boolean      查询成功返回历史记录数组，失败返回false。
     */
    function get_send_history($start_date, $end_date, $page_size = 20, $page = 1)
    {
        /* 获取XML文本串 */
        $xml = $this->get_send_history_by_xml($start_date, $end_date, $page_size, $page);
        if (!$xml)
        {
            return false;
        }

        /* 解析XML文本串 */
        $xmlarr = $this->xml2array($xml);
        if (empty($xmlarr))
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        $result = array();

        $attrs = &$xmlarr[0]['attributes'];
        $result['count'] = $attrs['count'];

        $elems = &$xmlarr[0]['elements'];
        $count = count($elems);
        if ($count === 0)
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        /* 提取信息 */
        $send_num = $count - 1;
        for ($i = 0; $i < $send_num; $i++)
        {
            if (empty($elems[$i]['attributes']))//属性为空则跳过
            {
                continue;
            }
            $result['send'][$i]['phone'] = $elems[$i]['attributes']['phone'];
            $result['send'][$i]['content'] = $elems[$i]['attributes']['content'];
            $result['send'][$i]['charge_num'] = $elems[$i]['attributes']['charge_num'];
            $result['send'][$i]['send_date'] = $elems[$i]['attributes']['send_date'];
            $result['send'][$i]['send_status'] = $elems[$i]['attributes']['send_status'];
        }
        $this->errors['api_errors']['error_no'] = @$elems[$send_num]['elements'][0]['text'];

        /* 如果API出错了 */
        if (intval($this->errors['api_errors']['error_no']) !== 0)
        {
            return false;
        }

        return $result;
    }

    /**
     * 获取XML格式的充值历史记录
     *
     * @access  public
     * @param   string  $start_date     开始日期
     * @param   string  $end_date       结束日期
     * @param   string  $page_size      每页显示多少条记录，默认为20
     * @param   string  $page           显示多少页，默认为1页
     * @return  string or boolean       查询成功返回XML格式的文本串，失败返回false。
     */
    function get_charge_history_by_xml($start_date, $end_date, $page_size = 20, $page = 1)
    {
        /* 检查查询条件的合法性 */
        if (!$this->check_history_query($start_date, $end_date, $page_size, $page))
        {
            $this->errors['server_errors']['error_no'] = 4;//填写的查询信息有误

            return false;
        }

        /* 获取身份验证信息 */
        $login_info = $this->get_login_info();
        if (!$login_info)
        {
            $this->errors['server_errors']['error_no'] = 5;//无效的身份信息

            return false;
        }

        $params = array('login_info' => $login_info,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'page_size' => $page_size,
                        'page' => $page);

        /* 获取API URL */
        $url = $this->get_url('charge_history');
        if (!$url)
        {
            $this->errors['server_errors']['error_no'] = 6;//URL不对

            return false;
        }

        /* 发送HTTP请求 */
        $response = $this->t->request($url, $params);
        $http_body = $response['body'];
        if (!$response || !$http_body)
        {
            $this->errors['server_errors']['error_no'] = 7;//HTTP响应体为空

            return false;
        }

        /* 更新最后请求API的时间 */
        $this->update_sms_last_request();

        return $http_body;//返回xml文本串
    }

    /**
     * 获取解析后的充值历史记录
     *
     * @access  public
     * @param   string  $start_date                 开始日期
     * @param   string  $end_date                   结束日期
     * @param   string  $page_size                  每页显示多少条记录，默认为20
     * @param   string  $page                       显示多少页，默认为1页
     * @return  1-dimensional-array or boolean      查询成功返回历史记录数组，失败返回false。
     */
    function get_charge_history($start_date, $end_date, $page_size, $page)
    {
        /* 获取XML文本串 */
        $xml = $this->get_charge_history_by_xml($start_date, $end_date, $page_size, $page);
        if (!$xml)
        {
            return false;
        }

        /* 解析XML文本串 */
        $xmlarr = $this->xml2array($xml);
        if (empty($xmlarr))
        {
            $this->errors['server_errors']['error_no'] = 8;//无效的XML文件

            return false;
        }

        $result = array();

        $attrs = &$xmlarr[0]['attributes'];
        $result['count'] = $attrs['count'];

        $elems = &$xmlarr[0]['elements'];
        $count = count($elems);
        $charge_num = $count - 1;//数组的前N-1个元素存放充值记录，最后一个元素存放错误信息
        /* 提取信息 */
        for ($i = 0; $i < $charge_num; $i++)
        {
            if (empty($elems[$i]['attributes']))
            {
                continue;
            }
            $result['charge'][$i]['order_id'] = $elems[$i]['attributes']['order_id'];
            $result['charge'][$i]['money'] = $elems[$i]['attributes']['money'];
            $result['charge'][$i]['log_date'] = $elems[$i]['attributes']['log_date'];
        }

        $this->errors['api_errors']['error_no'] = @$elems[$charge_num]['elements'][0]['text'];

        if (intval($this->errors['api_errors']['error_no']) !== 0)
        {
            return false;
        }

        return $result;
    }

    /**
     * 检测用户注册信息是否合法
     *
     * @access  private
     * @param   string      $email          邮箱，充当短信用户的用户名
     * @param   string      $password       密码
     * @param   string      $domain         网店域名
     * @param   string      $phone          商家绑定的手机号码
     * @return  boolean                     如果注册信息格式合法返回true，否则返回false。
     */
    function check_register_info($email, $password, $domain, $phone)
    {
        /*
         * 远程API会做相应的过滤处理，但如果有一值为空，API会直接退出，
         * 这不利于我们进一步处理，
         * 因此此处仅需简单地判断这三个值是否为空。
         * 以下凡是涉及到远程API已有相应处理措施的代码，一律只进行简单地判空检测。
         */
        if (empty($email) || empty($password) || empty($domain))
        {
            return false;
        }

        if (!empty($phone))
        {
            if (preg_match('/^\d+$/', $phone))
            {
                $sql = 'UPDATE ' . $this->ecs->table('shop_config') . "
                        SET `value` = '$phone'
                        WHERE `code` =  'sms_shop_mobile'";
                $this->db->query($sql);
            }
            else
            {
                return false;
            }
        }

        return true;
    }

    /**
     * 存储短信特服信息
     *
     * @access  private
     * @param   1-dimensional-array     $my_info    短信特服信息数组
     * @return  boolean                             存储成功返回true，失败返回false。
     */
    function store_my_info($my_info)
    {
        /* 形参如果不是数组，返回false */
        if (!is_array($my_info))
        {
            return false;
        }

        foreach ($my_info AS $key => $value)
        {
            $sql = 'UPDATE ' . $this->ecs->table('shop_config') . " SET `value` = '$value' WHERE `code` = '$key'";
            $result = $this->db->query($sql);

            if (empty($result))
            {
                return false;
            }
        }

        return true;
    }

    /**
     * 更新数据库中的最后请求记录
     *
     * @access  private
     * @return  boolean             更新成功返回true，失败返回false。
     */
    function update_sms_last_request()
    {
        $sql = 'UPDATE ' . $this->ecs->table('shop_config') . " SET `value` = '" . gmtime() . "' WHERE `code` = 'sms_last_request'";
        $result = $this->db->query($sql);

        if (empty($result))
        {
            return false;
        }

        return true;
    }

    /**
     * 检测启用短信服务需要的信息
     *
     * @access  private
     * @param   string      $email          邮箱
     * @param   string      $password       密码
     * @return  boolean                     如果启用信息格式合法就返回true，否则返回false。
     */
    function check_enable_info($email, $password)
    {
        if (empty($email) || empty($password))
        {
            return false;
        }

        return true;
    }

    /**
     * 检测发送的短消息格式是否合法
     *
     * @access  private
     * @param   string      $phone          发送到哪些个电话号码
     * @param   string      $msg            消息内容
     * @param   string      $send_date      定时发送时间
     * @return  boolean                     短消息格式合法返回true，否则返回false。
     */
    function check_send_sms($phone, $msg, $send_date)
    {
        if (empty($phone) || empty($msg))
        {
            return false;
        }

        if (!empty($send_date) && $this->check_date_format($send_date))
        {
            return false;
        }

        return true;
    }

    /**
     * 获得用于验证身份的信息
     *
     * @access  private
     * @return  string or boolean   成功返回用于登录短信服务的帐号信息，失败返回false。
     */
    function get_login_info()
    {
        $sql = 'SELECT `code`, `value` FROM ' . $this->ecs->table('shop_config') . " WHERE `code` = 'sms_user_name' OR `code` = 'sms_password'";
        $result = $this->db->query($sql);

        $retval = array();
        if (!empty($result))
        {
            while ($temp_arr = $this->db->fetchRow($result))
            {
                $retval[$temp_arr['code']] = $temp_arr['value'];
            }

            return base64_encode($retval['sms_user_name'] . "\t" . $retval['sms_password']);
        }

        return false;
    }

    /**
     * 检测用于查询历史记录条件的格式是否合法
     *
     * @access  private
     * @param   string      $start_date         开始日期，可为空
     * @param   string      $end_date           结束日期，可为空
     * @param   string      $page_size          每页显示数量，默认为20
     * @param   string      $page               页数，默认为1
     * @return  boolean                         查询条件格式合法就返回true，否则返回false。
     */
    function check_history_query($start_date, $end_date, $page_size =  20, $page = 1)
    {
        /* 检查日期格式 */
        if (!empty($start_date) && !$this->check_date_format($start_date))
        {
            return false;
        }
        if (!empty($end_date) && !$this->check_date_format($end_date))
        {
            return false;
        }

        /* 检查数字参数 */
        if (!is_numeric($page_size) || !is_numeric($page))
        {
            return false;
        }

        return true;
    }

    /**
     * 日期的格式是否符合远程API所要求的格式
     *
     * @access  private
     * @param   1-dimensional-array or string       $date           日期
     * @return  boolean                                             格式合法就返回true，否则返回false。
     */
    function check_date_format($date)
    {
        $pattern = '/\d{4}-\d{2}-\d{2}/';
        if (is_array($date))
        {
            foreach ($date AS $value)
            {
                if (!preg_match($pattern, $value))
                {
                    return false;
                }
            }
        }
        elseif (!preg_match($pattern, $date))
        {
            return false;
        }

        return true;
    }

    /**
     * 把XML串转换成PHP关联数组
     *
     * @access  private
     * @param   string      $xml    XML串
     * @author  www.google.com
     *
     * @return  array       PHP关联数组
     */
    function xml2array($xml)
    {
        $xmlary = array();

        $reels = '/<(\w+)\s*([^\/>]*)\s*(?:\/>|>(.*)<\/\s*\1\s*>)/s';
        $reattrs = '/(\w+)=(?:"|\')([^"\']*)(:?"|\')/';

        preg_match_all($reels, $xml, $elements);

        foreach ($elements[1] AS $ie => $xx)
        {
            $xmlary[$ie]['name'] = $elements[1][$ie];

            if ($attributes = trim($elements[2][$ie]))
            {
                preg_match_all($reattrs, $attributes, $att);
                foreach ($att[1] AS $ia => $xx)
                {
                    $xmlary[$ie]['attributes'][$att[1][$ia]] = $att[2][$ia];
                }
            }

            $cdend = strpos($elements[3][$ie], '<');
            if ($cdend > 0)
            {
                $xmlary[$ie]['text'] = substr($elements[3][$ie], 0, $cdend - 1);
            }

            if (preg_match($reels, $elements[3][$ie]))
            {
                $xmlary[$ie]['elements'] = $this->xml2array($elements[3][$ie]);
            }
            elseif ($elements[3][$ie])
            {
                $xmlary[$ie]['text'] = $elements[3][$ie];
            }
        }

        //如果找不到任何匹配，则返回空数组
        return $xmlary;
    }
}

?>