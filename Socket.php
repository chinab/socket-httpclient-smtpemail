<?php
/**
 * Socket基类
 * @author 徐东海
 *
 */
abstract class SocketBase
{
	protected $host ;
	protected $port ;
	protected $fp ;
	protected $errno ;
	protected $errstr ;
	protected $timeout = 20 ;
	protected $mode ; // 1:HttpClient; 2:SMTPEmail
	public $debug = false ;

	protected function SoketBase($host,$port)
	{
		$this->host = $host ;
		$this->port = $port ;
		$this->fp = fsockopen($this->host,$this->port,$this->errno,$this->errstr,$this->timeout) ;
		if(!$this->fp){
			$this->throwError('连接失败：'.$this->errno.' '.$this->errstr) ;
			exit;
		}
	}

	protected function sendRequest($commend)
	{
		fwrite($this->fp,$commend) ;
		$result = $this->getResponse() ;
		$this->debug($commend,$result);
		return $result ;
	}

	protected function getResponse()
	{
		$response = $line = '' ;
		switch ($this->mode){
			case 1:{
				$firstLine = true ;
				while (!feof($this->fp)) {
					$line = fgets($this->fp, 128);
		    	    if ($firstLine) {
		    	        $firstLine = false;
		    	        if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
		    	            $this->throwError('无效的请求： '.htmlentities($line));
		    	            return false;
		    	        }
		    	        $http_version = $m[1]; // not used
		    	        $this->status = $m[2];
		    	        $status_string = $m[3]; // not used
		    	    }
					$response .= $line ;
				}
				break ;
			}
			case 2:{
				$starttime = time() ;
				$response = $line = "";
				while(true){
					$line = fgets($this->fp,4096);
					$response .= $line;
					if(strpos($line,"\r\n")!==false){
						break;
					}
					if((time() - $starttime) > 30){
						break;
					}
				}
				break;
			}
		}
		return $response ;
	}

	/**
	 * 设置超时时间，默认30
	 * @param int $timeout
	 */
	public function setTimeout($timeout)
	{
		$this->timeout = $timeout ;
	}
	
	protected function throwError($msg)
	{
		$this->close();
		throw new Exception($msg) ;
	}

	protected function close()
	{
		fclose($this->fp) ;
	}
	
	protected function getBoundary()
	{
		return substr(md5(rand(0,32000)),0,10);
	}
	
	protected function debug($request,$response)
	{
		if($this->debug){
			if(!empty($request)){
				echo '<b>Client:</b><br/>' ;
				echo $request.'<br/>' ;
			}
			if(!empty($response)){
				echo '<b>Server:</b><br/>' ;
				echo $response.'<br/>' ;
			}
			echo '<font color="red">========================================================================</font><br/>' ;
		}
	}
}

/**
 * 快速方法：
 * 		HttpClient::quickGet("http://www.baidu.com?key=American") ;
 * 		HttpClient::quickPost("http://www.baidu.com",array("uid"=>"xdh2571","password"=>"123456")) ;
 * 实例化：
 * 		$client = new HttpClient("127.0.0.1",80) ; 							//端口默认80
 * 		$client->setDebug = true ; 											//调试默认false
 * 		$client->setEnctype = 1 ; 											//post方式，1表示支持提交文件数据 默认为0不支持
 * 		$client->setData(array("uid"=>"xdh2571","password"=>"123456")) ; 	//设置post数据，get方式可忽略
 * 		$client->setAttachment(array("file1","file2")) ; 					//设置提交的附件控件名称
 * 		$client->submit("/test.php","POST"); 								//默认post提交
 *
 */
class HttpClient extends SocketBase
{
	private $method ; //请求方法，默认为POST
	private $path ;  //请求页面路径
	private $enctype = 0 ; //post时 1表示 mutipart/form-data
	private $data = array() ; //post时提交的数据(名称，值)
	private $attachment = array() ; //提交的附件(附件的控件名称)

	/**
	 * 构造方法
	 * @param string $host 连接的主机地址
	 * @param string $port 端口，默认80
	 */
	public function HttpClient($host,$port=80)
	{
		$this->SoketBase($host,$port) ;
		$this->mode = 1 ;
	}
	
	/**
	 * 提交执行
	 * @param string $path 页面路径,相对于主机根目录
	 * @param string $method 提交方法，默认POST
	 * @return string 返回服务器响应内容
	 */
	public function submit($path,$method="POST")
	{
		$this->path = $path ;
		$this->method = strtoupper($method) ;
		$result = $this->sendRequest($this->buildRequest()) ;
		$this->close() ;
		return $result ;
	}
	
