codingdavinci
=============

## API ##
- Person nach Name:
	- Bsp: http://api.lobid.org/person?name=wolf+victoria
- Publikation nach Titelstichwort und Autor
	- Bsp: http://lobid.org/resource?q=Ignaz+Zadek+1907+frauenleiden&type=http://purl.org/dc/terms/BibliographicResource

- Eingefügte Daten nach GND:
 	- http://hub.culturegraph.org/entityfacts/{GND_Nummer}
 
## Tabellenschema ##

    Person
    - forename
    - surname
    - preferredName (Nachname, Vorname; z.B. Wolf, Victoria)
    - variantNames (Wolf, Victoria Trude; Wolf, Victoria T.; Wolf, Viktoria; Wolff, Victoria; Wolff, Viktoria);
    - gender (female, male)
    - gnd

    TODO: Publication

    Publisher
    - preferredName (Verlag C.H. Beck)
    - variantNames ()
    - places
    - gnd
