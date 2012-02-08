<?php
class SpecialDuererExport extends SpecialPage {
  function __construct() {
    parent::__construct( 'DuererExport' );
  }
  
  function execute( $par ) {
    global $wgRequest, $wgOut;

    $this->setHeaders();
                                                                          
    # Get request data from, e.g.
    $param = $wgRequest->getText('param');
    
    $settings['wikiroot'] = "http://localhost/duererwiki/";
    $settings['user'] = "Fichtner";
    $settings['pass'] = "Knurgx";
    $settings['cookiefile'] = "/tmp/cookies.tmp";

    // log in to the system
    try {
      $token = $this->login($settings, $settings['user'], $settings['pass']);
      $this->login($settings, $settings['user'], $settings['pass'], $token);
      $wgOut->addHTML("Welcome to the Duerer Export.");
    } catch (Exception $e) {
      $wgOut->addHTML("Failed to log in: " . serialize($e->getMessage()));
      return;
    }
    
    // we should be logged in now.
    // get subcategories of a certain category
    $category = urlencode("Category:Personen");
    /*
    $post = "action=query&list=categorymembers&format=php&cmtitle=$category&cmnamespace=0";
    
    $categories = array();
    $tmp = array();
    do {
      if(!empty($tmp) && !empty($tmp['query-continue']))
        $post .= ("&cmcontinue=" . $tmp['query-continue']['categorymembers']['cmcontinue']);
      $got_tmp = $this->httpRequest($settings, $settings['wikiroot'] . "api.php", $post);
      
      $tmp = unserialize($got_tmp);
      $categories = array_merge($categories, $tmp['query']['categorymembers']);
    } while (!empty($tmp) && !empty($tmp['query-continue']) );
    */
    // now we should have it.
    
    //$output = serialize($categories);
    
    //$output = serialize($this->getRecursiveSubCategories($settings, urlencode("Category:Personen")));
    //$output = serialize($this->getRecursivePages($settings, urlencode("Category:Personen")));
    
    // All pages with the chosen category on it
    $categorypages = $this->getRecursivePages($settings, $category);
    
    $internal = array();
    $structure = array();
    $multifields = array();
    foreach($categorypages as $categorypage) {
      //$output = serialize($categorypage);
      $internal[$categorypage['pageid']] = ($this->getAllFromPage($settings, $categorypage['pageid']));

//      $output .= serialize($internal);
//      break;
      
      foreach($internal[$categorypage['pageid']]['structure'] as $key => $fields) {
        //$output .= serialize($key) . ' has value ' . serialize($fields);
        if(!isset($structure[$key]))
          $structure[$key] = array();
        if(!isset($multifieldstructure[$key]))
          $multifieldstructure[$key] = array();
          
        $before = count($structure[$key]);
        $structure[$key] = array_unique(array_merge($structure[$key], $fields));
        $after = count($structure[$key]);
        if($after > $before)
          $output .= "New elements, total elements: " . serialize($structure[$key]) . " in node " . $categorypage['pageid'] . " with title " . $internal[$categorypage['pageid']]['title'] . "<br/>"; 
        if(!empty($internal[$categorypage['pageid']]['multifieldstructure'][$key])) {
          $multifieldstructure[$key] = array_unique(array_merge($internal[$categorypage['pageid']]['multifieldstructure'][$key], $multifieldstructure[$key]));
        }    
        
      }
//      break;
    }
    
    $output .= "Multifieldstructure: " . serialize($multifieldstructure);
    
    $host = "localhost";
    $user = "mark";
    $pass = "Fr0!sch";
    $db = "duererdaten";
    
    $connection = mysql_connect($host, $user, $pass) or die ("Verbindungsversuch fehlgeschlagen");

    mysql_select_db($db, $connection) or die("Konnte die Datenbank nicht waehlen. Error: " . mysql_error());

    /* Basic structure from infoboxes */
    foreach($structure as $name => $rows) {
      $sql = "CREATE TABLE `$name` ( `id` INT NOT NULL AUTO_INCREMENT, `pageid` INT NOT NULL , ";
      foreach($rows as $row) {
        $sql .= "`$row` VARCHAR( 255 ) , ";
      }
      
      $sql .= "PRIMARY KEY ( `id` ) ) ENGINE = MYISAM;";
            
      $create_query = mysql_query($sql) or die("Anfrage $sql nicht erfolgreich. Error: " . mysql_error());
      $output .= $sql;
    }
    
    /* Basic structure from enum fields */
    foreach($multifieldstructure as $table => $fields) {
      foreach($fields as $field) {
        $sql = "CREATE TABLE `" . $table . "_" . "$field` ( `id` INT NOT NULL AUTO_INCREMENT, `pageid` INT NOT NULL , ";
        $sql .= "`value` VARCHAR( 255 ) , ";
      
        $sql .= "PRIMARY KEY ( `id` ) ) ENGINE = MYISAM;";
            
        $create_query = mysql_query($sql) or die("Anfrage $sql nicht erfolgreich. Error: " . mysql_error());
        $output .= $sql . "<br/>";
      }
    }
    
    /* insert all data per node */
    foreach($internal as $nodeid => $base) {
      /* insert values in main table */
      foreach($base['values'] as $name => $fields) {
        $sql1 = "INSERT INTO `$name` (`pageid`";
        $sql2 = ") VALUES ($nodeid";
        
        foreach($fields as $key => $value) {
          $sql1 .= ",`$key`";
          $sql2 .= ",'" . mysql_real_escape_string($value) . "'";
        }
        
        //$sql1 = substr($sql1, 0, -1);
        //$sql2 = substr($sql2, 0, -1);
        
        $sql = $sql1 . $sql2 . ")";
        mysql_query($sql) or die("Anfrage $sql nicht erfolgreich. Error: " . mysql_error() );
      }
      
      /* expand side tables */
      foreach($multifieldstructure as $table => $fields) {
      //foreach($base['multifieldvalues'] as $name => $fields) {
        foreach($fields as $name) {
          if(!empty($base['multifieldvalues'][$table][$name])) {
            foreach($base['multifieldvalues'][$table][$name] as $value) {
              $sql1 = "INSERT INTO `$table" . "_" . "$name` (`pageid`, `value`";
              $sql2 = ") VALUES ($nodeid";
              $sql2 .= ",'" . mysql_real_escape_string($value) . "'";
              $sql = $sql1 . $sql2 . ")";
              mysql_query($sql) or die("Anfrage $sql nicht erfolgreich. Error: " . mysql_error() );
            }
          } else {
            if(!empty($base['values'][$table][$name])) {
              $sql1 = "INSERT INTO `$table" . "_" . "$name` (`pageid`, `value`";
              $sql2 = ") VALUES ($nodeid";
              $sql2 .= ",'" . mysql_real_escape_string($base['values'][$table][$name]) . "'";
              $sql = $sql1 . $sql2 . ")";
              mysql_query($sql) or die("Anfrage $sql nicht erfolgreich. Error: " . mysql_error() );
            }
          }
        }
      }
    }
    
    
    
    $output .= serialize($structure) . "<br/>";
   //$output = serialize($structure); //"done";   
    // log out
    //$output = $this->logout($settings);
                                                                                                  
    # Do stuff
    # ...
//    $output = 'Hello world! Param was ' . serialize($param);
    $wgOut->addHTML( $output );
  }

