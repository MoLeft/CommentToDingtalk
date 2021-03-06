<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
/**
 * Typecho评论推送到钉钉插件
 *
 * @package CommentToDingtalk
 * @author MoLeft
 * @version 1.0
 * @link http://www.moleft.cn/
 */
class CommentToDingtalk_Plugin implements Typecho_Plugin_Interface
{
    /* 激活插件方法 */
    public static function activate()
    {
        //挂载评论接口
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('CommentToDingtalk_Plugin', 'send');

        return '插件已激活,请设置相关信息';
    }

    /* 禁用插件方法 */
    public static function deactivate()
    {
        return '插件已禁用';
    }

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $webhook = new Typecho_Widget_Helper_Form_Element_Text('webhook', null, '', 'Webhook地址', '请将钉钉中的webhook地址填到此处');
        $secret  = new Typecho_Widget_Helper_Form_Element_Text('secret', null, '', 'Secret密钥', '请将钉钉中的Secret密钥填到此处');
        $form->addInput($webhook);
        $form->addInput($secret);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {

    }

    /* 插件实现方法 */
    public static function render()
    {

    }

    /* 推送通知方法 */
    public static function send($post)
    {
        //获取系统配置
        $options = Helper::options();
        //判断是否配置webhook地址
        if (is_null($options->plugin('CommentToDingtalk')->webhook)) {
            throw new Typecho_Plugin_Exception(_t('Webhook地址未配置'));
        }
        //判断是否配置secret密钥
        if (is_null($options->plugin('CommentToDingtalk')->secret)) {
            throw new Typecho_Plugin_Exception(_t('Secret密钥未配置'));
        }
        $webhook = $options->plugin('CommentToDingtalk')->webhook;
        $secret  = $options->plugin('CommentToDingtalk')->secret;
        list($msec, $sec) = explode(' ', microtime());
        $timestamp        = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $stringToSign = $timestamp."\n".$secret;
        $sign             = urlencode(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)));
        $webhook_url      = "{$webhook}&timestamp={$timestamp}&sign={$sign}";
		$text = "![screenshot](https://cdn.jsdelivr.net/gh/Bhaoo/Cuckoo@1.0.1/assets/images/loading.gif)\n
### {$options->title}\n
文章标题：{$post->title}\n
评论作者：{$post->author}\n
评论内容：{$post->text}";
        $data = [
            'msgtype' => 'actionCard',
            'actionCard' => [
                'title' => '您有一条新评论',
                'text' => $text,
                'btnOrientation' => 1,
                'hideAvatar' => 0,
                'singleTitle' => '查看详情',
                'singleURL' =>$post->permalink
            ]
        ];
        $response = self::request($webhook_url, json_encode($data));
        if($response['errcode'] !== 0){
            //发送失败，记录日志
            $log = @file_get_contents('./error.log');
            file_put_contents('./error.log','['.date("Y-m-d H:i:s").']'.$response['errmsg']);
        }
    }

    /* Curl请求精简版 */
    private static function request($url, $postData)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data, true);
    }

}
