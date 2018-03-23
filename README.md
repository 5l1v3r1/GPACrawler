---
title: PHP爬虫-正方教务系统爬取成绩绩点
date: 2018-03-23 13:12:07
categories: 笔记
---

## 前言

从去年寒假开始，学校的一些二级域名就对外网访问进行了限制。而在校内同学们宿舍普遍是用的电信网，要使用内网需要到教学区域连学校的wifi才行。这样是很不方便的。

寒假一直想写个正方教务系统爬虫的，一直拖着拖着的没有完成。开学这一段时间挺闲的，目前完成了正方教务系统爬取成绩绩点和另外两个小项目，一个是正方系统一键报名四六级，一个是物业报修系统一键物业报修。这三个爬虫目前挂在自己的个人服务器上，以某种手段访问内网，后续会转移到内网服务器，转发到外网，提高访问速度。

习惯了，用博客记录自己写的东西，这里简单记录下正方教务系统爬虫成绩绩点的过程。

## 正文

### UI设计

这里提下这个登陆表单和后端的UI界面，这里是借鉴了我在Github上看到的一个类似的项目

https://github.com/wangyufeng0615/bjuthelper

整个项目跟上述的项目有点类似，都是正方系统，仅仅是在一些小小的地方有区别。

![](http://obr4sfdq7.bkt.clouddn.com/psb.png)



### login_grade.php

在上面项目中，个别院校的教务系统是有无需验证码的接口的。而在我们学校是没有的，这里我们需要先获取到验证码

这里用到PHP中的CURL进行获取验证码，并把访问页面的cookie保存到本地。并把验证码图片保存到本地。

```
    $rand_id = rand(100000, 999999);    //for verifycode
    require_verify_code();  //获取验证码
    function require_verify_code(){
        $cookie = dirname(__FILE__).'/cookie/'.$_SESSION['id'].'.txt';    //cookie路径  
        $verify_code_url = "http://localhost/CheckCode.aspx";      //验证码地址
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $verify_code_url);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);                     //保存cookie
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $img = curl_exec($curl);                                            //执行curl
        curl_close($curl);
        global $rand_id;
        $path_of_verifyCode =dirname(__FILE__).'/verifyCodes/verifyCode_'.$rand_id.'.jpg';
        $fp = fopen($path_of_verifyCode,"w");                                  //文件名
        fwrite($fp,$img);                                                   //写入文件
        fclose($fp);
    }
```

login_grade后面的页面就是利用WEUI写得一个提交表单了，这里就不要过多的说了。


### require_grade.php

#### 抓包分析

这里还是先简单的抓包分析，先抓登录页面的POST请求包。

```
POST /Default2.aspx HTTP/1.1
Host: jwjx.njit.edu.cn
Content-Length: 207
Cache-Control: max-age=0
Origin: http://jwjx.njit.edu.cn
Upgrade-Insecure-Requests: 1
Content-Type: application/x-www-form-urlencoded
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
Referer: http://jwjx.njit.edu.cn/
Accept-Encoding: gzip, deflate
Accept-Language: zh-CN,zh;q=0.9
Cookie: UM_distinctid=161e202e63c6d9-094afdbce42fa2-3b60450b-1fa400-161e202e63d5b7; ASP.NET_SessionId=ht5hadjnbrhcbebuqa3khb3s
Connection: close

__VIEWSTATE=dDwtNTE2MjI4MTQ7Oz7j2BjEQ4cDEffr%2BK8yeXHBPnpEJg%3D%3D&txtUserName=username&Textbox1=&TextBox2=password&txtSecretCode=3vrd&RadioButtonList1=%D1%A7%C9%FA&Button1=&lbLanguage=&hidPdrs=&hidsc=
```


简单的看下POST提交的参数有

```
__VIEWSTATE=

txtUserName=

Textbox1=

TextBox2=

txtSecretCode=

RadioButtonList1=

&Button1=&lbLanguage=&hidPdrs=&hidsc=

```

其中txtUserName是学号，TextBox2是密码，txtSecretCode是验证码，后面的参数直接默认提交就行。

这里重点的是__VIEWSTATE参数，这个参数是在登录页面里的。需要去获取这个参数，来找下这个参数的位置。


![](http://obr4sfdq7.bkt.clouddn.com/viewstate.png)

这里我们需要得到这个value值,这里我们需要代入login_grade页面里的cookie放入得到响应的vivewstate值


同样利用curl来访问。

```
    //function: 构造post数据并登陆
    function login_post($url,$cookie,$post){
		global $cookie;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);  //不自动输出数据，要echo才行
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  //重要，抓取跳转后数据
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie); 
        curl_setopt($ch, CURLOPT_REFERER, 'http://localhost/default2.aspx');  //重要，302跳转需要referer，可以在Request Headers找到 
        curl_setopt($ch, CURLOPT_POSTFIELDS,$post);  //post提交数据
        $result=curl_exec($ch);
        curl_close($ch);
        return $result;
    }
```

```
    $url="http://localhost/default2.aspx";  //教务地址
    $con1=login_post($url,$cookie,'');               //登陆
    preg_match_all('/<input type="hidden" name="__VIEWSTATE" value="([^<>]+)" \/>/', $con1, $view); //获取__VIEWSTATE字段并存到$view数组中
```

传入教务网地址，访问登录页面，利用preg_match_all函数正则匹配到页面里的__VIEWSTATE的value值。

参数都准备好了，可以模拟登录了。

```
    $_SESSION['xh']=$_POST['account'];
    $xh=$_POST['account'];
    $pw=$_POST['password'];
    $current_year=$_POST['current_year'];
    $current_term=$_POST['current_term'];
    $code= $_POST['verify_code'];
    $cookie = dirname(__FILE__) . '/cookie/'.$_SESSION['id'].'.txt';
	
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
```

这样我们就成功的登录到教务系统了，接下来我们需要跳转到成绩页面。

同样抓包分析，


```
POST /xscjcx.aspx?xh=1111111 HTTP/1.1
Host: jwjx.njit.edu.cn
Content-Length: 3839
Cache-Control: max-age=0
Origin: http://jwjx.njit.edu.cn
Upgrade-Insecure-Requests: 1
Content-Type: application/x-www-form-urlencoded
User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
Referer: http://jwjx.njit.edu.cn/xscjcx.aspx?xh=208150815&xm=%B3%C9%CF%E9%D4%C0&gnmkdm=N121617
Accept-Encoding: gzip, deflate
Accept-Language: zh-CN,zh;q=0.9
Cookie: UM_distinctid=161e202e63c6d9-094afdbce42fa2-3b60450b-1fa400-161e202e63d5b7; ASP.NET_SessionId=ht5hadjnbrhcbebuqa3khb3s
Connection: close

__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE=dDw&hidLanguage=&ddlXN=2017-2018&ddlXQ=1&ddl_kcxz=&btn_xq=%D1%A7%C6%DA%B3%C9%BC%A8
```



这里是查询用户选择学期的POST请求包，url里的/xscjcx.aspx?xh=中的xh是用户学号。

POST参数里

```
__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE=dDw&hidLanguage=&ddlXN=2017-2018&ddlXQ=1&ddl_kcxz=&btn_xq=%D1%A7%C6%DA%B3%C9%BC%A8
```

这几个参数都可以看出他们的意思，重要的是获取__VIEWSTATE的value值，同样利用一个正则去获取这个值

```
		$url2="http://localhost/xscjcx.aspx?xh=".$_SESSION['xh'];
		$viewstate=login_post($url2,'');
		preg_match_all('/<input type="hidden" name="__VIEWSTATE" value="([^<>]+)" \/>/', $viewstate, $vs);
		$state=$vs[1][0];  //$state存放一会post的__VIEWSTATE
```

获取到所有参数，提交post数据报就可以返回值了。

```
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
```

这里就得到要查询的学期的成绩了，得到的HTML里的table标签的数据，需要转换为数组进行输出。

用到一个函数get_td_array()


```
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
```

从该函数的代码来看，可以看出是利用正则匹配table标签下的tr,td将数据通过遍历存入数组返回。

到这里当前学期的成绩详情都可以获取到了。

下面是绩点的计算，我们学校的系统是把已修学科的学分绩点都列出来了的。我们只需要把数据爬取出来做一个简单的计算就行了。

数据报跟查询学期成绩是差不多的。简单的看下post数据。

```
		$post_allgrade=array(
		 '__EVENTTARGET'=>'',
		 '__EVENTARGUMENT'=>'',
		 '__VIEWSTATE'=>$state,
		 'hidLanguage'=>'',
		   'ddlXN'=>$current_year,  //当前学年
		   'ddlXQ'=>$current_term,  //当前学期
		   'ddl_kcxz'=>'',
		   'btn_zcj'=>'%C0%FA%C4%EA%B3%C9%BC%A8'  //历年成绩-gbk
		   );
		$content_allgrade=login_post($url2,$cookie,http_build_query($post_allgrade)); //获取原始数据
		$content_allgrade=get_td_array($content_allgrade);    //table转array
		
```

```
		//计算总的加权分数和总的GPA
		$i = 5;         //从array[5]开始是有效信息
		$all_value = 0; //总的学分权值
		$all_GPA = 0;   //总的GPA*分数
		$all_number_of_lesson_with_public = 0;
		$all_score_of_lesson_with_public = 0;
		//计算总和的东西，学分/GPA	
		while(isset($content_allgrade[$i][4])){
			if ($content_allgrade[$i][5] == iconv("utf-8","gb2312//IGNORE","公选")){
				//计算公选课课程数和总学分
				$all_number_of_lesson_with_public ++;
				$all_score_of_lesson_with_public += $content_allgrade[$i][6];
				$i++;
			}
			else{
				$all_value += $content_allgrade[$i][6];	//已修总学分
				$all_GPA += ($content_allgrade[$i][6] * $content_allgrade[$i][7]); 
				$i++;	
			}
```

上面是一个简单的计算GPA的过程，简单来说就是一个遍历叠加计算的过程。

到这里require_grade页面的PHP部分差不多都说完了，后面的数据的排版输出了。


## Github

https://github.com/uknowsec/GPACrawler

## Reference

https://github.com/wangyufeng0615/bjuthelper


