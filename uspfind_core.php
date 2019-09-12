<?php
/**
 * Classes file with main functions 
 */


/**
 * Elasticsearch Class
 */
class Elasticsearch
{

    /**
     * Executa o commando get no Elasticsearch
     *
     * @param string   $_id               ID do documento.
     * @param string[] $fields            Informa quais campos o sistema precisa retornar. Se nulo, o sistema retornará tudo.
     * @param string   $alternative_index Caso use indice alternativo
     *
     */
    public static function get($_id, $fields, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["_source"] = $fields;

        $response = $client->get($params);
        return $response;
    }

    /**
     * Executa o commando search no Elasticsearch
     *
     * @param string[] $fields Informa quais campos o sistema precisa retornar. Se nulo, o sistema retornará tudo.
     * @param int      $size   Quantidade de registros nas respostas
     * @param resource $body   Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    public static function search($fields, $size, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0 ) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["_source"] = $fields;
        $params["size"] = $size;
        $params["body"] = $body;

        $response = $client->search($params);
        return $response;
    }

    /**
     * Executa o commando update no Elasticsearch
     *
     * @param string   $_id  ID do documento
     * @param resource $body Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    public static function update($_id, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["body"] = $body;

        $response = $client->update($params);
        return $response;
    }

    /**
     * Executa o commando delete no Elasticsearch
     *
     * @param string $_id  ID do documento
     *
     */
    public static function delete($_id, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["client"]["ignore"] = 404;

        $response = $client->delete($params);
        return $response;
    }

    /**
     * Executa o commando delete_by_query no Elasticsearch
     *
     * @param string   $_id               ID do documento
     * @param resource $body              Arquivo JSON com os parâmetros das consultas no Elasticsearch
     * @param resource $alternative_index Se tiver indice alternativo
     * 
     * @return array Resposta do comando
     */
    public static function deleteByQuery($_id, $body, $alternative_index = "")
    {
        global $index;
        global $client;
        $params = [];

        if (strlen($alternative_index) > 0) {
            $params["index"] = $alternative_index;
        } else {
            $params["index"] = $index;
        }

        $params["id"] = $_id;
        $params["body"] = $body;

        $response = $client->deleteByQuery($params);
        return $response;
    }

    /**
     * Executa o commando update no Elasticsearch e retorna uma resposta em html
     *
     * @param string   $_id  ID do documento
     * @param resource $body Arquivo JSON com os parâmetros das consultas no Elasticsearch
     *
     */
    static function storeRecord($_id, $body)
    {
        $response = Elasticsearch::update($_id, $body);
        echo '<br/>Resultado: '.($response["_id"]).', '.($response["result"]).', '.($response["_shards"]['successful']).'<br/>';

    }

    /**
     * Cria o indice
     *
     * @param string   $indexName  Nome do indice
     *
     */
    static function createIndex($indexName, $client)
    {
        $createIndexParams = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'filter' => [
                            'portuguese_stop' => [
                                'type' => 'stop',
                                'stopwords' => 'portuguese'
                            ],
                            'my_ascii_folding' => [
                                'type' => 'asciifolding',
                                'preserve_original' => true
                            ],
                            'portuguese_stemmer' => [
                                'type' => 'stemmer',
                                'language' =>  'light_portuguese'
                            ]
                        ],
                        'analyzer' => [
                            'portuguese' => [
                                'tokenizer' => 'standard',
                                'filter' =>  [ 
                                    'lowercase', 
                                    'my_ascii_folding',
                                    'portuguese_stop',
                                    'portuguese_stemmer'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $responseCreateIndex = $client->indices()->create($createIndexParams);
    }
    
  
    /**
     * Cria o mapeamento
     *
     * @param string   $indexName  Nome do indice
     *
     */
    static function mappingsIndex($indexName, $client, $mappings = null)
    {
        if (isset($mappings)) {
            $mappingsParams = $mappings;
        } else {
            $mappingsParams = [
                'index' => $indexName,
                'body' => [
                    'properties' => [
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'portuguese',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256
                                ]
                            ]
                        ], 
                        'datePublished' => [
                            'type' => 'integer'
                        ]                                         
                    ]
                ]
            ];
        }
        // Update the index mapping
        $client->indices()->putMapping($mappingsParams);
    }      

}

