<?php
// All rights reserved. Distributable under GPL see COPYING
// a atom macro plugin for the MoniWiki
//
// Date: 2014-05-11
// Name: Atom Feeder/Reader
// Description: Atom Plugin
// URL: AtomMacro
// Version: $Revision: 1.4$
// License: GPLv2
//
// $Id: atom.php,v 1.3 2010/07/09 11:03:27 wkpark Exp $
// $orig Id: rss.php,v 1.7 2010/08/23 09:15:23 wkpark Exp $
// $orig Id: rss_rc.php,v 1.12 2005/09/13 09:10:52 wkpark Exp $

class WikiAtomParser {

   var $insideitem = false;
   var $tag = "";
   var $title = "";
   var $description = "";
   var $link = "";
   var $date = "";

   function WikiAtomParser() {
   }

   function startElement($parser, $tagName, $attrs) {
       if ($this->insideitem) {
           if ($tagName == "LINK") { // self-closing tag
             if ($attrs['REL'] == "alternate") {
               $this->link = $attrs['HREF'];
             }
           }
           $this->tag = $tagName;
       } elseif ($tagName == "ENTRY") {
           $this->insideitem = true;
       } elseif ($tagName == "IMAGE") {
           if (!empty($attrs['RDF:RESOURCE']))
           print "<img src=\"".$attrs['RDF:RESOURCE']."\"><br />";
       }
   }

   function endElement($parser, $tagName) {
       if ($tagName == "ENTRY") {
         if ($this->insideitem) {
           if ($this->status) print "[$this->status] ";
           printf("<a href='%s' target='_self'>%s</a>",
             trim($this->link),
             htmlspecialchars(trim($this->title)));
           #printf("<p>%s</p>",
           #  htmlspecialchars(trim($this->description)));
           if ($this->date) {
             $date=trim($this->date);
             $date[10]=" ";
             # 2003-07-11T12:08:33+09:00
             # http://www.w3.org/TR/NOTE-datetime
             $zone=str_replace(":","",substr($date,19));
             $time=strtotime(substr($date,0,19).$zone);
             $date=date("@ m-d [h:i a]",$time);
             printf(" %s<br />\n", htmlspecialchars(trim($date)));
           } else
             printf("<br />\n");
           $this->title = "";
           $this->description = "";
           $this->link = "";
           $this->date = "";
           $this->status = "";
           $this->insideitem = false;
         }
       }
   }

   function characterData($parser, $data) {
       if ($this->insideitem) {
           switch ($this->tag) {
               case "TITLE":
               $this->title .= $data;
               break;
               case "DESCRIPTION":
               $this->description .= $data;
               break;
               case "LINK":
               #$this->link .= $data;
               break;
               case "PUBLISHED":
               #$this->date .= $data;
               break;
               case "WIKI:STATUS":
               $this->status .= $data;
               break;
           }
           #print $this->tag."/";
       }
   }
}

function macro_Atom($formatter,$value) {
  global $DBInfo;

  $xml_parser = xml_parser_create();

  $atom_parser = new WikiAtomParser();
  xml_set_object($xml_parser,$atom_parser);
  xml_set_element_handler($xml_parser, "startElement", "endElement");
  xml_set_character_data_handler($xml_parser, "characterData");

  // get opt
  if (preg_match("/(.[^,]*),(.*)/", $value, $matches))
  {
    $src = $matches[1];
    $opt_raw = preg_split("/[\s,]+/", $matches[2]);

    foreach($opt_raw as $optset) {
      $optval = preg_split("/[\s=]+/", $optset);
      $opt[$optval[0]] = $optval[1];
    }
  }
  else
  {
    $src = $value;
  }

  $key=_rawurlencode($src);

  $cache= new Cache_text("atom");
  # refresh rss each 3480 second (58*60) 58 min.
  if (!$cache->exists($key) or (time() > $cache->mtime($key) + 3480)) {
#  if (1) { // no cache
    $URL_parsed = parse_url($src);

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
      return ("[[Atom(ERR: not a valid URL! $src)]]");

    fputs($fp, $out);
    $body = false;
    while (!feof($fp)) {
      $data = fgets($fp, 4096);
      if ($body == false) {
        if (preg_match('/Location: http:\/\/(.[^\/]*)(.*)/',$data,$m))
        {
          fclose($fp);
          $host = $m[1];
          $path = $m[2];
          $out = "GET $path HTTP/1.0\r\nHost: $host\r\n\r\n";
          echo $out;

          $fp = fsockopen($host, $port, $errno, $errstr, 30);
          fputs($fp, $out);
          continue;
        }
      }
      else {
        $xml_data .= $data;
      }
      if ($data == "\r\n")
        $body = true;
    }
    fclose($fp);
    $cache->update($key,$xml_data);
  } else {
    $xml_data=$cache->fetch($key);
  }

  list($line,$dummy)=explode("\n",$xml_data,2);
  preg_match("/\sencoding=?(\"|')([^'\"]+)/",$line,$match);
  if ($match) $charset=strtoupper($match[2]);
  else $charset='UTF-8';
  # override $charset for php5
  if ((int)phpversion() >= 5) $charset='UTF-8';

  // exceptions for xml format error
  $xml_data=str_replace("&","&amp;",$xml_data);
  $xml_data=str_replace("","",$xml_data);
  $xml_data=preg_replace("/<p>.[^<]*<\/p>/","",$xml_data); // delete contents
#  $xml_data = htmlspecialchars($xml_data);
#  $xml_data = preg_replace("/<summary.*<\/summary>/", "", $xml_data);

  ob_start();
  $ret= xml_parse($xml_parser, $xml_data);

  if (!$ret) {
    ob_end_clean();
    return (sprintf("[[Atom(XML error: %s at line %d)]]",  
      xml_error_string(xml_get_error_code($xml_parser)),  
      xml_get_current_line_number($xml_parser)));
  }
  $out=ob_get_contents();
  ob_end_clean();
  xml_parser_free($xml_parser);

  #  if (strtolower(str_replace("-","",$options['oe'])) == 'euckr')
  if (function_exists('iconv') and strtoupper($DBInfo->charset) != $charset) {
    $new=iconv($charset,$DBInfo->charset,$out);
    if ($new !== false) return $new;
  }

  if (empty($opt["list"]))
  {
    return $out;
  }
  else
  {
    if ($opt["list"] <= 0)
      return $out;

    $lines = explode("<br />", $out);
    $limit = $opt["list"] < count($lines) ? $opt["list"] : count($lines) - 1;
    for ($i = 0; $i < $limit; ++$i) {
      $lines_comp .= $lines[$i] . "<br />";
    }
    return $lines_comp;
  }
}

