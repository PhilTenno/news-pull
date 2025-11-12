# News Pull
## Contao Erweiterung für den automatisierten Import von News - N8N
```
'News Pull' ist eine Contao-Erweiterung für den automatischen Import von Neuigkeiten. 
Über Konfigurationen im Backend können hierbei mehrere News-Archive des 
Contao-News-Bundles unabhängig von einander befüllt werden. 
Alle importierten News lassen sich im Anschluss wie gewohnt im Backend 
weiter bearbeiten oder direkt automatisch veröffentlichen. 
Das Importformat ist Json. Die Übermittlung erfolgt über einen HTTP-Request (Post):
https://domain.com/newspullimport?token=dein_token 
```
```
'News Pull' ermöglicht somit das Automatisieren von Neuigkeiten mit Hilfe 
von Plattformen wie z.B. [N8N](https://n8n.io/). 
Ein Frontend-Modul ermöglicht zusätzlich das Anzeigen verwandter Artikel 
auf Basis der Keywords. Das Frontendmodul kann hierfür einfach nach dem 
Contao-News-Reader-Modul eingefügt werden.
```

## Import-Formate
```
Der Import erfolgt per HTTP POST Request über den Endpunkt:
https://domain.com/newspullimport?token=dein_token
```
### Variante 1: Aufbau Json:
```
[
  {
  'title': 'News-Titel 1',
  'teaser': 'Teaser-Text',
  'article': 'Artikel-Text: erlaubt ist reiner Text und/oder HTML-Elemenmte wie in TinyMce',
  'metaTitle': 'Meta-Titel der News', // -> optional: Fallback = title 
  'metaDescription': 'Meta Beschreibung des Artikels', // -> optional: Fallback = Teaser
  'dateShow': '2025-06-10 04:06:00', // -> optional: Datum, wann der Artikel sichtbar sein soll
  'keywords': 'keyword-1,keyword-2,keyword-3', // -> optional: Keywords für verwandte Artikel
  'image': 'Name eines Bildes', // -> imagage.jpg // -> optional, wenn ein Bild separat auf den Server (Verzeichnis wird in der Erweiterungs-Konfiguration in Contao angelegt) wurde
  'imageAlt': 'Alt Beschreibung' // -> Bildbeschreibung des Bildes // -> optional
  }
]
```

### Variante 2: Multipart (neu):
```
curl -X POST "https://domain.com/newspullimport?token=dein_token" \
  -F 'payload={
    "items": [
      {
        "title": "News-Titel 1",
        "teaser": "Teaser-Text",
        "article": "<p>Artikeltext oder HTML-Elemente</p>",
        "metaTitle": "Meta-Titel der News",
        "metaDescription": "Meta-Beschreibung der News",
        "dateShow": "2025-06-10 04:06:00",
        "keywords": "keyword-1,keyword-2,keyword-3",
        "imageAlt": "Beschreibung des Bildes"
      }
    ]
  };type=application/json' \
  -F 'image=@/pfad/zur/datei.jpg;type=image/jpeg'


```
### Installation
```
$ composer require philtenno/news-pull
```

#### Anwendungsmöglichkeiten
```
--> News
--> Magazine
--> SEO
--> Intranetveröffentlichungen
```
*made with love by [hardbitrocker](https://www.hardbitrocker.de/blog/artikel-news-import-fuer-contao-5-3-mittels-ki-workflow-automation-n8n-io)*
