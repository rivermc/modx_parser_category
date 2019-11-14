<?php
error_reporting(E_ALL);
include_once('/var/www/html/base/variable.php');
include_once('/var/www/html/base/simple_html_dom.php');

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

function getPage($URL, &$CSV, &$modx) {
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
	$menu = $html->find('ul#aside-nav-brands', 0);
	parseData($modx, $CSV, $menu, 1366, 0, 0, 0, $menu->find('.level1 > span > a'), 1);
}



function parseData(&$modx, &$CSV, $menu, $parent, $index, $index_two, $index_three, $dom_links, $level) {

   	foreach($dom_links as $a) {
		echo '<br>' .' Level: ' . $level . '<br>' .' Index: ' . $index . '<br>' .' Index_Two: ' . $index_two . '<br>' . ' Index_Three: ' . $index_three . '<br>'.' Index_Three: ' . $index_three . '<br>';

		if ($menu == null) {
		    echo 'Null';
		    return;
		}

   		$dom_class_key = 'msCategory';
        $dom_pagetitle = $a->plaintext;
   		$dom_parent = $parent;
   		$dom_template = 5;
   		$dom_published = 1;
        $dom_href = $a->href;
        $dom_alias = basename($dom_href);


		importItem($modx, $dom_parent, $dom_pagetitle, $dom_template, $dom_published);
		$dom_id = getID($modx, $dom_parent, $dom_pagetitle);

		addData($CSV, $dom_class_key);
		addData($CSV, $dom_pagetitle);
		addData($CSV, $dom_parent);
		addData($CSV, $dom_template);
		addData($CSV, $dom_published);
		addData($CSV, $dom_alias);
		addData($CSV, $dom_id);
		addCSV($CSV);

		echo $dom_pagetitle;

		echo '<br>' . '--------------------------------------------------------------------' .'<br>';

		if ($level == 1) {
			parseData($modx, $CSV, $menu, $dom_id, $index, 0, 0, $menu->find('.level1', $index)->find('ul > .level2 > span > a'), 2);
		}
		else if ($level == 2) {
			parseData($modx, $CSV, $menu, $dom_id, $index, $index_two, $index_three, $menu->find('.level1', $index)->find('ul > .level2', $index_two)->find('ul > .level3 > span > a'), 3);
		}

		if ($level == 1) {
			$index++;
		}
		else if ($level == 2) {
		    $index_two++;
		}
		else if ($level == 3) {
			$index_three++;
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

function importItem(&$modx, $dom_parent, $dom_pagetitle, $dom_template, $dom_published) {
	$data = array(
	    'class_key' => 'msCategory',
	    'parent' => $dom_parent,
	    'pagetitle' => $dom_pagetitle,
	    'template' => $dom_template,
	    'published' => $dom_published,
	    'context_key' => 'web',
	);
	$modx->runProcessor('resource/create', $data);
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
	$file = '/var/www/html/base/bases.csv';
	file_put_contents($file, trim($string).PHP_EOL, FILE_APPEND);
	$CSV = array();
}


// Start Parse
getPage($URL, $CSV, $modx);



?>