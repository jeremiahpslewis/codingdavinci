<?php

namespace WebApplication\Controller;

use Silex\Application as BaseApplication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticController
{

    public function homeAction(Request $request, BaseApplication $app)
    {
        // display the static content
        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => '<div style="position: absolute; bottom: 2px; right: 15px; font-size: smaller; color: gray;"> Bildquelle: <a style="color: #ddd" href="http://commons.wikimedia.org/wiki/File:Berlin_DenkmalBuecherverbrennung_BookBurningMemorial_Bebelplatz.jpg" target="_blank">Wikimedia Commons, Daniel Neugebauer</a>, CC-BY-SA-2.5</div>',
                                          )
                                    );
    }

    public function geschichtenAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<!-- Beginn der storymap -->
    <h2 id="kolb" class="anchor-offset">Annette Kolb</h2>
    <iframe src='http://cdn.knightlab.com/libs/storymapjs/latest/embed/?url=https://www.googledrive.com/host/0B98wXqM9dji8UzQ1cjlZcFI4bUE/published.json&lang=de&height=600' width='100%' height='600' frameborder='0' style="margin-bottom: 100px"></iframe>

<!-- Ende der Storymap -->

<!-- Beginn der Timeline: Erich Kästner -->
    <h2 id="kaestner" class="anchor-offset">Erich Kästner</h2>
    <iframe src='http://cdn.knightlab.com/libs/timeline/latest/embed/index.html?source=0AiSeZXd_qNJ4dHUxRFFsZy1lemFYelpFLTBQQWFUNnc&font=PT&maptype=toner&lang=de&height=650' width='100%' height='650' frameborder='0'></iframe>


<!-- Ende der Timeline: Erich Kästner -->
EOT;
        // display the static content
        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

    public function tableauPublikationsOrteAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<script type='text/javascript' src='https://public.tableausoftware.com/javascripts/api/viz_v1.js'></script><div class='tableauPlaceholder' style='width: 804px; height: 656px;'><noscript><a href='#'><img alt='Dashboard Map ' src='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardMap&#47;1_rss.png' style='border: none' /></a></noscript><object class='tableauViz' width='804' height='656' style='display:none;'><param name='host_url' value='https%3A%2F%2Fpublic.tableausoftware.com%2F' /> <param name='site_root' value='' /><param name='name' value='Book2banned-books-final&#47;DashboardMap' /><param name='tabs' value='no' /><param name='toolbar' value='yes' /><param name='static_image' value='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardMap&#47;1.png' /> <param name='animate_transition' value='yes' /><param name='display_static_image' value='yes' /><param name='display_spinner' value='yes' /><param name='display_overlay' value='yes' /><param name='display_count' value='yes' /></object></div><div style='width:804px;height:22px;padding:0px 10px 0px 0px;color:black;font:normal 8pt verdana,helvetica,arial,sans-serif;'><div style='float:right; padding-right:8px;'><a href='http://www.tableausoftware.com/public/about-tableau-products?ref=https://public.tableausoftware.com/views/Book2banned-books-final/DashboardMap' target='_blank'>Learn About Tableau</a></div></div>
EOT;
        // display the static content
        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

    public function tableauDotAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<script type='text/javascript' src='https://public.tableausoftware.com/javascripts/api/viz_v1.js'></script><div class='tableauPlaceholder' style='width: 804px; height: 656px;'><noscript><a href='#'><img alt='Dashboard Points ' src='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardPoints&#47;1_rss.png' style='border: none' /></a></noscript><object class='tableauViz' width='804' height='656' style='display:none;'><param name='host_url' value='https%3A%2F%2Fpublic.tableausoftware.com%2F' /> <param name='site_root' value='' /><param name='name' value='Book2banned-books-final&#47;DashboardPoints' /><param name='tabs' value='no' /><param name='toolbar' value='yes' /><param name='static_image' value='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardPoints&#47;1.png' /> <param name='animate_transition' value='yes' /><param name='display_static_image' value='yes' /><param name='display_spinner' value='yes' /><param name='display_overlay' value='yes' /><param name='display_count' value='yes' /></object></div><div style='width:804px;height:22px;padding:0px 10px 0px 0px;color:black;font:normal 8pt verdana,helvetica,arial,sans-serif;'><div style='float:right; padding-right:8px;'><a href='http://www.tableausoftware.com/public/about-tableau-products?ref=https://public.tableausoftware.com/views/Book2banned-books-final/DashboardPoints' target='_blank'>Learn About Tableau</a></div></div>
EOT;
        // display the static content
        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

    public function tableauBubbleAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<script type='text/javascript' src='https://public.tableausoftware.com/javascripts/api/viz_v1.js'></script><div class='tableauPlaceholder' style='width: 804px; height: 656px;'><noscript><a href='#'><img alt='Dashboard Bubble ' src='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardBubble&#47;1_rss.png' style='border: none' /></a></noscript><object class='tableauViz' width='804' height='656' style='display:none;'><param name='host_url' value='https%3A%2F%2Fpublic.tableausoftware.com%2F' /> <param name='site_root' value='' /><param name='name' value='Book2banned-books-final&#47;DashboardBubble' /><param name='tabs' value='no' /><param name='toolbar' value='yes' /><param name='static_image' value='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;Bo&#47;Book2banned-books-final&#47;DashboardBubble&#47;1.png' /> <param name='animate_transition' value='yes' /><param name='display_static_image' value='yes' /><param name='display_spinner' value='yes' /><param name='display_overlay' value='yes' /><param name='display_count' value='yes' /></object></div><div style='width:804px;height:22px;padding:0px 10px 0px 0px;color:black;font:normal 8pt verdana,helvetica,arial,sans-serif;'><div style='float:right; padding-right:8px;'><a href='http://www.tableausoftware.com/public/about-tableau-products?ref=https://public.tableausoftware.com/views/Book2banned-books-final/DashboardBubble' target='_blank'>Learn About Tableau</a></div></div>
EOT;
        // display the static content
        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

    public function aboutAction(Request $request, BaseApplication $app)
    {
        $file = $app['base_path'] . '/resources/view/about.md';
         $markdown = file_get_contents($file);

        $parsedown = new \Parsedown();
        $content = $parsedown->parse($markdown);

        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

}
