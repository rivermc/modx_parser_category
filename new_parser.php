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
            1: $html_items - String - Selector for items
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
            if ($html_items = $html->find($options[0])) {
                $modx_parent = $options[1];
                $prs_level = $options[2];
                parseData($html_items, $modx_parent, $prs_level);
            }
            else {
                echo 'Missing Elements - '. $URL . PHP_EOL;
            }
        }
    }
    clear($html);
}


/*
 The function for parsing web page
___________________________________________

 * args:
    $html_items - Array - The Action for handler function
    $modx_parent - Number - ID parent category for item
    $prs_level - Number - Level menu
___________________________________________
*/
function parseData($html_items, $modx_parent, $prs_level) {
    global $BASEURL;
    global $MODX;
    $index = 0;
    $prs_level++;
   	foreach($html_items as $html_item) {
        // Parse data
   		$modx_class_key = 'msCategory';
        $modx_pagetitle = $html_item->innertext;
        $modx_pagetitle = trim(preg_replace("/<span.*?>.*?<\/span>/", "", $modx_pagetitle));
   		$modx_template = 5;
   		$modx_published = 1;
        $modx_href = $html_item->href;
        $modx_alias = basename($modx_href);
        $page_link = $modx_href[0] == '/' ? $BASEURL . $modx_href : $BASEURL . '/' . $modx_href;
        $content_type = $prs_level == 1 ? 'Brands' : 'Catalog';

   	    // Duplicate check
        $item_id = getID($modx_parent, $modx_pagetitle);
        if ($item_id) {
            echo 'Skip category: '. $item_id .' '. $modx_pagetitle . PHP_EOL;
            //$action = 'update';
            //importItem($modx_item_data, $action);
        }
        else {
            echo 'Create category: '. $modx_pagetitle . PHP_EOL;

            // Get content
            $page_data = parseContent($page_link, $content_type);
            $modx_longtitle = $page_data[0];
            $modx_content = $page_data[1];
            $modx_img_link = $page_data[2];

            // Modx item data
            $modx_item_data = array(
                'parent' => $modx_parent,
                'pagetitle' => $modx_pagetitle,
                'template' => $modx_template,
                'published' => $modx_published,
                'class_key' => $modx_class_key,
                'tv33' => $page_link,
                'tv34' => $modx_img_link,
                'longtitle' => $modx_longtitle,
                'content' => $modx_content,
                'context' => 'web'
            );

            // Import a item in modx
            $action = 'create';
            $modx_item_id = importItem($modx_item_data, $action);

            // Deeper level parsing
            if ($prs_level == 1 && $modx_parent == 1366) {
                $html_items = '.categories-item-header-top > a';
            }
            else {
                $html_items = '.cats_cat > .my-auto';
            }

            addCsv(array($page_link, $html_items, $modx_item_id, $prs_level));
            pushCsv($prs_level . '_catalog.csv');

            // Set indexes parsing
            $index += 1;
        }
    }
}




/*
 The function for get ID item in modx
___________________________________________

 * args:
    $modx_parent - Number - ID parent item
    $modx_pagetitle - String - Item pagetitle
___________________________________________
*/
function getID($modx_parent, $modx_pagetitle) {
    global $MODX;
	$where = $MODX->toJSON(array('pagetitle' => $modx_pagetitle));
	$modx_item_id = $MODX->runSnippet('pdoResources', array(
       'parents' =>  $modx_parent,
       'depth' => 0,
	   'limit' => 1,
       'tpl' => '@INLINE [[+id]]',
       'where' => $where
    ));
    return $modx_item_id;
}



/*
 The function for importing item in modx
___________________________________________

 * args:
    $modx_item_data - Array - Array modx item data
 * return
    $item_id - Number - ID item
___________________________________________
*/
function importItem($modx_item_data, $action) {
    global $MODX;
	$response = $MODX->runProcessor('resource/'.$action, $modx_item_data);
    $MODX->cacheManager->clearCache();
	$item_id = $response->getObject()['id'];
	return $item_id;
}


/*
 The function for getting content page
___________________________________________

 * args:
    $page_link - String - Link for content
    $content_type - String - Content type
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
    }
    clear($html);
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
	if (!file_exists($file)) {
        $fp = fopen($file, "w");
        fclose($fp);
    }
	file_put_contents($file, trim($string).PHP_EOL, FILE_APPEND);
	$CSV = array();
}


function getLinks($filename) {
    global $MODX;
	$handle = fopen(dirname(__FILE__) .'/'. $filename, 'r');
	$items_array = array();
	if ($handle) {
	    $iteration = 0;
	    while (($line = fgets($handle)) !== false) {
	        $item_data = str_getcsv($line, ";"); //parse the rows
	        $URL = $item_data[0];
	        $items_selector = $item_data[1];
	        $parent = $item_data[2];
	        $level = $item_data[3];
	        getPage($URL, 'ParsingBrands', array($items_selector, $parent, $level));
	        $iteration++;
	    }
        echo 'Iteration - '. $iteration . PHP_EOL;
	    fclose($handle);
	} else {
	    echo 'Error opening the file';
	}
}


$catalog_level = $argv[1];
echo 'Start - Parsing Catalog Level: '. $catalog_level . PHP_EOL;
getLinks($catalog_level . '_catalog.csv');
echo 'Stop - Parsing Catalog Level: '. $catalog_level . PHP_EOL;
























?>