  function getRecursivePages($settings, $category) {
    $pages = $this->getPagesForCategory($settings, $category);
    $categories = $this->getRecursiveSubCategories($settings, $category);
    
    foreach($categories as $a_cat) {
      foreach($this->getPagesForCategory($settings, urlencode($a_cat['title'])) as $page)
        $pages[$page['pageid']] = $page;
    }
    return $pages;
  }
  
  function getBlocks($content, $pageid) {
    $allblocks = array();
    $startarr = array();
    $endarr = array();

    $start = strpos($content, "{{");
    
    while($start !== FALSE) {
      $startarr[$start] = $start;
      $start = strpos($content, "{{", $start + 2);
    }
    sort($startarr);
        
    $end = strpos($content, "}}");
    while($end !== FALSE) {
      $endarr[$end] = $end;
      $end = strpos($content, "}}", $end + 2);
    }
    sort($endarr);
    /*
    if($pageid == 1030) {
      global $wgOut;
      $wgOut->addHTML("startarr: " . serialize($startarr) . "<br/>");
      $wgOut->addHTML("endarr: " . serialize($endarr) . "<br/>");
    }
    */
    
    while(count($startarr) > 0 && count($endarr) > 0) {
      $endToWorkOn = array_shift($endarr);
      
      foreach($startarr as $key => $value) {
        if($value >= $endToWorkOn)
         break;
        $startKey = $key;
      }
      
      $startToWorkOn = $startarr[$startKey];
      unset($startarr[$startKey]);
      
      $allblocks[] = substr($content, $startToWorkOn+2, $endToWorkOn - $startToWorkOn - 2);
        
    }
    
    if(count($startarr) > 0 || count($endarr) > 0) {
      global $wgOut;
      $wgOut->addHTML("Error in Analysis at content '$content'");
      return array();
    } 
    
    return $allblocks;
    
  }
  
