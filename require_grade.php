<!DOCTYPE html>
<html lang='zh_cn'>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
	<title><?php printf($_POST['account']); ?> - 成绩查询结果</title>
	<link rel="stylesheet" href="//cdn.bootcss.com/weui/0.4.0/style/weui.min.css"/>
	<link rel="stylesheet" href="style/accordion.css">
</head>

<?php 
    session_start();
    header("Content-type: text/html; charset=utf-8");  //视学校而定，一般是gbk编码，php也采用的gbk编码方式
    include('simple_html_dom-master/simple_html_dom.php');//引入类库文件    
    //function: 构造post数据并登陆
    function login_post($url,$cookie,$post){
		global $cookie;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  //不自动输出数据，要echo才行
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //重要，抓取跳转后数据
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie); 
        curl_setopt($ch, CURLOPT_REFERER, 'http://202.119.160.5/default2.aspx');  //重要，302跳转需要referer，可以在Request Headers找到 
        curl_setopt($ch, CURLOPT_POSTFIELDS,$post);  //post提交数据
        $result=curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    //获取VIEWSTATE
    $_SESSION['xh']=$_POST['account'];
    $xh=$_POST['account'];
    $pw=$_POST['password'];
    $current_year=$_POST['current_year'];
    $current_term=$_POST['current_term'];
    $code= $_POST['verify_code'];
    $cookie = dirname(__FILE__) . '/cookie/'.$_SESSION['id'].'.txt';
    $url="http://202.119.160.5/default2.aspx";  //教务地址
    $con1=login_post($url,$cookie,'');               //登陆
    preg_match_all('/<input type="hidden" name="__VIEWSTATE" value="([^<>]+)" \/>/', $con1, $view); //获取__VIEWSTATE字段并存到$view数组中
    //为登陆准备的POST数据
	
	$post=array(
        '__VIEWSTATE'=>$view[1][0],
        'txtUserName'=>$xh,
        'TextBox2'=>$pw,
        'txtSecretCode'=>$code,
        'RadioButtonList1'=>iconv('utf-8', 'gb2312', '学生'),
        'Button1'=>iconv('utf-8', 'gb2312', '登录'),
        'lbLanguage'=>'',
        'hidPdrs'=>'',
        'hidsc'=>''
    );
    $con2=login_post($url,$cookie,http_build_query($post));

	
   //若登陆信息输入有误
    if(!preg_match("/xs_main/", $con2)){
		//echo $con2;
        echo '<h2>&nbsp;<i class="weui_icon_warn"></i>&nbsp;您的账号 or 密码输入错误，或者是选择了无效的学年/学期，请<a href="/login_grade.php">返回</a>重新输入</h2>';
        exit();
    }


    //Login done.
    require_score($cookie, $current_year, $current_term);    //获取加权平均分和成绩明细
    
	function require_score($cookie, $current_year, $current_term){
		// 不知道为什么，不提交姓名信息也能查询
		// preg_match_all('/<span id="xhxm">([^<>]+)/', $con2, $xm);   //正则出的数据存到$xm数组中
		// print_r($xm);
		// $xm[1][0]=substr($xm[1][0],0,-4);  //字符串截取，获得姓名
		$url2="http://202.119.160.5/xscjcx.aspx?xh=".$_SESSION['xh'];
		$viewstate=login_post($url2,'');
		preg_match_all('/<input type="hidden" name="__VIEWSTATE" value="([^<>]+)" \/>/', $viewstate, $vs);
		$state=$vs[1][0];  //$state存放一会post的__VIEWSTATE
		
		//查询某一学期的成绩
		$post=array(
		 '__EVENTTARGET'=>'',
		 '__EVENTARGUMENT'=>'',
		 '__VIEWSTATE'=>$state,
		 'hidLanguage'=>'',
		   'ddlXN'=>$current_year,  //当前学年
		   'ddlXQ'=>$current_term,  //当前学期
		   'ddl_kcxz'=>'',
		   'btn_xq'=>'%D1%A7%C6%DA%B3%C9%BC%A8'  //“学期成绩”的gbk编码，视情况而定
		   );
		$content=login_post($url2,$cookie,http_build_query($post)); //获取原始数据
		$content=get_td_array($content);    //table转array
		
		//查询总成绩
		$post_allgrade=array(
		 '__EVENTTARGET'=>'',
		 '__EVENTARGUMENT'=>'',
		 '__VIEWSTATE'=>$state,
		 'hidLanguage'=>'',
		   'ddlXN'=>$current_year,  //当前学年
		   'ddlXQ'=>$current_term,  //当前学期
		   'ddl_kcxz'=>'',
		   'btn_zcj'=>'%C0%FA%C4%EA%B3%C9%BC%A8'  //课程最高成绩-gbk
		   );
		$content_allgrade=login_post($url2,$cookie,http_build_query($post_allgrade)); //获取原始数据
		$content_allgrade=get_td_array($content_allgrade);    //table转array
		
	//	print_r($content_allgrade);
		//查询已获取学分
		$post_getgrade=array(
		 '__EVENTTARGET'=>'',
		 '__EVENTARGUMENT'=>'',
		 '__VIEWSTATE'=>$state,
		 'hidLanguage'=>'',
		   'ddlXN'=>$current_year,  //当前学年
		   'ddlXQ'=>$current_term,  //当前学期
		   'ddl_kcxz'=>'',
		   'Button1' => '%B3%C9%BC%A8%CD%B3%BC%C6'  //成绩统计-gbk
		   );
		   
		$content_getgrade=login_post($url2,$cookie,http_build_query($post_getgrade));
	//	print_r($content_getgrade);
		$html = new simple_html_dom(); 
		$html->load($content_getgrade);
		foreach($html->find('span[id=xftj]') as $v){
			$arr = $v->find('b', 0)->plaintext;
		}
		foreach($html->find('table.datelist') as $tr){
			foreach($tr->find('td') as $td){
				$table[] = $td->plaintext;
			}
		}
		$html->clear(); 
		preg_match_all('/获得学分(.*?)；/', $arr, $allgrade);
		//计算总的加权分数和总的GPA
		$i = 5;         //从array[5]开始是有效信息
		$all_value = 0; //总的学分权值
		$all_GPA = 0;   //总的GPA*分数
		$all_number_of_lesson = 0;  //总的课程数
		$all_number_of_lesson_with_nopass = 0; //包含未过课程的总数
		$all_number_of_lesson_with_public = 0;
		$all_score_of_lesson_with_public = $table[17];
		$all_number_of_lesson_with_wantonly = 0;
		$all_score_of_lesson_with_wantonly = $table[7];
		$all_number_of_lesson_with_others = 0;
		$all_score_of_lesson_with_others = 0;
		//计算总和的东西，学分/GPA	
		while(isset($content_allgrade[$i][4])){
			if ($content_allgrade[$i][5] == iconv("utf-8","gb2312//IGNORE","公选")){
				//计算公选课课程数和总学分
				$all_number_of_lesson_with_public ++;
				//$all_score_of_lesson_with_public += $content_allgrade[$i][6];
				$i++;
			}
			elseif($content_allgrade[$i][5] == iconv("utf-8","gb2312//IGNORE","任选")){
				$all_number_of_lesson_with_wantonly ++;
				//$all_score_of_lesson_with_wantonly += $content_allgrade[$i][6];
				$i++;
			}
			elseif($content_allgrade[$i][5] == iconv("utf-8","gb2312//IGNORE","跨专业选修")){
				$all_number_of_lesson_with_others ++;
				//$all_score_of_lesson_with_others += $content_allgrade[$i][6];
				$i++;
			}
			else{
				//$all_value += $content_allgrade[$i][6];	//已修总学分
				$all_GPA += ($content_allgrade[$i][6] * $content_allgrade[$i][7]); 
				$i++;	
			}
		}
		$all_value = $allgrade[1][0] - $all_score_of_lesson_with_public - $all_score_of_lesson_with_others;
		echo '<div class="weui_cells_title">综合统计</div>';
		echo '<div class="weui_cells">';
		echo '<div class="weui_cell">';
		echo '<div class="weui_cell_bd weui_cell_primary">';
		printf("大学获得任选课学分: %.2d 还需学分：%.2d ",$all_score_of_lesson_with_wantonly,$table[9]);
		echo '</div>';
		echo '</div>'; 
		echo '<div class="weui_cell">';
		echo '<div class="weui_cell_bd weui_cell_primary">';
		printf("大学获得公选课学分: %.2d 还需学分：%.2d ",$all_score_of_lesson_with_public,$table[19]);
		echo '</div>';
		echo '</div>';
		echo '<div class="weui_cell">';
		echo '<div class="weui_cell_bd weui_cell_primary">';
		printf("大学获得跨专业课学分: %.2d  还需学分：%.2d ",$all_score_of_lesson_with_others,$table[14]);
		echo '</div>';
		echo '</div>'; 
		echo '<div class="weui_cell">';
		echo '<div class="weui_cell_bd weui_cell_primary">';
		printf("大学获得总课程学分: %.2lf ",$allgrade[1][0]);
		echo '</div>';
		echo '</div>'; 
		echo '<div class="weui_cell">';
		echo '<div class="weui_cell_bd weui_cell_primary">';
		printf("获得平均学分绩点（GPA）: %.2lf",$all_GPA / $all_value);
		echo '</div>';
		echo '</div>'; 
		echo '
			</div>';
		//输出课程明细,主修课程
		echo '<div class="weui_cells_title">当前学期课程明细</div>';
		echo '<div class="weui_cells">';
		$i = 5;
		while(isset($content[$i][7])){   
			if ($content[$i][9] == 0){
				echo '<div class="weui_cell">';
				echo '<div class="weui_cell_bd weui_cell_primary">';
				echo iconv("gb2312","utf-8//IGNORE",$content[$i][3])."  分数: ".iconv("gb2312","utf-8//IGNORE",$content[$i][8])."   课程学分: ".$content[$i][6];
				echo '</div>';
				echo '</div>';    
			}  
			$i++;
		}   
		echo '</div>';
		echo '<a class="weui_btn weui_btn_default" href="javascript:;" onClick="location.href=document.referrer">返回</a>';
	}
	//table转array
    function get_td_array($table) {
        $table = preg_replace("'<table[^>]*?>'si","",$table);
        $table = preg_replace("'<tr[^>]*?>'si","",$table);
        $table = preg_replace("'<td[^>]*?>'si","",$table);
        $table = str_replace("</tr>","{tr}",$table);
        $table = str_replace("</td>","{td}",$table);
            //去掉 HTML 标记
        $table = preg_replace("'<[/!]*?[^<>]*?>'si","",$table);
            //去掉空白字符
        $table = preg_replace("'([rn])[s]+'","",$table);
        $table = preg_replace('/&nbsp;/',"",$table);
        $table = str_replace(" ","",$table);
        $table = str_replace(" ","",$table);
        $table = explode('{tr}', $table);
        array_pop($table);
        foreach ($table as $key=>$tr) {
            $td = explode('{td}', $tr);
            array_pop($td);
            $td_array[] = $td;
        }
        return $td_array;
    }
	
	//error_reporting(E_ALL^E_NOTICE^E_WARNING);
?>
</body>
</html>
