<?php
// Copyright 2003-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// Blog plugin for the MoniWiki
//
// Usage: {{{#!blog ID @date@ title
// Hello World
// }}}
// this processor is used internally by the Blog action
// $Id: blog.php,v 1.29 2010/08/23 09:20:34 wkpark Exp $

include_once("plugin/BlogChanges.php");

function processor_blog($formatter,$value="",$options) {
  static $date_anchor='';
  global $DBInfo;
  #static $tackback_list=array();

  if ($value[0]=='#' and $value[1]=='!')
    list($line,$value)=explode("\n",$value,2);

  $datetag = '';
  if ($date_anchor=='' and $DBInfo->use_trackback) {
    #read trackbacks and set entry counter
    $cache= new Cache_text('trackback');
    if ($cache->exists($formatter->page->name)) {
      $trackback_raw=$cache->fetch($formatter->page->name);

      $trackbacks=explode("\n",$trackback_raw);
      foreach ($trackbacks as $trackback) {
        list($dummy,$entry,$extra)=explode("\t",$trackback);
        if ($entry) {
          if($formatter->trackback_list[$entry]) $formatter->trackback_list[$entry]++;
          else $formatter->trackback_list[$entry]=1;
        }
      }
    }
  }
  #print($date_anchor);print_r($trackback_list);
  if ($line) {
    # get parameters
    list($tag, $user, $date, $title)=explode(" ",$line, 4);

    if (preg_match('/^[\d\.]+$/',$user)) {
      if (!$DBInfo->mask_hostname and $DBInfo->interwiki['Whois'])
        #$user=_("Anonymous")."[<a href='".$DBInfo->interwiki['Whois']."$user'>$user</a>]";
        $user="<a href='".$DBInfo->interwiki['Whois']."$user'><span> >"._("Anonymous")."</a>";
      else
        $user=_("Anonymous");
    } else if ($DBInfo->hasPage($user)) {
      $user_id=$user;
      $user=$formatter->link_tag($user);
      ### rel="author" 추가 2013/11/26 rhealove
      $append_author='" title="Submitted by '.$user_id.'"><span class="vcard author"><span class="fn" style="display:none;">Daehyeon Kim</span><span class="nickname">';
      $user=str_replace('" ><span>',$append_author,$user);
      $user=str_replace('th</span>','th</span></span>',$user);
    }

    ### 날짜 표기 방법 변경 -- 2012/12/13 rhealove
    if ($date && $date[10] == 'T') {
      $date[10]=' ';
      $time=strtotime($date." GMT");
# 1
#      $date= gmdate("m-d [h:i a]",$time+$formatter->tz_offset);
#      $date= gmdate("F jS, Y",$time+$formatter->tz_offset);
# 2
#      $date= gmdate("D, F jS Y",$time+$formatter->tz_offset);
#      $date_content= gmdate("Y-m-d",$time+$formatter->tz_offset);
      $date= gmdate("F jS, Y",$time+$formatter->tz_offset);
      $date_content= gmdate("Y-m-d",$time+$formatter->tz_offset);

      $pagename=$formatter->page->name;
      $p=strrpos($pagename,'/');
      if ($p and preg_match('/(\d{4})(-\d{1,2})?(-\d{1,2})?/',substr($pagename,$p),$match)) {
        if ($match[3]) $anchor='';
        else if ($match[2]) $anchor= gmdate("d",$time);
        else if ($match[1]) $anchor= gmdate("md",$time);
      } else
        $anchor= gmdate("Ymd",$time);
      if ($date_anchor != $anchor) {
        $anchor_date_fmt=$DBInfo->date_fmt_blog;
        #$anchor_date_fmt = 'M d, Y';
        #$datetag= "<div class='blog-date'>".date($anchor_date_fmt,$time)." <a name='$anchor'></a><a class='perma' href='#$anchor'>$formatter->perma_icon</a></div>";
        $date_anchor= $anchor;
      }
    }
    ### 구조화된 데이터 마크업 2013/11/13 rhealove
    $date = '</div><span class="meta date updated"><time datetime="'.$date_content.'">'.$date.'</time></span>';
    $md5sum=md5(substr($line,7));
  }

  $src= rtrim($value);

  if (!empty($src)) {
    $options['nosisters']=1;
    $options['nojavascript']=1;
    $tmp = explode("----\n",$src,2);
    $src = $tmp[0];
    if (!empty($tmp[1])) $comments = $tmp[1];

    $add_button= _("Add comment");
    if (!empty($comments)) {
      $count=sizeof(explode("----\n",$comments));

      if (!empty($options['noaction']) or !empty($DBInfo->blog_comments)) {
        $comments=preg_replace("/----\n/","[[HTML(</div></div><div class='separator'><hr /></div><div class='blog-comment'><div>)]]",$comments);
      } else {
        $comments='';
        $add_button=($count == 1) ? _("%s comment"):_("%s comments");
        $count_tag = '<span class="count">'.$count.'</span>';
        $add_button=sprintf($add_button,$count_tag);
      }
    }

    if (!empty($formatter->trackback_list[$md5sum])) $counter=' ('.$formatter->trackback_list[$md5sum].')';
    else $counter='';

#    if (empty($options['noaction']) and $md5sum) {
    if ($md5sum) {
### disqus plugin added -- rhealove 2012/12/03
      $title_trim = str_replace('(', '\\\\(', $title);
      $title_trim = str_replace(')', '\\\\)', $title_trim);
	  $current_url = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	  $current_url_spe = htmlspecialchars($current_url);	  
      $year = date('Y',$time).'12';
      $disqus = <<<DISQUS
<script type="text/javascript">
            (function() {
                var blog_list_url = '<a href="http://hyacinth.byus.net/moniwiki/wiki.php/Blog?category=Blog/.*&amp;date=$year&amp;action=highlight&amp;value='+encodeURI('$title_trim')+'" rel="nofollow">';

                document.write(blog_list_url + decodeURI('%EB%B8%94%EB%A1%9C%EA%B7%B8%20%EB%AA%A9%EB%A1%9D%20%EB%B3%B4%EA%B8%B0') + '</a>');
//                document.write(blog_list_url + decodeURI('&laquo; Go Back') + '</a></div>');
            })();
        </script>
<div class='blog-disqus'>
<div id="disqus_thread"></div>
        <script type="text/javascript">
            var disqus_developer = 1;
            var disqus_url = '$current_url';
            var disqus_identifier = '$md5sum $current_url';
            var disqus_container_id = 'disqus_thread';
            var disqus_domain = 'disqus.com';
            var disqus_shortname = 'yhyacinth';
            var disqus_title = 'hrp: $title';
            
        </script>

        <script type="text/javascript">
            (function() {
                if (navigator.appVersion.indexOf("MSIE 6") != -1 ||
                    navigator.appVersion.indexOf("MSIE 7") != -1 ||
                    navigator.appVersion.indexOf("MSIE 8") != -1) {
                    document.write('Mozilla Firefox/Chrome/Safari/Opera/IE 9.0 ' + decodeURI('%EB%B2%84%EC%A0%84%20%EC%9D%B4%EC%83%81%20%EB%B8%8C%EB%9D%BC%EC%9A%B0%EC%A0%80%EC%97%90%EC%84%9C%EB%A7%8C%20%EB%8C%93%EA%B8%80%EC%9D%84%20%EC%82%AC%EC%9A%A9%ED%95%A0%20%EC%88%98%20%EC%9E%88%EC%8A%B5%EB%8B%88%EB%8B%A4.') + '<br />');
                    return;
                }

                var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
                dsq.src = 'http://' + disqus_shortname + '.disqus.com/embed.js';
                (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);

            })();
        </script>
        <noscript>Please enable JavaScript to view the <a href="http://disqus.com/?ref_noscript">comments powered by Disqus.</a></noscript>
        <a href="http://disqus.com" class="dsq-brlink">comments powered by <span class="logo-disqus">Disqus</span></a><br /><br />
</div></div>
DISQUS;
		$nav_post = "<div class='blog-nav-previous'>".
			macro_BlogChanges($formatter,"'Blog/.*',simple,titleonly,prev")."</div><div class='blog-nav-next'>".
			macro_BlogChanges($formatter,"'Blog/.*',simple,titleonly,next")."</div>";

    if (!empty($options['noaction'])) {
      $current_url = "http://" . $_SERVER["HTTP_HOST"] . "/moniwiki/wiki.php" . $_SERVER["PATH_INFO"] . "?action=blog&amp;value=" . $md5sum . "#disqus_thread"; 
      $disqus_count = <<<DISQUS_COUNT
            <script type="text/javascript">
        /* * * CONFIGURATION VARIABLES: EDIT BEFORE PASTING INTO YOUR WEBPAGE * * */
        var disqus_shortname = 'yhyacinth'; // required: replace example with your forum shortname

        /* * * DON'T EDIT BELOW THIS LINE * * */
        (function () {
            var s = document.createElement('script'); s.async = true;
            s.type = 'text/javascript';
            s.src = 'http://' + disqus_shortname + '.disqus.com/count.js';
            (document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);

        }());
        </script>
        <span class='bullet'>&raquo;</span> <a href="$current_url">Link</a>.
    
DISQUS_COUNT;
    }
      $comment_tag= $formatter->link_tag($formatter->page->urlname,"?action=blog&amp;value=$md5sum",$add_button);
      # 코멘트 제거 2014/04/18
      #$action= $formatter->link_tag($formatter->page->urlname,"?action=trackback&amp;value=$md5sum",_("track back").$counter).' | '.$comment_tag.'<br />';
      $action= $formatter->link_tag($formatter->page->urlname,"?action=trackback&amp;value=$md5sum",_("track back").$counter).'<br />';
      if (getPlugin('SendPing')) {
            if (!empty($options['noaction'])) {
                #$action.= 'Trackback: http://hyacinth.byus.net/moniwiki/wiki.php/Blog/2013-02/e5df5ecfb182e600d44565dd89442d73?action=trackback<br />';
                $action.= $nav_post;
                $action.= '<br /><hr />';
                $action.= $disqus;
            }
            else {
                $action.= $disqus_count;
            }
      }
      if (!empty($DBInfo->use_rawblog))
        $action.= ' | '.$formatter->link_tag($formatter->page->urlname,"?action=rawblog&amp;value=$md5sum",_("raw"));
    }

    if (!empty($action))
      $action="<div class='blog-action'><span class='bullet'>&raquo;</span> ".$action."</div>\n";
    else
      $action='';

    $save=!empty($formatter->preview) ? $formatter->preview : '';
    $formatter->preview=1;
    ob_start();
    $formatter->send_page($src,$options);
    $msg= ob_get_contents();
    ob_end_clean();
    if (!empty($comments)) {
      ob_start();
      $formatter->send_page($comments,$options);
      $comments= "<br /><div align='right' style='padding: 0px; '><div class='blog-action'><span class='bullet'>&raquo;</span> <font color='#406EA7'>Comments</font></div><div class='blog-comments'><div class='blog-comment'>".ob_get_contents()."</div></div></div>";
      ob_end_clean();
    } else
      $comments="";
    !empty($save) ? $formatter->preview=$save : null;
  }

  $out="$datetag<div class='blog'>";
  if (!empty($title)) {
    #$tag=normalize($title);
    $tag=$md5sum;
    if ($tag[0]=='%') $tag="n".$tag;
    $perma="<a class='perma' href='#$tag'>$formatter->perma_icon</a>";
    $title=preg_replace("/(".$formatter->wordrule.")/e",
                        "\$formatter->link_repl('\\1')",$title);
    ### 일자 id rhealove 2013/04/04
    #$timepost=strtotime($date." GMT");
    #$day= gmdate("d",$timepost+$formatter->tz_offset);
    #$out.="<div class='blog-title'><a id=\"$day\" name='$tag'></a>$title $perma</div>\n";
    $out.="<div class='blog-title'><a href='/moniwiki/wiki.php".$_SERVER["PATH_INFO"]."?action=blog&amp;value=$tag' name=$tag style='color:black; text-decoration:none; font-size:16px;'>$title</a></div>\n";
  }
  $info = sprintf(_("Submitted by %s @ %s"), $user, $date);
  if (!empty($options['noaction'])) {
    global $_title;
    $cur_page = rawurlencode("http://hyacinth.byus.net".$_SERVER["REQUEST_URI"]);
    $cur_page_raw = "http://hyacinth.byus.net".$_SERVER["REQUEST_URI"];

    $gplus = '<div id="blog-gplus" style="text-align:right"><div class="g-plusone" data-size="medium" data-align="right"></div></div>';
#    $gplus = '<div class="sharedaddy sd-sharing-enabled"><div class="robots-nocontent sd-block sd-social sd-social-icon-text sd-sharing"><div class="sd-content"><ul><li class="share-twitter"><a rel="nofollow" class="share-twitter sd-button share-icon" href="https://twitter.com/intent/tweet?text='.$_title.'&url='.$cur_page.'" title="Click to share on Twitter" id="sharing-twitter"><span>Twitter</span></a></li><li class="share-facebook"><a rel="nofollow" class="share-facebook sd-button share-icon" href="https://www.facebook.com/sharer/sharer.php?u='.$cur_page.'&t=" title="Share on Facebook" id="sharing-facebook"><span>Facebook</span></a></li><li class="share-google-plus-1"><div style="margin-top:1px"><div class="g-plusone" data-width="200" data-size="medium"></div></div></li><li class="share-end"></li></ul></div></div>';
#    $extra = '<script type="text/javascript">WPCOM_sharing_counts = {"'.$cur_page_raw.'":0}</script>';
  }
  $out.="<div class='blog-user'><div style='display:none'>$info</div>\n".
    "<div class='blog-content entry-content' style='margin:0'>$msg</div><br />$gplus$extra$comments$action\n".
    "</div>\n";
  return $out;
}

// vim:et:sts=2:
?>
