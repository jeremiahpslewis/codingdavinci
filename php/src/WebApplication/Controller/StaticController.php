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
                                          'content' => '',
                                          )
                                    );
    }

    public function geschichtenAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<!-- Beginn der storymap -->
    <a name="kolb" class="anchor-offset"></a>
    <iframe src='http://cdn.knightlab.com/libs/storymapjs/latest/embed/?url=https://www.googledrive.com/host/0B98wXqM9dji8UzQ1cjlZcFI4bUE/published.json&lang=de&height=600' width='100%' height='600' frameborder='0'></iframe>

<!-- Ende der Storymap -->

<!-- Beginn der Timeline: Erich Kästner -->
    <a name="kaestner" class="anchor-offset"></a>
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
}
