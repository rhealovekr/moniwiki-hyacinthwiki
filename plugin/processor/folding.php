<?php
// Copyright 2003-2005 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// sample plugin for the MoniWiki
//
// Author: Your name <foobar@foo.bar>
// Date: 2006-01-01
// Name: Hello world
// Description: Hello world Processor
// URL: to_plugin url/interwiki name etc.
// Version: $Revision: 1.1 $
// License: GPL
//
// Usage: {{{#!folding [[+|-] Name
// Hello World
// }}}
// $Id: folding.php,v 1.1 2007/05/15 11:18:40 iolo Exp $

function processor_folding($formatter,$value="",$options=array()) {
    global $DBInfo;
    $imgdir=$DBInfo->imgs_dir;

    if ($value[0]=='#' and $value[1]=='!')
        list($line,$value)=explode("\n",$value,2);

    if ($line)
        list($tag,$args)=explode(' ',$line,2);

    // unique id for folding area(dd tag)
    $id = md5($args);

    // allow wiki syntax in folding content
    ob_start();
    $formatter->send_page($value);
    $value = ob_get_contents();
    ob_end_clean();

    $init_state_block = 'none';
    $init_state_img = 'f_up';

    if ($args[0] == '+' &&
        $args[1] == ' ') {
      $args = str_replace('+ ', '', $args);
      $init_state_block = 'block';
      $init_state_img = 'f_down';
    }
    else if ($args[0] == '-' &&
      $args[1] == ' ') {
      $args = str_replace('- ', '', $args);
    }

    return <<<HERE
<dl class="folding">
<dt onclick="document.getElementById('folding_$id').style.display=(document.getElementById('folding_$id').style.display == 'block') ? 'none' : 'block';document.getElementById('folding_stat_$id').src=(document.getElementById('folding_stat_$id').src.indexOf('f_down.png') > 0) ? '$imgdir/plugin/Folding/f_up.png' : '$imgdir/plugin/Folding/f_down.png';"><img id="folding_stat_$id" src="$imgdir/plugin/Folding/$init_state_img.png" style='border-width:10px;vertical-align:middle;' /> $args</dt>
<dd id="folding_$id" style="display:$init_state_block;">$value</dd>
</dl>
HERE;
}

// vim:et:sts=4:
?>
