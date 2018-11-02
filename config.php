<?php
/**
 * 配置文件
 * upload_max_filesize = 4096M;
 * post_max_size = 4096M;
 * max_execution_time = 600
 * max_input_time = 600
 * memory_limit = 2048M
 * 
 */
session_start();
if(empty($_SESSION['loginStatus']['status']) || !$_SESSION['loginStatus']['status']) {
    header('Location:/index.php');// 跳转到登录页面
    exit;
}
$siteTitle='蓝悉科技'; // 站点标题
$siteSubtitle='选品管理系统'; // 站点副标题
$dbname = md5($siteTitle.$siteSubtitle);
class LANTHY{
	public $db;
	function __construct($dbfile=null){
		try{
			$this->db=new SQLite3($dbfile?$dbfile:"db",SQLITE3_OPEN_READONLY);
		}catch(Exception $e){
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 3600');
			echo "<h2>Database error. 503 Service Temporarily Unavailable</h2><p>".$e->getMessage()."</p>";
			header('Location:/login.php');// 跳转到登录页面
			die();
		}
	}
	function __destruct(){
		if($this->db)$this->db->close();
	}
	function getData($sql){
		$result=$this->db->query($sql) or die("Error:".$sql);
		$ret=array();
		while($row=$result->fetchArray(SQLITE3_ASSOC))$ret[]=$row;
		$result->finalize();
		unset($result);
		unset($row);
		return $ret;
	}
	function getLine($sql,$type=true){
		return $this->db->querySingle($sql,$type);
    }
    /**
     * getUser
     */
	function getUsers(){
        $sql="SELECT rowid,* FROM hl_user WHERE user_auth < 9";
		return $this->getData($sql);
    }
    /**
     * getCategories
     */
    function getCategories($id=0){
        $sql="SELECT rowid,* FROM hl_info WHERE info_status = 'publish' AND info_father = ".intval($id)." ORDER BY info_posttime DESC";
		return $this->getData($sql);
    }
    /**
     * getSearch
     */
    function getSearch($q,$id){
        $ids="";
        preg_match_all('/./u', $q, $tags);
		$tags=$tags[0];
		$allid=array();
		foreach($tags as $tag){
			$tag=trim($tag);
			if($tag){
				$tempid=explode(",",$this->getLine("select index_ids from hl_index where index_title='".$this->db->escapeString($tag)."'",false));
				if(!empty($tempid)){
					foreach($tempid as $v)if($v)$allid[$v]=1;
					if(count($allid)>$count)break;
				}
			}
		}
		$allid=array_keys($allid);
		$countID=count($allid);
		if($countID==0)return array();
		if($countID>$count){
			shuffle($allid);
			$allid=array_slice($allid,0,$count);
		}
		/*modified by sorata 2014.11.13*/
		$tmps=$this->getData("SELECT rowid,* FROM hl_info WHERE info_status='publish' AND rowid IN (".implode(",",$allid).")");
		$newarray=array();
		foreach($tmps as $post){
			$newarray[array_search($post['rowid'],$allid)]=$post;
		}
		ksort($newarray);
		return $newarray;
    }
    /**
     * getTrash
     */
    function getTrash($id){
        $sql="SELECT rowid,* FROM hl_info WHERE info_status = 'trash' ORDER BY info_posttime DESC";
		return $this->getData($sql);
    }
    /**
     * getNav
     */
    function getNav($id){
        $sql="SELECT rowid,info_title FROM hl_info WHERE info_status = 'publish' AND rowid=".intval($id)." ORDER BY info_posttime DESC";
		return $this->getLine($sql);
    }
    /**
     * getNavFatherId
     */
    function getNavFatherId($id){
        $sql="SELECT info_father FROM hl_info WHERE info_status = 'publish' AND rowid=".intval($id);
		return $this->getLine($sql,false);
    }
    /**
     * getTotalSize
     */
    function getFileSize($id){
        $sql="SELECT info_filesize FROM hl_info WHERE rowid=".intval($id);
		return $this->getLine($sql,false);
    }
    /**
     * getFileByType
     */
    function getFileByType($type){
        $sql="SELECT rowid,* FROM hl_info WHERE info_status = 'publish' AND info_filetype LIKE '%".$this->db->escapeString($type)."%' ORDER BY info_posttime DESC";
		return $this->getData($sql);
    }
    /**
     * getRowids
     */
    function getRowids($id){
        $sql="SELECT rowid FROM hl_info WHERE info_filetype != 'category' AND info_father=".intval($id);
		return $this->getData($sql);
    }
    
}
// 格式时间
function format_date($time){
    $t=time()-$time;
    $f=array(
        '31536000'=>'年',
        '2592000'=>'个月',
        '604800'=>'星期',
        '86400'=>'天',
        '3600'=>'小时',
        '60'=>'分钟',
        '1'=>'秒'
    );
    foreach ($f as $k=>$v)    {
        if (0 !=$c=floor($t/(int)$k)) {
            return $c.$v.'前';
        }
    }
}
// 格式化文件大小
function format_size($size){
    if($size>1073741824){
        return ceil($size/1073741824).' GB';
    }elseif($size>1048576){
        return ceil($size/1048576).' MB';
    }elseif($size>1024){
        return ceil($size/1024).' KB';
    }else{
        return ceil($size).' B';
    }
}
// 文件下载
function downloadFile($data){
    set_time_limit(0);
    ini_set('max_execution_time', '0');
    ob_clean();
    //通过header()发送头信息
    header('Content-type: '.$data['info_filetype']);
    header('Accept-Ranges: bytes');
    header('Accept-Length: '.$data['info_filesize']);
    header('Content-Disposition: attachment; filename="'.$data['info_title'].'"');
    //针对大文件，规定每次读取文件的字节数为4096字节，直接输出数据
    $read_buffer=4096;
    $handle=fopen($data['info_path'], 'r');
    //总的缓冲的字节数
    $sum_buffer=0;
    //只要没到文件尾，就一直读取
    while(!feof($handle) && $sum_buffer<$data['info_filesize']) {
        echo fread($handle,$read_buffer);
        $sum_buffer+=$read_buffer;
    }
    //关闭句柄
    fclose($handle);
    exit;
 }
 /**
  * updateIndex();
  */
  function updateIndex(){
    global $db;
    $tags=array();
    $sql="select rowid,info_tag from hl_info";
    $result=$db->query($sql) or die("Error:".$sql);
    while($row=$result->fetchArray(SQLITE3_ASSOC)){
        $postTags = explode(",",$row['info_tag']);
        foreach($postTags as $pt){
            if(isset($tags[$pt])) $tags[$pt] .= ','.$row['rowid']; else $tags[$pt] = $row['rowid'];
        }
    }
	if(!empty($tags)){
        $db->exec("drop table hl_index");
        // 创建index表 index_title,index_ids,index_counts
        $db->exec("CREATE TABLE \"hl_index\" (
            \"index_title\"  varchar,  /*标签*/
            \"index_ids\"    TEXT,     /*对应的id*/      
            \"index_counts\" INTEGER)  /*数量*/
        ");
        $db->exec("CREATE UNIQUE INDEX index_index_title on hl_index (index_title)");
        $db->exec("CREATE INDEX index_index_counts on hl_index (index_counts DESC)");
		$db->exec("begin exclusive transaction");
		foreach($tags as $k=>$v){
			$sql="INSERT INTO hl_index(\"index_title\",\"index_ids\",\"index_counts\") values ('".$db->escapeString($k)."','{$v}',".(substr_count($v,",")+1).")";
			$db->exec($sql);
		}
		$db->exec("end transaction");
	}
  }

// 实例化
$lanthy = New LANTHY("{$_SERVER['DOCUMENT_ROOT']}/DATA/{$dbname}");

/**
 * icons
 */
$icons = array(
    'category'=>'dir-small',
    'image/jpeg'=>'fileicon-small-pic',
    'video/mp4'=>'fileicon-small-video',
    'video/x-matroska'=>'fileicon-small-video',
    'application/x-zip-compressed'=>'fileicon-small-zip',
    'text/plain'=>'fileicon-small-txt',
    'application/msword'=>'fileicon-small-doc',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'fileicon-small-xls',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'fileicon-small-ppt',
    'application/pdf'=>'fileicon-small-pdf',
    'text/html'=>'fileicon-sys-s-web',
    'application/octet-stream'=>'fileicon-sys-s-code',
    // ''=>'',
    // ''=>'',
);