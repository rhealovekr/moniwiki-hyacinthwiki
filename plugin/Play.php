<?php
// Copyright 2004-2007 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a media Play macro plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2004-08-02
// Name: Play macro
// Description: media Player Plugin
// URL: MoniWikiDev:PlayMacro
// Version: $Revision: 1.7 $
// License: GPL
//
// Usage: [[Play(http://blah.net/blah.mp3)]]
//
// $Id: Play.php,v 1.7 2007/10/09 05:11:45 wkpark Exp $

function GetID3($mp3name) // by solidox, 2002
{
    if(!$mp3name)
        return false;

    $fp = @fopen($mp3name, "r");
    if(!$fp)
        return false;
    fseek($fp, -128, SEEK_END);
    $header = fread($fp, 3);
    if(!strcmp($header, "TAG"))
    {
        $id3['title'] = fread($fp, 30);
        $id3['artist'] = fread($fp, 30);
        $id3['album'] = fread($fp, 30);
        $id3['year'] = fread($fp, 4);
        $id3['comment'] = fread($fp, 30);
        $id3['genre'] = ord(fread($fp, 1)); //number, use lookup table to get names
        fclose($fp);
        return $id3;
    }
    fclose($fp);
    return false;
}

function macro_Play($formatter,$value) {
  global $DBInfo;
  $max_width=600;

  # default
  $autostart="no";
  $loop="no";
  $animation="no";
  $default_width=290;
  $initialvolume=60;
  $rtl="no";

  #
  $media=array();
  #

  $value=preg_replace("/&amp;/u","%26",$value);
# preg_match("/^(([^,]+\s*,?\s*)+)$/",$value,$match);
  preg_match("/^(([^,]+\s*,?\s*)+)$/",$value,$match);

  if (!$match) return '[[Play(error!! '.$value.')]]';

  if (($p=strpos($match[1],','))!==false) {
    $my=explode(',',$match[1]);
    for ($i=0,$sz=count($my);$i<$sz;$i++) {
      if (strpos($my[$i],'=')) {
        list($key,$val)=explode('=',$my[$i]);
        if ($key == 'width' and $val > 0) {
          $width=$val;
	    }
	    if ($key == 'autostart') {
          $autostart=$val;
	    }
	    if ($key == 'loop') {
          $loop=$val;
	    }
	    if ($key == 'animation') {
          $animation=$val;
	    }
	    if ($key == 'initialvolume') {
          $initialvolume=$val;
	    }
	    if ($key == 'rtl') {
          $rtl=$val;
	    }
		if ($key == 'titles') {
		  $val=preg_replace("/'/","",$val);
          $titles=$val;
		  $i++;
		  while (!strpos($my[$i],'=') and $i<$sz)
		  {
			$val=$my[$i];
			$val=preg_replace("/'/","",$val);
			$titles=$titles.','.$val;
			$i++;
		  }
		  $i--;
	    }
		if ($key == 'artists') {
		  $val=preg_replace("/'/","",$val);
          $artists=$val;
		  $i++;
		  while (!strpos($my[$i],'=') and $i<$sz)
		  {
			$val=$my[$i];
			$val=preg_replace("/'/","",$val);
			$artists=$artists.','.$val;
			$i++;
		  }
		  $i--;
	    }	
      } else { // multiple files
        $media[]=$my[$i];
      }
    }
  } else {
    $media[]=$match[1];
  }

  $value=preg_replace("/,([^,]*)=(.*)/u","",$value);
  # echo $value;
  # get id3 info
#  $m00 = GetID3($value);
#  if($m00['artist'])
#    $artists = $m00['artist'];
#  if($m00['title'])
#    $titles = $m00['title'];

  # set embeded object size
  $width=$width ? min($width,$max_width):$default_width;

  $url=array();
  $my_check=1;
  for ($i=0,$sz=count($media);$i<$sz;$i++) {
    if (!preg_match("/^(http|ftp|mms|rtsp):\/\//",$media[$i])) {
      $fname=$formatter->macro_repl('Attachment',$media[$i],1);
      if ($my_check and !file_exists($fname)) {
        return $formatter->macro_repl('Attachment',$value);
      }
      $my_check=1; // check only first file.
      $url[]=qualifiedUrl($DBInfo->url_prefix."/"._urlencode($fname));
    } else {
      $url[]=$media[$i];
    }
  }

  $swfobject_num=$GLOBALS['swfobject_num'] ? $GLOBALS['swfobject_num']:0;
  if (!$swfobject_num) {
    $swfobject_script="<script type=\"text/javascript\" src=\"$DBInfo->url_prefix/local/audio-player/audio-player.js\"></script><script type=\"text/javascript\"> 
        AudioPlayer.setup(\"$DBInfo->url_prefix/local/audio-player/player.swf\", {  
		width: $width,
		autostart: \"$autostart\",
		loop: \"$loop\",
		animation: \"$animation\",
		initialvolume: $initialvolume,
		rtl: \"$rtl\",
        transparentpagebg: \"yes\",
		checkpolicy: \"yes\",
        bg: \"FFFFFF\",
        voltrack: \"CCCCCC\",
        leftbg: \"F7F7F7\",
        lefticon: \"3DA5CF\",
        rightbg: \"AAE0F9\",
        righticon: \"0072BC\",
        rightbghover: \"7CB7CF\"
            });  
        </script>  ";
      $num=1;
    } else {
      $num=++$swfobject_num;
    }
    $GLOBALS['swfobject_num']=$num;

EOS;
    $player_script=<<<EOS
<p id="audioplayer$num">Alternative content</p>
<script type="text/javascript">
        AudioPlayer.embed("audioplayer$num", {  
        soundFile: "$value",
        titles: "$titles",
        artists: "$artists"
		}); 
</script>
EOS;
    return <<<EOS
		$swfobject_script$player_script
EOS;
    return $out;
}

?>
