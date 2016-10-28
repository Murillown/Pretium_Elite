<?php
set_time_limit(-1);
ini_set('xdebug.max_nesting_level', -1);
include 'simple_html_dom.php';
function valorReal($string){
    $string = str_replace(".", "", $string);
    $string = str_replace(",", ".", $string);
    $string = str_replace("R$", "", $string);
    $string = str_replace(" ", "", $string);
    $string = str_replace('<span class="int">', "", $string);
    $string = str_replace('<span class="dec">', '', $string);
    $string = str_replace('<span itemprop="price/salesPrice">', '', $string);
    $string = str_replace('</span>', '', $string);
    return trim($string);
}

function createOrUpdateConcorrente($id_produto, $canal, $nome, $preco){
    $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');
    $update = false;

    if($nome == '')
        return;

    $sql = "SELECT COUNT(*) FROM comparador_concorrentes WHERE id_produto='$id_produto' and canal='$canal' and nome='$nome';";
    if(!$result = $db->query($sql)){
        throw new Exception('There was an error running the query [' . $db->error . ']');
    }

    if ($result->fetch_array()[0] > 0){
        $update = true;
    }


    if ($update){
        $SQL = "UPDATE comparador_concorrentes SET ultima_alteracao=NOW(), preco='$preco' WHERE id_produto='$id_produto' and canal='$canal' and nome='$nome';";
    } else {
        $SQL = "INSERT INTO comparador_concorrentes(id_produto, canal, nome, preco, ultima_alteracao) VALUES ('$id_produto', '$canal', '$nome', '$preco', NOW());";
    }

    if(!$resultC = $db->query($SQL)){
        echo $SQL;
        throw new Exception('createOrUpdateConcorrente : There was an error running the query['.$SQL.'] ERROR [' . $db->error . ']');
    }

}

function updateProduto($id, $preco){
    $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');

    $sql = "UPDATE comparador_produtos SET ultima_alteracao=NOW(), preco='$preco' WHERE id='$id';";

    if(!$result = $db->query($sql)){
        throw new Exception("updateProduto : Error executing query [".$db->error."]");
    }
}

function isLojistaName($name){
    if (preg_match('/mega emp.*rio/', $name)){
        return true;
    } else {
        return false;
    }
}

class check{
    protected $_url;

    public function __construct($url){
        $this->_url = $url;
    }

