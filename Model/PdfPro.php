<?php
/**
 * PDF Pro Class
 *
 * @author		VnEcoms Team <support@vnecoms.com>
 * @website		http://www.vnecoms.com
 */
class PdfPro
{
    const PDF_PRO_WSDL			= 'http://www.easypdfinvoice.com/api/soap?wsdl';
    const PDF_PRO_XMLRPC		= 'http://www.easypdfinvoice.com/api/xmlrpc/';
    const PDF_PRO_API_USERNAME	= 'test';
    const PDF_PRO_API_PASSWORD	= 'admin123';
    
    protected $_api_key;
    /**
     * Encode text
     * @param string $code
     * @param string $key
     * @return string
     */
    protected function _encode($code,$key){
    	$code = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $code, MCRYPT_MODE_CBC, md5(md5($key))));
    	return $code;
    }
    /**
     * Decode text
     * @param string $encoded
     * @param string $key
     * @return string
     */
    protected function _decode($encoded,$key){
    	return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($encoded), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
    }
    
    public function __construct($apiKey){
    	$this->_api_key = $apiKey;
    	return $this;
    }
    
    /**
     * Gets the detailed PDF Pro version information
     *
     * @return array
     */
    public static function getVersionInfo()
    {
    	return array(
    			'major'     => '1',
    			'minor'     => '0',
    			'revision'  => '',
    			'patch'     => '',
    			'stability' => '',
    			'number'    => '',
    	);
    }
    
    /**
     * Gets the current PDF Pro version string
     *
     * @return string
     */
    public static function getVersion()
    {
    	$i = self::getVersionInfo();
    	return trim("{$i['major']}.{$i['minor']}.{$i['revision']}" . ($i['patch'] != '' ? ".{$i['patch']}" : "")
    	. "-{$i['stability']}{$i['number']}", '.-');
    }
    
    /**
     * Send data to server return content of PDF invoice
     * @param array $data
     * @return array
     */
    public function getPDF($data = array()){
    	if(class_exists('SoapClient')){
	    	$client 			= new SoapClient(self::PDF_PRO_WSDL);
	    	$session 			= $client->login(self::PDF_PRO_API_USERNAME, self::PDF_PRO_API_PASSWORD);
	    	$result 			= $client->call($session, 'pdfpro.getPdf',array($this->_encode(json_encode($data),$this->_api_key),$this->_api_key,$this->getVersion()));
	    	$result['content']	= $this->_decode($result['content'],$this->_api_key);
	    	$client->endSession($session);
    	}else if(class_exists('Zend_XmlRpc_Client')){
    		$client 			= new Zend_XmlRpc_Client(self::PDF_PRO_XMLRPC);
    		$session 			= $client->call('login', array(self::PDF_PRO_API_USERNAME, self::PDF_PRO_API_PASSWORD));
    		$result 			= $client->call('call', array($session, 'pdfpro.getPdf', array(array($this->_encode(json_encode($data),$this->_api_key)), $this->_api_key)));
    		$result['content']	= $this->_decode($result['content'],$this->_api_key);
    		$client->call('endSession', array($session));
    	}else{
    		$result = array('success'=>false, 'msg'=>"Your website does not support for PDF Pro");
    	}
    	return $result;
    }
}