<?php
if(!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43(
	$_EXTKEY,
	'pi1/class.tx_webkitpdf_pi1.php',
	'_pi1',
	'list_type',
	0
);

// Clear cache Hook
#$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][$_EXTKEY] = 'EXT:webkitpdf/Classes/Tx_Webkitpdf_CacheManager.php:&Tx_Webkitpdf_CacheManager->clearCachePostProc';

// Unserializing the configuration so we can use it here
$_EXTCONF = unserialize($_EXTCONF);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['cacheThreshold'] = intval($_EXTCONF['cacheThreshold']);
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$_EXTKEY]['debug'] = intval($_EXTCONF['debug']);

?>
