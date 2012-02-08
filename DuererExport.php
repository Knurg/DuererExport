<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
/*
if (!defined('MEDIAWIKI')) {
  echo <<<EOT 
To install my extension, put the following line in LocalSettings.php: 
require_once("\$IP/extensions/DuererExport/DuererExport.php" ); 
EOT;
  exit( 1 );
}
*/
                 
$wgExtensionCredits['specialpage'][] = array(
  'path' => __FILE__,
  'name' => 'DuererExport',
  'author' => 'Mark Fichtner, Helen Kohler',
  'url' => 'https://www.mediawiki.org/wiki/Extension:DuererExport',
  'descriptionmsg' => 'duererexport-desc',
  'version' => '0.0.0',
);

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['SpecialDuererExport'] = $dir . 'SpecialDuererExport.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['DuererExport'] = $dir . 'DuererExport.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['DuererExportAlias'] = $dir . 'DuererExport.alias.php'; # Location of an aliases file (Tell MediaWiki to load this file)
$wgSpecialPages['DuererExport'] = 'SpecialDuererExport'; # Tell MediaWiki about the new special page and its class name
$wgSpecialPageGroups['DuererExport'] = 'other';
