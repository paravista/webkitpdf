<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Dev-Team Typoheads <dev@typoheads.at>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Plugin 'WebKit PDFs' for the 'webkitpdf' extension.
 *
 * @author Reinhard Führicht <rf@typoheads.at>
 */

# use autoloader instead
#require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('webkitpdf') . 'Classes/Tx_Webkitpdf_CacheManager.php');
#require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('webkitpdf') . 'Classes/Tx_Webkitpdf_Utils.php');

class tx_webkitpdf_pi1 extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin {
	var $prefixId = 'tx_webkitpdf_pi1';
	var $scriptRelPath = 'pi1/class.tx_webkitpdf_pi1.php';	
	var $extKey = 'webkitpdf';	

	// Disable caching: Don't check cHash, because the plugin is a USER_INT object
	public $pi_checkCHash = FALSE;
	public $pi_USER_INT_obj = 1;

	protected $cacheManager;
	protected $scriptPath;
	protected $outputPath;
	protected $paramName;
	protected $filename;
	protected $filenameOnly;
	protected $baseUrlToConvert;
	protected $contentDisposition;
	protected $db;

	/**
	 * Init parameters. Reads TypoScript settings.
	 *
	 * @param	array		$conf: The PlugIn configuration
	 * @return	void
	 */
	protected function init($conf) {

		$this->db = $GLOBALS['TYPO3_DB'];
		
		// Process stdWrap properties
		$temp = $conf['scriptParams.'];
		unset($conf['scriptParams.']);
		$this->conf = $this->processStdWraps($conf);
		if(is_array($temp)) {
			$this->conf['scriptParams'] = $this->processStdWraps($temp);
		}

		$this->pi_setPiVarDefaults();

		$this->scriptPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('webkitpdf') . 'res/';
		if($this->conf['customScriptPath']) {
			$this->scriptPath = $this->conf['customScriptPath'];
		}
		$this->outputPath = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_DOCUMENT_ROOT');
		if($this->conf['customTempOutputPath']) {
			$this->outputPath .= tx_webkitpdf_utils::sanitizePath($this->conf['customTempOutputPath']);
		} else {
			$this->outputPath .= '/typo3temp/tx_webkitpdf/';
		}

		$this->paramName = 'urls';
		if($this->conf['customParameterName']) {
			$this->paramName = $this->conf['customParameterName'];
		}

		$this->baseUrlToConvert = $this->piVars[$this->paramName][0];

		$this->generateFileName();

		$this->readScriptSettings();
		$this->cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Webkitpdf_CacheManager', $this->conf);

		$this->contentDisposition = 'attachment';
		if(intval($this->conf['openFilesInline']) === 1) {
			$this->contentDisposition = 'inline';
		}
	}

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	string 		The content that is displayed on the website
	 */
	public function main($content, $conf)	{

		$this->init($conf);

		$urls = $this->piVars[$this->paramName];
		if(!$urls) {
			if (isset($this->conf['urls.'])) {
				$urls = $this->conf['urls.'];
			} else {
				$urls = array($this->conf['urls']);
			}
		}

		$content = '';
		if(!empty($urls)) {
			if(count($urls) > 0) {
				
				$origUrls = implode(' ', $urls);
				$loadFromCache = TRUE;
				
				$allowedHosts = FALSE;
				if($this->conf['allowedHosts']) {
					$allowedHosts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->conf['allowedHosts']);
				}
				
				foreach($urls as &$url) {
					if($GLOBALS['TSFE']->loginUser) {
						
						// Do not cache access restricted pages
						$loadFromCache = FALSE;
						$url = tx_webkitpdf_utils::appendFESessionInfoToURL($url);
					}
					$url = tx_webkitpdf_utils::sanitizeURL($url, $allowedHosts);
				}
				
				// not in cache. generate PDF file
				if(!$this->cacheManager->isInCache($origUrls) || $this->conf['debugScriptCall'] === '1' || !$loadFromCache) {
					
					$scriptCall = 	$this->scriptPath. 'wkhtmltopdf --disable-javascript' .
									$this->buildScriptOptions() . ' ' .
									implode(' ', $urls) . ' ' .
									$this->filename .
									' 2>&1';
					
					exec($scriptCall, $output);
					file_put_contents('typo3temp/webkit_exec.txt', $scriptCall);

					// Write debugging information to devLog
					tx_webkitpdf_utils::debugLogging('Executed shell command', -1, array($scriptCall));
					tx_webkitpdf_utils::debugLogging('Output of shell command', -1, $output);
					
					$this->cacheManager->store($origUrls, $this->filename);
					
				} else {
					
					//read filepath from cache
					$this->filename = $this->cacheManager->get($origUrls);
				}
				
				if( (int) $this->conf['fileOnly'] === 1) {
					return $this->filename;
				}

				# debug
				#echo 'shell: ' . $scriptCall .'<br>';
				#exit($origUrls . ' ::: ' . $this->filename . ' ||| ' . $this->filenameOnly);
				
				$filesize = filesize($this->filename);
				
				header('Content-type: application/pdf');
				header('Content-Transfer-Encoding: Binary');
				header('Content-Length: ' . $filesize);
				header('Content-Disposition: ' . $this->contentDisposition . '; filename="' . $this->filenameOnly . '"');
				header('X-Robots-Tag: noindex');
				readfile($this->filename);

				if(!$this->cacheManager->isCachingEnabled()) {
					unlink($this->filename);
				}
				exit(0);
			}
		}
		
