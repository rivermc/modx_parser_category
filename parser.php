<?php
error_reporting(E_ALL);

/*
 Parsing Catalog MODX
___________________________________________
 * args_prefix:
    html_ - prefix for simple html variable
    modx_ - prefix for modx variable
    prs_  - prefix for parser variable
    page_ - prefix for page variable
___________________________________________
*/



/*
 Include php files
___________________________________________

 * Variable - Definition variable and init MODX
    $CSV - Array
    $BASEURL - String - Domain name
    $URL - String - Parse link
    $MODX - Object - Modx Object
 * Simple HTML DOM - Including lib
___________________________________________
*/
include_once(dirname(__FILE__) . '/variable.php');
include_once(dirname(__FILE__) . '/simple_html_dom.php');


/*
 The function returning Simple DOM HTML Object
___________________________________________

 * args:
    $URL - String - Web page link
___________________________________________
*/
function getSimpleHTML($URL) {
	$context = stream_context_create (
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
    $html = file_get_html(str_replace(PHP_EOL, '', $URL), false, $context);
    return $html;
}


/*
 The function retrieves the content of the web page and calls the handler function
___________________________________________

 * args:
    $URL - String - Web page link
    $action - String - The Action for handler function
    $options - Array - Array data for action function handler
        * for ParsingBrands
            0: $html_parent - String - Selector for parent html block
            1: $html_items - String - Selector for items in html block
            2: $modx_parent - Number - ID parent category for item
            3: $prs_level - Number - Level menu
 * action:
    ParsingBrands - Start parsing Brands catalog
___________________________________________
*/
function getPage($URL, $action, $options = array()) {
    $html = getSimpleHTML($URL);
	if ($html && is_object($html) && isset($html->nodes)) {
        if ($action == 'ParsingBrands') {
            $html_parent = $html->find($options[0], 0);
            if ($html_parent && is_object($html_parent) && isset($html_parent->nodes)) {
                $html_items = $html_parent->find($options[1]);
                $modx_parent = $options[2];
                $prs_level = $options[3];
                $prs_index = array(0,0,0);
                parseData($html_parent, $html_items, $modx_parent, $prs_index, $prs_level);
            }
        }
    }
    clear($html);
}


/*
 The function for parsing web page
___________________________________________

 * args:
    $html_parent - String - Selector for parent html block
    $html_items - Array - The Action for handler function
    $modx_parent - Number - ID parent category for item
    $prs_index - Array - Array indexes for parse levels of menu
    $prs_level - Number - Level menu
___________________________________________
*/
function parseData($html_parent, $html_items, $modx_parent, $prs_index, $prs_level) {
    global $BASEURL;

   	foreach($html_items as $html_item) {
        // Parse data
   		$modx_class_key = 'msCategory';
        $modx_pagetitle = $html_item->plaintext;
   		$modx_template = 5;
   		$modx_published = 1;
        $modx_href = $html_item->href;
        $modx_alias = basename($modx_href);
        $page_link = $modx_href[0] == '/' ? $BASEURL . $modx_href : $BASEURL . '/' . $modx_href;
        $content_type = $prs_level == 1 ? 'Brands' : 'Catalog';

        // Get content
        $page_data = parseContent($page_link, $content_type);
        $modx_longtitle = $page_data[0];
        $modx_content = $page_data[1];
        $modx_img_link = $page_data[2];

        // Modx item data
   	    $modx_item_data = array(
            'class_key' => $modx_class_key,
            'parent' => $modx_parent,
            'pagetitle' => $modx_pagetitle,
            'longtitle' => $modx_longtitle,
            'content' => $modx_content,
            'tv34' => $modx_img_link,
            'template' => $modx_template,
            'published' => $modx_published,
            'context_key' => 'web',
   	    );

   	    // Import a item in modx
		$modx_item_id = importItem($modx_item_data);

        // Deeper level parsing
        if ($prs_level <= 2) {

            // Set parser variable
            if ($prs_level == 1) {
                $html_items = $html_parent->find('.level1', $prs_index[0])->find('ul > .level2 > span > a');
                $prs_level = 2;
            }
            else if ($prs_level == 2) {
                $html_items = $html_parent->find('.level1', $prs_index[0])->find('ul > .level2', $prs_index[1])->find('ul > .level3 > span > a');
                $prs_level = 3;
            }

            // Parsing down level
            parseData($html_parent, $html_items, $modx_item_id, $prs_index, $prs_level);

            // Set indexes parsing
            if ($prs_level == 1) {
                $prs_index[0] += 1;
            }
            else if ($prs_level == 2) {
                $prs_index[1] += 1;
            }
        }
        else {
            getPage($page_link, 'ParsingBrands', array('.cats_div', '.cats_cat .product-type__desc', $modx_item_id, $prs_level + 1));
        }
    }
}


/*
 The function for importing item in modx
___________________________________________

 * args:
    $modx_item_data - Array - Array item data
___________________________________________
*/
function importItem($modx_item_data) {
    global $MODX;
	$response = $MODX->runProcessor('resource/create', $modx_item_data);
	$item_id = $response->getObject()['id'];
	return $item_id;
}


/*
 The function for getting content page
___________________________________________

 * args:
    $html - Array - Array simple dom html
___________________________________________
*/
function parseContent($page_link, $content_type) {
    $html = getSimpleHTML($page_link);
    $page_data = array('','','');
    if ($html && is_object($html) && isset($html->nodes)) {
        $page_longtitle = $html->find('h1', 0)->plaintext;
        $page_content = '';
        $page_img_src = '';

        if ($content_type == 'Brands') {
            $page_content = $html->find('.brand_face_content', 0)->innertext;
            $page_content = $page_content . $html->find('.brand_bottomText', 0)->innertext;
            $page_img_src = $html->find('.brand_face_image img', 0)->src;
            saveImage($page_img_src);
        }
        else if ($content_type == 'Catalog') {
            $page_contents = $html->find('.content_block');
            foreach($page_contents as $content) {
                $page_content = $page_content . $content->innertext;
            }
        }
        $page_data = array($page_longtitle, $page_content, $page_img_src);
        clear($html);
    }
    return $page_data;
}


/*
 The function for save images
___________________________________________

 * args:
    $img_src - String - URL for image
___________________________________________
*/
function saveImage($img_src) {
    global $BASEURL;
	$filename = dirname(dirname(__FILE__)) .'/'. $img_src;
    $catalog = dirname(dirname(__FILE__)) .'/'. substr($img_src, 0, strripos($img_src, '/'));
	if (!is_dir($catalog)) {
      mkdir($catalog, 0777, true);
    }
	if (!file_exists($filename)) {
		file_put_contents($filename, file_get_contents($BASEURL .'/'. $img_src));
	}
}


/*
 The function for clearing html variable
___________________________________________

 * args:
    $html - Array - Array Simple HTML DOM
___________________________________________
*/
function clear($html) {
    if (is_bool($html) !== true) {
	    $html->clear();
    }
	unset($html);
}


/*
 The function for add property in array for string of CSV
___________________________________________

 * args:
    $data_item - String, Number - Item property
___________________________________________
*/
function addCsv($data_item) {
    global $CSV;
    if (is_array($data_item) === true) {
        foreach($data_item as $item) {
            array_push($CSV, $item);
        }
    }
    else {
	    array_push($CSV, $data_item);
    }
}


/*
 The function for adding string in CSV
___________________________________________

 * args:
    $data_item - String, Number - Item property
___________________________________________
*/
function pushCsv($filename) {
    global $CSV;
	$string = implode(';', $CSV);
	$file = dirname(__FILE__) .'/' . $filename;
	file_put_contents($file, trim($string).PHP_EOL, FILE_APPEND);
	$CSV = array();
}


// Start Parse
getPage($URL, 'ParsingBrands', array('ul#aside-nav-brands', '.level1 > span > a', 1366, 1));



?>