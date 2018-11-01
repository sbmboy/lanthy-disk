<?php
// 导入配置文件
require_once 'config.php';
/**
 * show
 */
if(isset($_GET['id'])&&$_GET['id']>0){
    $id=$_GET['id'];
    $trash=false;
    // 获取列表，默认列表
    $lists=$lanthy->getCategories($id);
    $totalSize = $lanthy->getFileSize(1);
    
    if(isset($_GET['action'])){
        $action=$_GET['action'];
        switch ($action) {
            case 'logout':
                // 退出
                if(isset($_SESSION['loginStatus'])){
                    $_SESSION = array();
                    unset($_SESSION);
                    if(isset($_COOKIE[session_name()])){
                        setcookie(session_name(),'',time()-3600);
                    }
                    session_destroy();
                }
                header('Location:/index.php');
                break;
            case 'adduser':
                // 添加新用户
                if(isset($_POST['user_username'])&&$_POST['user_username']!=''){
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    // 写入
                    $sql="INSERT INTO hl_user (\"user_username\", \"user_nicename\", \"user_password\", \"user_email\", \"user_logintime\", \"user_auth\", \"user_status\")  VALUES ('".$db->escapeString($_POST['user_username'])."','".$db->escapeString($_POST['user_nicename'])."','".md5('123456'.$siteTitle)."','".$db->escapeString($_POST['user_email'])."',NULL,".intval($_POST['user_auth']).",'enable')";
                    $db->exec($sql);
                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                    header("location:/show.php?id=".intval($_GET['id']));
                }
                break;
            case 'user':
                // 用户列表
                if(isset($_GET['rowid'])&&$_GET['rowid']>0&&$_SESSION['loginStatus']['auth']>5){
                    switch ($_GET['status']) {
                        case 'enable':
                            // 启用
                            $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                            $db->exec("begin exclusive transaction");
                            // 写入
                            $sql = "UPDATE hl_user SET user_status = 'enable' WHERE rowid=".intval($_GET['rowid']);
                            $db->exec($sql);
                            // 关闭数据库
                            $db->exec("end transaction");
                            $db->close();
                            break;

                        case 'disable':
                            // 禁用
                            $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                            $db->exec("begin exclusive transaction");
                            // 写入
                            $sql = "UPDATE hl_user SET user_status = 'disable' WHERE rowid=".intval($_GET['rowid']);
                            $db->exec($sql);
                            // 关闭数据库
                            $db->exec("end transaction");
                            $db->close();
                            break;

                        case 'delete':
                            // 删除
                            $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                            $db->exec("begin exclusive transaction");
                            // 写入
                            $sql = "DELETE FROM hl_user WHERE rowid=".intval($_GET['rowid']);
                            $db->exec($sql);
                            // 关闭数据库
                            $db->exec("end transaction");
                            $db->close();
                            break;
                        
                        default:
                            # code...
                            break;
                    }
                }
                $users = $lanthy->getUsers($id);
                break;
            case 'filetype':
                //显示回收站
                $lists = $lanthy->getFileByType($_GET['filetype']);
                break;
            case 'priview':
                $image=false;
                // 预览
                if(isset($_GET['rowid'])&&$_GET['rowid']>0){
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    // 先获取文件相关信息
                    $sql = "SELECT info_title,info_path,info_filetype,info_filesize FROM hl_info WHERE rowid=".intval($_GET['rowid']);
                    $view = $db->querySingle($sql,true);
                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                }
                break;
            case 'password':
                //修改用户密码
                if(isset($_POST['password'])&&$_POST['password']!=''){
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    $password = md5($_POST['password'].$siteTitle);
                    // 更新密码
                    $sql = "UPDATE hl_user SET user_password='{$password}' WHERE user_username='{$_SESSION['loginStatus']['username']}'";
                    $db->exec($sql);
                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                    header("location:/show.php?id=".intval($_GET['id']));
                }
                break;
            case 'download':
                // 下载
                $info=array();
                $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                $db->exec("begin exclusive transaction");
                // 先获取文件相关信息
                $sql = "SELECT info_title,info_path,info_filetype,info_filesize FROM hl_info WHERE rowid=".intval($_GET['rowid']);
                $info = $db->querySingle($sql,true);
                // 关闭数据库
                $db->exec("end transaction");
                $db->close();
                // 下载资源
                downloadFile($info);
                header("location:/show.php?id=".intval($_GET['id']));
                break;
            case 'rename':
                // 重命名
                // var_dump($_POST);
                // exit;
                if(isset($_POST['info_title'])&&$_POST['info_title']!=''){
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    // 先获取原文件路径
                    $sql = "SELECT rowid,info_title,info_path FROM hl_info WHERE rowid=".intval($_GET['rowid']);
                    $info = $db->querySingle($sql,true);
                    
                    // 如果是文件，好办，修改文件路径,如果是目录，还需要修改目录里面的文件路径
                    if(is_dir($info['info_path'])){
                        // 替换成新路径
                        $info_path_new = str_replace('/'.base64_encode($info['info_title']),'/'.base64_encode($_POST['info_title']),$info['info_path']);
                        
                        if(!is_dir($info_path_new)){
                            if(rename($info['info_path'],$info_path_new)){
                                // 更新数据库
                                $sql = "UPDATE hl_info SET info_title='".$db->escapeString($_POST['info_title'])."',info_path='".$db->escapeString($info_path_new)."' WHERE rowid=".intval($_GET['rowid']);
                                $db->exec($sql);
                                // 更新子文件
                                $sql = "UPDATE hl_info SET info_path = REPLACE(info_path, '/".base64_encode($info['info_title'])."', '/".base64_encode($_POST['info_title'])."') WHERE info_father =".$info['rowid'];
                                $db->exec($sql);
                            }
                        }
                    }else{
                        // 替换成新路径
                        $info_path_new = str_replace('/'.base64_encode($info['info_title']),'/'.base64_encode($_POST['info_title']),$info['info_path']);
                        if(!file_exists($info_path_new)){
                            if(rename($info['info_path'],$info_path_new)){
                                // 更新数据库
                                $sql = "UPDATE hl_info SET info_title='".$db->escapeString($_POST['info_title'])."',info_path='".$db->escapeString($info_path_new)."' WHERE rowid=".intval($_GET['rowid']);
                                $db->exec($sql);
                            }
                        }

                    }

                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                    header("location:/show.php?id=".intval($_GET['id']));
                }
                break;
            case 'publish':
                // 还原文件
                $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                $db->exec("begin exclusive transaction");
                // 还原目录
                $sql = "UPDATE hl_info SET info_status='publish' WHERE rowid=".intval($_GET['rowid']); // 更新根目录大小
                $db->exec($sql);
                // 还原目录下的文件和文件夹
                $sql = "UPDATE hl_info SET info_status='publish' WHERE info_father=".intval($_GET['rowid']); // 更新根目录大小
                $db->exec($sql);

                // 关闭数据库
                $db->exec("end transaction");
                $db->close();
                header("location:/show.php?action=trash&id=".intval($_GET['id']));
                break;
            case 'trash':
                //显示回收站
                $lists = $lanthy->getTrash($id);
                $trash=true;
                break;
            case 'delete':
                // 删除,只是到回收站
                $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                $db->exec("begin exclusive transaction");
                // 删除该目录
                $sql = "UPDATE hl_info SET info_status='trash' WHERE rowid=".intval($_GET['rowid']); // 更新根目录大小
                $db->exec($sql);
                // 还需要删除该目录下面的文件和文件夹
                $sql = "UPDATE hl_info SET info_status='trash' WHERE info_father=".intval($_GET['rowid']); // 更新根目录大小
                $db->exec($sql);
                // 关闭数据库
                $db->exec("end transaction");
                $db->close();
                header("location:/show.php?id=".intval($_GET['id']));
                break;
            case 'search':
                $lists = $lanthy->getSearch($_GET['q'],$_GET['id']);
                break;
            case 'create': 
                // 创建
                if(isset($_POST['info_title'])&&$_POST['info_title']!=''){
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    // 获取父路径
                    $sql = "SELECT info_path FROM hl_info WHERE rowid=".intval($id);
                    $father_path = $db->querySingle($sql);
                    if(!is_dir($father_path.'/'.base64_encode($_POST['info_title']))){
                        $check = mkdir($father_path.'/'.base64_encode($_POST['info_title']));
                        if($check){
                            // 获取product_tag
                            preg_match_all('/./u', $_POST['info_title'], $info_tag);
                            $info_tag = implode(",",$info_tag[0]);
                            //写入数据库
                            $sql="INSERT INTO hl_info (\"info_title\", \"info_path\", \"info_father\", \"info_posttime\", \"info_filetype\", \"info_tag\", \"info_filesize\", \"info_status\", \"info_author\") VALUES ('".$db->escapeString($_POST['info_title'])."','".$father_path.'/'.base64_encode($_POST['info_title'])."',".intval($_GET['id']).",".time().",'category','".$db->escapeString($info_tag)."',0,'publish','".$_SESSION['loginStatus']['nickname']."')";
                            $db->exec($sql);
                            // 更新索引
                            updateIndex();
                        }
                    }
                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                    header("location:/show.php?id=".intval($_GET['id']));
                }
                break;

            case 'upload':
                // 上传
                if(isset($_FILES["files"]["tmp_name"])&&!empty($_FILES["files"]["tmp_name"][0])){
                    $totalsize = 0;
                    $db = new SQLite3("DATA/{$dbname}",SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                    $db->exec("begin exclusive transaction");
                    // 先获取分类的path
                    $sql = "SELECT info_path FROM hl_info WHERE rowid=".intval($id);
                    $dir_path = $db->querySingle($sql);
                    // 更新写入数据库
                    for($i=0;$i<count($_FILES["files"]["type"]);$i++){
                        // 保存文件
                        $info_title = $_FILES["files"]["name"][$i];
                        $info_path = $dir_path.'/'.base64_encode($info_title);
                        // 获取product_tag
                        preg_match_all('/./u', $info_title, $info_tag);
                        $info_tag = implode(",",$info_tag[0]);
                        // 检测文件是否已经存在
                        while(file_exists("FILES/".$file_path)){
                            $info_title = str_replace('.','-2.',$info_title);
                            $info_path = $dir_path.'/'.$info_title;
                        }
                        $info_type = $_FILES["files"]["type"][$i];
                        // $file_type = explode("/",$_FILES["files"]["type"][$i]);
                        // $info_type = $file_type[1];

                        $info_size = $_FILES["files"]["size"][$i];
                        $totalsize += $info_size;

                        // 上传文件
                        $check = move_uploaded_file($_FILES["files"]["tmp_name"][$i], $info_path);
                        if($check){
                            //写入数据库
                            $sql="INSERT INTO hl_info (\"info_title\", \"info_path\", \"info_father\", \"info_posttime\", \"info_filetype\", \"info_tag\", \"info_filesize\", \"info_status\", \"info_author\") VALUES ('".$db->escapeString($info_title)."','".$db->escapeString($info_path)."',".intval($_GET['id']).",".time().",'".$db->escapeString($info_type)."','".$db->escapeString($info_tag)."',".intval($info_size).",'publish','".$_SESSION['loginStatus']['nickname']."')";
                            $db->exec($sql);   
                        }
                    }

                    // 更新文件大小数据
                    $sql = "UPDATE hl_info SET info_filesize=info_filesize+{$totalsize} WHERE rowid=1"; // 更新根目录大小
                    $db->exec($sql);

                    // 更新索引
                    updateIndex();

                    // 关闭数据库
                    $db->exec("end transaction");
                    $db->close();
                    header("location:/show.php?id=".intval($_GET['id']));

                }
                break;
            
            default:
                
                break;
        }
    }else{
        $action=false;
    }

}else{
    header("location:/show.php?id=1");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?=$siteSubtitle.'|'.$siteTitle?></title>
    <link rel="stylesheet" type="text/css" href="/box-static/disk-system/css/alpha.css?t=1540867395556">
    <link rel="stylesheet" type="text/css" href="/static/css/show.css">
    <link rel="stylesheet" type="text/css" href="/box-static/base/css/function.css?t=1537274867313">
    <link rel="stylesheet" type="text/css" href="/box-static/system-core/pkg/context-all_dc9ada7.css">
    <link rel="stylesheet" type="text/css" href="/box-static/disk-system/pkg/home-all_7429cab.css">
    <link rel="stylesheet" type="text/css" href="/box-static/disk-system/pkg/all_c0ed5fb.css">
    <link rel="stylesheet" type="text/css" href="/box-static/service-widget-1/pkg/main-all_1804208.css">
    <link rel="stylesheet" type="text/css" href="/box-static/function-widget-1/pkg/sync-all_e4b9d15.css">
    <link rel="stylesheet" type="text/css" href="/box-static/interface-widget-1/pkg/asideAppDownloads-all_65e681f.css">
    <link rel="stylesheet" type="text/css" href="/box-static/interface-widget-1/pkg/sync-all_6df50b9.css">
    <link rel="stylesheet" type="text/css" href="/box-static/interface-widget-1/mainSmallBanner/util/activity_26dfb4a.css">
    <link rel="stylesheet" type="text/css" href="/box-static/interface-widget-1/pkg/quota-all_328fb3b.css">
    <link rel="stylesheet" type="text/css" href="/box-static/interface-widget-1/pkg/vipWarn-all_2f77222.css">
    <link rel="stylesheet" type="text/css" href="/box-static/disk-system/pkg/uploader-all_3c6fc77.css">
    <link rel="stylesheet" type="text/css" href="/box-static/system-core/system/uiService/rMenu/rMenu_6fb4554.css">
    <link rel="stylesheet" type="text/css" href="/static/css/show2.css">
    <link rel="stylesheet" type="text/css" href="/box-static/disk-header/disk.header.css?t=1540277078037">
    <style type="text/css">
        #content_left {
            min-width: 580px;
        }

        .c-container {
            min-width: 580px;
        }
    </style>
    <link rel="stylesheet" type="text/css" href="/box-static/function-widget-1/pkg/device-all_e04414c.css">
    <link rel="stylesheet" type="text/css" href="/box-static/disk-system/css/cover.css?t=1540867395556">
    <link rel="stylesheet" node-type="theme-link" type="text/css" href="/box-static/disk-theme/theme/white/diskSystem-theme.css">
</head>

<body>
    <div class="frame-all" id="layoutApp">
        <div class="skin-main"></div>
        <div class="DFoOVG2" node-type="DFoOVG2">
            <div class="kkKUtT2" node-type="kkKUtT2">
                <div class="frame-aside" id="layoutAside">
                    <div class="34diFD" node-type="34diFD">
                        <div class="ZAdPjhI" node-type="ZAdPjhI">
                            <div node-type="wbc09vA" class="module-aside DtJtsC">
                                <div class="KHbQCub"></div>
                                <!-- 侧边栏 -->
                                <ul class="fOHAbxb">
                                    <li class="ti0GxG<?php if($_GET['filetype']=='') echo ' bHzsaPb';?>">
                                        <a href="show.php?id=1" class="lxkvNArM amOb89">
                                            <span class="text">
                                                <span class="icon icon-disk"></span>
                                                <span>全部文件</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="ti0GxG<?php if($_GET['filetype']=='image') echo ' bHzsaPb';?>">
                                        <a class="amOb89" href="show.php?id=1&action=filetype&filetype=image">
                                            <span class="text">
                                                <span>图片</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="ti0GxG<?php if($_GET['filetype']=='document') echo ' bHzsaPb';?>">
                                        <a class="amOb89" href="show.php?id=1&action=filetype&filetype=document">
                                            <span class="text">
                                                <span>文档</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li class="ti0GxG<?php if($_GET['filetype']=='video') echo ' bHzsaPb';?>">
                                        <a class="amOb89" href="show.php?id=1&action=filetype&filetype=video">
                                            <span class="text">
                                                <span>视频</span>
                                            </span>
                                        </a>
                                    </li>

                                    <!-- <li class="ti0GxG<?php if($_GET['filetype']=='other') echo ' bHzsaPb';?>">
                                        <a class="amOb89" href="show.php?id=1&action=filetype&filetype=">
                                            <span class="text">
                                                <span>其它</span>
                                            </span>
                                        </a>
                                    </li> -->
                                </ul>
                                <!-- 侧边栏 -->

                                <!-- 回收站 -->
                                <ul class="JKEQDvb">
                                    <div class="elObjO">
                                        <a class="g-button" href="show.php?id=1&action=trash" title="回收站">
                                            <span class="g-button-right">
                                                <em class="icon icon-delete" title="回收站"></em>
                                                <span class="text" style="width: auto;">回收站</span>
                                            </span>
                                        </a>
                                    </div>
                                </ul>
                                <!-- // 回收站 -->

                                <div class="aside-absolute-container">
                                    <div style="width: 100%; height: 50px; background:none; z-index: 3; ">
                                        <div node-type="dkJ46A" class="QGOvsxb" style="_visibility:visible;">
                                            <!--空间使用-->
                                            <ul class="tDuODs">
                                                <li class="g-clearfix bar">
                                                    <div class="remainingSpaceUi">
                                                        <span class="remainingSpaceUi_span" style="background: rgb(146, 239, 85); transition-duration: 0.635675s; width: <?=$totalSize/2147483648?>%;"></span>
                                                    </div>
                                                    <div class="DIeHPCb remaining-space">
                                                        <span class="bold"><?=format_size($totalSize)?></span>/<span>2 TB</span>
                                                    </div>
                                                </li>
                                            </ul>
                                            <!--//空间使用-->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="jopU61">
                <div class="frame-main" id="layoutMain" style="display: block;">
                    <div class="v9hifKNW">
                        <div class="X552xQ">
                            <div class="DxdbeCb g-clearfix">
                                <div class="je06M2">
                                    <!--搜索框-->
                                    <div class="OFaPaO">
                                        <div class="bkN5ee">
                                            <form class="vwyOqQ8 ui3XZR" method="get">
                                                <input type="hidden" name="id" value="<?=$id?>">
                                                <input type="hidden" name="action" value="search">
                                                <input class="amglNLXd" name="q" value="<?=isset($_GET['q'])?$_GET['q']:'';?>" type="text" placeholder="搜索您的文件">
                                                <span class="gHHsaL">
                                                    <button type="submit" style="border:none; display:block; width:15px; height:15px; font-size: 14px; cursor: pointer; background:transparent;">
                                                        <span class="icon icon-search"></span>
                                                    </button>
                                                </span>
                                            </form>
                                        </div>
                                    </div>
                                    <!--//搜索框-->


                                    <div class="htmrOvxW" style="white-space: nowrap; position: relative;">
                                        <div class="tcuLAu" style="position: absolute; top: 0px; line-height: normal; padding-top: 11px; padding-left: 0px; width: auto; visibility: visible;">
                                            <!--上传-->
                                            <span class="g-dropdown-button" style="display: inline-block;">
                                                <div class="g-button g-button-blue blue-upload upload-wrapper">
                                                    <a href="show.php?id=<?=$id?>&action=upload">
                                                        <span class="g-button-right">
                                                            <em class="icon icon-upload" title="上传"></em>
                                                            <span class="text" style="width: 40px;">上传</span>
                                                        </span>
                                                    </a>
                                                </div>
                                            </span>
                                            <!--//上传-->
                                            <!--新建文件夹-->
                                            <a class="g-button" href="show.php?id=<?=$id?>&action=create" title="新建文件夹">
                                                <span class="g-button-right">
                                                    <em class="icon icon-newfolder" title="新建文件夹"></em>
                                                    <span class="text">新建文件夹</span>
                                                </span>
                                            </a>
                                            <!--//新建文件夹-->
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="yMy6ly">
                                <div class="AH9PZW">
                                    <div class="KPDwCE">
                                        <!--面包屑导航-->
                                        <div class="JDeHdxb">
                                            <span class="EgMMec">全部文件</span>
                                            <span class="FcucHsb">已全部加载，共<?=count($lists)?>个</span>
                                            
                                            <?php
                                                $navs=array();
                                                $temid=$id;
                                                $fatherid=$lanthy->getNavFatherId($id);
                                                while($temid>1){
                                                    $navs[]=$lanthy->getNav($temid);
                                                    $temid = $lanthy->getNavFatherId($temid);
                                                }
                                            ?>
                                            <ul class="FuIxtL" node-type="FuIxtL" style="display:<?php echo $id>1?'block':'none'; ?>">
                                                <li><a href="?id=<?=$fatherid?>">返回上一级</a><span class="EKIHPEb">|</span></li>
                                                <li>
                                                    <a href="?id=1" title="全部文件">全部文件</a>
                                                    <?php
                                                        foreach(array_reverse($navs) as $nav){
                                                            echo '<span class="KLxwHFb">&gt;</span><a href="?id='.$nav['rowid'].'" title="'.$nav['info_title'].'">'.$nav['info_title'].'</a>';
                                                        }
                                                    ?>
                                                </li>
                                            </ul>
                                        </div>
                                        <!--//面包屑导航-->

                                        <!--表头-->
                                        <div class="QxJxtg">
                                            <div class="xGLMIab">
                                                <ul class="QAfdwP tvPMvPb">
                                                    <li class="fufHyA yfHIsP" style="width:60%;">
                                                        <div class="Qxyfvg fydGNC"></div>
                                                        <span class="text">文件名</span>
                                                    </li>
                                                    <li class="fufHyA" style="width:10%;">
                                                        <span class="text">大小</span>
                                                    </li>
                                                    <li class="fufHyA" style="width:10%;">
                                                        <span class="text">作者</span>
                                                    </li>
                                                    <li class="fufHyA gObdAzb MCGAxG" style="width:20%;">
                                                        <span class="text">修改日期</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <!--//表头-->

                                        <!--文件列表-->
                                        <div class="zJMtAEb">
                                            <div class="NHcGw" id="NHcGw" style="overflow-y:auto;height:300px;">
                                                <div class="vdAfKMb">

                                                    <!--新建-->
                                                    <?php if($action=='create'):?>
                                                    <dd class="g-clearfix AuPKyz open-enable">
                                                        <div class="rkua3EGO dir-small"></div>
                                                        <div class="file-name" style="width:60%">
                                                            <div class="text">
                                                                <div class="ExFGye" style="position: inherit; display: block; padding-left:0; margin-left:0;">
                                                                    <div class="ufm31ML">
                                                                        <form action="" method="POST" id="createform">
                                                                            <input class="GadHyA" name="info_title" type="text" value="" autofocus="autofocus">
                                                                            <span class="amppO4EQ" id="create">
                                                                                <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                <em class="icon btOyBY" style="text-indent: 0;"></em>
                                                                            </span>
                                                                            <span class="zfnxOk1p">
                                                                                <a href="?id=<?=$id?>">
                                                                                    <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                    <em class="icon nu3n9k" style="text-indent: 0;"></em>
                                                                                </a>
                                                                            </span>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="vpmt3Wea" style="width:16%">-</div>
                                                        <div class="klpg024G" style="width:23%">-</div>
                                                    </dd>
                                                    <?php endif; ?>
                                                    <!--//新建-->

                                                    <!--重命名-->
                                                    <?php if($action=='rename'):?>
                                                    <dd class="g-clearfix AuPKyz open-enable">
                                                        <div class="rkua3EGO default-small"></div>
                                                        <div class="file-name" style="width:60%">
                                                            <div class="text">
                                                                <div class="ExFGye" style="position: inherit; display: block; padding-left:0; margin-left:0;">
                                                                    <div class="ufm31ML">
                                                                        <form action="" method="POST" id="createform">
                                                                            <input class="GadHyA" name="info_title" type="text" value="<?=$_GET['title']?>" autofocus="autofocus">
                                                                            <span class="amppO4EQ" id="create">
                                                                                <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                <em class="icon btOyBY" style="text-indent: 0;"></em>
                                                                            </span>
                                                                            <span class="zfnxOk1p">
                                                                                <a href="?id=<?=$id?>">
                                                                                    <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                    <em class="icon nu3n9k" style="text-indent: 0;"></em>
                                                                                </a>
                                                                            </span>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="vpmt3Wea" style="width:16%">-</div>
                                                        <div class="klpg024G" style="width:23%">-</div>
                                                    </dd>
                                                    <?php endif; ?>
                                                    <!--//重命名-->


                                                    <?php
                                                    /**
                                                     * 列表询盘
                                                     */
                                                    foreach($lists as $list):
                                                        if($list['info_filetype']=='category') $isCategory = true; else $isCategory = false;
                                                    ?>
                                                    <!--列表循环-->
                                                    <dd class="g-clearfix AuPKyz open-enable">
                                                        <div class="rkua3EGO <?=$icons[$list['info_filetype']]?>"></div>
                                                        <div class="file-name" style="width:60%">
                                                            <div class="text">
                                                                <?php echo $isCategory?'<a href="?id='.$list['rowid'].'" class="pubNp2V" title="打开 '.$list['info_title'].'">'.$list['info_title'].'</a>':'<span class="pubNp2V">'.$list['info_title'].'</span>'; ?>
                                                            </div>
                                                            <div class="operate" style="display:block">
                                                                <div class="x-button-box" style="position: absolute; top: 0px; line-height: normal; visibility: visible; width: 0px; padding-left: 0px; display: block;">
                                                                    <?php if($trash){ ?>
                                                                    <a class="g-button" href="?id=<?=$id?>&action=publish&rowid=<?=$list['rowid']?>" title="还原" style="display: inline-block;">
                                                                        <span class="g-button-right">
                                                                            <em class="icon icon-recovery" title="还原"></em>
                                                                            <span class="text">还原</span>
                                                                        </span>
                                                                    </a>
                                                                    <?php }else{ ?>
                                                                        <?php if(!$isCategory):?>
                                                                        <a class="g-button" href="?id=<?=$id?>&action=priview&rowid=<?=$list['rowid']?>" title="预览" style="display: inline-block;">
                                                                            <span class="g-button-right">
                                                                                <em class="icon icon-picpre-enlarge" title="预览"></em>
                                                                                <span class="text">预览</span>
                                                                            </span>
                                                                        </a>
                                                                        <a class="g-button" href="?id=<?=$id?>&action=download&rowid=<?=$list['rowid']?>" title="下载" style="display: inline-block;">
                                                                            <span class="g-button-right">
                                                                                <em class="icon icon-download" title="下载"></em>
                                                                                <span class="text">下载</span>
                                                                            </span>
                                                                        </a>
                                                                        <?php endif; ?>
                                                                        <a class="g-button" href="?id=<?=$id?>&action=rename&rowid=<?=$list['rowid']?>&title=<?=$list['info_title']?>" title="重命名"
                                                                        style="display: inline-block;">
                                                                        <span class="g-button-right">
                                                                            <em class="icon icon-edit" title="重命名"></em>
                                                                            <span class="text">重命名</span>
                                                                        </span>
                                                                        </a>
                                                                        <a class="g-button" href="?id=<?=$id?>&action=delete&rowid=<?=$list['rowid']?>" title="删除"
                                                                            style="display: inline-block;">
                                                                            <span class="g-button-right">
                                                                                <em class="icon icon-delete" title="删除"></em>
                                                                                <span class="text">删除</span>
                                                                            </span>
                                                                        </a>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="vpmt3Wea" style="width:10%"><?=$isCategory?'-':format_size($list['info_filesize'])?></div>
                                                        <div class="vpmt3Wea" style="width:10%"><?=$list['info_author']?></div>
                                                        <div class="klpg024G" style="width:20%"><?=format_date($list['info_posttime'])?></div>
                                                    </dd>
                                                    <!--//列表循环-->
                                                    <?php endforeach; ?>


                                                    <?php if($action=='upload'):?>
                                                    <!--上传-->
                                                    <dd class="g-clearfix AuPKyz open-enable">
                                                        <div class="rkua3EGO default-small"></div>
                                                        <div class="file-name" style="width:60%">
                                                            <div class="text">
                                                                <div class="ExFGye" style="position: inherit; display: block; padding-left:0; margin-left:0;">
                                                                    <div class="ufm31ML">
                                                                        <form id="uploadform" method="post" enctype="multipart/form-data">
                                                                            <input class="GadHyA" name="files[]" type="file" multiple>
                                                                            <span class="amppO4EQ" id="upload">
                                                                                <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                <em class="icon btOyBY" style="text-indent: 0;"></em>
                                                                            </span>
                                                                            <span class="zfnxOk1p">
                                                                                <a href="?id=<?=$id?>">
                                                                                    <em class="icon wvOvbW" style="text-indent: 0;"></em>
                                                                                    <em class="icon nu3n9k" style="text-indent: 0;"></em>
                                                                                </a>
                                                                            </span>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="vpmt3Wea" style="width:16%">-</div>
                                                        <div class="klpg024G" style="width:23%">-</div>
                                                    </dd>
                                                    <!--//上传-->
                                                    <?php endif; ?>

                                                </div>
                                            </div>
                                        </div>
                                        <!--//文件列表-->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- 用户信息 -->
        <div id="layoutHeader">
            <div class="module-header">
                <div class="module-header-wrapper" style="height: 62px;">
                    <dl class="xtJbHcb">
                        <dt class="EHazOI">
                            <a href="/" target="_self" title="蓝悉网盘"></a>
                        </dt>
                        <dd class="CDaavKb" id="profile">
                            <span class="DIcOFyb " id="profilebox">
                                <span class="user-photo-box">
                                    <i class="user-photo" style="background-image:url(<?='http://www.gravatar.com/avatar/'.md5($_SESSION['loginStatus']['email']).'';?>);filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='<?='http://www.gravatar.com/avatar/'.md5($_SESSION['loginStatus']['email']).'';?>', sizingMethod='scale');"></i>
                                </span>
                                <span class="user-name"><?=$_SESSION['loginStatus']['nickname']?></span>
                                <span class="animate-start animate-level-0">
                                    <i class="star start-left"></i>
                                    <i class="bar-bling"></i>
                                    <i class="bar-bling2"></i>
                                    <i class="star start-right"></i>
                                </span>
                                <a class="JS-user-level level-0" href="#" target="_blank"></a>
                                <i node-type="img-ico" class="NxuPcOb icon icon-dropdown-arrow"></i>
                                <dl class="PvsOgyb" node-type="app-user-box">
                                    <dt class="OMDFeH level-0">
                                        <i class="desc-arrow"></i>
                                        <i class="desc-layer"></i>
                                        <span class="desc-header">
                                            <span class="user-photo-box">
                                                <i class="user-photo" style="background-image:url(<?='http://www.gravatar.com/avatar/'.md5($_SESSION['loginStatus']['email']).'';?>);filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src='<?='http://www.gravatar.com/avatar/'.md5($_SESSION['loginStatus']['email']).'';?>', sizingMethod='scale');"></i>
                                            </span>
                                            <span class="user-info" title="<?=$_SESSION['loginStatus']['nickname']?>">
                                                <span class="username"><?=$_SESSION['loginStatus']['nickname']?></span>
                                                <a class="JS-user-level level-0" href="/buy/center?tag=8&amp;from=hicon" target="_blank"></a>
                                            </span>
                                        </span>
                                    </dt>
                                    <dd class="desc-box">
                                        <div class="vip-notice">超级会员尊享15项特权：<a href="#" target="_blank" class="eQyIbEb">开通超级会员</a></div>
                                        <div class="vip-privilege">
                                            <a target="_blank" href="#" class="JS-privilege-icon icon-capacity-5t" title="5T超大空间"></a>
                                            <a target="_blank" href="#" class="JS-privilege-icon icon-download-speed-raising" title="极速下载"></a>
                                            <a target="_blank" href="#" class="JS-privilege-icon icon-video" title="视频高速通道"></a>
                                            <a target="_blank" href="#" class="JS-privilege-icon icon-cloud-unzip" title="在线云解压"></a>
                                            <a target="_blank" href="#" class="JS-privilege-icon icon-know-more" title="了解更多"></a>
                                        </div>
                                        <ul class="QgxQAN">
                                            <li class="cMEMEF"><a target="_blank">个人资料</a></li>
                                            <li class="cMEMEF"><a target="_blank">帮助中心</a></li>
                                            <?php if($_SESSION['loginStatus']['auth']>5):?><li class="cMEMEF"><a href="?id=<?=$id?>&action=user">用户管理</a></li><?php endif; ?>
                                            <li class="cMEMEF password"><a href="?id=<?=$id?>&action=password">修改密码</a></li>
                                            <li class="cMEMEF"><a href="?id=<?=$id?>&action=logout">退出</a></li>
                                        </ul>
                                    </dd>
                                </dl>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <!-- //用户信息 -->
    </div>

    <?php if($action=='password'):?>
    <!-- 修改密码 -->
    <link rel="stylesheet" type="text/css" href="/box-static/function-widget-1/pkg/offlineDownload-all_c812dee.css">
    <div class="dialog dialog-newoffline-dialog   dialog-gray" id="newoffline-dialog" style="width: 560px; top: 115px; bottom: auto; left: 680px; right: auto; display: block; visibility: visible; z-index: 54;">
        <div class="dialog-header dialog-drag">
            <h3><span class="dialog-header-title"><em class="select-text">修改密码</em></span></h3>
            <div class="dialog-control"><a href="?id=<?=$id?>"><span class="dialog-icon dialog-close icon icon-close"><span class="sicon">×</span></span></a></div>
        </div>
        <div class="dialog-body">
            <div class="dlg-bd g-clearfix">
                <div class="tab-content-mail">
                    <form method="post" id="passwordform">
                        <dl class="form-flds g-clearfix"><dt>新密码：</dt>
                            <dd class="g-clearfix">
                                <div class="b-input b-fl b-share-n">
                                <input id="share-offline-link" name="password" class="share-n" type="password">
                                </div>
                            </dd>
                        </dl>
                    </form>
                </div>
            </div>
        </div>
        <div class="dialog-footer g-clearfix">
            <a class="g-button g-button-gray" href="?id=<?=$id?>" title="关闭" style="float: right; padding-left: 36px; margin: 0px 14px 0px 5px;">
                <span class="g-button-right" style="padding-right: 36px;">
                    <span class="text" style="width: auto;">关闭</span>
                </span>
            </a>
            <a class="g-button g-button-blue" href="javascript:;" onclick="passwordSubmit()" title="确定" style="float: right; padding-left: 36px; margin: 0px 5px;">
                <span class="g-button-right" style="padding-right: 36px;">
                    <span class="text" style="width: auto;">确定</span>
                </span>
            </a>
        </div>
    </div>
    <div class="module-canvas" style="position: fixed;left: 0px;top: 0px;z-index: 50;background: rgb(0, 0, 0);opacity: 0.5;width: 100%;height: 100%;"></div>
    <script>
        function passwordSubmit(){
            document.getElementById('passwordform').submit()
        }
        var pass_w = document.documentElement.clientWidth || document.body.clientWidth;
        pass_w=(pass_w-560)/2;
        document.getElementById('newoffline-dialog').style.left = pass_w + "px";
    </script>
    <!-- // 修改密码 -->
    <?php endif; ?>

    <?php if($action=='priview'):?>
    <!-- 预览 -->
    <link rel="stylesheet" type="text/css" href="/box-static/file-widget-1/pkg/image-all_9bc05b7.css">
    <div class="dialog dialog-dialog1  dialog-gray imgDialog" id="dialog1" style="width: 1900px; height: 350px; top: 0px; bottom: auto; left: 0px; right: auto; display: block; visibility: visible; z-index: 55;">
        <div class="dialog-body">
            <div class="img-dialog module-picPreview" id="imgs-dialog">
                <div node-type="dlg-hd" class="dlg-hd">
                    <a href="?id=<?=$id?>" title="关闭" hidefocus="true" id="_disk_id_4" class="dlg-img-ic dlg-img-close icon icon-picpre-close"></a>
                </div>
                <div class="dlg-bd" id="dlg-bd" style="width: 1600px; height: 300px;">
                    <div class="module-showPic clearfix" id="module-showPic" style="width: 1480px; height: 280px;">
                        <div class="img-wrap">
                            <?php if(strstr($view['info_filetype'],'image')){
                                $image=true;
                            ?>
                            <img src="<?=$view['info_path']?>" id="viewpic-image" style="transform: rotate(0deg); visibility: visible;">
                            <?php }elseif(strstr($view['info_filetype'],'video')){ ?>
                            <video width="100%" height="100%" controls>
                                <source src="<?=$view['info_path']?>" type="<?=$view['info_filetype']?>" />
                                暂不支持此格式文件的预览，请下载后查看
                            </video>
                            <?php }else{
                                echo '暂不支持此格式文件的预览，请下载后查看';
                            }?>
                        </div>
                    </div>
                </div>
                <div class="module-thumbnailPic">
                    <div class="operate-area" node-type="operate-area">
                        <div class="operate-container" node-type="operate-container">
                            
                            <div class="img-control" node-type="img-control">
                                <?php if($image): ?>
                                <a href="<?=$view['info_path']?>" target="_blank"><em class="control control-left icon icon-picpre-enlarge" title="放大"></em></a>
                                <em id="control-left" class="control control-left icon icon-picpre-rotate" title="旋转"></em>
                                <?php endif; ?>
                                <a href="?id=<?=$id?>&action=download&rowid=<?=$_GET['rowid']?>"><em class="control control-download icon icon-picpre-download" title="下载图片"></em></a>
                                <a href="?id=<?=$id?>&action=delete&rowid=<?=$_GET['rowid']?>"><em class="control control-delete icon icon-picpre-delete" title="删除图片"></em></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="module-canvas" style="position: fixed;left: 0px;top: 0px;z-index: 50;background: rgb(0, 0, 0);opacity: 0.5;width: 100%;height: 100%;"></div>
    <script>
        var view_w = document.documentElement.clientWidth || document.body.clientWidth;
        var view_h = document.documentElement.clientHeight || document.body.clientHeight;
        document.getElementById('dialog1').style.width = view_w + "px";
        document.getElementById('dialog1').style.height = view_h + "px";
        document.getElementById('dlg-bd').style.width = view_w + "px";
        document.getElementById('dlg-bd').style.height = (view_h-52) + "px";
        document.getElementById('module-showPic').style.width = (view_w-120) + "px";
        document.getElementById('module-showPic').style.height = (view_h-72) + "px";
        document.getElementById('control-left').onclick=function(){
            var deg = document.getElementById('viewpic-image').style.WebkitTransform
            var num = parseInt(deg.replace(/[^0-9]/ig,""))
            num = num+90
            document.getElementById('viewpic-image').style.WebkitTransform="rotate(" + num + "deg)";
        }
    </script>
    <!-- 预览 -->
    <?php endif; ?>

    <?php if($action=='user'):?>
    <!-- 用户列表 -->
    <link rel="stylesheet" type="text/css" href="/box-static/function-widget-1/pkg/offlineDownload-all_c812dee.css">
    <div class="dialog dialog-offlinelist-dialog  dialog-gray" id="offlinelist-dialog" style="width: 620px; top: 152.5px; bottom: auto; left: 474.5px; right: auto; display: block; visibility: visible; z-index: 54;">
        <div class="dialog-header dialog-drag">
            <h3><span class="dialog-header-title"><em class="select-text">用户列表</em></span></h3>
            <div class="dialog-control">
                <a href="?id=<?=$id?>">
                    <span class="dialog-icon dialog-close icon icon-close">
                        <span class="sicon">×</span>
                    </span>
                </a>
            </div>
        </div>
        <div class="dialog-body">
            <div class="dlg-bd g-clearfix offline-list-dialog">
                <div class="headdiv g-clearfix">
                <a class="g-button g-button-blue g-float-left create-bt-button upload-wrapper" 
                    href="?id=<?=$id?>&action=adduser" style="padding-left:10px;">
                    <span class="g-button-right" style="padding-right:10px">
                        <span class="text">创建新用户</span>
                    </span>
                </a>
            </div>
            <div class="g-clearfix header-task-title" style="display: block;">
                <div class="header-task-name">用户名</div>
                <div class="header-task-size">状态</div>
                <div class="header-task-status">最近登录</div>
                <div class="header-task-handler">操作</div>
                </div>
                <div id="OfflineListContainer" class="b-bdr-1 g-clearfix">
                    <dl id="OfflineListView">
                        <!-- 用户列表 -->
                        <?php foreach($users as $user): ?>
                        <dd class="g-clearfix offline-item" data-id="2732747276" data-odtype="0" data-over="1">
                            <div class="offline-filename lfloat">
                                <a class="file-handler lfloat" title="<?=$user['user_nicename']?>"><?=$user['user_username']?></a>
                            </div>
                            <div class="offline-filesize lfloat"><?=$user['user_status']=='enable'?'正常':'禁用';?></div>
                            <div class="offline-complete lfloat">
                                <span class="offline-state"><?=$user['user_logintime']?format_date($user['user_logintime']):'未登录';?></span>
                            </div>
                            <div class="offline-handles">
                                <?php if($user['user_status']=='disable'): ?>
                                <a href="?id=<?=$id?>&action=user&rowid=<?=$user['rowid']?>&status=enable" class="offline-open">启用</a>
                                <?php endif; ?>
                                <?php if($user['user_status']=='enable'): ?>
                                <a href="?id=<?=$id?>&action=user&rowid=<?=$user['rowid']?>&status=disable" class="offline-open">禁用</a>
                                <?php endif; ?>
                                <a href="?id=<?=$id?>&action=user&rowid=<?=$user['rowid']?>&status=delete" class="offline-delete">删除</a>
                            </div>
                        </dd>
                        <?php endforeach; ?>
                        <!-- 用户列表 -->
                        
                    </dl>
                </div>

            </div>
        </div>
    </div>
    <div class="module-canvas" style="position: fixed;left: 0px;top: 0px;z-index: 50;background: rgb(0, 0, 0);opacity: 0.5;width: 100%;height: 100%;"></div>
    <!-- // 用户列表 -->
    <?php endif; ?>

    <?php if($action=='adduser'):?>
    <!-- 添加新用户 -->
    <link rel="stylesheet" type="text/css" href="/box-static/function-widget-1/pkg/offlineDownload-all_c812dee.css">
    <div class="dialog dialog-newoffline-dialog dialog-gray" id="newoffline-dialog" style="width: 560px; top: 115px; bottom: auto; left: 680px; right: auto; display: block; visibility: visible; z-index: 54;">
        <div class="dialog-header dialog-drag">
            <h3><span class="dialog-header-title"><em class="select-text">添加新用户</em></span></h3>
            <div class="dialog-control"><a href="?id=<?=$id?>"><span class="dialog-icon dialog-close icon icon-close"><span class="sicon">×</span></span></a></div>
        </div>
        <div class="dialog-body">
            <div class="dlg-bd g-clearfix">
                <div class="tab-content-mail">
                    <form method="post" id="adduserform">
                        <dl class="form-flds g-clearfix"><dt>昵称：</dt>
                            <dd class="g-clearfix">
                                <div class="b-input b-fl b-share-n">
                                    <input id="share-offline-link" name="user_nicename" class="share-n" type="text">
                                </div>
                            </dd>
                        </dl>
                        <dl class="form-flds g-clearfix"><dt>用户名：</dt>
                            <dd class="g-clearfix">
                                <div class="b-input b-fl b-share-n">
                                    <input id="share-offline-link" name="user_username" class="share-n" type="text">
                                </div>
                            </dd>
                        </dl>
                        <dl class="form-flds g-clearfix"><dt>邮箱：</dt>
                            <dd class="g-clearfix">
                                <div class="b-input b-fl b-share-n">
                                    <input id="share-offline-link" name="user_email" class="share-n" type="text">
                                    <label class="input-placeholder">密码默认为123456，设置后用户可自行修改</label>
                                </div>
                            </dd>
                        </dl>
                        <dl class="form-flds g-clearfix"><dt>权限：</dt>
                            <dd class="g-clearfix">
                                <div class="b-input b-fl b-share-n">
                                    <select name="user_auth" id="share-offline-link" class="share-n">
                                        <option value="1">普通用户</option>
                                        <option value="6">高级用户</option>
                                    </select>
                                    <label class="input-placeholder">密码默认为123456，设置后用户可自行修改</label>
                                </div>
                            </dd>
                        </dl>
                    </form>
                </div>
            </div>
        </div>
        <div class="dialog-footer g-clearfix">
            <a class="g-button g-button-gray" href="?id=<?=$id?>" title="关闭" style="float: right; padding-left: 36px; margin: 0px 14px 0px 5px;">
                <span class="g-button-right" style="padding-right: 36px;">
                    <span class="text" style="width: auto;">关闭</span>
                </span>
            </a>
            <a class="g-button g-button-blue" href="javascript:;" onclick="adduserSubmit()" title="确定" style="float: right; padding-left: 36px; margin: 0px 5px;">
                <span class="g-button-right" style="padding-right: 36px;">
                    <span class="text" style="width: auto;">确定</span>
                </span>
            </a>
        </div>
    </div>
    <div class="module-canvas" style="position: fixed;left: 0px;top: 0px;z-index: 50;background: rgb(0, 0, 0);opacity: 0.5;width: 100%;height: 100%;"></div>
    <script>
        function adduserSubmit(){
            document.getElementById('adduserform').submit()
        }
        var pass_w = document.documentElement.clientWidth || document.body.clientWidth;
        pass_w=(pass_w-560)/2;
        document.getElementById('newoffline-dialog').style.left = pass_w + "px";
    </script>
    <!-- // 修改密码 -->
    <?php endif; ?>



    <script>
        // 新建文件夹
        if(document.getElementById('create')){
            document.getElementById('create').onclick = function(){
                document.getElementById('createform').submit();
            };
        }
        
        // 上传文件
        if(document.getElementById('upload')){
            document.getElementById('upload').onclick = function(){
                document.getElementById('uploadform').submit();
            }
        }
        // 打开用户
        document.getElementById('profile').onclick = function(){
            // document.getElementById('profilebox').className += 'mouseon'; //在原来的后面加这个
            var c = document.getElementById('profilebox').className;
            //有mouseon属性
            if(c != null && c.indexOf('mouseon') > -1){
                document.getElementById('profilebox').className = c.replace('mouseon', '');
            }else{
                document.getElementById('profilebox').className = c + ' mouseon';
            }
        }
        autoheight();
        function autoheight(){
            var h = document.documentElement.clientHeight || document.body.clientHeight;
            h=h-210;
            document.getElementById('NHcGw').style.height = h + "px";
        }
        window.onresize=autoheight;
    </script>
</body>
</html>