class Requests
{

    static function getParser($get)
    {
        global $antiXss;
        $query = [];

        if (!empty($get['fields'])) {
            $query["query"]["bool"]["must"]["query_string"]["fields"] = $get['fields'];
        } else {
            $query["query"]["bool"]["must"]["query_string"]["default_field"] = "*";
        }

        /* codpes */
        if (!empty($get['codpes'])) {
            $get['search'][] = 'authorUSP.codpes:'.$get['codpes'].'';
        }

        /* Pagination */
        if (isset($get['page'])) {
            $page = $get['page'];
            unset($get['page']);
        } else {
            $page = 1;
        }

        /* Pagination variables */
        $limit = 20;
        $skip = ($page - 1) * $limit;
        $next = ($page + 1);
        $prev = ($page - 1);

        $i_filter = 0;
        if (!empty($get['filter'])) {
            foreach ($get['filter'] as $filter) {
                $filter_array = explode(":", $filter);
                $filter_array_term = str_replace('"', "", (string)$filter_array[1]);
                $query["query"]["bool"]["filter"][$i_filter]["term"][(string)$filter_array[0].".keyword"] = $filter_array_term;
                $i_filter++;
            }

        }

        if (!empty($get['notFilter'])) {
            $i_notFilter = 0;
            foreach ($get['notFilter'] as $notFilter) {
                $notFilterArray = explode(":", $notFilter);
                $notFilterArrayTerm = str_replace('"', "", (string)$notFilterArray[1]);
                $query["query"]["bool"]["must_not"][$i_notFilter]["term"][(string)$notFilterArray[0].".keyword"] = $notFilterArrayTerm;
                $i_notFilter++;
            }
        }

        if (!empty($get['search'])) {

            $resultSearchTermsComplete = [];
            foreach ($get['search'] as $getSearch) {
                if (strpos($getSearch, 'base.keyword') !== false) {
                    $query["query"]["bool"]["filter"][$i_filter]["term"]["base.keyword"] = "Produção científica";
                    $i_filter++;
                } elseif (empty($getSearch)) {
                    $query["query"]["bool"]["must"]["query_string"]["query"] = "*";
                } else {
                    //$getSearchClean = $antiXss->xss_clean($getSearch);
                    if (preg_match_all('/"([^"]+)"/', $getSearch, $multipleWords)) {
                        //Result is storaged in $multipleWords
                    }
                    $queryRest = preg_replace('/"([^"]+)"/', "", $getSearch);
                    $parsedRest = explode(' ', $queryRest);
                    $resultSearchTerms = array_merge($multipleWords[1], $parsedRest);
                    $resultSearchTerms = array_filter($resultSearchTerms);
                    $resultSearchTermsComplete = array_merge($resultSearchTermsComplete, $resultSearchTerms);
                    $getSearchResult = implode("\) AND \(", $resultSearchTermsComplete);
                    $query["query"]["bool"]["must"]["query_string"]["query"] = "\($getSearchResult\)";
                }
            }


        }
        
        if (!empty($get['initialYear']) || !empty($get['finalYear'])) {
            if (!empty($get['initialYear'])) {
                $initialYear = $get['initialYear'];
            } else {
                $initialYear = "*";
            }

            if (!empty($get['finalYear'])) {
                $finalYear = $get['finalYear'];
            } else {
                $finalYear = "*";
            }
            $dateString = 'datePublished:['.$initialYear.' TO '.$finalYear.']';
            $query["query"]["bool"]["must"]["query_string"]["query"] = $dateString;
        }           

        if (!empty($get['range'])) {
            $query["query"]["bool"]["must"]["query_string"]["query"] = $get['range'][0];
        }         
        
        if (!isset($query["query"]["bool"]["must"]["query_string"]["query"])) {
            $query["query"]["bool"]["must"]["query_string"]["query"] = "*";
        }

        //$query["query"]["bool"]["must"]["query_string"]["default_operator"] = "AND";
        $query["query"]["bool"]["must"]["query_string"]["analyzer"] = "portuguese";
        $query["query"]["bool"]["must"]["query_string"]["phrase_slop"] = 10;
        
        return compact('page', 'query', 'limit', 'skip');
    }

}

