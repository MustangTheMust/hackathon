<?php

// MASSIMO DI PAROLE DA MOSTRARE
define("MAX_RESULTS", 20);

function sanitize($str){
    $content = read();

    // RIMUOVO GLI SPAZI BIANCHI E SEPARO TUTTE LE PAROLE CON LO SPAZIO, METTENDOLE POI IN UN ARRAY
    $replace = array_map("trim", explode(" ", $content));
    
    // SOSTITUISCO TUTTI I SEGNI DI PUNTEGGIATURA E I CARATTERI SPECIALI CON UNO SPAZIO NELLA STRINGA DATA
    $str = preg_replace("/[.,\/#!$%\“”’^&*';\":{}=\-_`~()]/", ' ', $str);

    // SEPARO TUTTE LE PAROLE DEL TESTO DATO CON UNO SPAZIO CREANDO UN ARRAY
    $words = explode(" ", strtolower($str));
    
    $return = $words;

    // SE L'ARRAY PRECEDENTEMENTE CREATO CONTIENE UNA DELLE PAROLE DA SCARTARE, TOLGO QUELLE PAROLE DALL'ARRAY
    foreach($words as $k=>$v){
        if(in_array($v, $replace) || strlen($v) == 1 || is_numeric($v) || ctype_space($v)){
            unset($return[$k]);
        }
    }
    
    return array_filter($return);
}

function read(){
    // RESTITUISCO IL CONTENUTO DEL FILE
    return file_get_contents("files/stopwords_".$_POST['lang'].".txt");
}

function findwords($str, $url){
    $occurrences = array();

    // PRENDO LE PAROLE "VALIDE" DALL'INPUT DELL'UTENTE
    $words = sanitize(strip_tags($str));

    // TOTALE DELLE OCCORRENZE (100%)
    $tot = 0;

    // SE L'ARRAY $occurrences CONTIENE UNA DELLE PAROLE, ALLORA AUMENTO IL SUO VALORE, ALTRIMENTI AGGIUNGO LA PAROLA ALL'ARRAY
    foreach($words as $v){
        if(array_key_exists($v, $occurrences))
            $occurrences[$v] += 1;
        else
            $occurrences[$v] = 1;

        $tot++;
    }

    // ORDINO LE PAROLE DA QUELLA CHE APPARE MAGGIORMENTE A QUELLA CHE APPARE MENO
    arsort($occurrences);

    // PRENDO SOLO LE PRIME 20 PAROLE CON LA MAGGIOR FREQUENZA
    $occurrences = array_slice($occurrences, 0, MAX_RESULTS);

    // COSTRUISCO UNA TABELLA PER VISUALIZZARE I RISULTATI
    $returnstring = '<h5 class="text-uppercase font-weight-bold text-center">Le '.MAX_RESULTS.' parole che appaiono di più sono:</h5>';

    $returnstring .= '<ul class="nav nav-tabs border-bottom-0 ml-5 mt-5" role="tablist">';
    $returnstring .= '<li class="nav-item">';
    $returnstring .= '<a class="nav-link active" data-toggle="tab" href="#results" role="tab">Risultati</a>';
    $returnstring .= '</li>';
    $returnstring .= '<li class="nav-item">';
    $returnstring .= '<a class="nav-link" data-toggle="tab" href="#cloud" role="tab">Nuvoletta</a>';
    $returnstring .= '</li>';
    $returnstring .= '<li class="nav-item">';
    $returnstring .= '<a class="nav-link" data-toggle="tab" href="#page" role="tab">Nella pagina</a>';
    $returnstring .= '</li>';
    $returnstring .= '<li class="nav-item">';
    $returnstring .= '<a class="nav-link" data-toggle="tab" href="#homepage" role="tab">Ultimi 10 articoli (Cesenatoday)</a>';
    $returnstring .= '</li></ul>';
    $returnstring .= '<div class="tab-content border p-3 rounded">';

    $returnstring .= '<div class="tab-pane fade show active" id="results" role="tabpanel">';
    $returnstring .= '<table class="table table-striped table-bordered mt-2 mb-0">';
    $returnstring .= '<thead class="font-weight-bold"">';
    $returnstring .= '<tr>';
    $returnstring .= '<td>Parola</td>';
    $returnstring .= '<td>Occorrenze</td>';
    $returnstring .= '<td>Percentuale (sul totale)</td>';
    $returnstring .= '</tr></thead><tbody>';

    // PAROLE PER LA WORD CLOUD
    $cloud_words = array();

    $title = "<h4>".searchBytag("title", $url)."</h4>";
    
    $body = searchByTag("p", $url);

    foreach($occurrences as $k=>$v){
        // PERCENTUALE DELLE OCCORRENZE DELLA PAROLA
        $perc = number_format((100 * $v) / $tot, 2)."%";
        
        $returnstring .= '<tr>';
        $returnstring .= '<td width="50%">'.$k.'</td>';
        $returnstring .= '<td width="30%">'.$v.'</td>';
        $returnstring .= '<td width="20%">'.$perc.'</td>';
        $returnstring .= '</tr>';
        
        $title = preg_replace("/\b".$k."\b/", "<strong>".$k."</strong>", strtolower($title));
        // $title = str_replace($k, "<strong>".$k."</strong>", strtolower($title));
        $body = preg_replace("/\b".$k."\b/", "<strong>".$k."</strong>", strtolower($body));
        // $body = str_replace($k, "<strong>".$k."</strong>", strtolower($body));

        // CICLO LE PAROLE PER INSERIRLE NELLA STRINGA DELLA WORD CLOUD
        for($i = 0; $i < $v; $i++)
            $cloud_words[] = $k;
    }

    $returnstring .= '</tbody></table></div>';

    $cloud_words = implode(";", $cloud_words);

    // INCLUDO TRAMITE IFRAME LA NUVOLETTA
    $returnstring .= '<div class="tab-pane fade" id="cloud" role="tabpanel">';
    $returnstring .= '<iframe width="100%" height="500" class="border-0 mt-5" src="getcloud.php?max='.MAX_RESULTS.'&words='.$cloud_words.'"></iframe>';
    $returnstring .= '</div>';
    
    // STAMPO IL CODICE DELLA PAGINA PRINCIPALE EVIDENZIANDO LE PAROLE TROVATE
    $returnstring .= '<div class="tab-pane fade text-justify" id="page" role="tabpanel">';
    $returnstring .= $title.$body;
    $returnstring .= '</div>';
    
    // TROVO GLI ULTIMI 10 ARTICOLI DI CESENATODAY
    $returnstring .= '<div class="tab-pane fade" id="homepage" role="tabpanel">';
    
    foreach(findwordsMorePages("http://cesenatoday.it") as $k=>$v)
        $returnstring .= '<a class="d-block read-article pointer" href="javascript:void(0)" data-url="'.$k.'">'.$v.'</a>';

    $returnstring .= '</div></div>';

    //  INSERISCO IL SALVATAGGIO
    insertData($url, $occurrences, $body, $title);

    return $returnstring;
}

