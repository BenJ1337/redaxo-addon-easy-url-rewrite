<?php

class MyClass
{
    private static $instance = null;
    private $articleId2UrlMap = [];
    private $url2ArticleIdMap = [];
    private $langMap = [];
    private $debug = true;

    function __construct()
    {


        $sql = rex_sql::factory();
        $sql->setDBQuery("SELECT r_article.id as a_id, r_article.name as a_name, r_article.catname as a_cat_name
                                , r_article.path as a_path, r_clang.code as cl_code, r_clang.id as cl_id
                                FROM rex_article r_article 
                                LEFT JOIN rex_clang r_clang ON (r_article.clang_id = r_clang.id)
                                ORDER BY a_path");
        $tableMap = $sql->getArray();

        foreach ($tableMap as $key => $value) {
            $this->articleId2UrlMap[$value['cl_id']][$value['a_id']]['name'] = $this->umlauteumwandeln($value['a_name']);
            $this->articleId2UrlMap[$value['cl_id']][$value['a_id']]['cat_name'] = $this->umlauteumwandeln($value['a_cat_name']);
            $this->url2ArticleIdMap[$value['cl_code']][$this->umlauteumwandeln($value['a_name'])] = $value['a_id'];

            $parents = array_filter(
                explode("|", $value['a_path']),
                function ($value) {
                    return $value !== '';
                });

            if (isset($parents) && sizeof($parents) > 0) {
                $this->articleId2UrlMap[$value['cl_id']][$value['a_id']]['cat_ids'] = $parents;
            } else {
                $this->articleId2UrlMap[$value['cl_id']][$value['a_id']]['cat'] = [];
            }

            if (!in_array($value['cl_id'], $this->langMap)) {
                $this->langMap[$value['cl_id']] = $value['cl_code'];
            }
        }


        if ($this->debug) {
            print_r("<pre>" . json_encode($this->articleId2UrlMap) . "</pre>");
            print_r("<br>");
            print_r("<pre>" . json_encode($this->url2ArticleIdMap) . "</pre>");
        }
    }

    public function rewriteURL($ep)
    {
        if (isset($params['subject']) && $params['subject'] != '') {
            return $params['subject'];
        }
        $params = $ep->getParams();
        $url = "";
        $url .= "/" . $this->langMap[$params['clang']];

        if (isset($this->articleId2UrlMap[$params['clang']][$params['id']]['cat_ids'])
            && sizeof($this->articleId2UrlMap[$params['clang']][$params['id']]['cat_ids']) > 0) {

            foreach ($this->articleId2UrlMap[$params['clang']][$params['id']]['cat_ids'] as $key => $value) {
                $url .= "/" . $this->articleId2UrlMap[$params['clang']][$value]['cat_name'];

                $this->url2ArticleIdMap[$params['clang']][$params['id']]['cats'][]
                    = $this->articleId2UrlMap[$params['clang']][$value]['cat_name'];

            }
        }

        $url .= "/" . $this->articleId2UrlMap[$params['clang']][$params['id']]['name'];
        $params['subject'] = $url;

        // params
        $urlparams = '';
        if (isset($params['params'])) {
            $urlparams = rex_string::buildQuery($params['params'], $params['separator']);
        }

        return $url . ($urlparams ? '?' . $urlparams : '');;
    }

    public function mapURL2Article($params)
    {

        $path = $_SERVER['REQUEST_URI'];

        $path_dirs = array_filter(
            explode("/", $path),
            function ($value) {
                return $value !== '';
            });

        if ($this->debug) {
            /* var_dump($params);
            print_r("<pre>".$path."</pre>");
            print_r("<pre>".implode(", ", $path_dirs)."</pre>");
            print_r("<pre>".$path_dirs["1"]."</pre>");
            print_r("<pre>".$path_dirs[sizeof($path_dirs)]."</pre>");



            print_r("<pre>"
                .$this->url2ArticleIdMap[$path_dirs["1"]][$path_dirs[sizeof($path_dirs)]]
                ."</pre>");
            */

        }


        \rex_clang::setCurrentId(1);
        \rex_addon::get('structure')->setProperty('article_id',
            $this->url2ArticleIdMap[$path_dirs["1"]][$path_dirs[sizeof($path_dirs)]]);
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new MyClass();
        }
        return self::$instance;
    }

    private function umlauteumwandeln($str)
    {
        $tempstr = array("Ä" => "Ae", "Ö" => "Oe", "Ü" => "Ue", "ä" => "ae", "ö" => "oe", "ü" => "ue", " " => "+");
        return strtr($str, $tempstr);
    }
}