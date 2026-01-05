# News Pull
## Contao extension for automated news import – N8N
'News Pull' is a Contao extension for the automatic import of news items.
Via backend configuration, multiple news archives of the
Contao news bundle can be populated independently of each other.
All imported news items can then be edited further in the backend as usual
or published automatically right away.
The import format is JSON. Transmission is done via an HTTP-Request (POST):
https://domain.com/newspullimport?token=your_token

'News Pull' enables the automation of news items using platforms
such as N8N.
A frontend module additionally allows displaying related articles
based on keywords. The frontend module can simply be placed
after the Contao news reader module.


## Import formats
The import is performed via HTTP POST request to the endpoint:
https://domain.com/newspullimport?token=your_token


### Variant 1: JSON structure:
[
{
'title': 'News title 1',
'teaser': 'Teaser text',
'article': 'Article text: plain text and/or HTML elements as in TinyMCE are allowed',
'metaTitle': 'Meta title of the news item', // -> optional: fallback = title
'metaDescription': 'Meta description of the article', // -> optional: fallback = teaser
'dateShow': '2025-06-10 04:06:00', // -> optional: date when the article should become visible
'keywords': 'keyword-1,keyword-2,keyword-3', // -> optional: keywords for related articles
'image': 'Name of an image file', // -> image.jpg // -> optional, if an image has been uploaded separately to the server (directory is defined in the extension configuration in Contao)
'imageAlt': 'Alt description' // -> image description / alt text // -> optional
}
]


### Variant 2: Multipart (new):
curl -X POST "https://domain.com/newspullimport?token=your_token" \
-F 'payload={
"items": [
{
"title": "News title 1",
"teaser": "Teaser text",
"article": "Article text or HTML elements",
"metaTitle": "Meta title of the news item",
"metaDescription": "Meta description of the news item",
"dateShow": "2025-06-10 04:06:00",
"keywords": "keyword-1,keyword-2,keyword-3",
"imageAlt": "Description of the image"
}
]
};type=application/json' \
-F 'image=@/path/to/file.jpg;type=image/jpeg'


### Installation
$ composer require philtenno/news-pull


#### Use cases
--> News
--> Magazines
--> SEO
--> Intranet publications

*made with love by [hardbitrocker](https://www.hardbitrocker.de/blog/artikel-news-import-fuer-contao-5-3-mittels-ki-workflow-automation-n8n-io)*