function searchByTag($tag, $page){
    // PRENDO IL CONTENUTO DEL DOCUMENTO TRAMITE L'URL CHE MI VIENE DATO
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    $html = file_get_contents($page);

    // CARICO L'HTML PROVENIENTE DALL'URL
    $dom->loadHTML($html);

    // PRENDO IL CONTENUTO DEL TAG CHE VIENE SPECIFICATO (ES: IL CONTENUTO DI TUTTI I TAG <p></p>)
    $books = $dom->getElementsByTagName($tag);
    
    // LA LUNGHEZZA DELL'OGGETTO CHE VIENE CREATO
    $count = $tag == "title" ? 1 : $books->length;

    $testo = "";

    // CICLO TUTTI I TAG P PER PRENDERNE IL CONTENUTO
    for($i = 0; $i < $count; $i++)
        $testo .= $dom->saveXML($books->item($i));

    return strip_tags($testo);
}

// FUNZIONE PER PRENDERE GLI ULTIMI 10 ARTICOLI DEL SITO
function findwordsMorePages($url)
{
    $dom = new DOMDocument();

    $html = file_get_contents($url);
    $dom->loadHTML($html);

    $multiText = array();

    $anchors = $dom->getElementsByTagName('a');
    $i = 0;

    foreach ($anchors as $element) {

        // PRENDO L'ATTRIBUTO HREF DEI VARI LINK
        $href = $element->getAttribute('href');

        // CREO UN ARRAY CON I VARI LINK, MA SOLO SE SONO DIVERSI L'UNO DALL'ALTRO
        if (strpos($href, 'http')!==0 && strpos($href, '.html') > 0 && !isset($multiText[$url.$href])) {
            $multiText[$url.$href] = searchByTag("title", $url.$href);
            $i++;
        }
        
        if($i > 9) break;
    }
    return $multiText;
}

function getPageContent($url){
    // PRENDO IL CONTENUTO DELLA PAGINA TRAMITE I TAG <title></title> E <p></p>
    $content = searchByTag("title", $url)." ";
    $content .= searchByTag("p", $url);

    return $content;
}

function connectToDatabase(){
    // CONNESSIONE AL DATABASE
    return mysqli_connect("localhost:3308","root","password","find");
}

function insertData($url, $results, $text, $title){
    $conn = connectToDatabase();

    $res = "";

    // TRASFORMO L'ARRAY DATO IN INPUT IN UNA STRINGA SEPARATA DA SPAZI
    foreach($results as $k=>$v)
        $res .= $k." (".$v." volte) ";
   
    // INSERISCO NEL DATABASE IL SALVATAGGIO
    $conn->query("INSERT INTO ricerca (url, risultati, testo, titolo) VALUES ('".$url."','".$res."','".addslashes($text)."','".addslashes($title)."'); ") or die(mysqli_error($conn));
}

function getDataFromDB(){
    $conn = connectToDatabase();

    // PRENDO I SALVATAGGI DAL DATABASE
    $history = $conn->query("SELECT data, id FROM ricerca ORDER BY data DESC");

    $div = "";

    // PER OGNI SALVATAGGIO INSERISCO UN'ANCORA NEL BOX
    while($h = $history->fetch_assoc()){
        $div .= '<a href="#page-content" class="d-block p-2 border-bottom pointer nav-link small"><span id="'.$h['id'].'">'.$h['data'].'</span></a>';
    }

    return $div;
}

// PER CHIAMATA AJAX
function getContent($id){
    $conn = connectToDatabase();

    // PRENDO IL SALVATAGGIO SELEZIONATO DAL DATABASE
    $content = $conn->query("SELECT titolo, testo, risultati FROM ricerca WHERE id = '".$id."' LIMIT 1")->fetch_assoc();
    
    // RESTITUISCO IL TITOLO, IL CONTENUTO DELL'ARTICOLO (CON RISULTATI EVIDENZIATI) E LE PAROLE TROVATE
    $return = $content['titolo'];
    $return .= '<p>'.$content['testo'].'</p>';
    $return .= '<p>'.$content['risultati'].'</p>';

    return $return;
}