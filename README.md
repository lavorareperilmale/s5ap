# s5ap

Sistema Sincrono Spesa Singola Soggetto Altri Professionisti (s5ap)

Una piccola libreria sviluppata con approccio [CdC](https://www.youtube.com/watch?v=2fAfsZZde3U) per inviare spese all'agenzia delle entrate. ([Maggiori dettagli](https://sistemats1.sanita.finanze.it/portale/spese-sanitarie/documenti-e-specifiche-tecniche-strumenti-per-lo-sviluppo)).

La libreria e' stata sviluppata per inviare fatture di fisioterapia. 

## Installazione

```
composer require lavorareperilmale/s5ap
```

## Uso

Non c'e' bisogno di criptare dati, l'encryption avviene automaticamente all'interno della libreria. 
Esempio di uso, assumendo una tabella fatture:

```php
use lavorareperilmale\s5ap\Connettore;
// [...]

$connettore = new Connettore(); // Senza parametri per ambiente di test
// oppure per prod usare i propri dati:
// $connettore = new Connettore('Nome ut.','Password','Pin','CFProprietario','Piva'); 

foreach ($fatture as $fattura ) {
    // Assumendo che $fatture siano le fatture che si vogliono inviare
    $response = $connettore->invia(
                            $fattura->data_fattura,
                            $fattura->id_fattura, 
                            $fattura->data_pagamento,
                            $fattura->cliente->codice_fiscale,
                            number_format($fattura->valore,2)
    );
    if ( is_soap_fault($response) ) {
        // Qualcosa e' andato storto. Si puo' stampare messaggio con:
        echo $response->getMessage(); // Vedi https://www.php.net/manual/en/class.soapfault.php
        die("Qualcosa e' andato storto");
    }

    if ( $response->esitoChiamata == 0 || $response->esitoChiamata == 2 ) {
        // Invio andato a buon fine, magari con warnings
        echo $response->protocollo;
    } else {
        // Errore nell'invio: dump della risposta
        echo (serialize($response));
    }
```
Se non diversamente specificati, il metodo assume i seguenti valori di default:
```php
$pagamentoTracciato="SI", 
$flagOpposizione=0,
$tipoSpesa="SP",
$naturaIVA="N2.1", // non soggetta iva 633/72
$tipoDocumento = "F"
```

## TODO

* Aggiungere test
* Refactor passaggio opzioni
* Verificare che non ci siano problemi con campi con leading zeros (magari pin?), come nel caso di partita Iva che ora usa SoapFixer.
* Varie ed eventuali.