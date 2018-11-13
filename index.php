<?php
/**
 * 安装登录
 */
session_start();
$siteTitle='蓝悉科技'; // 站点标题
$siteSubtitle='选品管理系统'; // 站点副标题
$dbname = md5($siteTitle.$siteSubtitle);
if(!file_exists('DATA/'.$dbname)){
    // 启动安装程序
    set_time_limit(0);
    if(!is_dir('DATA')) mkdir('DATA');
    if(!is_dir('全部文件')) mkdir('全部文件');
	if(!extension_loaded('sqlite3')) die("检查php.in文件是否支持sqlite3数据库！");
    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	$db->exec("begin exclusive transaction");
    // 创建user表 user_username,user_nicename,user_password,user_email,user_logintime,user_auth,user_status
    $db->exec("CREATE TABLE \"hl_user\" (
  		\"user_username\"  varchar(255),  /*用户名*/
  		\"user_nicename\"  varchar(255),  /*昵称*/
  		\"user_password\"  varchar(255),  /*密码*/
  		\"user_email\"     varchar(255),  /*邮箱*/
  		\"user_logintime\" INTEGER,       /*最后登录时间*/
  		\"user_auth\"      varchar(255),  /*权限*/
  		\"user_status\"    varchar(255))  /*状态*/
	");
    $db->exec("CREATE UNIQUE INDEX user_username on hl_user (user_username)");
    $db->exec("CREATE INDEX user_logintime on hl_user (user_logintime DESC)");
    // 写入超级管理员
    $sql="INSERT INTO hl_user (\"user_username\", \"user_nicename\", \"user_password\", \"user_email\", \"user_logintime\", \"user_auth\", \"user_status\") VALUES ('zhuhailong','".$db->escapeString('朱海龙')."','".md5('123456'.$siteTitle)."','sbmboy@gmail.com',NULL,9,'enable')";
    $db->exec($sql);
	
    // 创建分类管理表 info_title,info_path,info_father,info_posttime,info_filetype,info_tag,info_filesize,info_status,info_author
    $db->exec("CREATE TABLE \"hl_info\" (
  		\"info_title\"    varchar,  /*名称*/
        \"info_path\"     varchar,  /*保存路径*/
        \"info_father\"   INTEGER,  /*父级id*/
  		\"info_posttime\" INTEGER,  /*创建时间*/
  		\"info_filetype\" varchar,  /*类型*/
        \"info_tag\"      varchar,  /*标签*/
        \"info_filesize\" INTEGER,  /*文件大小*/
        \"info_status\"   varchar,  /*状态：trash,publish*/
        \"info_author\"   varchar)  /*创建者*/
    "); 
    $db->exec("CREATE UNIQUE INDEX info_info_path on hl_info (info_path)");
    $db->exec("CREATE INDEX info_info_father on hl_info (info_father)");
    $db->exec("CREATE INDEX info_info_status on hl_info (info_status)");
    $db->exec("CREATE INDEX info_info_posttime on hl_info (info_posttime DESC)");
    $db->exec("CREATE INDEX info_info_filesize on hl_info (info_filesize)");
    // 写入根目录
    $sql="INSERT INTO hl_info (\"info_title\", \"info_path\", \"info_father\", \"info_posttime\", \"info_filetype\", \"info_tag\", \"info_filesize\", \"info_status\", \"info_author\") VALUES ('".$db->escapeString('全部文件')."','全部文件',0,".time().",'category',NULL,0,'publish','系统')";
    $db->exec($sql);


    // 创建index表 index_title,index_ids,index_counts
    $db->exec("CREATE TABLE \"hl_index\" (
        \"index_title\"  varchar,  /*标签*/
        \"index_ids\"    TEXT,     /*对应的id*/      
        \"index_counts\" INTEGER)  /*数量*/
    ");
    $db->exec("CREATE UNIQUE INDEX index_index_title on hl_index (index_title)");
    $db->exec("CREATE INDEX index_index_counts on hl_index (index_counts DESC)");

    // 关闭数据库
	$db->exec("end transaction");
    $db->close();
	header("Content-type:text/html;charset=utf-8");
	//echo '<script>alert("安装成功！");window.location.href="/index.php";</script>';
}
// 登录
if(isset($_POST['login'])){
    if(!empty($_POST['username'])&&!empty($_POST['password'])){
		$db=new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
		$sql="SELECT rowid,* FROM `hl_user` WHERE user_username = '{$_POST['username']}' AND user_password = '".md5($_POST['password'].$siteTitle)."'";
		$result=$db->query($sql);
		$row=$result->fetchArray(SQLITE3_ASSOC);
		if($row){
			if($row['user_status']=='disable'){
                $result->finalize();
                unset($result);
                $db->close();
                header("Content-type:text/html;charset=utf-8");
                echo '<script type="text/javascript">alert("对不起 '.$row['user_username'].'，你的账户已经被禁用，请联系管理员。");window.location.href="/index.php";</script>';
                exit;
            }else{
                $_SESSION['loginStatus']=array(
                    'username' => $row['user_username'],
                    'email' => $row['user_email'],
                    'nickname' => $row['user_nicename'],
                    'auth' => $row['user_auth'],
                    'status' =>true,
                    'loginTime' => time(),
                );
                $sql="UPDATE \"hl_user\" SET \"user_logintime\" = '".time()."' WHERE \"user_username\" = '{$row['user_username']}'";
                $db->exec($sql) or print($sql);
                $result->finalize();
                $db->close();
                unset($result);
                unset($row);
                header('Location:/show.php');
                exit;
            }
		}else{//若查到的记录不对，则设置错误信息
            header("Content-type:text/html;charset=utf-8");
            echo '<script type="text/javascript">alert("你的用户名或者密码错误！");window.location.href="/index.php";</script>';
            exit;
        }
    }else{
		header("Content-type:text/html;charset=utf-8");
        echo '<script type="text/javascript">alert("请输入用户名和密码！");window.location.href="index.php";</script>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>蓝悉网盘，让美好永远陪伴</title>
    <link href="/static/images/favicon.ico" rel="shortcut icon" type="images/x-icon" />
    <link href="/static/css/login-all-min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="/static/css/login.css" type="text/css" />
</head>

<body>
    <div id="login-container">
        <div class="ibg-bg index-banner-0"></div>
        <div class="header-container">
            <div id="login-header">
                <div class="logo">
                    <a class="yun-logo" href="/" target="_blank" title="蓝悉网盘">蓝悉网盘</a>
                    <a class="pan-logo" href="/" title="网盘">网盘</a>
                </div>

            </div>
        </div>
        <div class="login-main">
            <div class="all-index-banner">
                <div class="index-body-content">
                    <p><span class="left-quote"></span><span>安全存储 在线预览</span></p>
                    <p><span>文件即开即看 工作井井有条</span><span class="right-quote"></span></p>
                </div>
            </div>
            <div class="login-sdk-v4">
                <div class="header-login">
                    <div class="tang-pass-login" style="display: block; visibility: visible; opacity: 1;">
                        <form action="./index.php" class="pass-form pass-form-normal" method="POST">
                            <p class="pass-form-logo">帐号密码登录</p>
                            <p class="pass-generalErrorWrapper"><span class="pass-generalError pass-generalError-error"></span></p>
                            <p class="pass-form-item pass-form-item-userName">
                            <input type="text" name="username" class="pass-text-input pass-text-input-userName" 
                            placeholder="手机/邮箱/用户名"></p>
                            <p class="pass-form-item pass-form-item-password">
                            <input type="password" name="password" class="pass-text-input pass-text-input-password"
                                    placeholder="密码"></p>
                            <p class="pass-form-item pass-form-item-memberPass"><input type="checkbox"
                                    class="pass-checkbox-input pass-checkbox-memberPass" checked="checked"><label for="TANGRAM__PSP_4__memberPass"
                                    class="">下次自动登录</label></p>
                            <p class="pass-foreignMobile-link-wrapper pass-foreignMobile-link">海外手机号</a></p>
                            <p class="pass-foreignMobile-back-wrapper pass-foreignMobile-link">帐号密码登录</a></p>
                            <p class="pass-form-item pass-form-item-submit">
                                <input type="submit" value="登录" class="pass-button pass-button-submit">
                                <a class="pass-fgtpwd pass-link" href="javascript:void(0)" target="_blank">忘记密码？</a>
                                <a class="pass-sms-btn pass-link" title="短信快捷登录">短信快捷登录&gt;</a></p>
                            <input type="hidden" value="true" name="login">
                        </form>
                    </div>


                    <div class="tang-pass-footerBar">
                        <p class="tang-pass-footerBarQrcode pass-link" title="扫一扫登录">扫一扫登录</p>
                        <div class="tang-pass-footerBarPhoenix"><span class="tang-pass-footerBarPhoenixSplit"></span>
                            <div class="tang-pass-footerBarPhoenixItem">
                                <div id="pass-phoenix-login" class="tang-pass-login-phoenix">
                                    <div id="pass-phoenix-list-login" class="pass-phoenix-list clearfix">
                                        <div class="pass-phoenix-btn clearfix">
                                            <ul class="bd-acc-list">
                                                <li class="bd-acc-tsina"><a class="phoenix-btn-item" href="#"
                                                        data-title="tsina" title="新浪微博">新浪微博</a></li>
                                                <li class="bd-acc-qzone"><a class="phoenix-btn-item" href="#"
                                                        data-title="qzone" title="QQ帐号">QQ帐号</a></li>
                                                <li class="bd-acc-weixin"><a class="phoenix-btn-item" href="#"
                                                        data-title="weixin" title="微信">微信</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div><a class="pass-reglink pass-link" target="_blank">立即注册</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="login-download">
        <ul class="tab-download clearfix" id="tab-download">
            <li><a class="windows download-target" href="#">Windows</a></li>
            <li><a class="android" href="#">Android</a></li>
            <li><a class="iphone" href="#">iPhone</a></li>
            <li><a class="ipad download-open" href="#">iPad</a></li>
            <li><a class="wp wphone  download-open hidden" href="#">WP</a></li>
            <li><a class="tongbupan download-target" href="#">MAC</a></li>
        </ul>
    </div>
    <div class="footer">
        <div xmlns="http://www.w3.org/1999/xhtml">©2018 Lanthy <a class="b-lnk-gy" href="/" target="_blank">移动开放平台</a>
            | <a class="b-lnk-gy" href="/disk/duty/" target="_blank">服务协议</a> | <a class="b-lnk-gy" href="#" target="_blank">权利声明</a>
            | <a class="b-lnk-gy" href="#" target="_blank">版本更新</a> | <a class="b-lnk-gy" href="#" target="_blank">帮助中心</a>
            | <a class="b-lnk-gy" href="#" target="_blank">版权投诉</a></div>
    </div>
    <script src="/static/js/login-all-min.js?t=20140427000" type="text/javascript"></script>
</body>

</html>