	private function buildRequest()
	{
		$headers = "" ;
		$headers .= "{$this->method} {$this->path} HTTP/1.0\r\n" ;
		$headers .= "Host: {$this->host}\r\n" ;
		if($this->method == 'GET'){
			$headers .= "\r\n" ;
			return $headers ;
		}
		$dt = "" ;
		if($this->enctype){
			$boundary = $this->getBoundary();
			$headers .= "Content-Type: multipart/form-data; boundary=\"{$boundary}\"\r\n" ;
			if(!empty($this->data)){
				foreach ($this->data as $key=>$value){
					$dt .= "--{$boundary}\r\n" ;
					$dt .= "Content-Disposition: form-data; name=\"{$key}\"\r\n" ;
					$dt .= "\r\n" ;
					$dt .= "{$value}\r\n" ;
					$dt .= "\r\n" ;
				}
			}
			if(!empty($this->attachment)){
				foreach ($this->attachment as $field){
					$files = $_FILES[$field] ;
					$dt .= "--{$boundary}\r\n" ;
					$dt .= "Content-Disposition: form-data; name=\"{$field}\"; filename=\"{$files['name']}\"\r\n" ;
					$dt .= "Content-Type: {$files['type']}\r\n" ;
					$dt .= "\r\n" ;
					$dt .= "".join("",file($files['tmp_name']))."\r\n" ;
					$dt .= "\r\n" ;
				}
			}
			if($dt == ""){ //没有数据处理
				
			}
			$dt .= "--{$boundary}--\r\n" ;
		}
		else{
			$headers .= "Content-Type: application/x-www-form-urlencoded\r\n" ;
			if(!empty($this->data)){
				foreach ($this->data as $key=>$value){
					$dt .= urlencode($key)."=".urlencode($value)."&" ;
				}
				$dt = substr($dt,0,-1) ;
			}
		}
		$headers .= "Content-Length: ".strlen($dt)."\r\n" ;
		$headers .= "Connection: close\r\n" ;
		$headers .= "\r\n" ;
		$headers .= "{$dt}\r\n" ;
		return $headers ;
	}

	/**
	 * post设置enctype为multipart/form-data
	 */
	public function setEnctype()
	{
		$this->enctype = 1 ;
	}
	/**
	 * 异步post提交的数据
	 * @param array $data 数据键值对
	 */
	public function setData($data)
	{
		$this->data = $data ;
	}
	/**
	 * 异步上传文件控件名称
	 * @param array $attachment 文件控件名称
	 */
	public function setAttachment($attachment)
	{
		$this->attachment = $attachment ;
	}
	
	/**
	 * 快速异步Get提交
	 * @param string $url 完整的url地址，包含参数
	 * @return string 返回服务器响应内容
	 */
	public static function quickGet($url) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        if (isset($bits['query'])) {
            $path .= '?'.$bits['query'];
        }
        $client = new HttpClient($host, $port);
        return $client->submit($path,'GET') ;
    }
    /**
     * 快速异步Post提交
     * @param string $url 完整的url地址
     * @param array $data 提交的数据键值对
     * @return string 返回服务器响应内容
     */
    public static function quickPost($url, $data) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        $client = new HttpClient($host, $port);
        $client->setData($data);
        return $client->submit($path) ;
        if (!$client->post($path, $data)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
}

/**
 * 快速方法：不支持上传附件
 * 		SMTPEmail::quickSend($email,$password,$to,$subject,$content,$mime,$username) ; 
 * 实例化：
 * 		$smtp = new SMTPEmail($email,$password,$port) ; 						//端口默认80
 * 		$smtp->setLocalAttaches(array("F:/a.txt","F:/b.pic")) ; 				//设置本地服务器附件文件路径
 * 		$smtp->setUploadAttaches(array("file1","file2")) ; 						//设置客户端上传的附件控件名称
 * 		$smtp->Send($email,$password,$to,$subject,$content,$mime,$username) ;	//mime: text/plain、text/html
 *
 */
