<?php
if(!function_exists('http_build_cookie')){
    function http_build_cookie($cookie){
        $c='';
        foreach($cookie as $k => $v){
            $c.=urlencode($k).'='.urlencode($v).';';
        }
        return $c;
    }
}
class http{
    public $curl=null;

    private $url='';
    private $urlParsed=[];

    private $requestHeader=[];
    private $responseHeader=[];
    private $proxy=false;
    private $follow=true;

    private $cookie=[];
    private $debug=false;

    function init(){
        $this->curl=curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->follow);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, $this->debug);
        if($this->proxy)
            curl_setopt($this->curl, CURLOPT_PROXY, $this->proxy);
    }
    function __construct($url){
        $this->url=$url;
        $this->urlParsed=parse_url($url);
        if(!isset($this->urlParsed['path']))
            $this->urlParsed['path']='/';

        $this->init();
    }
    public function setUserAgent($ua){
        curl_setopt($this->curl, CURLOPT_USERAGENT, $ua);
    }
    public function proxy($proxy=null){
        if($proxy!==null){
            $this->proxy=$proxy;
            curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
        }else
            echo $this->proxy=$proxy;
    }
    public function follow($follow=null){
        if($follow!==null){
            $this->follow=$follow;
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $follow);
        }else
            return $this->follow;
    }
    public function lastHttpCode(){
        return curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
    }
    public function debug($debug=null){
        if($debug!==null)
            $this->debug=$debug;
        else
            return $this->debug;
    }
    public function header($header=null){
        if($header){
            $this->requestHeader=array_merge($this->requestHeader, $header);
            $h=[];
            foreach($this->requestHeader as $k => $v){
                if($v===null)
                    $h[]=$k;    // 안들어감. curl 안에서 거르는듯
                else if($v!==false)
                    $h[]=$k.': '.$v;
            }
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $h);
        }else
            return $this->responseHeader;
    }
    // path 관련은 나중에...
    public function cookie($key=null, $value=null){
        if(!$key)
            return $this->cookie;

        if($value===null)
            return $this->cookie[$key];
        else if($value===false){
            unset($this->cookie[$key]);
        }else if($value)
            $this->cookie[$key]=$value;
        curl_setopt($this->curl, CURLOPT_COOKIE, http_build_cookie($this->cookie));
    }
    public function get($url=null, $param=null){
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');
        $url=$this->make_url($url);
        if($param){
            if(is_array($param))
                $param=http_build_query($param);
            $param=(strpos($url, '?')===false?'?':'&').$param;
        }
        curl_setopt($this->curl, CURLOPT_POST, false);
        curl_setopt($this->curl, CURLOPT_URL, $url.$param);
        return $this->process(curl_exec($this->curl));
    }
    public function post($url=null, $param=null){
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->curl, CURLOPT_POST, true);
        if($param)
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $param);
        curl_setopt($this->curl, CURLOPT_URL, $this->make_url($url));
        return $this->process(curl_exec($this->curl));
    }
    public function execute($method, $url=null, $param=null){
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->curl, CURLOPT_URL, $this->make_url($url));
        if($param)
            @curl_setopt($this->curl, CURLOPT_POSTFIELDS, $param);
        return $this->process(curl_exec($this->curl));
    }
    private function process($html){
        $size=curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $header=substr($html, 0, $size);
        $body=substr($html, $size);

        $this->responseHeader=explode("\n", $header);

        preg_match_all('/^Set-Cookie: ?(?<k>[^=]+)=(?<v>[^;\n]+)/m', $header, $match);
        if(count($match[0])){
            for($i=0, $len=count($match[0]); $i<$len; $i++)
                $this->cookie[urldecode($match['k'][$i])]=urldecode($match['v'][$i]);
            curl_setopt($this->curl, CURLOPT_COOKIE, http_build_cookie($this->cookie));
        }

        if($this->debug)
            echo curl_getinfo($this->curl, CURLINFO_HEADER_OUT);
        return $body;
    }
    private function build_url($parsed){
        $url=$parsed['scheme'].'://';
        if(isset($parsed['user']) && $parsed['user']!==false){
            $url.=$parsed['user'];
            if(isset($parsed['pass']) && $parsed['pass']!==false){
                $url.=':'.$parsed['pass'];
            }
            $url.='@';
        }
        $url.=$parsed['host'];
        if(isset($parsed['port']) && $parsed['port']!==false)
            $url.=':'.$parsed['port'];
        if(isset($parsed['path']) && $parsed['path']!==false)
            $url.=$parsed['path'];
        if(isset($parsed['query']) && $parsed['query']!==false)
            $url.='?'.$parsed['query'];

        return $url;
    }
    private function make_url($url){
        if(!$url)
            return $this->build_url($this->urlParsed);

        $parsed=parse_url($url);

        if(isset($parsed['host'])){
            if(!isset($parsed['scheme']))
                $parsed['scheme']=$this->urlParsed['scheme'];
            if(!isset($parsed['path']))
                $parsed['path']='/';
            return $this->build_url($this->urlParsed=$parsed);
        }else{
            if(isset($parsed['path']) && $parsed['path'][0] != '/')
                $parsed['path']=$this->urlParsed['path'].$parsed['path'];
            if(!isset($parsed['query']))
                $parsed['query']=false;
            return $this->build_url(array_merge($this->urlParsed, $parsed));
        }
    }
}
// curl_file_create