function do_atom($formatter,$options) {
  global $DBInfo;
  global $_release;
  define('ATOM_DEFAULT_DAYS',7);

  $days=$DBInfo->rc_days ? $DBInfo->rc_days:ATOM_DEFAULT_DAYS;
  $options['quick']=1;
  if ($options['c']) $options['items']=$options['c'];
  $lines= $DBInfo->editlog_raw_lines($days,$options);
    
  $time_current= time();
#  $secs_per_day= 60*60*24;
#  $days_to_show= 30;
#  $time_cutoff= $time_current - ($days_to_show * $secs_per_day);

  $URL=qualifiedURL($formatter->prefix);
  $img_url=qualifiedURL($DBInfo->logo_img);

  $url=qualifiedUrl($formatter->link_url($DBInfo->frontpage));
  $surl=qualifiedUrl($formatter->link_url($options['page'].'?action=atom'));
  $channel=<<<CHANNEL
  <title>$DBInfo->sitename</title>
  <link href="$url"></link>
  <link rel="self" type="application/atom+xml" href="$surl" />
  <subtitle>RecentChanges at $DBInfo->sitename</subtitle>
  <generator version="$_release">MoniWiki Atom feeder</generator>\n
CHANNEL;
  $items="";

  $ratchet_day= FALSE;
  if (!$lines) $lines=array();
  foreach ($lines as $line) {
    $parts= explode("\t", $line);
    $page_name= $DBInfo->keyToPagename($parts[0]);
    $addr= $parts[1];
    $ed_time= $parts[2];
    $user= $parts[4];
    $user_uri='';
    if ($DBInfo->hasPage($user)) {
      $user_uri= $formatter->link_url(_rawurlencode($user),"",$user);
      $user_uri='<uri>'.$user_uri.'</uri>';
    }
    $log= _stripslashes($parts[5]);
    $act= rtrim($parts[6]);

    $url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name)));
    $diff_url=qualifiedUrl($formatter->link_url(_rawurlencode($page_name),'?action=diff'));

    $extra="<br /><a href='$diff_url'>"._("show changes")."</a>\n";
    $content='';
    if (!$DBInfo->hasPage($page_name)) {
      $status='Deleted';
      $content="<content type='html'><a href='$url'>$page_name</a> is deleted</content>\n";
    } else {
      $status='Updated';
      if ($options['diffs']) {
        $p=new WikiPage($page_name);
        $f=new Formatter($p);
        $options['raw']=1;
        $options['nomsg']=1;
        $html=$f->macro_repl('Diff','',$options);
        if (!$html) {
          ob_start();
          $f->send_page('',array('fixpath'=>1));
          #$f->send_page('');
          $html=ob_get_contents();
          ob_end_clean();
          $extra='';
        }
        $content="  <content type='xhtml'><div xmlns='http://www.w3.org/1999/xhtml'>$html</content>\n";
      } else if ($log) {
        $html=str_replace('&','&amp;',$log);
        $content="<content type='text'>".$html."</content>\n";
      } else {
        $content="<content type='text'>updated</content>\n";
      }
    }
    $zone = '+00:00';
    $date = gmdate("Y-m-d\TH:i:s",$ed_time).$zone;
    if (!isset($updated)) $updated=$date;
    #$datetag = gmdate("YmdHis",$ed_time);

    $valid_page_name=str_replace('&','&amp;',$page_name);
    $items.="<entry>\n";
    $items.="  <title>$valid_page_name</title>\n";
    $items.="  <link href='$url'></link>\n";
    $items.='  '.$content;
    $items.="  <author><name>$user</name>$user_uri</author>\n";
    $items.="  <updated>$date</updated>\n";
    $items.="  <contributor><name>$user</name>$user_uri</contributor>\n";
    $items.="</entry>\n";
  }
  $updated="  <updated>$updated</updated>\n";

  $new="";
  if ($options['oe'] and (strtolower($options['oe']) != $DBInfo->charset)) {
    $charset=$options['oe'];
    if (function_exists('iconv')) {
      $out=$head.$channel.$items.$form;
      $new=iconv($DBInfo->charset,$charset,$out);
      if (!$new) $charset=$DBInfo->charset;
    }
  } else $charset=$DBInfo->charset;

  $head=<<<HEAD
<?xml version="1.0" encoding="$charset"?>
<!--<?xml-stylesheet href="$DBInfo->url_prefix/css/_feed.css" type="text/css"?>-->
<feed xmlns="http://www.w3.org/2005/Atom">
<!--
    Add "diffs=1" to add change diffs to the description of each items.
    Add "oe=utf-8" to convert the charset of this rss to UTF-8.
-->\n
HEAD;
  header("Content-Type: application/xml");
  if ($new) print $head.$new;
  else print $head.$channel.$updated.$items.$form;
  print "</feed>\n";
}
?>
