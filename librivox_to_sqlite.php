<?php
/** 
 * This file licensed under the terms of the GPL v2 or later.
 * Initially created by Joe Morris, joe@xenotropic.net
 * Latest version of this file https://github.com/xenotropic/librivox-sqlite
 */

error_reporting (E_ERROR);
$start_time=microtime(true);

global $time_for_iafetch, $time_for_lvfetch; $books_processed;
$time_for_lvfetch = 0;
$time_for_iafetch = 0;
$books_processed = 0;

// Parameters for what to get
// $offset=0; $set_size=100; $max=100000; // gets the entire database
//$offset=3000; $set_size=10; $max=3050; // gets the entire database
$set_size=100000;
$delay=500000; //microsecond delay between requests to reduce load on the server

// Reset database
$database_filename = './librivox.sqlite3';
unlink ($database_filename);
$db_file = 'sqlite:' . $database_filename;
$db = new PDO ($db_file);
$db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$create_db_sql = file_get_contents ("./librivox_db.sql");
$statements = explode(";", $create_db_sql); 
foreach ($statements as $stmt ) {  // pdo query can only handle one statement at a time
  $db->query($stmt);
}

$book_id_loader = new XmlArray ();
$book_id_list = $book_id_loader->load_string ( file_get_contents ("https://librivox.org/api/feed/audiobooks?limit=".$set_size."&fields={id}"));
$book_id_list = $book_id_list['children'][0]['children'];

echo "Loading ".count($book_id_list)." books, now on      ";

foreach ($book_id_list as $book_id_array) {
  $book_id = $book_id_array['children'][0]['content'];
  loadForURL ($db, "https://librivox.org/api/feed/audiobooks?id=".$book_id."&extended=1");
  
}

// print_r ( $book_id_list );


/** this doesn't work because with extended=1 the grouping is random even with offset and limit

while ( loadForURL ($db, "https://librivox.org/api/feed/audiobooks?offset=".$offset."&limit=".$set_size."&extended=1") ) {
  $offset += $set_size;
  if ( $offset > $max ) break;
};

*/


$elapsed_time = ((microtime(true) - $start_time));

echo "\n  Elapsed time: ". round ( $elapsed_time, 2) ."\n  LibriVox API fetch time: ". round ($time_for_lvfetch, 2) ."\n  iarchive fetch time: ".  round ($time_for_iafetch, 2) . "\n  Remaining (local processing): " . round (( $elapsed_time - ( $time_for_lvfetch + $time_for_iafetch ) ), 2) . " \n";

function loadForURL ( $db, $librivox_url ) { 
  global $time_for_iafetch, $time_for_lvfetch; $books_processed;
 
  $loader = new XmlArray();
  
  $time = microtime (true);
  $array = $loader->load_string (file_get_contents ( $librivox_url )); 
  if ( $array == null ) return false;
  $time_for_lvfetch += (microtime(true) - $time );
  
  $array = $array['children'][0]['children'];
  
  $iarchive_url;
   
  // echo "iarchive_url is " . $iarchive_url . "\n";
  
  foreach ( $array as $key=> $value ) {
    global $books_processed;
    $books_processed++;
    for ( $i = 0; $i < strlen ( $books_processed ); $i ++ ) echo chr(8);
    echo $books_processed;
    $book_properties = array();
    foreach ( $value['children'] as $properties_array ) {
      if ( $properties_array['name'] == 'url_iarchive') $iarchive_url = $properties_array['content']; 
    }

    foreach ( $value['children'] as $properties_array ) {
      if ( $properties_array['name']=='authors') {
	foreach ( $properties_array['children'] as $author_array) {
	  foreach ( $author_array['children'] as $author_property ) {
	    if ( $author_property['name'] == 'id' ) {
	      if ($book_properties['author_ids'] != '') $book_properties['author_ids'] .= "|"; // author ids are comma separated if there is more than one
	      $book_properties['author_ids'] .= $author_property['content'];
	    }
	  }
	}
      } else if ( $properties_array['name']=='genres') {
	foreach ( $properties_array['children'] as $genre_array) {
	  foreach ( $genre_array['children'] as $genre_property ) {
	    if ( $genre_property['name'] == 'id' ) {
	      if ($book_properties['genre_ids'] != '') $book_properties['genre_ids'] .= "|"; // genre ids are comma separated if there is more than one
	      $book_properties['genre_ids'] .= $genre_property['content'];
	    }
	  }
	}
      } else if ($properties_array['name']=='sections') {
	$book_properties['sections'] = insertSection ( $properties_array['children'], $db, $book_properties['id'], $iarchive_url );
      } else { // for properties that aren't an array, which is all except authors and sections
	$book_properties[$properties_array['name']] = $properties_array['content'];
      }
    }
    $stmt = $db->prepare ('INSERT INTO audiobooks VALUES (:id,:title,:description,:language,:copyright_year,:num_sections,:url_rss,:url_zip_file,:url_project,:url_librivox,:url_iarchive,:url_other,:totaltime,:totaltimesecs,:authors,:sections,:genres,:publicdate,:downloads)');
    
    $stmt->bindValue (':id', $book_properties['id'], PDO::PARAM_INT);
    $stmt->bindValue (':title', $book_properties['title'], PDO::PARAM_STR);
    $stmt->bindValue (':description', $book_properties['description'], PDO::PARAM_STR);
    $stmt->bindValue (':language', $book_properties['language'], PDO::PARAM_STR);
    $stmt->bindValue (':totaltime', $book_properties['totaltime'], PDO::PARAM_STR);
    $stmt->bindValue (':copyright_year', $book_properties['copyright_year'], PDO::PARAM_INT);
    $stmt->bindValue (':num_sections', $book_properties['num_sections'], PDO::PARAM_INT);
    $stmt->bindValue (':url_rss', $book_properties['url_rss'], PDO::PARAM_STR);
    $stmt->bindValue (':url_zip_file', $book_properties['url_zip_file'], PDO::PARAM_STR);
    $stmt->bindValue (':url_project', $book_properties['url_project'], PDO::PARAM_STR);
    $stmt->bindValue (':url_librivox', $book_properties['url_librivox'], PDO::PARAM_STR);
    $stmt->bindValue (':url_iarchive', $book_properties['url_iarchive'], PDO::PARAM_STR);
    $stmt->bindValue (':url_other', $book_properties['url_other'], PDO::PARAM_STR);
    $stmt->bindValue (':totaltime', $book_properties['totaltime'], PDO::PARAM_STR);
    $stmt->bindValue (':totaltimesecs', $book_properties['totaltimesecs'], PDO::PARAM_INT);
    $stmt->bindValue (':authors', $book_properties['author_ids'], PDO::PARAM_STR);
    $stmt->bindValue (':sections', $book_properties['sections'], PDO::PARAM_STR);
    $stmt->bindValue (':genres', $book_properties['genre_ids'], PDO::PARAM_STR);
    $stmt->bindValue (':publicdate', $book_properties['publicdate'], PDO::PARAM_STR);
    $stmt->bindValue (':downloads', $book_properties['downloads'], PDO::PARAM_INT);
    $stmt->execute();
  }
  
  return true;
  
}

