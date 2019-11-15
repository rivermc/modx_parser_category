<?php
error_reporting(E_ALL);
include_once('/var/www/html/base_parser/variable.php');
include_once('/var/www/html/base_parser/simple_html_dom.php');

// init modx
define('MODX_API_MODE', true);
require_once dirname(dirname(__FILE__)) . '/core/config/config.inc.php';
require_once '/var/www/html/index.php';
$modx = new modX();
$modx->initialize('web');
$is_debug = false;
// Load main services
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
$modx->setLogLevel($is_debug ? modX::LOG_LEVEL_INFO : modX::LOG_LEVEL_ERROR);
$modx->getService('error','error.modError');
$modx->lexicon->load('minishop2:default');
$modx->lexicon->load('minishop2:manager');

$CSV = array();


function getPage($URL, $BASEURL, &$CSV, &$modx, $action, $options = array()) {
	$context = stream_context_create(
	    [
	        'http' => [
	             'method' => 'GET',
	             'protocol_version' => '1.1',
	             'header' => [
				       'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:24.0) Gecko/20100101 Firefox/24.0',
				       'Connection: close'
	             ]
	        ]
	    ]
	);
	$html = file_get_html(str_replace(PHP_EOL, '', $URL), false, $context); // Create DOM from URL

	if ($html && is_object($html) && isset($html->nodes)) {

        if ($action == 'import') {
            $menu = $html->find('ul#aside-nav-brands', 0);
            if ($menu && is_object($menu) && isset($menu->nodes)) {
                $index = array(0,0,0);
                $parent = 1366;
                parseData($modx, $CSV, $BASEURL, $menu, $parent, $index, $menu->find('.level1 > span > a'), 1);
            }

        }
        else if ($action == 'getContentBrands') {
            return parseContentBrands($modx, $CSV, $BASEURL, $html);
        }
        else if ($action == 'getPageLevel') {
            $menu = $html->find('.cats_div', 0);
            if ($menu && is_object($menu) && isset($menu->nodes)) {
                $index = array(0,0,0);
                parseData($modx, $CSV, $BASEURL, $menu, $options[0], $index, $menu->find('.cats_cat .product-type__desc'), $options[1]);
            }
        }
        else {
            echo 'GetPage: Missing $action';
        }

        clear($html);
    }
}


function parseData(&$modx, &$CSV, &$BASEURL, $menu, $parent, $index, $dom_links, $level) {

   	foreach($dom_links as $a) {
   		$dom_class_key = 'msCategory';
        $dom_pagetitle = $a->plaintext;
   		$dom_parent = $parent;
   		$dom_template = 5;
   		$dom_published = 1;
        $dom_href = $a->href;
        $dom_alias = basename($dom_href);

        if ($level == 1) {
            $page_link = $BASEURL . $dom_href;
            $page_content = getPage($page_link, $BASEURL, $CSV, $modx, 'getContentBrands');
            $dom_longtitle = $page_content[0];
            $dom_content = $page_content[1];
            $dom_img_link = $page_content[2];
        }
        else {
            $dom_longtitle = '';
            $dom_content = '';
            $dom_img_link = '';
        }

		importItem($modx, $dom_parent, $dom_pagetitle, $dom_template, $dom_published, $dom_longtitle, $dom_content, $dom_img_link);
		$dom_id = getID($modx, $dom_parent, $dom_pagetitle);

		if ($level == 1) {
			parseData($modx, $CSV, $BASEURL, $menu, $dom_id, $index, $menu->find('.level1', $index[0])->find('ul > .level2 > span > a'), 2);
		}
		else if ($level == 2) {
			parseData($modx, $CSV, $BASEURL, $menu, $dom_id, $index, $menu->find('.level1', $index[0])->find('ul > .level2', $index[1])->find('ul > .level3 > span > a'), 3);
		}
		else if ($level == 3 || $level == 4) {
            $page_link = $BASEURL . $dom_href;
		    if ($level == 4) {
                $page_link = $BASEURL . '/' . $dom_href;
		    }
            $page_options = array($dom_id, $level + 1);
		    getPage($page_link, $BASEURL, $CSV, $modx, 'getPageLevel', $page_options);
		}

		if ($level == 1) {
			$index[0] += 1;
		}
		else if ($level == 2) {
			$index[1] += 1;
		}
    }
}


function getID(&$modx, $parent, $pagetitle) {
	$where = array('pagetitle' => $pagetitle);
	$where = $modx->toJSON(array($where));
	$dom_id = $modx->runSnippet('pdoResources', array(
       'parents' =>  $parent,
       'depth' => 0,
	   'limit' => 1,
       'tpl' => '@INLINE [[+id]]',
       'where' => $where
    ));
    return $dom_id;
}


function importItem(&$modx, $dom_parent, $dom_pagetitle, $dom_template, $dom_published, $dom_longtitle, $dom_content, $dom_img_link) {
	$data = array(
	    'class_key' => 'msCategory',
	    'parent' => $dom_parent,
	    'pagetitle' => $dom_pagetitle,
	    'longtitle' => $dom_longtitle,
	    'content' => $dom_content,
	    'tv34' => $dom_img_link,
	    'template' => $dom_template,
	    'published' => $dom_published,
	    'context_key' => 'web',
	);
	$modx->runProcessor('resource/create', $data);
}


function parseContentBrands(&$modx, &$CSV, &$BASEURL, $html) {
    $dom_page = array();
    $dom_longtitle = $html->find('h1', 0)->plaintext;
    $dom_content = $html->find('.brand_face_content', 0)->innertext;
    $dom_content_bottom = $html->find('.brand_bottomText', 0)->innertext;

	$dom_img_src = $html->find('.brand_face_image img', 0)->src;
    $dom_img_name = substr($dom_img_src, strripos($dom_img_src, '/'));
    $dom_img_link = '/assets/template/images/catalog/logo'. $img_name;

    array_push($dom_page, $dom_longtitle);
    array_push($dom_page, $dom_content . ' ' . $dom_content_bottom);
    array_push($dom_page, $dom_img_link);
    return $dom_page;
}


function saveImage(&$BASEURL, $img_src, $img_name) {
	$filename = '/var/www/html/base_parser/images/catalog/logo'. $img_name;
	if (!file_exists($filename)) {
		file_put_contents($filename, file_get_contents($BASEURL .'/'. $img_src));
	} else {
	    echo "The file exists" . $filename . "\n";
	}
}


function clear($html) {
	$html->clear();
	unset($html);
}


function addData(&$CSV, $data_item) {
	array_push($CSV, $data_item);
}


function addCSV(&$CSV) {
	$string = implode(';', $CSV);
	$file = '/var/www/html/base_parser/bases.csv';
	file_put_contents($file, trim($string).PHP_EOL, FILE_APPEND);
	$CSV = array();
}


// Start Parse
getPage($URL, $BASEURL, $CSV, $modx, 'import');



?>