class SMTPEmail extends SocketBase 
{
	private $emailaddr ;
	private $password ;
	private $user ;
	private $boundary ; 
	private $attachState = false ; //是否有附件，默认没有
	private $localAttaches = array(); //本地服务器上附件，以数组形式存放文件路径
	private $uploadAttaches =array() ; //客户端上传的附件，以数组形式存放客户端上传控件名称
	private $encoding = 'gbk' ; //邮件编码，默认gbk
	private $encodeMode = 2 ; //默认2,utf-8为1,gbk为2
	private $mime = 'text/plain' ; //Content-Type:默认是text/plain
	
	
	/**
	 * 构造方法
	 * @param string $emailaddr 发件人地址
	 * @param string $password 密码
	 * @param string $port 端口，默认值25
	 */
	public function SMTPEmail($emailaddr,$password,$port=25)
	{
		$this->emailaddr = $emailaddr ;
		$this->password = $password ;
		$emalarray = explode('@',$emailaddr) ;
		$this->user = trim($emalarray[0]) ;
		$this->host = 'smtp.'.trim($emalarray[1]) ;
		$this->port = $port ;
		$this->SoketBase($this->host,$this->port) ;
		$this->mode = 2 ;
		date_default_timezone_set('PRC') ; //设置中国时区
	}
	
	/**
	 * 发送邮件
	 * @param string $to 收件人，多个用";"分割
	 * @param string $subject 邮件标题
	 * @param string $content 邮件内容
	 * @param string $username 发件人名称，空则显示发件人的用户名
	 */
	public function send($to,$subject,$content,$username="")
	{
		$this->sendCommand("HELO {$this->host}\r\n",'250');
		$this->sendCommand("EHLO {$this->host}\r\n",'250');
		$this->sendCommand("AUTH LOGIN\r\n",'334');
		$this->sendCommand($this->encode($this->user)."\r\n",'334');
		$this->sendCommand($this->encode($this->password)."\r\n",'334');
		$this->buildEmail($to,$subject,$content,$username);
		$this->sendCommand("QUIT\r\n",'221');
	}
	
	private function sendCommand($command,$okstr)
	{
		$this->sendRequest($command) ;
		$result = $this->getResponse() ;
		$responseCode = substr($result,0,3) ;
		if(!$responseCode == $okstr){
			$this->throwError('Error:'.$command.' responseCode:'.$responseCode);
		}
		return $result ;
	}
	
	private function buildEmail($to,$subject,$content,$username)
	{
		$command = "" ;
		$to = explode(';',$to) ;
		foreach ($to as $t){
			$this->sendRequest("MAIL FROM:<{$this->emailaddr}>\r\n") ;
			$this->sendRequest("RCPT TO:<{$t}>\r\n") ;
			$this->sendRequest("DATA\r\n") ;
			$header = $this->buildHeader($to,$subject,$content,$username) ;
			$this->sendRequest($header) ;
		}
	}
	
	private function buildHeader($to,$subject,$content,$username)
	{
		$username = $username=="" ? $this->user : $username ;
		$this->boundary = $this->getBoundary() ;
		$header = "" ;
		$header .= "Date: ".date('r')."\r\n" ;
		$header .= "Subject: ".$this->encode($subject,$this->encodeMode)."\r\n" ; //base64
		$header .= "Message-Id: <".md5(uniqid(microtime()))."@{$this->host}>\r\n" ;
		$header .= "From: {$this->encode($username,$this->encodeMode)}<{$this->emailaddr}>\r\n" ;
		$header .= "To: " ;
		foreach ($to as $t){
			$tName = substr($t,0,strpos($t,'@')) ;
			$header .= "{$this->encode($tName,$this->encodeMode)}<{$t}>;" ;
		}
		$header .= "\r\n" ;
		
		//总的{文本+附件}分割
		$header .= "Content-Type: multipart/mixed; boundary=\"{$this->boundary}\"\r\n" ;
		$header .= "Content-Transfer-Encoding: 8bit\r\n" ;
		$header .= "X-Power-By: Xudonghai QQ:573689821\r\n" ;
		$header .= "MIME-Version: 1.0\r\n" ;
		$header .= "\r\n" ;
		
		//plain或html分割
		$header .= "--{$this->boundary}\r\n" ;
		$boundary_2 = $this->getBoundary() ;
		$header .= "Content-Type: multipart/alternative; boundary=\"{$boundary_2}\"\r\n" ;
		$header .= "\r\n" ;
		
		//plain或html内容
		$header .= "--{$boundary_2}\r\n" ;
		$header .= "Content-Type: {$this->mime};charset=\"{$this->encoding}\"\r\n" ;
		$header .= "Content-Transfer-Encoding: base64\r\n" ;
		$header .= "\r\n" ;
		$header .= $this->encode($content)."\r\n" ;
		$header .= "\r\n" ;
		$header .= "--{$boundary_2}--\r\n" ;
		$header .= "\r\n" ;
		
		//附件
		if($this->attachState){
			$header .= $this->buildAttaches() ;
		}
		
		//结束
		$header .= "--{$this->boundary}--\r\n" ;
		$header .= ".\r\n" ;
		return $header ;
	}
	
