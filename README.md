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
### List ###
Data from http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/verbannte-buecher.json

- row (1 based to connect the entry to the original JSON-entry)
- entry (the original entry for reference)

The following fields are taken verbatim from the original list

- title
- ssFlag
- ...

Enhanced with additional information
- completeWork (1: Gesamtwerk, 2: Teile des Gesamtwerkes)

and temporary properties until Publication.gnd and Person.gnd are set properly

- titleGnd VARCHAR(511) NULL,
- authorGnd  VARCHAR(511) NULL,
- authorGnd2 VARCHAR(511) NULL,

### Publication ###
Data about the publications, property-names from the GND-Linked Data Representation and Bibo-properties (https://bibotools.googlecode.com/svn/bibo-ontology/trunk/doc/classes/Document___-538479979.html)

- title
- otherTitleInformation (Untertitel)
- placeOfPublication
- publisher
- publisherId
- publicationStatement # Place : Publisher
- extent (see http://metadataregistry.org/schemaprop/show/id/1995.html, <isbd:P1053>16 S.</isbd:P1053>)
- isPartOf (e.g. Arbeiter-Gesundheits-Bibliothek)
- bibliographicCitation (e.g. Arbeiter-Gesundheits-Bibliothek ; H. 11)
- issued
- parentId (to related different editions of a Publication, e.g. re-editions or translations of the original work on the List)
- listRow  (reference to List.row)
- listEdition (marker to indicate if this is the entry specified in firstEdition* or secondEdition* properties)

Norm-data identifiers
- gnd
- oclc

###  Person ###
- forename
- surname
- preferredName (Nachname, Vorname; z.B. Wolf, Victoria)
- variantNames (Wolf, Victoria Trude; Wolf, Victoria T.; Wolf, Viktoria; Wolff, Victoria; Wolff, Viktoria);
- gender (female, male)
- listRow  (reference to List.row)
- gnd
- viaf

### PublicationPerson ###
-  publication_id
-  person_id
-  role ENUM ('aut', 'edt', 'trl'), see http://www.loc.gov/marc/relators/relaterm.html
-  ord INT NOT NULL 0, # for ordering multiple person


### Publisher ###
- preferredName (Verlag C.H. Beck)
- variantNames ()
- places
- gnd
