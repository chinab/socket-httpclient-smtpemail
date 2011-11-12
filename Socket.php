<?php
/**
 * Socket����
 * @author �춫��
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
			$this->throwError('����ʧ�ܣ�'.$this->errno.' '.$this->errstr) ;
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
		    	            $this->throwError('��Ч������ '.htmlentities($line));
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
	 * ���ó�ʱʱ�䣬Ĭ��30
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
 * ���ٷ�����
 * 		HttpClient::quickGet("http://www.baidu.com?key=American") ;
 * 		HttpClient::quickPost("http://www.baidu.com",array("uid"=>"xdh2571","password"=>"123456")) ;
 * ʵ������
 * 		$client = new HttpClient("127.0.0.1",80) ; 							//�˿�Ĭ��80
 * 		$client->setDebug = true ; 											//����Ĭ��false
 * 		$client->setEnctype = 1 ; 											//post��ʽ��1��ʾ֧���ύ�ļ����� Ĭ��Ϊ0��֧��
 * 		$client->setData(array("uid"=>"xdh2571","password"=>"123456")) ; 	//����post���ݣ�get��ʽ�ɺ���
 * 		$client->setAttachment(array("file1","file2")) ; 					//�����ύ�ĸ����ؼ�����
 * 		$client->submit("/test.php","POST"); 								//Ĭ��post�ύ
 *
 */
class HttpClient extends SocketBase
{
	private $method ; //���󷽷���Ĭ��ΪPOST
	private $path ;  //����ҳ��·��
	private $enctype = 0 ; //postʱ 1��ʾ mutipart/form-data
	private $data = array() ; //postʱ�ύ������(���ƣ�ֵ)
	private $attachment = array() ; //�ύ�ĸ���(�����Ŀؼ�����)

	/**
	 * ���췽��
	 * @param string $host ���ӵ�������ַ
	 * @param string $port �˿ڣ�Ĭ��80
	 */
	public function HttpClient($host,$port=80)
	{
		$this->SoketBase($host,$port) ;
		$this->mode = 1 ;
	}
	
	/**
	 * �ύִ��
	 * @param string $path ҳ��·��,�����������Ŀ¼
	 * @param string $method �ύ������Ĭ��POST
	 * @return string ���ط�������Ӧ����
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
			if($dt == ""){ //û�����ݴ���
				
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
	 * post����enctypeΪmultipart/form-data
	 */
	public function setEnctype()
	{
		$this->enctype = 1 ;
	}
	/**
	 * �첽post�ύ������
	 * @param array $data ���ݼ�ֵ��
	 */
	public function setData($data)
	{
		$this->data = $data ;
	}
	/**
	 * �첽�ϴ��ļ��ؼ�����
	 * @param array $attachment �ļ��ؼ�����
	 */
	public function setAttachment($attachment)
	{
		$this->attachment = $attachment ;
	}
	
