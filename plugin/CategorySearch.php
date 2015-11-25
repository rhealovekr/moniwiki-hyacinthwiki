<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a FullSearch plugin for the MoniWiki
//
// $Id: FullSearch.php,v 1.39 2010/09/07 12:11:49 wkpark Exp $

function macro_CategorySearch($formatter,$value="", &$opts) {
  global $DBInfo;
  $needle=$value;
  if ($value === true) {
    $needle = $value = $formatter->page->name;
    $options['noexpr']=1;
  } else {
    # for MoinMoin compatibility with [[FullSearch("blah blah")]]
    #$needle = preg_replace("/^('|\")([^\\1]*)\\1/","\\2",$value);
    $needle = $value;
  }

  // for pagination
  $offset = '';
  if (!empty($opts['offset']) and is_numeric($opts['offset'])) {
    if ($opts['offset'] > 0) $offset = $opts['offset'];
  }

  $url=$formatter->link_url($formatter->page->urlname);
  $fneedle = _html_escape($needle);
  $tooshort=!empty($DBInfo->fullsearch_tooshort) ? $DBInfo->fullsearch_tooshort:2;

  $m1=_("Display context of search results");
  $m2=_("Search BackLinks only");
  $m3=_("Case-sensitive searching");
  $msg=_("Go");
  $bchecked = !empty($DBInfo->use_backlinks) ? 'checked="checked"' : '';
  $form= <<<EOF
<form method='get' action='$url'>
   <input type='hidden' name='action' value='fullsearch' />
   <input name='value' size='30' value="$fneedle" />
   <span class='button'><input type='submit' class='button' value='$msg' /></span><br />
   <input type='checkbox' name='backlinks' value='1' $bchecked />$m2<br />
   <input type='checkbox' name='context' value='20' />$m1<br />
   <input type='checkbox' name='case' value='1' />$m3<br />
   </form>
EOF;

  if (!isset($needle[0]) or !empty($opts['form'])) { # or blah blah
     $opts['msg'] = _("No search text");
     return $form;
  }
  $opts['form'] = $form;
  # XXX
  $excl = array();
  $incl = array();

  if (!empty($opts['noexpr'])) {
    $tmp=preg_split("/\s+/",$needle);
    $needle=$value=join('|',$tmp);
    $raw_needle=implode(' ',$tmp);
    $needle = preg_quote($needle);
  } else if (empty($opts['backlinks'])) {
    $terms = preg_split('/((?<!\S)[-+]?"[^"]+?"(?!\S)|\S+)/s',$needle,-1,
      PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

    $common_words=array('the','that','where','what','who','how','too','are');
    $common = array();
    foreach($terms as $term) {
      if (trim($term)=='') continue;
      if (preg_match('/^([-+]?)("?)([^\\2]+?)\\2$/',$term,$match)) {
        $word=str_replace(array('\\','.','*'),'',$match[3]);
        $len=strlen($word);

        if (!$match[1] and $match[2] != '"') {
          if ($len < $tooshort or in_array($word,$common_words)) {
            $common[]=$word;
            continue;
          }
        }

        if ($match[1]=='-') $excl[] = $word;
        else $incl[] = $word;
      }
    }
    $needle=implode('|',$incl);
    $needle=_preg_search_escape($needle);

    $raw_needle=implode(' ',$incl);
    $test = validate_needle($needle);
    if ($test === false) {
      // invalid regex
      $tmp = array_map('preg_quote', $incl);
      $needle = implode('|', $tmp);
    }

    $excl_needle=implode('|',$excl);

    $test = validate_needle($excl_needle);
    if ($test2 === false) {
      // invalid regex
      $tmp = array_map('preg_quote', $excl);
      $excl_needle = implode('|', $tmp);
    }
  } else {
    $cneedle = _preg_search_escape($needle);
    $test = validate_needle($cneedle);
    if ($test === false) {
      $needle = preg_quote($needle);
    } else {
      $needle = $cneedle;
    }
  }

  $test3 = trim($needle);
  if (!isset($test3[0])) {
     $opts['msg'] = _("Empty expression");
     return $form;
  }

  # set arena and sid
  if (!empty($opts['backlinks'])) $arena='backlinks';
  else if (!empty($opts['keywords'])) $arena='keywords';
  else $arena='categorysearch';

  if ($arena == 'categorysearch') $sid = md5($value.'v'.$offset);
  else $sid=$value;

  $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

  # retrieve cache
  $fc=new Cache_text($arena);
  if (!$formatter->refresh and $fc->exists($sid)) {
    $data=$fc->fetch($sid);
    if (!empty($opts['backlinks'])) {
      // backlinks are not needed to check it.
      $hits = $data;

      // also fetch redirects
      $r = new Cache_Text('redirects');
      $redirects = $r->fetch($sid);
    } else if (is_array($data)) {
      # check cache mtime
      $cmt=$fc->mtime($sid);

      # check update or not
      $dmt= $DBInfo->mtime();
      if ($dmt > $cmt + $delay) { # XXX crude method
        $data=array();
      } else { # XXX smart but incomplete method
        if (isset($data['hits'])) $hits = &$data['hits'];
        else $hits = &$data;

        foreach ($hits as $p=>$c) {
          $mp=$DBInfo->getPage($p);
          $mt=$mp->mtime();
          if ($mt > $cmt + $delay) {
            $data=array();
            break;
          }
        }
      }
      if (isset($data['searched'])) extract($data);
      else if (!empty($data)) $hits = $data;
    }
  }

  $pattern = '/'.$needle.'/';
  if (!empty($excl_needle))
    $excl_pattern = '/'.$excl_needle.'/';
  if (!empty($opts['case'])) {
    $pattern.="i";
    $excl_pattern.="i";
  }

  if (isset($hits)) {
    if (in_array($arena, array('backlinks', 'keywords'))) {
      $test = key($hits);
      if (is_int($test) and $hits[$test] != -1) {
        // fix compatible issue for keywords, backlinks
        $hits = array_flip($hits);
        foreach ($hits as $k=>$v) $hits[$k] = -1;
        reset($hits);
      }

      // check invert redirect index
      if (!empty($redirects)) {
        $redirects = array_flip($redirects);
        ksort($redirects);
        foreach ($redirects as $k=>$v) $hits[$k] = -2;
        reset($hits);
      }
    }
    //continue;
  } else {
    $hits = array();

    set_time_limit(0);
    if (!empty($opts['backlinks']) and empty($DBInfo->use_backlink_search)) {
      $hits = array();
    } else if (!empty($opts['keywords']) and empty($DBInfo->use_keyword_search)) {
      $hits = array();
    } else if (!empty($opts['backlinks'])) {
      $pages = $DBInfo->getPageLists();
      #$opts['context']=-1; # turn off context-matching
      $cache=new Cache_text("pagelinks");
      foreach ($pages as $page_name) {
        $links = $cache->fetch($page_name);
        if (is_array($links)) {
          if (in_array($value,$links))
            $hits[$page_name] = -1;
            // ignore count if < 0
        }
      }
    } else if (!empty($opts['keywords'])) {
      $pages = $DBInfo->getPageLists();
      $opts['context']=-1; # turn off context-matching
      $cache=new Cache_text("keyword");
      foreach ($pages as $page_name) {
        $links=$cache->fetch($page_name);
        // XXX
        if (is_array($links)) {
          if (stristr(implode(' ',$links),$needle))
            $hits[$page_name] = -1;
            // ignore count if < 0
          }
        }
    } else {
      $params = array();
      $ret = array();
      $params['ret'] = &$ret;
      $params['offset'] = $offset;

      $params['search'] = 1;
      $params['incl'] = $incl;
      $params['excl'] = $excl;
      $pages = $DBInfo->getPageLists($params);

      // set time_limit
      $mt = explode(' ', microtime());
      $timestamp = $mt[0] + $mt[1];
      $j = 0;

      $time_limit = isset($DBInfo->process_time_limit) ?
          $DBInfo->process_time_limit : 3; // default 3-seconds

      $j = 0;
      while (list($_, $page_name) = each($pages)) {
        // 탐색 안 할 페이지 -- yhyacinth
        if (preg_match('/^FrontPage/', $page_name)) continue;
        if (preg_match('/^Blog/', $page_name)) continue;
        
        // check time_limit
        if ($time_limit and $j % 30 == 0) {
          $mt = explode(' ', microtime());
          $now = $mt[0] + $mt[1];
          if ($now - $timestamp > $time_limit) break;
        }
        $j++;

        $p = new WikiPage($page_name);
        if (!$p->exists()) continue;
        $body= $p->_get_raw_body();
        #$count = count(preg_split($pattern, $body))-1;
        $count = preg_match_all($pattern, $body,$matches);

        if ($count) {
          foreach($excl as $ex) if (stristr($body,$ex)) continue;
          foreach($incl as $in) if (!stristr($body,$in)) continue;
          $hits[$page_name] = $count;
        }
      }
      $searched = $j > 0 ? $j : 0;
      $offset = !empty($offset) ? $offset + $j: $j;
    }
    #krsort($hits);
    #ksort($hits);
    $name = array_keys($hits);
    array_multisort($hits, SORT_DESC, $name, SORT_ASC);

    if (in_array($arena, array('backlinks', 'keywords'))) {
      $fc->update($sid, $name);
    } else {
      $fc->update($sid, array('hits'=>$hits, 'offset'=>$offset, 'searched'=>$searched));
    }
  }

  $opts['hits']= $hits;
  $opts['hit']= count($hits);
  $opts['all']= $DBInfo->getCounter();
  if ($opts['all'] > $searched) {
    $opts['next'] = $offset;
    $opts['searched'] = $searched;
  }

  if (!empty($opts['call'])) return $hits;

  $out= "<!-- RESULT LIST START -->"; // for search plugin
  $out.= "<ul class='fullsearchResult'>";

  $idx=1;
  $checkbox = '';
  while (list($page_name, $count) = each($hits)) {
    if (preg_match('/^Category.*/', $page_name)) continue;
    
    $pgname = _html_escape($page_name);
    if (!empty($opts['checkbox'])) $checkbox="<input type='checkbox' name='pagenames[]' value=\"$pgname\" />";
    $out.= '<!-- RESULT ITEM START -->'; // for search plugin
    
    // 하이라이트 사용 안 함 -- yhyacinth
    $out.= '<li>'.$checkbox.$formatter->link_tag(_rawurlencode($page_name),
          '',
          $pgname,'tabindex="'.$idx.'"');
    // N 회 일치 표시 안 함 -- yhyacinth
    if ($count > 0)
      ; // $out.= ' . . . . ' . sprintf((($count == 1) ? _("%d match") : _("%d matches")), $count );
    else if ($count == -2)
      $out.= " <span class='redirectIcon'><span>"._("Redirect page")."</span></span>\n";
    if (!empty($opts['context']) and $opts['context']>0) {
      # search matching contexts
      $p = new WikiPage($page_name);
      if ($p->exists()) {
        $body= $p->_get_raw_body();
        $out.= find_needle($body,$needle,$excl_needle,$opts['context']);
      }
    }
    $out.= "</li>\n";
    $out.= '<!-- RESULT ITEM END -->'; // for search plugin
    $idx++;
    #if ($idx > 50) break;
  }
  $out.= "</ul>\n";
  $out.= "<!-- RESULT LIST END -->"; // for search plugin

  return $out;
}

// vim:et:sts=2:
?>