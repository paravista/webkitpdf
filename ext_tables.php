<?php
if(!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$_EXTKEY = 'webkitpdf';

// Add static file for plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'static/', 'WebKit PDF');

#t3lib_div::loadTCA('tt_content');
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY . '_pi1'] = 'layout,pages,select_key';

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	array(
		'LLL:EXT:webkitpdf/locallang_db.xml:tt_content.list_type_pi1',
		$_EXTKEY . '_pi1'
	),
	'list_type'
);

?>
