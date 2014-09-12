<?php
// from http://www.sitepoint.com/examples/phpxml/sitepointcover-oo.php.txt
// Public Domain ?
// $Id: DisqusComments.php,v 1.0 2014/06/02 14:32:44 hyacinth Exp $

class DisqusComments {

   var $insideitem = false;
   var $tag = "";
   var $title = "";
   var $description = "";
   var $creator = "";
   var $link = "";
   var $date = "";
   var $date_format = "F jS, Y";
   var $cnt = 5;
   var $show_opt = "titleonly";

   function DisqusComments() {
   }

   function startElement($parser, $tagName, $attrs) {
       if ($this->insideitem) {
           $this->tag = $tagName;
       } elseif ($tagName == "ITEM") {
           $this->insideitem = true;
       } elseif ($tagName == "IMAGE") {
           if ($attrs['RDF:RESOURCE'])
           print "<img src=\"".$attrs['RDF:RESOURCE']."\"><br />";
       }
   }

   function endElement($parser, $tagName) {
       if ($tagName == "ITEM" and $this->end_count < $this->cnt) {
           $this->end_count++;
           if ($this->status) print "[$this->status] ";
           if ($this->show_opt == "all") {
             printf("<div class=\"disqus-comments\">%s -- %s ", $this->description, $this->creator);
           }
           printf("<a href='%s'>%s</a>",
             trim($this->link),
             htmlspecialchars(trim($this->title)));
           
           if ($this->date) {
             $time=str_replace("-0000","",$this->date);
             $date=date($this->date_format, strtotime($time)+50400);
             printf(" <span class=\"blog-user\">%s %s </span><br/>\n", htmlspecialchars(trim($date)), $this->tz_off);

             if ($this->show_opt == "all")
               print "</div>";
           } else
             printf("<br />\n");
           $this->title = "";
           $this->description = "";
           $this->creator = "";
           $this->link = "";
           $this->date = "";
           $this->status = "";
           $this->insideitem = false;
       }
   }

   function characterData($parser, $data) {
       if ($this->insideitem) {
           switch ($this->tag) {
               case "TITLE":
               $data = str_replace("Re: ","",$data);
               $data = str_replace("hrp: ","",$data);
               $this->title .= $data;
               break;
               case "DESCRIPTION":
               $this->description .= $data;
               break;
               case "DC:CREATOR":
               $this->creator .= $data;
               break;
               case "LINK":
               $this->link .= $data;
               break;
               case "PUBDATE":
               $this->date .= $data;
               break;
               case "WIKI:STATUS":
               $this->status .= $data;
               break;
           }
           //TITLE/LINK/DESCRIPTION/DC:CREATOR/PUBDATE
           //print $this->tag."/";
       }
   }
}

function macro_DisqusComments($formatter,$value) {
  global $DBInfo;
  $this->end_count = 0;

  $xml_parser = xml_parser_create();

  $rss_parser = new DisqusComments();
  xml_set_object($xml_parser,$rss_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  list($username,$cnt,$show_opt)=explode(",",$value,3);

  if (!empty($cnt) and is_numeric($cnt))
    $rss_parser->cnt = trim($cnt);
  if (!empty($show_opt))
    $rss_parser->show_opt = trim($show_opt);

  $key=_rawurlencode($username);

  $cache= new Cache_text("rss");
  // refresh rss cache every 5 minutes (60*5)
  if (!$cache->exists($key) or (time() > $cache->mtime($key) + 300 )) {
    $URL_parsed = parse_url("http://".$username.".disqus.com/latest.rss");

    $host = $URL_parsed["host"];
    $port = $URL_parsed["port"];
    if ($port == 0)
      $port = 80;

    $path = $URL_parsed["path"];
    if ($URL_parsed["query"] != "")
      $path .= "?".$URL_parsed["query"];

    $out = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";
    $fp = fsockopen($host, $port, $errno, $errstr, 30);

    if (!$fp)
      return ("[[DisqusComments(ERR: not a valid URL! $value)]]");

    fputs($fp, $out);
    $body = false;
    while(!feof($fp)) {
      $data = fgets($fp, 4096);
      if ($body)
        $xml_data .= $data;
      if ($data == "\r\n")
        $body = true;
    }

    fclose ($fp);
    $cache->update($key,$xml_data);
  } else
    $xml_data=$cache->fetch($key);

  list($line,$dummy)=explode("\n",$xml_data,2);
  preg_match("/\sencoding=?(\"|')([^'\"]+)/",$line,$match);
  if ($match) $charset=strtoupper($match[2]);
  else $charset='UTF-8';
  // override $charset for php5
  if ((int)phpversion() >= 5) $charset='UTF-8';

  ob_start();
  $ret= xml_parse($xml_parser, $xml_data);

  if (!$ret)
    return (sprintf("[[DisqusComments(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));
  $out=ob_get_contents();
  ob_end_clean();
  xml_parser_free($xml_parser);

  if (function_exists('iconv') and strtoupper($DBInfo->charset) != $charset)
    $new=iconv($charset,$DBInfo->charset,$out);
  if ($new) return $new;

  return $out;
}

?>
