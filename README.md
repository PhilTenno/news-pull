# News Pull
## Contao Erweiterung für den automatisierten Import von News - N8N
```
'News Pull' ist eine Contao-Erweiterung für den automatischen Import von Neuigkeiten. 
Über Konfigurationen im Backend können hierbei mehrere News-Archive des 
Contao-News-Bundles unabhängig von einander befüllt werden. 
Alle importierten News lassen sich im Anschluss wie gewohnt im Backend 
weiter bearbeiten oder direkt automatisch veröffentlichen. 
Das Importformat ist Json.
```
```
'News Pull' ermöglicht somit das Automatisieren von Neuigkeiten mit Hilfe 
von Plattformen wie z.B. [N8N](https://n8n.io/). 
Ein Frontend-Modul ermöglicht zusätzlich das Anzeigen verwandter Artikel 
auf Basis der Keywords. Das Frontendmodul kann hierfür einfach nach dem 
Contao-News-Reader-Modul eingefügt werden.
```
```
Aufbau Json:
[
  {
  'title': 'News-Titel 1',
  'teaser': 'Teaer-Text',
  'article': 'Artikel-Text: erlaubt ist reiner Text und/oder HTML-Elemenmte wie in TinyMx',
  'metaTitle': 'Meta-Titel der News', // -> optional: Fallback = title 
  'metaDescription': 'Meta Beschreibung des Artikels', // -> optional: Fallback = teaser
  'dateShow': '2025-06-10 04:06:00', // -> optional: Datum, wann der Artikel sichtbar sein soll
  'keywords': 'keywords-1,keywords-2,keywords-3' // -> optional: Keywords für verwandte Artikeln
  },
  {
  'title': 'News-Titel 2',
  'teaser': 'Teaer-Text 2',
  'article': 'Artikel-Text 2',
  'metaTitle': 'Meta-titel 2',
  'metaDescription': 'Meta Beschreibung 2',
  'dateShow': '2025-06-12 00:01:00', 
  'keywords': 'keywords-1,keywords-2,keywords-3'
  },
  {...}
]
```
### Anwendungsmöglichkeiten
--> News
--> Magazine
--> SEO
--> Intranetveröffentlichungen

*made with love by [hardbitrocker](https://hardbitrocker.de)*
