<?php

// Legends
$GLOBALS['TL_LANG']['tl_newspull']['settings_legend']     = 'Settings';

// Fields
$GLOBALS['TL_LANG']['tl_newspull']['title']               = ['Title', 'Name of this news pull configuration.'];
$GLOBALS['TL_LANG']['tl_newspull']['token']               = ['Token',
    'Example URL: https://your-domain.com/newspullimport?token=YOUR_TOKEN'
];
$GLOBALS['TL_LANG']['tl_newspull']['image_dir']           = ['Image folder', 'Folder used to look up images by filename.'];
$GLOBALS['TL_LANG']['tl_newspull']['image_size']          = ['Image size', 'Predefined image size to use for the image content element.'];
$GLOBALS['TL_LANG']['tl_newspull']['news_archive']        = ['News archive', 'Target archive for imported news.'];
$GLOBALS['TL_LANG']['tl_newspull']['author']              = ['Author', 'Default author for imported news.'];
$GLOBALS['TL_LANG']['tl_newspull']['auto_publish']        = ['Auto publish', 'Publish imported news automatically.'];
$GLOBALS['TL_LANG']['tl_newspull']['batch_size']          = ['Batch size', 'Number of items to process per batch.'];
$GLOBALS['TL_LANG']['tl_newspull']['max_payload_size_kb'] = ['Max. payload size (KB)', 'Maximum size of the JSON payload for the POST import in kilobytes.'];
$GLOBALS['TL_LANG']['tl_newspull']['teaser_image']        = ['Set teaser image', 'Also store the image as teaser image on the news record (if available).'];
$GLOBALS['TL_LANG']['tl_newspull']['teaser_news']         = ['Teaser as content element', 'Insert the teaser as a separate content element before the article.'];
$GLOBALS['TL_LANG']['tl_newspull']['no_htmltags']         = ['Remove HTML tags', 'Remove all HTML tags from teaser and article (plain text only).'];
$GLOBALS['TL_LANG']['tl_newspull']['linktarget']          = ['Harden external links', 'Add target="_blank" and rel="nofollow noopener" to links automatically.'];

// Operations
$GLOBALS['TL_LANG']['tl_newspull']['settings_legend'] = 'Import settings';
$GLOBALS['TL_LANG']['tl_newspull']['delete'] = ['Delete', 'Delete this configuration'];

