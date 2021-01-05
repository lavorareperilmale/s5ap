<?php 
   
namespace lavorareperilmale\s5ap;
      
require_once(dirname(__FILE__) . "/config/config.php");
   
class SoapFixer extends \SoapClient {
      
   function __construct($wsdl, $options = null) {
      parent::__construct($wsdl, $options);
   }     
         
   function __doRequest($request, $location, $action, $version, $one_way = NULL) {
      $dom = new \DOMDocument('1.0');

      try {
         //loads the SOAP request to the Document
         $dom->loadXML($request);
      } catch (\DOMException $e) {
         die('Parse error with code ' . $e->code);
      }
      
      //create a XPath object to query the request
      $path = new \DOMXPath($dom);
      
      $pIvaToFix = $path->query('//ns1:pIva');
      foreach ($pIvaToFix as $i => $node) {
        $node->nodeValue = str_pad($node->nodeValue, 11, '0', STR_PAD_LEFT);
      }
      
      
      //save the modified SOAP request
      $request = $dom->saveXML();
      
      //doRequest
      return parent::__doRequest($request, $location, $action, $version, $one_way);
   }  
   
}
?>