	private function buildAttaches()
	{
		$header = "" ;
		//本地附件
		if(!empty($this->localAttaches)){
			foreach ($this->localAttaches as $filename){
				$header .= "--{$this->boundary}\r\n" ;
				$header .= "Content-Type: application/octet-stream;charset=\"{$this->encoding}\";name=\"{$this->encode(basename($filename),$this->encodeMode)}\"\r\n" ;
				$header .= "Content-Disposition: attachment; filename=\"{$this->encode(basename($filename),$this->encodeMode)}\"\r\n" ;
				$header .= "Content-Transfer-Encoding: base64\r\n" ;
				$header .= "\r\n" ;
				$handle = fopen($filename,'rb') ;
				$str = fread($handle,filesize($filename)) ;
				fclose($handle) ;
				$header .= chunk_split($this->encode($str),80,"\r\n") ;
				$header .= "\r\n" ;
			}
		}
		//远程上传附件
		if(!empty($this->uploadAttaches)){
			foreach ($this->uploadAttaches as $field) {
				$files = $_FILES[$field] ;
				$header .= "--{$this->boundary}\r\n" ;
				$header .= "Content-Type: application/octet-stream;charset=\"{$this->encoding}\";name=\"{$this->encode($files['name'],$this->encodeMode)}\"\r\n" ;
				$header .= "Content-Disposition: attachment; filename=\"{$this->encode($files['name'],$this->encodeMode)}\"\r\n" ;
				$header .= "Content-Transfer-Encoding: base64\r\n" ;
				$header .= "\r\n" ;
				$str = "".join("",file($files['tmp_name']))."\r\n" ;
				$header .= chunk_split($this->encode($str),80,"\r\n") ;
				$header .= "\r\n" ;
			}
		}
		return $header ;
	}
	
	/**
	 * 设置服务器本地附件路径
	 * @param array $filepaths 附件路径
	 */
	public function setLocalAttaches($filepaths)
	{
		$this->localAttaches = $filepaths ;
		$this->attachState = true ;
	}
	/**
	 * 设置上传附件
	 * @param array $fields 上传控件名称
	 */
	public function setUploadAttaches($fields)
	{
		$this->uploadAttaches = $fields ;
		$this->attachState = true ;
	}
	
	/**
	 * 设置邮件编码
	 * @param string $encoding 编码utf-8或者gbk，默认为gbk
	 */
	public function setEncoding($encoding)
	{
		$this->encoding = strtolower($encoding) ;
		switch($this->encoding){
			case 'utf-8': $this->encodeMode = 1 ; break ;
			case 'gb2312': $this->encodeMode = 2 ; break ;
			case 'gbk' : $this->encodeMode = 2 ; break ;
		}
	}
	/**
	 * 设置Content-Type
	 * @param string $mime 不设置则为text/plain
	 */
	public function setMime($mime='text/html')
	{
		$this->mime = $mime ;
	}
	
	private function encode($str,$mode=0)
	{
		switch($mode){
			case 0:
				return base64_encode($str);
			case 1:
				return "=?UTF8?B?".base64_encode($str)."?=";
			case 2:
				return "=?gbk?B?".base64_encode($str)."?=" ;
			default:
				return $str;
		}
	}
	
	/**
	 * 快速发邮件方法
	 * @param string $email 发件人地址
	 * @param string $password 密码
	 * @param string $to 收件人，多个以";"分割
	 * @param string $subject 邮件标题
	 * @param string $content 邮件内容
	 * @param string $mime 邮件类型
	 * @param string $username 发件人名称
	 * @throws Exception
	 */
	public static function quickSend($email,$password,$to,$subject,$content,$mime,$username)
	{
		try {
			$smtp = new SMTPEmail($email,$password) ;
			$smtp->setMime($mime) ;
			$smtp->send($to,$subject,$content,$username);
		}
		catch (Exception $Err){
			throw new Exception($Err->getMessage()) ;
		}
	}
}
?>