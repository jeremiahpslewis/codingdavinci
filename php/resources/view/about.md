## <a name="COD1NGDAV1NC1"></a>}COD1NG DA V1NC1{
Die Verbrannte und Verbannte Web-Site dient der Wiederentdeckung und Verbreitung der ["Liste des schädlichen und unerwünschten Schrifttums"](https://de.wikipedia.org/wiki/Liste_verbotener_Autoren_während_der_Zeit_des_Nationalsozialismus) und ist im Rahmen von [{COD1NG DA V1NC1}](http://codingdavinci.de/), dem ersten "Kultur-Hackathon" in Berlin, zwischen dem 26./27. April und 5./6. Juli 2014 entstanden.

## <a name="project"></a> Ziel des Projekts
Die zwischen 1938 und 1941 von der „Reichsschriftkammer“ erstellten Verbotslisten umfassen:
  * knapp **5'000** unerwünschte Einzelpublikationen
  * fast **1'000** Autorinnen und Autoren
  * eine Reihe von Verlagen, deren Gesamtwerk im „Dritten Reich“ verboten wurde

Anhand dieser Liste wollten wir ein neues digitales Zuhause für diesen längst vergessenen Teil der deutschen Geschichte schaffen und somit neue Entdeckungen von Laien und Experten durch Open-Data & Digital-Humanities ermöglichen.

## Web-Präsentation
* Aufsetzen einer Webpräsentation zu der Liste und deren Einträgen mit verbesserten Such- und Sortierfunktionen
* Visualisierung der "Masse" der betroffenen Werke
* Einspielen von ersten - primär statistischen - Analysen (Worthäufigkeiten, Regionalverteilungen etc.)
* Die exemplarische Präsentation der Lebensläufe zweier betroffener Autoren [(Annette Kolb, Erich Kästner)](http://verbrannte-und-verbannte.de/geschichten)

## Datengrundlage und Web-Services
* "Liste des schädlichen und unerwünschten Schrifttums"
[http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/](http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/), Lizenz: CC-BY
* Linked Data Service der Deutsche Nationalbibliothek: DNB-Titeldaten und GND-Normdaten [http://www.dnb.de/lds](http://www.dnb.de/lds), Lizenz: CC0
* SeeAlso-Dienst zu allen bekannten Beacon-Dateien zu Personen ("aks" für all known services) [http://beacon.findbuch.de/](http://beacon.findbuch.de/)
* Entity Facts: Ein leichtgewichtiger Normdatendienst auf Basis der GND [http://hub.culturegraph.org/](http://hub.culturegraph.org/)

## Datenergänzungen
Nicht explizit angegeben in den ursprünglichen Listen aber durch unsere Arbeit ergänzt wurden folgendes:
* Werksangaben zu den Autoren, bei denen "Sämtliche Schriften" verboten wurden
* biographische Angaben zu den Autoren u. a.
* Verlinkung der Listeneinträge zu externen, vornehmlich bibliographischen Datenbanken

## Verwendetee Technologien & Dienste
* Web App
  * [php](http://php.net)
  * [symphony](http://symfony.com)
* Visualisierungen
  * [Tableau](http://www.tableausoftware.com)
  * [HighCharts](http://www.highcharts.com)
* Geschichten von Annette Kolb und Erich Kästner
  * [timeline.js](http://timeline.knightlab.com) (Northwestern University Knight Lab)
  * [storymap.js](http://storymap.knightlab.com) (Northwestern University Knight Lab)
* Projektmanagement über [Github](www.github.com)

## Source Code
Der Code dieser Web-Site sowie der Import-Tools ist auf Github verfügbar:
* [Web-Seite und Daten](https://github.com/jlewis91/codingdavinci)
* [Import-Tools](https://github.com/mhinters/BannedBookUtils) Lizense: [MIT](https://github.com/mhinters/BannedBookUtils/blob/master/LICENSE.txt)


## Verwandte Projekte
* [@LebendigeListe](http://lebendigeliste.de/)
* [App "Verbotene Autoren"](http://cdvinci.hackdash.org/projects/53a703ff9bfef6693e000007)

Weitere Informationen zur Liste verbotener Publikationen und Autoren während der Zeit des Nationalsozialismus:
* [Datenbank der in der NS-Zeit verbannten und verbotenen Autoren und Bücher](http://djgd.hypotheses.org/296) von Harald Lordick
* ["Liste verbotener Autoren während der Zeit des Nationalsozialismus"](https://de.wikipedia.org/wiki/Liste_verbotener_Autoren_w%C3%A4hrend_der_Zeit_des_Nationalsozialismus) auf [Wikipedia.de](www.wikipedia.de)

## <a name="team"></a>Namen aller Teammitglieder
* Daniel Burckhardt ([github](https://github.com/burki))
* Dierk Eichel ([github](https://github.com/deichel))
* Michael Hintersonnleitner ([github](https://github.com/mhinters/))
* Frederike Kaltheuner ([github](https://github.com/fre8de8rike))
* Jeremy Lewis ([github](https://github.com/jlewis91))
* Jonas Parnow ([github](https://github.com/z3to))
* Kristin Sprechert ([github](https://github.com/kaeseoehrchen))
* Kai Teuber ([twitter](https://twitter.com/teuberkai) / [github](https://github.com/teuberkai))
* Clemens Wilding ([github](https://github.com/wigginus))
