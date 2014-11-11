<?php
// All rights reserved. Distributable under GPL see COPYING
// a atom macro plugin for the MoniWiki
//
// Date: 2014-11-04
// Name: CodeMacro
// Description: Code tag Plugin
// URL: CodeMacro
// Version: $Revision: 1.4$
// License: GPLv2
//
// Usage: [[Code(variable)]]
//
// $Id: Code.php,v 0.1 2014/11/04 13:36:01 hyacinth Exp $
// vim:et:ts=2:

function macro_Code($formatter,$value) {
  if (!empty($_SERVER[$value])) return $_SERVER[$value];
  if (!empty($_ENV[$value])) return $_ENV[$value];

  if (preg_match("/,#class.([^#]*)#$/",$value,$matches)) {
    $class = $matches[1];
    $value = preg_replace("/,#class.([^#]*)#$/","",$value);
  }
  if (preg_match("/,#color.([^#]*)#$/",$value,$matches)) {
    $color = $matches[1];
    $value = preg_replace("/,#color.([^#]*)#$/","",$value);
  }

  if (!empty($class))
    return "<code class=\"$class\">".$value."</code>";
  else if (!empty($color))
    return "<code class=\"fixed\" style=\"color:$color\">".$value."</code>";
  else
    return "<code class=\"fixed\">".$value."</code>";
}

?>