function insertSection ( $sectionXMLArray, $db, $parent_id, $iarchive_url ) { // inserts values for section, returns array of comma-separated section ids. A 'section' is a single track, either a chapter or sometimes a short story.

  // Since authors for tracks are not provided by LibriVox API, fetch from Internet Archive
  global $time_for_iafetch, $time_for_lvfetch;

  $iarchive_dl_url = str_replace ( '/details/','/download/', $iarchive_url );
  $iarchive_id = substr ( $iarchive_url, strrpos ($iarchive_url, '/') );
  $time = microtime(true);
  $iarchive_json_url = $iarchive_dl_url . $iarchive_id . '.json';
  $time_for_iafetch += (microtime(true) - $time );
  $json_array = json_decode ( file_get_contents ( $iarchive_json_url ), true );
  
  $track_to_author =  array();
  
  foreach ( $json_array as $entry ) {
    if ( $entry['creator'] != null && $entry['track'] != null) {
      if (  strpos ($entry['track'], '/') == false ) {
	$track_no = ltrim ($entry['track'], '0 ');
      } else {
	$track_no = substr($entry['track'], 0,  strpos ($entry['track'], '/'));
      }
      $track_to_author[$track_no] = $entry['creator'];
    }
  }
  
  // Parse LibriVox XML for other section information

  $section_ids="";
  foreach ( $sectionXMLArray as $section ) {
    $section_properties = array();
    foreach ($section['children'] as $section_property ) {
      if ( $section_property['name'] == 'id' ) {
	if ( $section_ids != "" ) $section_ids .= ",";
	$section_ids .= $section_property['content'];
      }
      if ( $section_property['name'] == 'readers' ) { // only getting first reader, with id separated by |
	$section_properties['reader_id'] = $section_property['children'][0]['children'][0]['content'];
	$section_properties['reader'] = $section_property['children'][0]['children'][1]['content'];
      } else {
	$section_properties[$section_property['name']] = $section_property['content'];
      }
    }
    $stmt = $db->prepare ('INSERT INTO sections VALUES (:id, :section_number, :title, :parent_id, :author, :reader_name, :reader_id)');
    $stmt->bindValue (':id', $section_properties['id'], PDO::PARAM_INT);
    $stmt->bindValue (':section_number', $section_properties['section_number'], PDO::PARAM_INT);
    $stmt->bindValue (':title', $section_properties['title'], PDO::PARAM_STR);
    $stmt->bindValue (':parent_id', $parent_id, PDO::PARAM_INT);
    $stmt->bindValue (':author', html_entity_decode ($track_to_author[$section_properties['section_number']]), PDO::PARAM_STR); // Obtained URL for iarchive JSON data for this collection from LibriVox XML.  Both LibriVox and iarchive provide track numbe.r  Then linking author (from iarchive) by track number.
    $stmt->bindValue (':reader_name', $section_properties['reader'], PDO::PARAM_STR);
    $stmt->bindValue (':reader_id', $section_properties['reader_id'], PDO::PARAM_INT);
    $stmt->execute();
  }
  return $section_ids;
}

class XmlArray {
  
  public function load_string ($s) {
    $node=simplexml_load_string($s);
    return $this->add_node($node);
  }
  
  private function add_node ($node, &$parent=null, $namespace='', $recursive=false) {
    
    if ( $node == null ) return;
    $namespaces = $node->getNameSpaces(true);
    $content="$node";
    
    $r['name']=$node->getName();
    if (!$recursive) {
      $tmp=array_keys($node->getNameSpaces(false));
      $r['namespace']=$tmp[0];
      $r['namespaces']=$namespaces;
    }
    if ($namespace) $r['namespace']=$namespace;
    if ($content) $r['content']=$content;
    
    foreach ($namespaces as $pre=>$ns) {
      foreach ($node->children($ns) as $k=>$v) {
        $this->add_node($v, $r['children'], $pre, true);
      }
      foreach ($node->attributes($ns) as $k=>$v) {
        $r['attributes'][$k]="$pre:$v";
      }
    }
    foreach ($node->children() as $k=>$v) {
      $this->add_node($v, $r['children'], '', true);
    }
    foreach ($node->attributes() as $k=>$v) {
      $r['attributes'][$k]="$v";
    }
    
    $parent[]=&$r;
    return $parent[0];
    
  }
}

?>