    public function requestProductInformation(){
        $resposta = array();

        $url = $this->_url;

        if (!preg_match("/^(https|http)/", $url)){
            $url = "http://".$url;
        }

        $user_agent = "Mozilla/6.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.7) Gecko/20050414 Firefox/1.0.3";
        
        $base_url = parse_url($url)['host'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url);
        curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
        curl_setopt($ch, CURLOPT_TIMEOUT,30);

        $resp = curl_exec($ch);

        curl_setopt($ch, CURLOPT_URL, $url);

        $exec = curl_exec($ch);
        $info = curl_getinfo($ch);


        //echo curl_error($ch);

        if ($info['http_code'] != 200) {
            echo "Erro diferente hein truta";
            throw new Exception("ConnectionFailed HttpCode[".$info['http_code']."]");
        }

        if (strpos($base_url, "walmart.com.br") !== false){ //O link é de um produto do walmart, vamos usar o regex dele.
            
            $html_read = str_get_html($exec);
            $n_concorrentes = 0;
            $lojistas = array();
            
            if (strpos($exec, '<link itemprop="availability" href="//schema.org/OutOfStock">') !== false){
                $resposta = ['esgotado'=>1];
            }
            else {

                foreach($html_read->find('div#buy-box-accordion section') as $lojista){
                    foreach($lojista->find('div.product-price span.payment-price') as $preco){
                        $lojista_price = valorReal($preco->plaintext);
                    }
                    foreach($lojista->find('div.seller-name a') as $seller){
                        $lojista_name = trim($seller->plaintext);
                    }

                    $lojistas[] = ['nome'=>$lojista_name, 'preco'=>$lojista_price];
                    $resposta[] = ['nome'=>$lojista_name, 'preco'=>$lojista_price];
                }


            }

        }

        if ((strpos($base_url, "extra.com.br") !== false) || (strpos($base_url, "pontofrio.com.br") !== false) || (strpos($base_url, "casasbahia.com.br") !== false)){ //O link é um produto da CNova, vamos usar o regex da CNova.

            $html_read = str_get_html($exec);
            $lojistas = array();

            if (strpos($exec, '<div class="alertaIndisponivel box3">') !== false){
                $resposta = ['esgotado'=>1];
                foreach($html_read->find('h1.fn b') as $prodName){
                    //echo $prodName->plaintext;
                    $resposta['nome'] = str_replace("'", "", $prodName->plaintext);
                }
                foreach($html_read->find('h1.fn span') as $spanID){
                       /*if(strpos($spanID->itemprop, 'productID') !== false){
                            $resposta['id'] = $spanID->plaintext);
                        }*/
                        if(strpos($spanID->itemprop, 'productID')!==false){
                            //echo $spanID->plaintext;
                            preg_match("/Item [0-9]*/", $spanID->plaintext, $output_teste);
                            $resposta['mktplace_id'] = str_replace("Item ", "", $output_teste[0]);
                        }
                }

                foreach($html_read->find('strong.brand') as $brand){
                        $resposta['marca'] = $brand->plaintext;
                        echo "</br></br>MARCA:".$brand->plaintext;
                }

                echo "</br></br>Categoria:".$html_read->find('div.breadcrumb span a span', 1)->plaintext."</br>";
                $resposta['categoria'] = $html_read->find('div.breadcrumb span a span', 1)->plaintext;
            }
            else {
                preg_match('/(.*?)class="seller"(.*?)>(.*?)<\/a>/', $exec, $output_lojista);

                preg_match_all('/<i class="sale price">(.*?)<\/i>/', $exec, $output_array);
                $resposta[] = ['nome'=>$output_lojista[3], 'preco'=>valorReal($output_array[1][0])];

                foreach($html_read->find('h1.fn b') as $prodName){
                    $resposta['nome'] = str_replace("'", "", $prodName->plaintext);
                }

                foreach($html_read->find('h1.fn span') as $spanID){

                        if(strpos($spanID->itemprop, 'productID')!==false){
                            preg_match("/Item [0-9]*/", $spanID->plaintext, $output_teste);
                            $resposta['mktplace_id'] = str_replace("Item ", "", $output_teste[0]);
                        }
                }

                foreach($html_read->find('strong.brand') as $brand){
                        $resposta['marca'] = $brand->plaintext;
                        echo "</br></br>MARCA:".$brand->plaintext;
                }

                echo "</br></br>Categoria:".$html_read->find('div.breadcrumb span a span', 1)->plaintext."</br>";
                $resposta['categoria'] = $html_read->find('div.breadcrumb span a span', 1)->plaintext;

                foreach($html_read->find('table.sellerList tbody tr') as $concorrente){
                    $nomeC = '';
                    $precoC = '';
                    foreach($concorrente->find('div.sellerList-name a') as $nome_concorrente){
                        $nomeC = $nome_concorrente->plaintext;
                    }
                    foreach($concorrente->find('div.sellerList-price strong') as $preco_concorrente){
                        $precoC = $preco_concorrente->plaintext;
                    }
                    if($nomeC != '' && $precoC != '')
                    $resposta[] = ['nome'=>$nomeC, 'preco'=>valorReal($precoC)];
                    echo "</br>";
                }

            }

        }

        if (strpos($base_url, "americanas.com.br") !== false){

            $html_read = str_get_html($exec);
            if (strpos($exec, '<div class="unavailable-product">') !== false){
                $resposta = ['esgotado'=>1];

                foreach($html_read->find('h1.prodTitle') as $prodName){
                    $resposta['nome'] = str_replace("'", "", $prodName->plaintext);
                }

                foreach($html_read->find('span') as $spanID){
                    if(strpos($spanID->itemprop, 'productID')!==false)
                        $resposta['mktplace_id'] = $spanID->plaintext;
                }

                foreach($html_read->find('table tr') as $brand){

                    if(strpos($brand->find('th', 0)->plaintext, "Marca") !== false){
                        $resposta['marca'] = $brand->find('td', 0)->plaintext;
                        echo "</br></br>MARCA:".$brand->find('td', 0)->plaintext."</br>";
                    }

                }
                echo "</br></br>Categoria:".$html_read->find('ol.breadcrumb li a span', 0)->plaintext."</br>";
                $resposta['categoria'] = $html_read->find('ol.breadcrumb li a span', 0)->plaintext;
            }
            else {
                $priceBuybox='';
                foreach($html_read->find('form.buy-form div.mp-price span') as $price){
                    if($price->itemprop == "price/salesPrice"){
                        $priceBuybox = $price->plaintext;
                    }
                }

                $lojistaName = NULL;

                foreach($html_read->find('span.pickup-store a.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                    }
                }

                foreach($html_read->find('span.pickup-store span.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                    }
                }
                $resposta[] = ['nome'=>$lojistaName, 'preco'=>valorReal($priceBuybox)];

                foreach($html_read->find('h1.prodTitle') as $prodName){
                    $resposta['nome'] = str_replace("'", "", $prodName->plaintext);
                }

                foreach($html_read->find('span') as $spanID){
                    if(strpos($spanID->itemprop, 'productID')!==false)
                        $resposta['mktplace_id'] = $spanID->plaintext;
                }

                foreach($html_read->find('table tr') as $brand){

                    if(strpos($brand->find('th', 0)->plaintext, "Marca") !== false){
                        $resposta['marca'] = $brand->find('td', 0)->plaintext;
                        echo "</br></br>MARCA:".$brand->find('td', 0)->plaintext."</br>";
                    }

                }

                echo "</br></br>Categoria:".$html_read->find('ol.breadcrumb li a span', 0)->plaintext."</br>";
                $resposta['categoria'] = $html_read->find('ol.breadcrumb li a span', 0)->plaintext;

                foreach($html_read->find('div.box-partners ul li') as $concorrente){
                    $nomeC = '';
                    $priceC = '';
                    foreach($concorrente->find('div.bp-name a') as $nome_concorrente){
                        $nomeC = $nome_concorrente->plaintext;
                    }
                    foreach($concorrente->find('div.bp-link span') as $preco_concorrente){
                        if(strpos($preco_concorrente->itemprop, "price") !== false){

                            $priceC = $preco_concorrente->plaintext;
                        }
                    }
                    if($nomeC != '' && $priceC != ''){
                        $resposta[] = ['nome'=>$nomeC, 'preco'=>valorReal($priceC)];
                    }
                }

            }

        }

        elseif (strpos($base_url, "submarino.com.br") !== false){ //Submarino

            $html_read = str_get_html($exec);
            $n_concorrentes = 0;
            $concorrentes = array();

            if (strpos($exec, '<div class="unavailable-product">') !== false){
                $resposta = ['esgotado'=>1];
            }
            else {
                $priceBuybox = '';
                foreach($html_read->find('form.buy-form div.mp-price span') as $price){
                    if($price->itemprop == "price/salesPrice"){

                        $priceBuybox = $price->plaintext;
                    }
                }

                $lojistaName = null;

                foreach($html_read->find('div.mp-delivered-by a.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                        echo "Lojista: ".$lojistaName;
                    }
                }

                foreach($html_read->find('div.mp-delivered-by span.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                        echo "Lojista: ".$lojistaName;
                    }
                }

                $resposta[] = ['nome'=>$lojistaName, 'preco'=>valorReal($priceBuybox)];

                foreach($html_read->find('div.box-partners ul li') as $concorrente){
                    foreach($concorrente->find('div.bp-name a') as $nome_concorrente){
                        $concorrentes[$n_concorrentes][0] = $nome_concorrente->plaintext;
                    }
                    foreach($concorrente->find('div.bp-link span') as $preco_concorrente){
                        if(strpos($preco_concorrente->itemprop, "price") !== false){
                            $concorrentes[$n_concorrentes][1] = $preco_concorrente->plaintext;
                            $n_concorrentes++;
                        }
                    }
                }
                foreach($concorrentes as $conc){
                    if(isset($conc[0])){
                        $resposta[] = ['nome'=>$conc[0], 'preco'=>valorReal($conc[1])];
                    } else {
                        $resposta[] = ['nome'=>$base_url, 'preco'=>valorReal($conc[1])];
                    }
                }

            }

        }

        elseif (strpos($base_url, "shoptime.com.br") !== false ){

            $html_read = str_get_html($exec);
            $n_concorrentes = 0;
            $concorrentes = array();

            if (strpos($exec, '<div class="unavailable-product">') !== false){
                $resposta = ['esgotado'=>1];
            }
            else {
                $priceBuybox = '';
                foreach($html_read->find('form.buy-form div.mp-price span') as $price){
                    if($price->itemprop == "price/salesPrice"){
                        $priceBuybox = $price->plaintext;
                    }
                }

                $lojistaName = null;

                foreach($html_read->find('div.product-stock-info a.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                    }
                }

                foreach($html_read->find('div.product-stock-info span.stock-highlight') as $lojista){
                    if(is_null($lojistaName)){
                        $lojistaName = $lojista->plaintext;
                    }
                }

                $resposta[] = ['nome'=>$lojistaName, 'preco'=>valorReal($priceBuybox)];

                foreach($html_read->find('div.box-partners ul li') as $concorrente){
                    foreach($concorrente->find('div.bp-name a') as $nome_concorrente){
                        $concorrentes[$n_concorrentes][0] = $nome_concorrente->plaintext;
                    }
                    foreach($concorrente->find('div.bp-link span') as $preco_concorrente){
                        if(strpos($preco_concorrente->itemprop, "price") !== false){
                            $concorrentes[$n_concorrentes][1] = $preco_concorrente->plaintext;
                            $n_concorrentes++;
                        }
                    }
                }

                foreach($concorrentes as $conc){
                    if(isset($conc[0])){
                        $resposta[] = ['nome'=>$conc[0], 'preco'=>valorReal($conc[1])];
                    } else {
                        $resposta[] = ['nome'=>$base_url, 'preco'=>valorReal($conc[1])];
                    }
                }
            }

        }

        if (strpos($base_url, "mercadolivre.com.br") !== false){ //MercadoLivre

            $html_read = str_get_html($exec);

            $frete_gratis = 0;
            $parcelado_semjuros = 0;
            $qnt_vendas = '0';

            if(strpos($exec, '<div class="wrap-breadcrumb">') !== false){ // Direcionado pagina de catalogo
                $resposta = ['inexistente'=>1];
            } else if(strpos($exec, '<p class="buy-btn buyDisabled">') !== false){ //Anuncio pausado
                $resposta = ['pausado'=>1];
            } else {
                $preco = "";
                foreach($html_read->find('article.ch-price strong') as $price){
                    $preco .= $price->plaintext;
                }
                $split = substr($preco, 0, strlen($preco)-5);
                $split2 = substr($preco, strlen($preco)-5);
                $preco = $split.",".$split2;

                foreach($html_read->find('span.free-shipping') as $pagamento){
                    if(strpos(strtolower($pagamento->style), 'display: none') !== false){
                        $parcelado_semjuros = 0;
                    } else {
                        $parcelado_semjuros = 1;
                    }
                }

                foreach($html_read->find('div.item-conditions dd') as $vendidos){
                    if(strpos($vendidos->plaintext, 'vendido') !== false){
                        preg_match('/(.*?)vendido/', $vendidos->plaintext, $matches_vendidos);
                        die(var_dump($matches_vendidos));
                        $qnt_vendas = trim($matches_vendidos[1]);
                    }
                }

                $frete_gratis = 0;

                foreach($html_read->find('p.free-shipping') as $frete_int){
                    if (strpos($frete_int->plaintext, 'Frete grátis') !== false){
                        $frete_gratis = 1;
                    } 
                }  

                $resposta = ['preco'=>valorReal($preco), 'frete_gratis'=>$frete_gratis, 'parcelado_semjuros'=>$parcelado_semjuros, 'esgotado'=>0, 'inexistente'=>0, 'pausado'=>0, 'qnt_vendas'=>$qnt_vendas];
            }

        }
        return $resposta;
    }

}

function getConcorrentes($id, $canal){
    $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');

    $sql = "SELECT * from comparador_concorrentes WHERE id_produto='$id' and canal='$canal';";

    $ret = array();

    if(!$result = $db->query($sql)){
        throw new Exception("Error executing query [".$db->error."]");
    }

    while($row = $result->fetch_assoc()){
        $ret[] = $row;
    }

    return $ret;
}

function updateProdutoStatus($sku_comparador){


    $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');
    $produto_id = $sku_comparador['id'];
    $canal = $sku_comparador['canal'];
    $concorrentes = getConcorrentes($produto_id, $canal);

    if ($sku_comparador['esgotado']){
        $status = 'Esgotado';
    }

    if(sizeof($concorrentes) < 1){
        $status = 'Sem concorrentes';
    }

    if (isLojistaName(strtolower($sku_comparador['buybox']))){
        $status = 'Ganhando buybox';
    } else {
        $status = 'Perdendo buybox';
    }

    $sql = "UPDATE comparador_produtos SET ultimo_status='$status' WHERE id='$produto_id';";
    if(!$result = $db->query($sql)){
        die("ERROR".$db->error);
    }

    return true;

}

function tryUpdateProductFromUrl($url){
    $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');
    $check = new check($url);
    $resposta = $check->requestProductInformation();
    $prodMktPlaceId = $resposta['mktplace_id'];
    $prodMarca = 'Não encontrada';
    $prodCategoria = 'Não encontrada';
    if(array_key_exists('marca', $resposta)){
        $prodMarca = $resposta['marca'];
        unset($resposta['marca']);
    }
    if(array_key_exists('categoria', $resposta)){
        $prodCategoria = $resposta['categoria'];
        unset($resposta['categoria']);
    }
    unset($resposta['nome']);
    unset($resposta['mktplace_id']);
    $sqlSelect = "SELECT * FROM comparador_produtos WHERE anuncio='$url';";
    if(!$result = $db->query($sqlSelect)){
        die("SQL:".$db->error);
    } 
    $produto = $result->fetch_assoc();

    $produto_id = $produto['id'];

    if(isset($resposta['esgotado'])){
            if($resposta['esgotado']){
                $SQL = "UPDATE comparador_produtos SET ultima_alteracao=NOW(), esgotado='1' WHERE anuncio='$url';";
                if(!$result = $db->query($SQL)){
                    throw new Exception("Error executing query [".$db->error."]");
                }
            }
        } else {
            $buybox = $resposta[0]['nome'];
            $SQL = "UPDATE comparador_produtos SET ultima_alteracao=NOW(), categoria='$prodCategoria', marca='$prodMarca', mktplace_id='$prodMktPlaceId', buybox='$buybox' WHERE anuncio='$url';";
            if(!$result = $db->query($SQL)){
                throw new Exception("Error executing query [".$db->error."]");
            }

            foreach($resposta as $lojista){
                if(isLojistaName(strtolower($lojista['nome']))){ 
                    try{
                        echo "Lojista preco:".$lojista['preco'].'</br>';
                        updateProduto($produto_id, $lojista['preco']);

                    } catch(Exception $ex){
                        throw new Exception($ex->getMessage());
                    }

                } else {

                    try{
                        createOrUpdateConcorrente($produto_id, $produto['canal'], $lojista['nome'], $lojista['preco']);
                    } catch(Exception $ex){
                        throw new Exception($ex->getMessage());
                    }
                }
            }

            updateProdutoStatus($produto);
        }

}

$db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste');
$sqll = "SELECT * FROM links_canais WHERE canal='extra';";
if(!$resultAAA = $db->query($sqll)){
    die("SQL:".$db->error);
}

$linnks = array();
while($row = $resultAAA->fetch_assoc()){
    $linnks[] = $row['url'];
}

foreach($linnks as $links){
    sleep(0.25);
    ob_end_flush();
    echo 'Analisando link: '.$links.'</br>';
    ob_start();
    flush();
    $url = $links;
    $sql = "SELECT COUNT(*) FROM comparador_produtos WHERE anuncio='$url';";
    if(!$result = $db->query($sql)){
        die("SQL:".$db->error);
    }

    $arr = $result->fetch_array();
    if($arr[0] > 0){
        echo("Já tem esse link</br>");
        tryUpdateProductFromUrl($url);
    } else {
        try {
            $check = new check($url);
            $resposta = $check->requestProductInformation();
            $prodName = $resposta['nome'];
            $prodMktPlaceId = $resposta['mktplace_id'];
            $prodMarca = 'Não encontrada';
            if(array_key_exists('marca', $resposta)){
                $prodMarca = $resposta['marca'];
                unset($resposta['marca']);
            }
            $prodCategoria = 'Não encontrada';
            if(array_key_exists('categoria', $resposta)){
                $prodCategoria = $resposta['categoria'];
                unset($resposta['categoria']);
            }
            unset($resposta['nome']);
            unset($resposta['mktplace_id']);

            $base_url = parse_url($url)['host'];
            $base_url = str_replace(".com", "", $base_url);
            $base_url = str_replace(".br", "", $base_url);
            $base_url = str_replace("www.", "", $base_url);

            $sqlInsert = "INSERT INTO comparador_produtos(categoria, marca, mktplace_id, nome, anuncio, canal) VALUES ('$prodCategoria', '$prodMarca', '$prodMktPlaceId', '$prodName', '$url', '$base_url')";

            if(!$result = $db->query($sqlInsert)){
                die("SQL:".$db->error);
            }

            tryUpdateProductFromUrl($url);

        } catch(Exception $ex){
            echo $ex->getMessage();
        }
    }

}
?>
