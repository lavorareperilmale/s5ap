<?php 

namespace lavorareperilmale\s5ap;

require_once(dirname(__FILE__) . "/config/config.php");

use lavorareperilmale\s5ap\SoapFixer;

class Connettore
{

    // Se settati tramite costruttore, siamo in prod
    // Dati da https://sistemats1.sanita.finanze.it/portale/
    private $username; 
    private $password;
    private $pincode;
    private $cfProprietario;
    private $pivaProprietario;

    private $endpoint; // puo' essere test o prod.
    private $soapClient; // inizializzato alla fine del costruttore

    // Piccolo hack per multiple constructors.
    // Giusto due casi: 
    //   1) senza parametri: ambiente test.
    //   2) con parametri: ambiente prod.
    public function __construct() {
        $argomenti = func_get_args();
        $numero_argomenti = func_num_args();

        if ($numero_argomenti == 0) {
            // Setup valori di test 
            $this->username = TEST_USERNAME;
            $this->password = TEST_PASSWORD;
            $this->pincode = TEST_PINCODE;
            $this->cfProprietario = TEST_CFPROPRIETARIO;
            $this->pivaProprietario = TEST_PIVAPROPRIETARIO;

            $this->endpoint = ENDPOINT_TEST;
        } else if (method_exists($this, $function = '__construct'.$numero_argomenti)) {
            call_user_func_array(array($this, $function), $argomenti);
        }
        // Alla fine setup del SoapClient
        $this->setupSoapClient();
    }

    // Secondo costruttore
    public function __construct5($username, $password, $pincode, $cfProprietario, $pivaProprietario) {
        $this->username = $username;
        $this->password = $password;
        $this->pincode = $pincode;
        $this->cfProprietario = $cfProprietario;
        $this->pivaProprietario = $pivaProprietario;

        $this->endpoint = ENDPOINT_PROD;
        $this->setupSOapClient(false); //FIXME
    }


    // Se non ci sono argomenti, siamo in test
    private function setupSOapClient($prod = false) {
        $context = "";
        if (! $prod ) { 
            // In ambiente test niente check di SSL cert.
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
        } else {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ]); 
        }

        $soapClientParam = array
            (
                "location"          => ENDPOINT_TEST,
                "login"             => $this->username,
                "password"          => $this->password,
                "authentication"    => SOAP_AUTHENTICATION_BASIC,
                "trace"             => false,
                "exceptions"        => false,
                "stream_context" => $context
            );
        $this->soapClient = new SoapFixer(dirname(__FILE__) . "/wsdl/DocumentoSpesa730p.wsdl", 
                                   $soapClientParam);
    }


    private function encryptString($plain_text) {
        $keyFile=fopen(dirname(__FILE__) . "/config/SanitelCF.cer","r");
        $publicKey=fread($keyFile,8192);
        fclose($keyFile);
        openssl_get_publickey($publicKey);
        openssl_public_encrypt($plain_text, $cryptText, $publicKey);
        return base64_encode($cryptText);
    }

    public function invia($dataemissione, 
                          $numerodocumento, 
                          $datapagamento, 
                          $cfcittadino, 
                          $importo, 
                          $pagamentoTracciato="SI", 
                          $flagOpposizione=0,
                          $tipoSpesa="SP",
                          $naturaIVA="N2.1", // non soggetta iva 633/72
                          $tipoDocumento = "F"
                          ) {

        // TODO: validate CF
        //       format importo
        $documentoSpesa = Array (
            "idSpesa" => Array (
                "pIva" => $this->pivaProprietario,
                "dataEmissione" => $dataemissione,
                "numDocumentoFiscale" => Array (
                    "dispositivo" => "1", 
                    "numDocumento" => $numerodocumento
                )
            ),
            "dataPagamento" => $datapagamento,
            "cfCittadino" => $this->encryptString($cfcittadino),
            "voceSpesa" => Array (
                "tipoSpesa" => $tipoSpesa, 
                "importo" => $importo,
                "naturaIVA" => $naturaIVA // non soggetta iva 633/72
            ),
            "pagamentoTracciato" => $pagamentoTracciato,
            "tipoDocumento" => $tipoDocumento,
            "flagOpposizione" => $flagOpposizione
            );

        $output = $this->soapClient->Inserimento( Array ( 
            //"pincode" => "W+cy4Lz7rOOgldsbK98TEAwR6OIikKMkQJ1nWS09LgnJ6up+4e2LfIHe9z6aOQ9NocHOqepHuUcqmNNXpOq2JDCZQms65cX2oif8VhSUsvOk/9mc/8A9A7tpLnHcoGNrrjrg0z3fat7JmENXo5LF5uQV2IqvT4z5BDJbNa5XZpQ=",
            // "pincode" => "UVlBSIhTWnbQIJgJPReJHudcqwulW6D765f1QNcTc1HVvx4TD7qoDabol66sqSe7pysTxU3Ao6E4I7a3v70EQZgOLpFN2rJNnpRp7W5RZ2wHeKlifhooB8JBVY1CLypBKpyZILDBiDTeGl+KCToplJP3pjcWgOGQR0BroCisJXY=",
            "pincode" => $this->encryptString($this->pincode),
            "Proprietario" => Array (
                "cfProprietario" => $this->encryptString($this->cfProprietario)
                // "SsFrZY1plknIYKxk2MxIsgCyH2X3cfnrbg7B1aywMzw4SYwfzCa797Bb40vZMlS1pRjBki3SYZT/dao7W7SCwarTTLQqFmfXu7SGBStGzfAyVWcXAZapnW3d8QWfY7EgbktdHPfcoslCqY+K4JJrHQA9H2bk2ngSA7n+xOjuLVw="
                //"cfProprietario" => "Ah1LnixnVEUqomYgH+MEOuhnowj/+iaS+wFLYP35BoeNbq8+68lkjTvo0h49p/iO1XjPVs8edoqNY1OXdcw1eNaaR4x4OsiM9zWvXQVYh2ocFBcpHAJlvoe7a0BPSF7Q94fUh/HkPzx+yi6t57DIatctICh4UIE3a+qW7e+1hzU="
            ),
            "idInserimentoDocumentoFiscale" => $documentoSpesa
          ) 
        );

        return $output;
    }
}

?>