	/**
	 * �����첽Get�ύ
	 * @param string $url ������url��ַ����������
	 * @return string ���ط�������Ӧ����
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
     * �����첽Post�ύ
     * @param string $url ������url��ַ
     * @param array $data �ύ�����ݼ�ֵ��
     * @return string ���ط�������Ӧ����
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
 * ���ٷ�������֧���ϴ�����
 * 		SMTPEmail::quickSend($email,$password,$to,$subject,$content,$mime,$username) ; 
 * ʵ������
 * 		$smtp = new SMTPEmail($email,$password,$port) ; 						//�˿�Ĭ��80
 * 		$smtp->setLocalAttaches(array("F:/a.txt","F:/b.pic")) ; 				//���ñ��ط����������ļ�·��
 * 		$smtp->setUploadAttaches(array("file1","file2")) ; 						//���ÿͻ����ϴ��ĸ����ؼ�����
 * 		$smtp->Send($email,$password,$to,$subject,$content,$mime,$username) ;	//mime: text/plain��text/html
 *
 */
class SMTPEmail extends SocketBase 
{
	private $emailaddr ;
	private $password ;
	private $user ;
	private $boundary ; 
	private $attachState = false ; //�Ƿ��и�����Ĭ��û��
	private $localAttaches = array(); //���ط������ϸ�������������ʽ����ļ�·��
	private $uploadAttaches =array() ; //�ͻ����ϴ��ĸ�������������ʽ��ſͻ����ϴ��ؼ�����
	private $encoding = 'gbk' ; //�ʼ����룬Ĭ��gbk
	private $encodeMode = 2 ; //Ĭ��2,utf-8Ϊ1,gbkΪ2
	private $mime = 'text/plain' ; //Content-Type:Ĭ����text/plain
	
	
	/**
	 * ���췽��
	 * @param string $emailaddr �����˵�ַ
	 * @param string $password ����
	 * @param string $port �˿ڣ�Ĭ��ֵ25
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
		date_default_timezone_set('PRC') ; //�����й�ʱ��
	}
	
	/**
	 * �����ʼ�
	 * @param string $to �ռ��ˣ������";"�ָ�
	 * @param string $subject �ʼ�����
	 * @param string $content �ʼ�����
	 * @param string $username ���������ƣ�������ʾ�����˵��û���
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
		
		//�ܵ�{�ı�+����}�ָ�
		$header .= "Content-Type: multipart/mixed; boundary=\"{$this->boundary}\"\r\n" ;
		$header .= "Content-Transfer-Encoding: 8bit\r\n" ;
		$header .= "X-Power-By: Xudonghai QQ:573689821\r\n" ;
		$header .= "MIME-Version: 1.0\r\n" ;
		$header .= "\r\n" ;
		
		//plain��html�ָ�
		$header .= "--{$this->boundary}\r\n" ;
		$boundary_2 = $this->getBoundary() ;
		$header .= "Content-Type: multipart/alternative; boundary=\"{$boundary_2}\"\r\n" ;
		$header .= "\r\n" ;
		
		//plain��html����
		$header .= "--{$boundary_2}\r\n" ;
		$header .= "Content-Type: {$this->mime};charset=\"{$this->encoding}\"\r\n" ;
		$header .= "Content-Transfer-Encoding: base64\r\n" ;
		$header .= "\r\n" ;
		$header .= $this->encode($content)."\r\n" ;
		$header .= "\r\n" ;
		$header .= "--{$boundary_2}--\r\n" ;
		$header .= "\r\n" ;
		
		//����
		if($this->attachState){
			$header .= $this->buildAttaches() ;
		}
		
		//����
		$header .= "--{$this->boundary}--\r\n" ;
		$header .= ".\r\n" ;
		return $header ;
	}
	
	private function buildAttaches()
	{
		$header = "" ;
		//���ظ���
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
		//Զ���ϴ�����
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
	 * ���÷��������ظ���·��
	 * @param array $filepaths ����·��
	 */
	public function setLocalAttaches($filepaths)
	{
		$this->localAttaches = $filepaths ;
		$this->attachState = true ;
	}
	/**
	 * �����ϴ�����
	 * @param array $fields �ϴ��ؼ�����
	 */
	public function setUploadAttaches($fields)
	{
		$this->uploadAttaches = $fields ;
		$this->attachState = true ;
	}
	
	/**
	 * �����ʼ�����
	 * @param string $encoding ����utf-8����gbk��Ĭ��Ϊgbk
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
	 * ����Content-Type
	 * @param string $mime ��������Ϊtext/plain
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
	 * ���ٷ��ʼ�����
	 * @param string $email �����˵�ַ
	 * @param string $password ����
	 * @param string $to �ռ��ˣ������";"�ָ�
	 * @param string $subject �ʼ�����
	 * @param string $content �ʼ�����
	 * @param string $mime �ʼ�����
	 * @param string $username ����������
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