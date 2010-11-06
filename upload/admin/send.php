<?php
/**
 * ECSHOP 快钱联合注册接口
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liuhui $
 * $Id: send.php 15013 2008-10-23 09:31:42Z liuhui $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

//商户密钥
$key='LHLEF8EA4ZY853NF';

//接口版本，不可空
//固定值：150120
$version='150120';

//编码字符串格式
//固定值：1、2、3
//1代表UTF-8;2代表GBK；3代表GB2312
$inputCharset='3';

//签名类型，不可空
//固定值：1，1代表MD5加密
$signType='1';

//商户在快钱的会员编号，不可空
$merchantMbrCode='10017518267';

//申请编号，不可空
//只允许是字母、数字、“_”等，以字母或数字开头
$requestId=date('YmdHis');


//注册类型，不可空
//固定值：1、2
//1代表新注册用户；2代表重复注册用户
$registerType='1';

//用户在商户系统的ID，不可空
//只允许是字母、数字、“_”等，以字母或数字开头
$userId=date('YmdHis');

//用户类型，不可空
//固定值：1、2
//1代表个人；2代表企业
$userType='1';

//用户的EMAIL
$userEmail='payment@shopex.cn';

//用户的手机
$userMobile='';

//用户的姓名
//中文或英文
$userName='';

//联系人
//中文或英文
$linkMan='';

//联系电话
//手机或固定电话
$linkTel='';

//单位名称
//中文或英文
$orgName='';

//网站地址
$websiteAddr='';

//商户接收返回页面的地址，不可空
//商户服务器接收快钱返回结果的后台地址
//快钱通过服务器连接的方式将交易结果参数传递给商户提供的这个url，商户处理后输出接收结果和返回页面地址
$backUrl=$ecs->url() . ADMIN_PATH . '/receive.php';
//扩展参数一
//中文或英文
$ext1='';

//扩展参数二
//中文或英文
$ext2='';

//功能函数。将变量值不为空的参数组成字符串
Function appendParam($returnStr,$paramId,$paramValue){
    if($returnStr!=""){
        if($paramValue!=""){
            $returnStr.="&".$paramId."=".$paramValue;
        }
    }else{
        If($paramValue!=""){
            $returnStr=$paramId."=".$paramValue;
        }
    }
    return $returnStr;
}
//功能函数。将变量值不为空的参数组成字符串。结束

//生成加密签名串
///请务必按照如下顺序和规则组成加密串！
$signMsgVal="";
$signMsgVal=appendParam($signMsgVal,"version",$version);
$signMsgVal=appendParam($signMsgVal,"inputCharset",$inputCharset);
$signMsgVal=appendParam($signMsgVal,"signType",$signType);
$signMsgVal=appendParam($signMsgVal,"merchantMbrCode",$merchantMbrCode);
$signMsgVal=appendParam($signMsgVal,"requestId",$requestId);
$signMsgVal=appendParam($signMsgVal,"registerType",$registerType);
$signMsgVal=appendParam($signMsgVal,"userId",$userId);
$signMsgVal=appendParam($signMsgVal,"userType",$userType);
$signMsgVal=appendParam($signMsgVal,"userEmail",$userEmail);
$signMsgVal=appendParam($signMsgVal,"userMobile",$userMobile);
$signMsgVal=appendParam($signMsgVal,"userName",$userName);
$signMsgVal=appendParam($signMsgVal,"linkMan",$linkMan);
$signMsgVal=appendParam($signMsgVal,"linkTel",$linkTel);
$signMsgVal=appendParam($signMsgVal,"orgName",$orgName);
$signMsgVal=appendParam($signMsgVal,"websiteAddr",$websiteAddr);
$signMsgVal=appendParam($signMsgVal,"backUrl",$backUrl);
$signMsgVal=appendParam($signMsgVal,"ext1",$ext1);
$signMsgVal=appendParam($signMsgVal,"ext2",$ext2);
$signMsgVal=appendParam($signMsgVal,"key",$key);
//echo $signMsgVal;exit;
$signMsg=strtoupper(md5($signMsgVal));

header("location:https://www.99bill.com/website/signup/memberunitedsignup.htm?version=".$version."&inputCharset=".$inputCharset."&signType=".$signType."&merchantMbrCode=".$merchantMbrCode."&requestId=".$requestId."&registerType=".$registerType."&userId=".$userId."&userType=".$userType."&userEmail=".$userEmail."&userMobile=".$userMobile."&userName=".$userName."&linkMan=".$linkMan."&linkTel=".$linkTel."&orgName=".$orgName."&websiteAddr=".$websiteAddr."&backUrl=".$backUrl."&ext1=".$ext1."&ext2=".$ext2."&signMsg=".$signMsg);

?>