  function getAllFromPage($settings, $pageid) {
    $post = "action=query&prop=revisions&rvlimit=1&rvprop=content&format=php&pageids=" . $pageid;
    
    $got_tmp = $this->httpRequest($settings, $settings['wikiroot'] . "api.php", $post);
    
    $tmp = unserialize($got_tmp);
    
    $structure = array();
    $multifieldstructure = array();
    $multifieldvalues = array();
    $values = array();
    
    $content = $tmp['query']['pages'][$pageid]['revisions'][0]['*'];
    $title = $tmp['query']['pages'][$pageid]['title'];
    
    $boxes = $this->getBlocks($content, $pageid);
    
    foreach($boxes as $box) {
//      $box = substr($box, 2, -2);

      preg_match("/([^\|]+)/m", $box, $boxname);
      $boxname = trim($boxname[0]);

//      return $boxname;
//      preg_match_all("/\|[^\|]*(\[\[.*?\]\]|\{\{.*?\}\})[^\|]*|\|[^\|]*/m", $box, $fields);
      preg_match_all("/\|(\[\[.+?\]\]|\{\{.+?\}\}|[^\|])+/m", $box, $fields);

      /*
      if($pageid == 1030) {
        global $wgOut;
        $wgOut->addHTML("box from 1030: " . serialize($boxes));
        $wgOut->addHTML("data from 1030: " . serialize($fields));
      }
      */
      
      if(!empty($fields[0])) {
        foreach($fields[0] as $field) {
          preg_match("/\|([^=\|]+)=(.*)/m", $field, $hits);
          $key = trim($hits[1]);
          $value = trim($hits[2]);
          
          /*
          if(preg_match_all("/\*([^\*])+/m", $field, $multihits)) { 
            global $wgOut;
            $wgOut->addHTML("multi: " . serialize($multihits));
          }
          */
          preg_match_all("/\*([^\*])+/m", $field, $multihits);
          
          if(!empty($key))
            $structure[$boxname][] = $key;
          if(!empty($value))
            $values[$boxname][$key] = $value;
          
          foreach($multihits[0] as $multi) {
//            global $wgOut;
//            $wgOut->addHTML("doing smthg multi: " . serialize($multi));
            $multi = trim(substr($multi, 1));
            if(!empty($key))
              $multifieldstructure[$boxname][] = $key;
            if(!empty($multi))
              $multifieldvalues[$boxname][$key][] = $multi;
          }
        }
      }
    }
    
    return array('structure' => $structure, 'values' => $values, 'multifieldstructure' => $multifieldstructure, 
      'multifieldvalues' => $multifieldvalues, 'title' => $title, 'pageid' => $pageid, 'text' => $content);
  }

  function getPagesForCategory($settings, $category) {
    $post = "action=query&list=categorymembers&format=php&cmlimit=5000&cmtitle=$category&cmnamespace=0";
    
    $pages = array();
    $tmp = array();
    do {
      if(!empty($tmp) && !empty($tmp['query-continue']))
        $post .= ("&cmcontinue=" . $tmp['query-continue']['categorymembers']['cmcontinue']);
      $got_tmp = $this->httpRequest($settings, $settings['wikiroot'] . "api.php", $post);
      
      $tmp = unserialize($got_tmp);
      
      $pages = array_merge($pages, $tmp['query']['categorymembers']);
    } while (!empty($tmp) && !empty($tmp['query-continue']) );
    
    $outpages = array();
    
    foreach($pages as $key => $page) {
      $outpages[$page['pageid']] = $page;
    }
    
    return $outpages;
  }
  
