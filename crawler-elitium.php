<?php
set_time_limit(-1);
ini_set('xdebug.max_nesting_level', -1);
require 'simple_html_dom.php'; //biblioteca com as funções
ini_set('display_errors', true);
// USAGE
$startURL = 'http://www.extra.com.br/Lojista/10194/Mega-Emporio?Filtro=L10194&Ordenacao=nomeCrescente&paginaAtual='; // qual url vai ser utilizada
$depth = 99;
$crawler = new crawler($startURL, $depth); //define a variável crawler setando a url e a "profundidade" que vai ser buscada
$crawler->run(); //rodar o crawler

class crawler
{
    protected $_url;
    protected $_depth;
    protected $_host;
    protected $_useHttpAuth = false; // nao vai usar autenticação 
    protected $_user;
    protected $_pass;
    protected $_seen = array(); //definindo a variavel de visão como um array
    protected $_filter = array(); //definindo a variavel de filtro como um array
    
    public function __construct($url, $depth = 5, $filter) //método construtor que vai receber a url e 5 níveis de profundidade para a busca
    {
        $this->_url = $url;
        $this->_depth = $depth;
        $parse = parse_url($url);
        $this->_host = $parse['host'];
    }

    protected function _processAnchors($content, $url, $depth) //Função para processamento das âncora que tem como parametro
    														  //conteúdo, a url e os níveis "profundidade"
    {
        $dom = new DOMDocument('1.0');
        @$dom->loadHTML($content); //dom vai carregar o conteúdo do html
        $anchors = $dom->getElementsByTagName('a');// vai guardar na $anchors todos os elementos html que tiverem a tag 'a'
        $html_read = str_get_html($content); //  html_read vai ler todo o conteúdo que tiver no html
        
       foreach($html_read->find('a.link') as $tag){ // para cada tag A que tiver link dentro do que o html read leu
                $db = mysqli_connect('localhost', 'root', 'qwepoi123', 'teste'); // vai conectar com bando de dados
                $link = $tag->href;// $link vai guardar todos os links que tiverem href
                $sql = "INSERT INTO links_canais(url, canal) VALUES ('$link', 'extra')"; //vai ser guardado no db todos os links do extra
                $db->query($sql); // vai ser executada a query do insert
                echo $tag->href."</br>"; // vai printar a tag que tiver href e pular linha
        }
    }

    public function _getContent($url) // função para pegar o conteúdo da url
    {
        $handle = curl_init();
        if ($this->_useHttpAuth) {
            curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);// alguma coisa relacionada a autenticação
            curl_setopt($handle, CURLOPT_USERPWD, $this->_user . ":" . $this->_pass); // tentando autenticar com o usuário e senha?
        }
        $user_agent = "Mozilla/6.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.7) Gecko/20050414 Firefox/1.0.3";
        curl_setopt($handle, CURLOPT_USERAGENT,$user_agent);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION,true);
        curl_setopt($handle, CURLOPT_AUTOREFERER, true);
        curl_setopt($handle, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_COOKIEFILE, '');
        curl_setopt($handle, CURLOPT_TIMEOUT,30);
        curl_setopt($handle, CURLOPT_URL, "http://www.extra.com.br");
        curl_exec($handle);
        curl_setopt($handle, CURLOPT_URL, $url);
        $response = curl_exec($handle);
        $time = curl_getinfo($handle, CURLINFO_TOTAL_TIME);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        return array($response, $httpCode, $time);
    }

    protected function _printResult($url, $depth, $httpcode, $time)
    {
        ob_end_flush();
        $currentDepth = $this->_depth - $depth;
        $count = count($this->_seen);
        echo "N::$count,CODE::$httpcode,TIME::$time,DEPTH::$currentDepth URL::$url <br>";
        ob_start();
        flush();
    }

    protected function isValid($url, $depth)
    {
        if (strpos($url, $this->_host) === false
            || $depth === 0
            || isset($this->_seen[$url])
        ) {
            return false;
        }
        foreach ($this->_filter as $excludePath) {
            if (strpos($url, $excludePath) !== false) {
                return false;
            }
        }
        return true;
    }

    public function crawl_page($url, $depth)
    {

        // add to the seen URL
        $this->_seen[$url] = true;
        // get Content and Return Code
        list($content, $httpcode, $time) = $this->_getContent($url);
        // print Result for current Page
        $this->_printResult($url, $depth, $httpcode, $time);
        // process subPages
        $this->_processAnchors($content, $url, $depth);
    }

    public function setHttpAuth($user, $pass)
    {
        $this->_useHttpAuth = true;
        $this->_user = $user;
        $this->_pass = $pass;
    }

    public function addFilterPath($path)
    {
        $this->_filter[] = $path;
    }

    public function run()
    {   
        $z = 48; //Numero de paginas
        for($i=1; $i<=$z; $i++){
            $this->crawl_page($this->_url.$i, $this->_depth);
        }
}

?>