		return $this->pi_wrapInBaseClass($content);
	}


	/**
	 * Generate File Name
	 *
	 * @return 	void
	 */
	protected function generateFileName() {

		if ( $this->conf['useUrlAsFileName'] ) {
			$this->filename = $this->outputPath . $this->conf['filePrefix'] . $this->convertUrlToFileName() . '.pdf';
		} else {
			$this->filename = $this->outputPath . $this->conf['filePrefix'] . tx_webkitpdf_utils::generateHash() . '.pdf';
		}

		$this->filenameOnly = basename($this->filename);

		if($this->conf['staticFileName']) {
			$this->filenameOnly = $this->conf['staticFileName'];
		}

		if(substr($this->filenameOnly, strlen($this->filenameOnly) - 4) !== '.pdf') {
			$this->filenameOnly .= '.pdf';
		}

	}


	/**
	 * Convert Url to PDF file name - only working with realUrl !!!
	 *
	 * @throws
	 * @return string file name
	 */
	protected function convertUrlToFileName() {

		if (filter_var($this->baseUrlToConvert, FILTER_VALIDATE_URL) === false) {
			throw new Exception('Invalid Url!');
		}

		// remove trailing slash
		$url = rtrim($this->baseUrlToConvert, '/');

		$urlSegments = explode('/', $url);

		$lastUrlPart = end($urlSegments);
		$rawFileName = str_replace('.html', '', $lastUrlPart);
		$rawFileName = str_replace('.htm', '', $rawFileName);
		$rawFileName = str_replace('-', '_', $rawFileName);

		return $rawFileName;

	}


	/**
	 * Read PDF settings
	 *
	 * @return array PDF settings
	 */
	protected function readScriptSettings() {
		$defaultSettings = array(
			'footer-right' => '[page]/[toPage]',
			'footer-font-size' => '6',
			'header-font-size' => '6',
			'margin-left' => '15mm',
			'margin-right' => '15mm',
			'margin-top' => '15mm',
			'margin-bottom' => '15mm',
		);

		$tsSettings = $this->conf['scriptParams'];
		foreach($defaultSettings as $param => $value) {
			if(!isset($tsSettings[$param])) {
				$tsSettings[$param] = $value;
			}
		}
		
		$finalSettings = array();
		foreach($tsSettings as $param => $value) {
			$value = trim($value);
			if(substr($param, 0, 2) !== '--') {
				$param = '--' . $param;
			}
			$finalSettings[$param] = $value;
		}
		return $finalSettings;
	}

	/**
	 * Creates the parameters for the wkhtmltopdf call.
	 *
	 * @return string Parameter string / arguments for shell call
	 */
	protected function buildScriptOptions() {
		$options = array();
		if($this->conf['pageURLInHeader']) {
			$options['--header-center'] = '[webpage]';
		}
		
		if($this->conf['copyrightNotice']) {
			$options['--footer-left'] = '© ' . date('Y', time()) . $this->conf['copyrightNotice'] . '';
		}
		
		if($this->conf['additionalStylesheet']) {
			$this->conf['additionalStylesheet'] = $this->sanitizePath($this->conf['additionalStylesheet'], FALSE);
			$options['--user-style-sheet'] = \TYPO3\CMS\Core\Utility\GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . $this->conf['additionalStylesheet'];
				
		}

		$userSettings = $this->readScriptSettings();
		$options = array_merge($options, $userSettings);
		
		$paramsString = '';
		foreach($options as $param => $value) {
			if(strlen($value) > 0) {
				$value = '"' . $value . '"';
			}
			$paramsString .= ' ' . $param . ' ' . $value; 
		}
		return $paramsString;
	}

	/**
	 * Makes sure that given path has a slash as first and last character
	 *
	 * @param	string		$path: The path to be sanitized
	 * @param 	bool		$trailingSlash
	 * @return	string 		Sanitized path
	 */
	protected function sanitizePath($path, $trailingSlash=TRUE) {
		
		// slash as last character
		if($trailingSlash && substr($path, (strlen($path) - 1)) !== '/') {
			$path .= '/';
		}
		
		//slash as first character
		if(substr($path, 0, 1) !== '/') {
			$path = '/' . $path;
		}
		
		return $path;
	}
	
	/**
	 * Processes the stdWrap properties of the input array
	 *
	 * @param	array	$tsSettings TypoScript array
	 * @return	array	Processed TypoScript array
	 */
	protected function processStdWraps($tsSettings) {
		
		// Get TS values and process stdWrap properties
		if(is_array($tsSettings)) {
			foreach ($tsSettings as $key => $value) {
				$process = TRUE;			
				if (substr($key, -1) === '.') {
					$key = substr($key, 0, -1);
					if (array_key_exists($key, $tsSettings)) {
						$process = FALSE;
					}
				}
				
				if ((substr($key, -1) === '.' && !array_key_exists(substr($key, 0, -1), $tsSettings)) ||
					(substr($key, -1) !== '.' && array_key_exists($key . '.', $tsSettings)) && !strstr($key, 'scriptParams')) {
					
					$tsSettings[$key] = $this->cObj->stdWrap($value, $tsSettings[$key . '.']);
	
					// Remove the additional TS properties after processing, otherwise they'll be translated to pdf properties
					if (isset($tsSettings[$key . '.'])) {
						unset($tsSettings[$key . '.']);
					}
				}
			}
		}
		return $tsSettings;
	}
	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/webkitpdf/pi1/class.tx_webkitpdf_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/webkitpdf/pi1/class.tx_webkitpdf_pi1.php']);
}

?>