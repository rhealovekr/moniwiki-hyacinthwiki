<?php
// This is a irccolor plugin for MoniWiki
//
// Author: hyacinth
// Date: 2015-02-26
// Name: irccolor processor
// Description: convert to irc color processor for the MoniWiki
// URL: hyacinth.byus.net/moniwiki/wiki.php
// Version: $Revision: 0.1 $
// License: GPL
//
// Usage: {{{#!irccolor
// 18<hyacinth18> chat
// 18<hyacinth18> chat
// ...
// }}}
//
// $Id: irccolor.php,v 1.7 2015/02/26 17:51:22 hyacinth Exp $

function processor_irccolor($formatter,$value="",$options=array()) {
    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    $lines=explode("\n",$value);
    foreach ($lines as $line) {
      $line=str_replace("0?","",$line);
      $line=preg_replace("/(\[\d\d:\d\d\]) (\*.*)/","$1 <font color='#009400'>$2</font>",$line);
      // HexChat
      $line=preg_replace("/20<(.*)>30/","<<font color='red'>$1</font>>",$line); // Me
      $line=preg_replace("/18<(.*)18>/","<<font color='#000080'>$1</font>>",$line); // Oper
      $line=preg_replace("/23(.*)23(.*)/","<font color='#CE5C00'>$1$2</font>",$line); // Joined
      $line=preg_replace("/24(.*)/","<font color='#C4A000'>$1$2 </font>",$line); // Quit
      $line=preg_replace("/22(.[^ ]*)? 26(.[^ ]*)? (.*)18(.*)?/","$1 <font color='#11A879'>$2</font> $3<font color='#000080'>$4</font> ",$line); // Mode Changed
      $line=preg_replace("/(\d)/","$1",$line);
      $line=preg_replace("/18(.[^]*)/","<font color='#000080'>$1</font>",$line);
      $line=preg_replace("/19(.[^]*)/","<font color='#11A879'>$1</font>",$line);
      $line=preg_replace("/20(.[^]*)/","<font color='#11A879'>$1</font>",$line);
      $line=preg_replace("/22(.[^]*)/","<font color='#5C3566'>$1</font>",$line);
      $line=preg_replace("/26(.[^]*)/","<font color='#11A879'>$1</font>",$line);
      
      // mIRC
      $line=preg_replace("/<(@.[^>]*)>/","<<font color='#000080'>$1</font>>",$line);
      $line=preg_replace("/<(\+.[^>]*)>/","<<font color='#800000'>$1</font>>",$line);
      $line=preg_replace("/02(.[^]*)/","<font color='#000080'>$1</font>",$line);
      $line=preg_replace("/05(.[^]*)/","<font color='#800000'>$1</font>",$line);
      $line=preg_replace("/04(.[^]*)/","<font color='red'>$1</font>",$line);
      $line=preg_replace("/(.[^]*)/","<b>$1</b>",$line);
      $line=preg_replace("/(.[^]*)/","$1",$line);
      $line=preg_replace("/10(.*)/","<font color='#009490'>$1 </font>",$line);
      $line=preg_replace("/11(.*)/","<font color='#00FFFF'>$1 </font>",$line);
      $line=preg_replace("/12(.*)/","<font color='blue'>$1 </font>",$line);
      $line=preg_replace("/13(.*)/","<font color='#FF00FF'>$1 </font>",$line);
      $line=preg_replace("/14(.*)/","<font color='#808080'>$1 </font>",$line);
      $line=preg_replace("/15(.*)/","<font color='#D0D4D0'>$1 </font>",$line);
      $line=preg_replace("/0?1([^\d].*)/","<font color='black'>$1 </font>",$line);
      $line=preg_replace("/0?2(.*)/","<font color='#000080'>$1 </font>",$line);
      $line=preg_replace("/0?3(.*)/","<font color='#009400'>$1 </font>",$line);
      $line=preg_replace("/0?4(.*)/","<font color='red'>$1 </font>",$line);
      $line=preg_replace("/0?5(.*)/","<font color='#800000'>$1 </font>",$line);
      $line=preg_replace("/0?6(.*)/","<font color='#A000A0'>$1 </font>",$line);
      $line=preg_replace("/0?7(.*)/","<font color='#FF8000'>$1 </font>",$line);
      $line=preg_replace("/0?8(.*)/","<font color='yellow'>$1 </font>",$line);
      $line=preg_replace("/0?9(.*)/","<font color='#00FC00'>$1 </font>",$line);
      $line=preg_replace("/\d?\d/","",$line);
      $line=preg_replace("/||/","",$line);

      // href
      //$line=preg_replace("/(https?:\/\/([\da-z\.-]+\.[a-z\.]{2,6})([\/\w_\.-]*)*\/?)/","<a href=\"$1\">$1</a>",$line);

      $out.="$line<br />";
    }

    return $out;
}

// vim:et:sts=4:sw=4:
?>
