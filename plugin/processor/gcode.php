<?php
// Copyright 2003 by Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a vim colorizer plugin for the MoniWiki
//
// Usage: {{{#!vim sh|c|sh|.. [number]
// some codes
// }}}
// $Id: vim.php,v 1.25 2005/04/08 10:43:23 wkpark Exp $

function processor_gcode($formatter,$value,$options) {
  global $DBInfo;
  static $jsloaded=0;
  $cache_dir=$DBInfo->upload_dir."/gcodeProcessor";

  $syntax=array("php","c","python","jsp","sh","cpp",
          "java","ruby","forth","fortran","perl",
          "haskell","lisp","st","objc","tcl","lua",
          "asm","masm","tasm","make",
          "awk","docbk","diff","html","tex","vim",
          "xml","dtd","sql","conf","config","nosyntax","apache");

  $alias['Cpp'] = array("c","cpp","c++");
  $alias['CSharp'] = array("c#","csharp");
  $alias['Css'] = array("css","style");
  $alias['Delphi'] = array("delphi");
  $alias['Java'] = array("java");
  $alias['JScript'] = array("jscript","javascript");
  $alias['Php'] = array("php");
  $alias['Python'] = array("phthon");
  $alias['Ruby'] = array("ruby");
  $alias['Sql'] = array("sql");
  $alias['Vb'] = array("vb","visualbasic");
  $alias['Xml'] = array("xml","html");

  #$opts=array("number");

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);
  # get parameters
  if ($line)
    list($tag,$type,$extra)=preg_split('/\s+/',$line,3);
  $src=$value;

  $type = strtolower($type);
  $format = "";
  foreach($alias as $key => $ar_values){
	if(in_array($type, $ar_values)){
		$format = $key;
		break;
	}
  }
  if(!$format){
	  $format = "Cpp"; //default setting
  }

  $script = "
<script type='text/javascript' src='".$DBInfo->url_prefix."/gcode/js/shCore.js'></script>
<script type='text/javascript' src='".$DBInfo->url_prefix."/gcode/js/shBrush$format.js'></script>
<script type='text/javascript' src='".$DBInfo->url_prefix."/gcode/js/shBrushXml.js'></script>";
 
 $uniq=md5($option.$src);
 $content = "<textarea name='$uniq' class='$format' cols='60' rows='10'>$value</textarea>";

  $script .="<script type='text/javascript'>
dp.SyntaxHighlighter.ClipboardSwf = '".$DBInfo->url_prefix."/gcode/flash/clipboard.swf';
dp.SyntaxHighlighter.HighlightAll('$uniq');
</script>";

  return '<div>'.$content.$script.'</div>';


}

// vim:et:sts=2:
?>