class Facets
{
    public function facet($field, $size, $field_name, $sort, $sort_type, $get_search, $open = false)
    {
        global $url_base;

        $query = $this->query;
        $query["aggs"]["counts"]["terms"]["field"] = "$field.keyword";
        if (!empty($_SESSION['oauthuserdata'])) {
            $query["aggs"]["counts"]["terms"]["missing"] = "Não preenchido";
        }
        if (isset($sort)) {
            $query["aggs"]["counts"]["terms"]["order"][$sort_type] = $sort;
        }
        $query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::search(null, 0, $query);

        $result_count = count($response["aggregations"]["counts"]["buckets"]);        

        if ($result_count == 0) {

        } elseif (($result_count != 0) && ($result_count < 5)) {

            echo '<a href="#" class="list-group-item list-group-item-action active">'.$field_name.'</a>';
            echo '<ul class="list-group list-group-flush">';
            foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
                if ($facets['key'] == "Não preenchido") {
                    echo '<li>';
                    echo '<div uk-grid>
                            <div class="uk-width-expand" style="color:#333">
                                <a href="result.php?'.$get_search.'&search[]=(-_exists_:'.$field.')">'.$facets['key'].'</a>
                            </div>
                            <div class="uk-width-auto" style="color:#333">
                                <span class="uk-badge" style="font-size:80%">'.number_format($facets['doc_count'], 0, ',', '.').'</span>
                            </div>';
                    echo '</div></li>';
                } else {
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                        echo '<a href="result.php?'.$get_search.'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $facets['key']).'&quot;"  title="E" style="color:#0040ff;font-size: 90%">'.$facets['key'].'</a>
                        <span class="badge badge-primary badge-pill">'.number_format($facets['doc_count'], 0, ',', '.').'</span>';
                        echo '</li>'; 
                }

            };
            echo '</ul>';

        } else {
            $i = 0;
            echo '<a href="#" class="list-group-item list-group-item-action active">'.$field_name.'</a>';
            echo '<ul class="list-group list-group-flush">';  
            while ($i < 5) {
                if ($response["aggregations"]["counts"]["buckets"][$i]['key'] == "Não preenchido") {
                    echo '<li>';
                    echo '<div uk-grid>
                            <div class="uk-width-expand uk-text-small" style="color:#333">
                                <a href="result.php?'.$get_search.'&search[]=(-_exists_:'.$field.')">'.$response["aggregations"]["counts"]["buckets"][$i]['key'].'</a>
                            </div>
                            <div class="uk-width-auto" style="color:#333">
                            <span class="uk-badge" style="font-size:80%">'.number_format($response["aggregations"]["counts"]["buckets"][$i]['doc_count'], 0, ',', '.').'</span>
                            </div>';
                    echo '</div></li>';
                } else {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                    echo '<a href="result.php?'.$get_search.'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $response["aggregations"]["counts"]["buckets"][$i]['key']).'&quot;"  title="E" style="color:#0040ff;font-size: 90%">'.$response["aggregations"]["counts"]["buckets"][$i]['key'].'</a>
                    <span class="badge badge-primary badge-pill">'.number_format($response["aggregations"]["counts"]["buckets"][$i]['doc_count'], 0, ',', '.').'</span>';
                    echo '</li>';                   
                }
                $i++;                
            }


            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
            echo '<button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#'.str_replace(".", "", $field).'Modal">mais >>></button>  ';
            echo '</li>';
            echo '</ul>';
            echo '<div class="modal fade" id="'.str_replace(".", "", $field).'Modal" tabindex="-1" role="dialog" aria-labelledby="'.str_replace(".", "", $field).'ModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="'.$field.'ModalLabel">'.$field_name.'</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <ul class="list-group list-group-flush">';
                    foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                        echo '<a href="result.php?'.$get_search.'&filter[]='.$field.':&quot;'.str_replace('&', '%26', $facets['key']).'&quot;"  title="E" style="color:#0040ff;font-size: 90%">'.$facets['key'].'</a>
                            <span class="badge badge-primary badge-pill">'.number_format($facets['doc_count'], 0, ',', '.').'</span>';
                        echo '</li>';
                    }
            echo '</ul>';
             echo '
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
                </div>
            </div></div></div>
            ';         


        }
        echo '</li>';

    }

    public function rebuild_facet($field,$size,$nome_do_campo)
    {
        $query = $this->query;
        $query["aggs"]["counts"]["terms"]["field"] = "$field.keyword";
        if (isset($sort)) {
            $query["aggs"]["counts"]["terms"]["order"]["_count"] = "desc";
        }
        $query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::elasticSearch(null, 0, $query);

        echo '<li class="uk-parent">';
        echo '<a href="#" style="color:#333">'.$nome_do_campo.'</a>';
        echo ' <ul class="uk-nav-sub">';
        foreach ($response["aggregations"]["counts"]["buckets"] as $facets) {
            $termCleaned = str_replace("&", "*", $facets['key']);
            echo '<li">';
            echo "<div uk-grid>";
            echo '<div class="uk-width-2-3 uk-text-small" style="color:#333">';
            echo '<a href="admin/autoridades.php?term=&quot;'.$termCleaned.'&quot;" style="color:#0040ff;font-size: 90%">'.$termCleaned.' ('.number_format($facets['doc_count'], 0, ',', '.').')</a>';
            echo '</div>';
            echo '</li>';
        };
        echo   '</ul>
          </li>';

    }

    public function facet_range($field,$size,$nome_do_campo,$type_of_number = "")
    {
        $query = $this->query;
        if ($type_of_number == "INT") {
            $query["aggs"]["ranges"]["range"]["field"] = "$field";
            $query["aggs"]["ranges"]["range"]["ranges"][0]["to"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["from"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["to"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["from"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["to"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["from"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 20;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 20;
        } else {
            $query["aggs"]["ranges"]["range"]["field"] = "$field";
            $query["aggs"]["ranges"]["range"]["ranges"][0]["to"] = 0.5;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["from"] = 0.5;
            $query["aggs"]["ranges"]["range"]["ranges"][1]["to"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["from"] = 1;
            $query["aggs"]["ranges"]["range"]["ranges"][2]["to"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["from"] = 2;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 5;
            $query["aggs"]["ranges"]["range"]["ranges"][3]["to"] = 10;
            $query["aggs"]["ranges"]["range"]["ranges"][4]["from"] = 10;
        }

        //$query["aggs"]["counts"]["terms"]["size"] = $size;

        $response = Elasticsearch::elasticSearch(null, 0, $query);

        $result_count = count($response["aggregations"]["ranges"]["buckets"]);

        if ($result_count > 0) {
            echo '<li class="uk-parent">';
            echo '<a href="#" style="color:#333">'.$nome_do_campo.'</a>';
            echo ' <ul class="uk-nav-sub">';
            foreach ($response["aggregations"]["ranges"]["buckets"] as $facets) {
                $facets_array = explode("-", $facets['key']);
                echo '<li>
                    <div uk-grid>
                    <div class="uk-width-3-3 uk-text-small" style="color:#333">';
                    echo '<a style="color:#333" href="http://'.$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"].'&search[]='.$field.':['.$facets_array[0].' TO '.$facets_array[1].']">Intervalo '.$facets['key'].' ('.number_format($facets['doc_count'],0,',','.').')</a>';
                    echo '</div>';

                echo '</div></li>';
            };
            echo   '</ul></li>';
        }


    }
}

class Citation
{

    static function getType($material_type)
    {
        switch ($material_type) {
        case "ARTIGO DE JORNAL":
            return "article-newspaper";
        break;
        case "ARTIGO DE PERIODICO":
            return "article-journal";
        break;
        case "PARTE DE MONOGRAFIA/LIVRO":
            return "chapter";
        break;
        case "APRESENTACAO SONORA/CENICA/ENTREVISTA":
            return "interview";
        break;
        case "TRABALHO DE EVENTO-RESUMO":
            return "paper-conference";
        break;
        case "TRABALHO DE EVENTO":
            return "paper-conference";
        break;
        case "TESE":
            return "thesis";
        break;
        case "TEXTO NA WEB":
            return "post-weblog";
        break;
        }
    }

    static function citationQuery($citacao)
    {

        $array_citation = [];
        $array_citation["type"] = Citation::getType($citacao["type"]);
        $array_citation["title"] = $citacao["name"];

        if (!empty($citacao["author"])) {
            $i = 0;
            foreach ($citacao["author"] as $authors) {
                $array_authors = explode(',', $authors["person"]["name"]);
                $array_citation["author"][$i]["family"] = $array_authors[0];
                if (!empty($array_authors[1])) {
                    $array_citation["author"][$i]["given"] = $array_authors[1];
                }
                $i++;
            }
        }

        if (!empty($citacao["isPartOf"]["name"])) {
            $array_citation["container-title"] = $citacao["isPartOf"]["name"];
        }
        if (!empty($citacao["doi"])) {
            $array_citation["DOI"] = $citacao["doi"];
        }
        if (!empty($citacao["url"][0])) {
            $array_citation["URL"] = $citacao["url"][0];
        }
        if ($citacao["base"][0] == "Teses e dissertações") {
            $citacao["publisher"]["organization"]["name"] = "Universidade de São Paulo";
        }

        if (!empty($citacao["publisher"]["organization"]["name"])) {
            $array_citation["publisher"] = $citacao["publisher"]["organization"]["name"];
        }
        if (!empty($citacao["publisher"]["organization"]["location"])) {
            $array_citation["publisher-place"] = $citacao["publisher"]["organization"]["location"];
        }
        if (!empty($citacao["datePublished"])) {
            $array_citation["issued"]["date-parts"][0][] = intval($citacao["datePublished"]);
        }

        if (!empty($citacao["isPartOf"]["USP"]["dados_do_periodico"])) {
            $periodicos_array = explode(",", $citacao["isPartOf"]["USP"]["dados_do_periodico"]);
            foreach ($periodicos_array as $periodicos_array_new) {
                if (strpos($periodicos_array_new, 'v.') !== false) {
                    $array_citation["volume"] = str_replace("v.", "", $periodicos_array_new);
                } elseif (strpos($periodicos_array_new, 'n.') !== false) {
                    $array_citation["issue"] = str_replace("n.", "", $periodicos_array_new);
                } elseif (strpos($periodicos_array_new, 'p.') !== false) {
                    $array_citation["page"] = str_replace("p.", "", $periodicos_array_new);
                }

            }
        }

        $json = json_encode($array_citation);
        $data = json_decode($json);
        return array($data);
    }

}


class UI {
   
    static function pagination($page, $total, $limit, $t)
    {

        echo '<nav>';
        echo '<ul class="list-group list-group-horizontal">';
        if ($page == 1) {
            echo '<li class="list-group-item w-25 disabled"><a href="#"> Anterior</a></li>';
        } else {
            $_GET["page"] = $page-1 ;
            echo '<li class="list-group-item w-25"><a href="result.php?'.http_build_query($_GET).'"> Anterior</a></li>';
        }
        echo '<li class="list-group-item w-25 disabled"><a href="#">Página '.number_format($page, 0, ',', '.') .'</a></li>';
        echo '<li class="list-group-item w-25 disabled"><a href="#">'.number_format($total, 0, ',', '.') .'&nbsp;registros</a></li>';
        if ($total/$limit > $page) {
            $_GET["page"] = $page+1;
            echo '<li class="list-group-item w-25"><a href="result.php?'.http_build_query($_GET).'"> Próxima</a></li>';
        } else {
            echo '<li class="list-group-item w-25 disabled"><a href="#">Próxima</a></li>';
        }
        echo '</ul>';
        echo '</nav>';
    }
}



class Authorities {
    static function tematres($term, $tematres_url)
    {
        // Clean term
        $term = preg_replace("/\s+/", " ", $term);
        $clean_term = str_replace(array("\r\n", "\n", "\r"), "", $term);
        $clean_term = preg_replace('/^\s+|\s+$/', '', $clean_term);
        $clean_term = str_replace("\t\n\r\0\x0B\xc2\xa0", " ", $clean_term);
        $clean_term = trim($clean_term, " \t\n\r\0\x0B\xc2\xa0");
        $clean_term = rawurlencode($clean_term);
        $clean_term_p = $term;
        $clean_term = str_replace("%C2%A0", "%20", $clean_term);
        $clean_term = str_replace("&", "e", $clean_term);

        // Query tematres
        $ch = curl_init();
        $method = "GET";
        $url = ''.$tematres_url.'?task=fetch&arg='.$clean_term.'&output=json';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        $result_get_id_tematres = curl_exec($ch);
        $resultado_get_id_tematres = json_decode($result_get_id_tematres, true);
        curl_close($ch);

        // Get correct term
        if ($resultado_get_id_tematres["resume"]["cant_result"] != 0) {
            foreach ($resultado_get_id_tematres["result"] as $key => $val) {
                $term_key = $key;
            }
            $ch = curl_init();
            $method = "GET";
            $url = ''.$tematres_url.'?task=fetchTerm&arg='.$term_key.'&output=json';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            $result_term = curl_exec($ch);
            $resultado_term = json_decode($result_term, true);
            $found_term = $resultado_term["result"]["term"]["string"];
            $term_not_found = "";
            curl_close($ch);

            $ch_country = curl_init();
            $method = "GET";
            $url_country = ''.$tematres_url.'?task=fetchUp&arg='.$term_key.'&output=json';
            curl_setopt($ch_country, CURLOPT_URL, $url_country);
            curl_setopt($ch_country, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch_country, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            $result_country = curl_exec($ch_country);
            $resultado_country = json_decode($result_country, true);
            foreach ($resultado_country["result"] as $country_list) {
                if ($country_list["order"] == 1) {
                    $country = $country_list["string"];
                }
            }
            curl_close($ch_country);

        } else {
            $term_not_found = $clean_term_p;
            $found_term = "";
            $country = "ND";
        }
        return compact('found_term', 'term_not_found', 'country');
    }
}

/**
 * DSpaceREST
 *
 * @category Class
 * @package  DSpaceREST
 * @author   Tiago Rodrigo Marçal Murakami <tiago.murakami@dt.sibi.usp.br>
 * @license  
 * @link     
 */
class DSpaceREST
{
    static function loginREST()
    {

        global $dspaceRest;
        global $dspaceEmail;
        global $dspacePassword;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/login");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            http_build_query(array('email' => $dspaceEmail,'password' => $dspacePassword))
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);
        $output_parsed = explode(" ", $server_output);

        return $output_parsed[3];

        curl_close($ch);

    }

    static function logoutREST($DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: $DSpaceCookies"));
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/logout");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);
    }

    static function searchItemDSpace($sysno, $DSpaceCookies = null)
    {
        global $dspaceRest;
        $data_string = "{\"key\":\"usp.sysno\", \"value\":\"$sysno\"}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/items/find-by-metadata-field");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        if (!empty($result)) {
            return $result[0]["uuid"];
        } else {
            return "";
        }
        curl_close($ch);
    }

    static function getBitstreamDSpace($itemID, $DSpaceCookies = NULL)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/items/$itemID/bitstreams");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    static function getBitstreamPolicyDSpace($bitstreamID, $DSpaceCookies = null)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/bitstreams/$bitstreamID/policy");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    static function deleteBitstreamPolicyDSpace($bitstreamID, $policyID, $DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/bitstreams/$bitstreamID/policy/$policyID");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Cookie: $DSpaceCookies",
            'Content-Type: application/json'
            )
        );
        $output = curl_exec($ch);
        //var_dump($output);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    static function addBitstreamPolicyDSpace($bitstreamID, $policyAction, $groupId, $resourceType, $rpType, $DSpaceCookies, $embargoStartDate = "", $embargoEndDate = "")
    {
        global $dspaceRest;
        $policyArray["action"] =  $policyAction;
        $policyArray["epersonId"] =  "";
        $policyArray["groupId"] =  $groupId;
        $policyArray["resourceId"] =  $bitstreamID;
        $policyArray["resourceType"] =  $resourceType;
        $policyArray["rpDescription"] =  "";
        $policyArray["rpName"] =  "";
        $policyArray["rpType"] =  $rpType;
        $policyArray["startDate"] =  "$embargoStartDate";
        $policyArray["endDate"] =  "$embargoEndDate";
        $data_string = json_encode($policyArray);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/bitstreams/$bitstreamID/policy");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    // static function getBitstreamRestrictedDSpace($bitstreamID, $DSpaceCookies)
    // {
    //     global $dspaceRest;
    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/bitstreams/$bitstreamID/retrieve/64171-196117-1-PB.pdf");
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    //     if (!empty($DSpaceCookies)) {
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //             "Cookie: $DSpaceCookies",
    //             'Content-Type: application/json'
    //             )
    //         );
    //     }
    //     $output = curl_exec($ch);
    //     var_dump($output);
    //     //$result = json_decode($output, true);
    //     return $result;
    //     curl_close($ch);
    // }

    static function createItemDSpace($dataString,$collection,$DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/collections/$collection/items");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Cookie: $DSpaceCookies",
            'Content-Type: application/json'
            )
        );
        $output = curl_exec($ch);
        curl_close($ch);

    }

    static function deleteItemDSpace($uuid, $DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/items/$uuid");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    static function addBitstreamDSpace($uuid, $file, $userBitstream, $DSpaceCookies)
    {
        global $dspaceRest;
        $filename = rawurlencode($file["file"]["name"]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/items/$uuid/bitstreams?name=$filename&description=$userBitstream");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file["file"]["tmp_name"]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Cookie: $DSpaceCookies",
            'Content-Type: text/plain',
            'Accept: application/json'
            )
        );
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        curl_close($ch);
        return $result;
    }

    static function deleteBitstreamDSpace($bitstreamId, $DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/bitstreams/$bitstreamId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        if (!empty($DSpaceCookies)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Cookie: $DSpaceCookies",
                'Content-Type: application/json'
                )
            );
        }
        $output = curl_exec($ch);
        $result = json_decode($output, true);
        return $result;
        curl_close($ch);
    }

    static function buildDC($cursor,$sysno)
    {
        $arrayDC["type"] = "item";

        /* Title */
        $title["key"] = "dc.title";
        $title["language"] = "pt_BR";
        $title["value"] = $cursor["_source"]["name"];
        $arrayDC["metadata"][] = $title;
        $title = [];

        /* Sysno */
        $sysnoArray["key"] = "usp.sysno";
        $sysnoArray["language"] = "pt_BR";
        $sysnoArray["value"] = $sysno;
        $arrayDC["metadata"][] = $sysnoArray;
        $sysnoArray = [];

        // /* Abstract */
        // if (!empty($marc["record"]["940"]["a"])){
        //     $abstractArray["key"] = "dc.description.abstract";
        //     $abstractArray["language"] = "pt_BR";
        //     $abstractArray["value"] = $marc["record"]["940"]["a"][0];
        //     $arrayDC["metadata"][] = $abstractArray;
        //     $abstractArray = [];
        // } elseif (!empty($marc["record"]["520"]["a"])){
        //     $abstractArray["key"] = "dc.description.abstract";
        //     $abstractArray["language"] = "pt_BR";
        //     $abstractArray["value"] = $marc["record"]["520"]["a"][0];
        //     $arrayDC["metadata"][] = $abstractArray;
        //     $abstractArray = [];
        // }


        /* DateIssued */
        $dateIssuedArray["key"] = "dc.date.issued";
        $dateIssuedArray["language"] = "pt_BR";
        $dateIssuedArray["value"] = $cursor["_source"]["datePublished"];
        $arrayDC["metadata"][] = $dateIssuedArray;
        $dateIssuedArray = [];

        /* DOI */
        if (!empty($cursor["_source"]["doi"])) {
            $DOIArray["key"] = "dc.identifier";
            $DOIArray["language"] = "pt_BR";
            $DOIArray["value"] = $cursor["_source"]["doi"];
            $arrayDC["metadata"][] = $DOIArray;
            $DOIArray = [];
        }

        /* IsPartOf */
        if (!empty($cursor["_source"]["isPartOf"])) {
            $IsPartOfArray["key"] = "dc.relation.ispartof";
            $IsPartOfArray["language"] = "pt_BR";
            $IsPartOfArray["value"] = $cursor["_source"]["isPartOf"]["name"];
            $arrayDC["metadata"][] = $IsPartOfArray;
            $IsPartOfArray = [];
        }

        /* Authors */
        foreach ($cursor["_source"]["author"] as $author) {
            $authorArray["key"] = "dc.contributor.author";
            $authorArray["language"] = "pt_BR";
            $authorArray["value"] = $author["person"]["name"];
            $arrayDC["metadata"][] = $authorArray;
            $authorArray = [];
        }


        /* Unidade USP */
        if (isset($cursor["_source"]["authorUSP"])) {
            foreach ($cursor["_source"]["authorUSP"] as $unidadeUSP) {
                $unidadeUSPArray["key"] = "usp.unidadeUSP";
                $unidadeUSPArray["language"] = "pt_BR";
                $unidadeUSPArray["value"] = $unidadeUSP["unidadeUSP"];
                $arrayDC["metadata"][] = $unidadeUSPArray;
                $unidadeUSPArray = [];

                $authorUSPArray["key"] = "usp.authorUSP.name";
                $authorUSPArray["language"] = "pt_BR";
                $authorUSPArray["value"] = $unidadeUSP["name"];
                $arrayDC["metadata"][] = $authorUSPArray;
                $authorUSPArray = [];
            }
        }

        /* Subject */
        foreach ($cursor["_source"]["about"] as $subject) {
            $subjectArray["key"] = "dc.subject.other";
            $subjectArray["language"] = "pt_BR";
            $subjectArray["value"] = $subject;
            $arrayDC["metadata"][] = $subjectArray;
            $subjectArray = [];
        }

        /* USP Type */
        $USPTypeArray["key"] = "usp.type";
        $USPTypeArray["language"] = "pt_BR";
        $USPTypeArray["value"] = $cursor["_source"]["type"];
        $arrayDC["metadata"][] = $USPTypeArray;
        $USPTypeArray = [];

        $jsonDC = json_encode($arrayDC);
        return $jsonDC;

    }

    static function testREST($DSpaceCookies)
    {
        global $dspaceRest;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: $DSpaceCookies"));
        curl_setopt($ch, CURLOPT_URL, "$dspaceRest/rest/status");
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        print_r($server_output);
        curl_close($ch);

    }
}

?>