  function getRecursiveSubCategories($settings, $startcat) {
    $to_do_categories = array();
    $donecategories = array();
    
    $to_do_categories = $this->getSubCategories($settings, $startcat);
    while($a_cat = array_pop($to_do_categories)) {
      if(in_array($a_cat, $donecategories)) {
        continue;
      }

      $to_do_categories = array_merge($to_do_categories, $this->getSubCategories($settings, urlencode($a_cat['title'])));
      $donecategories[$a_cat['pageid']] = $a_cat;      
    }
    
    return $donecategories;
  }
  
  function getSubCategories($settings, $startcat) {
    $post = "action=query&list=categorymembers&format=php&cmlimit=5000&cmtitle=$startcat&cmnamespace=" . NS_CATEGORY;
    
    $categories = array();
    $tmp = array();
    do {
      if(!empty($tmp) && !empty($tmp['query-continue']))
        $post .= ("&cmcontinue=" . $tmp['query-continue']['categorymembers']['cmcontinue']);
      $got_tmp = $this->httpRequest($settings, $settings['wikiroot'] . "api.php", $post);
      
      $tmp = unserialize($got_tmp);
      
      $categories = array_merge($categories, $tmp['query']['categorymembers']);
    } while (!empty($tmp) && !empty($tmp['query-continue']) );
    
    $outcats = array();
    
    foreach($categories as $key => $cat) {
      $outcats[$cat['pageid']] = $cat;
    }
    
    return $outcats;
  }

  function httpRequest($settings, $url, $post="") {
    
    $ch = curl_init();
    //Change the user agent below suitably
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.9) Gecko/20071025 Firefox/2.0.0.9');
    curl_setopt($ch, CURLOPT_URL, ($url));
    curl_setopt($ch, CURLOPT_ENCODING, "UTF-8" );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $settings['cookiefile']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $settings['cookiefile']);
    if (!empty($post)) curl_setopt($ch,CURLOPT_POSTFIELDS,$post);
    //UNCOMMENT TO DEBUG TO output.tmp
/*
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Display communication with server
    $fp = fopen("/tmp/output.tmp", "w");
    curl_setopt($ch, CURLOPT_STDERR, $fp); // Display communication with server
*/    
    $xml = curl_exec($ch);
    
    if (!$xml) {
      throw new Exception("Error getting data from server ($url): " . curl_error($ch));
    }
    
    curl_close($ch);
    
    return $xml;
  }
  
  function logout($settings) {
    $url = $settings['wikiroot'] . "api.php";
    $params = "action=logout";
    
    $data = $this->httpRequest($settings, $url, $params);
    
    if (empty($data)) {
      throw new Exception("No data received from server. Check that API is enabled.");
    }
    
    return $data;
  }
  
  function login ($settings, $user, $pass, $token='') {
    $url = $settings['wikiroot'] . "api.php";

    $params = "action=login&lgname=$user&lgpassword=$pass";
    if (!empty($token)) {
      $params .= "&lgtoken=$token";
    }
    $params .= "&format=xml";
                                                          
    $data = $this->httpRequest($settings, $url, $params);
                                                          
    if (empty($data)) {
      throw new Exception("No data received from server. Check that API is enabled.");
    }
                                                                                                          
    $xml = simplexml_load_string($data);
                                                                                                                          
    if (!empty($token)) {
    //Check for successful login
      $expr = "/api/login[@result='Success']";
      $result = $xml->xpath($expr);
                                                                                                                                                                                  
      if(!count($result)) {
        throw new Exception("Login failed");
      }
    } else {
      $expr = "/api/login[@token]";
      $result = $xml->xpath($expr);
                                                                                                                                                                                                                                                                                 
      if(!count($result)) {
        throw new Exception("Login token not found in XML");
      }
    }
                                                                                                                                                                                                                                                                                                                                                          
    return $result[0]->attributes()->token;
  }

}
