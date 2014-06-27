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

    public function tableauOrtAction(Request $request, BaseApplication $app)
    {
        $content = <<<EOT
<script type='text/javascript' src='https://public.tableausoftware.com/javascripts/api/viz_v1.js'></script><div class='tableauPlaceholder' style='width: 1204px; height: 657px;'><noscript><a href='#'><img alt=' ' src='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;ba&#47;banned-books-v02&#47;Dashboard-Map-black&#47;1_rss.png' style='border: none' /></a></noscript><object class='tableauViz' width='1204' height='657' style='display:none;'><param name='host_url' value='https%3A%2F%2Fpublic.tableausoftware.com%2F' /> <param name='site_root' value='' /><param name='name' value='banned-books-v02&#47;Dashboard-Map-black' /><param name='tabs' value='no' /><param name='toolbar' value='yes' /><param name='static_image' value='https:&#47;&#47;public.tableausoftware.com&#47;static&#47;images&#47;ba&#47;banned-books-v02&#47;Dashboard-Map-black&#47;1.png' /> <param name='animate_transition' value='yes' /><param name='display_static_image' value='yes' /><param name='display_spinner' value='yes' /><param name='display_overlay' value='yes' /><param name='display_count' value='yes' /></object></div><div style='width:1204px;height:22px;padding:0px 10px 0px 0px;color:black;font:normal 8pt verdana,helvetica,arial,sans-serif;'><div style='float:right; padding-right:8px;'><a href='http://www.tableausoftware.com/public/about-tableau-products?ref=https://public.tableausoftware.com/views/banned-books-v02/Dashboard-Map-black' target='_blank'>Learn About Tableau</a></div></div>
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
        $markdown = <<<EOT
## <a name="COD1NGDAV1NC1"></a>}COD1NG DA V1NC1{
Diese Web-Site ist im Rahmen von [{COD1NG DA V1NC1}](http://codingdavinci.de/), dem ersten "Kultur-Hackathon" in Berlin, zwischen dem 26./27. April und 5./6. Juli 2014 entstanden.

## <a name="project"></a>Beschreibung und Ziel des Projekts
* Als Datengrundlage dient die vom Land Berlin veröffentlichte Liste der verbannten Bücher. Diese zwischen 1938 und 1941 von der „Reichsschriftkammer“ erstellten Verbotslisten umfassen knapp 5'000 unerwünschte Einzelpublikationen sowie fast 1'000 Autorinnen und Autoren sowie eine Reihe von Verlagen, deren Gesamtwerk im „Dritten Reich“ verboten wurde.
* Ergänzung der Liste um
  * Werksangaben zu den Autoren, bei denen "Sämtliche Schriften" verboten wurden,
  * biographische Angaben zu den Autoren u. a.
* Verlinkung der Listeneinträge zu externen, vornehmlich bibliographischen Datenbanken
* Aufsetzen einer Webpräsentation zu der Liste und deren Einträgen mit verbesserten Such- und Sortierfunktionen
* Visualisierung der "Masse" der betroffenen Werke
* Einspielen von ersten - primär statistischen - Analysen (Worthäufigkeiten, Regionalverteilungen etc.)
* Die exemplarische Präsentation der Lebensläufe zweier betroffener Autoren (Annette Kolb, Erich Kästner) mit Hilfe frei nutzbarer, webaffiner Storytelling-Formate (hier: [timeline js](http://timeline.knightlab.com), [storymap js](http://storymap.knightlab.com) des Northwestern University Knight Lab)

## Datengrundlage und Web-Services
* "Liste des schädlichen und unerwünschten Schrifttums"
[http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/](http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/), Lizenz: CC-BY
* Linked Data Service der Deutsche Nationalbibliothek: DNB-Titeldaten und GND-Normdaten [http://www.dnb.de/lds](http://www.dnb.de/lds), Lizenz: CC0
* SeeAlso-Dienst zu allen bekannten Beacon-Dateien zu Personen ("aks" für all known services) [http://beacon.findbuch.de/](http://beacon.findbuch.de/)
* Entity Facts: Ein leichtgewichtiger Normdatendienst auf Basis der GND

## Links
Der Code dieser Web-Site sowie der Import-Tools ist auf Github verfügbar
* [Github](https://github.com/jlewis91/codingdavinci)
* [Github](https://github.com/mhinters/BannedBookUtils)

Im Rahmen von haben weitere Teams mit diesem Datensatz gearbeitet
* [@LebendigeListe](http://lebendigeliste.de/)
* [App "Verbotene Autoren"](http://cdvinci.hackdash.org/projects/53a703ff9bfef6693e000007)

Weitere Informationen zur Liste verbotener Publikationen und Autoren während der Zeit des Nationalsozialismus
* Harald Lordick: [Datenbank der in der NS-Zeit verbannten und verbotenen Autoren und Bücher](http://djgd.hypotheses.org/296)
* [Wikipedia-Eintrag "Liste verbotener Autoren während der Zeit des Nationalsozialismus"](https://de.wikipedia.org/wiki/Liste_verbotener_Autoren_w%C3%A4hrend_der_Zeit_des_Nationalsozialismus)

## <a name="team"></a>Namen aller Teammitglieder
* Daniel Burckhardt ([github](https://github.com/burki))
* Dierk Eichel
* Michael Hintersonnleitner ([github](https://github.com/mhinters/))
* Frederike Kaltheuner ([github](https://github.com/fre8de8rike))
* Jeremy Lewis ([github](https://github.com/jlewis91))
* Jonas Parnow ([github](https://github.com/z3to))
* Kristin Sprechert ([github](https://github.com/kaeseoehrchen))
* Kai Teuber ([twitter](https://twitter.com/teuberkai) / [github](https://github.com/teuberkai))
* Clemens Wilding ([github](https://github.com/wigginus))


EOT;
        $parsedown = new \Parsedown();
        $content = $parsedown->parse($markdown);

        return $app['twig']->render('static.twig',
                                    array(
                                          'content' => $content,
                                          )
                                    );
    }

}
