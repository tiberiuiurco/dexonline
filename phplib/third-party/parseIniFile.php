<?php

/**
 * @file phplib/third-party/parseIniFile.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class parseIniFile
 * @ingroup config
 *
 * @brief Class for parsing and modifying .ini style configuration files.
 */

// $Id$


class parseIniFile {

	/** Contents of the config file currently being parsed */
	var $content;
  private $parseCommented;

	/**
	 * Constructor.
   * @param $parseCommented  default = false
	 */
  public function __construct($parseCommented = false)
  {
    $this->parseCommented = $parseCommented;
  }

	/**
	 * Read a configuration file into a multidimensional array.
	 * This is a replacement for the PHP parse_ini_file function, which does not type setting values.
	 * @param $file string full path to the config file
	 * @return array the configuration data (same format as http://php.net/parse_ini_file)
	 */
	function &readConfig($file) {
		$configData = array();
		$currentSection = false;
		$falseValue = false;

		if (!file_exists($file) || !is_readable($file)) {
			return $falseValue;
		}

		$fp = fopen($file, 'rb');
		if (!$fp) {
			return $falseValue;
		}

		while (!feof($fp)) {
			$line = fgets($fp, 1024);
			$line = trim($line);
			if ($line === '' || ($line[0] === ';' && !$this->parseCommented)) {
				// Skip empty lines or commented ones (only if !parseCommented is false)
				continue;
			}
      
      //if ($line[0] === ';') {
      //  $line = substr($line, 1);
			//}
      
			if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
				// Found a section
        $currentSection = $matches[1];
        if (!isset($configData[$currentSection])) {
          $configData[$currentSection] = array();
        }
			} else if (strpos($line, '=') !== false) {
				// Found a setting
				list($key, $value) = explode('=', $line, 2);
				$key = trim($key);
				$value = trim($value);
        
				if (preg_match('/^[\"\'](.*)[\"\']$/', $value, $matches)) {
					// Treat value as a string
					$value = stripslashes($matches[1]);

				} else {
					preg_match('/^([\S]*)/', $value, $matches); 
					//$value = $matches[1]; // we don't need this when importing abbreviation confs!!!

					// Try to determine the type of the value
					if ($value === '') {
						$value = null;

					} else if (is_numeric($value)) {
						if (strstr($value, '.')) {
							// floating-point
							$value = (float) $value;
						} else if (substr($value, 0, 2) == '0x') {
							// hex
							$value = intval($value, 16);
						} else if (substr($value, 0, 1) == '0') {
							// octal
							$value = intval($value, 8);
						} else {
							// integer
							$value = (int) $value;
						}

					} else if (strtolower($value) == 'true' || strtolower($value) == 'on') {
						$value = true;

					} else if (strtolower($value) == 'false' || strtolower($value) == 'off') {
						$value = false;

					} else if (defined($value)) {
						// The value matches a named constant
						$value = constant($value);
					}
				}

				if ($currentSection === false) {
					$configData[$key] = $value;

				} else if (is_array($configData[$currentSection])) {
					$configData[$currentSection][$key] = $value;
				}
			}
		}

		fclose($fp);

		return $configData;
	}

	/**
	 * Read a configuration file and update variables.
	 * This method stores the updated configuration but does not write it out.
	 * Use writeConfig() or getFileContents() afterwards to do something with the new config.
	 * @param $file string full path to the config file
	 * @param $params array an associative array of configuration parameters to update. If the value is an associative array (of variable name/value pairs) instead of a scalar, the key is treated as a section instead of a variable. Parameters not in $params remain unchanged
	 * @return boolean true if file could be read, false otherwise
	 */
	function updateConfig($file, $params) {
		if (!file_exists($file) || !is_readable($file)) {
			return false;
		}

		$this->content = '';
		$lines = file($file);

		// Parse each line of the configuration file
		for ($i=0, $count=count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			if (preg_match('/^;/', $line) || preg_match('/^\s*$/', $line)) {
				// Comment or empty line
				$this->content .= $line;

			} else if (preg_match('/^\s*\[(\w+)\]/', $line, $matches)) {
				// Start of new section
				$currentSection = $matches[1];
				$this->content .= $line;

			} else if (preg_match('/^\s*(\w+)\s*=/', $line, $matches)) {
				// Variable definition
				$key = $matches[1];

				if (!isset($currentSection) && array_key_exists($key, $params) && !is_array($params[$key])) {
					// Variable not in a section
					$value = $params[$key];

				} else if (isset($params[$currentSection]) && is_array($params[$currentSection]) && array_key_exists($key, $params[$currentSection])) {
					// Variable in a section
					$value = $params[$currentSection][$key];

				} else {
					// Variable not to be changed, do not modify line
					$this->content .= $line;
					continue;
				}

				if (preg_match('/[^\w\-\/]/', $value)) {
					// Escape strings containing non-alphanumeric characters
					$valueString = '"' . $value . '"';
				} else {
					$valueString = $value;
				}

				$this->content .= "$key = $valueString\n";

			} else {
				$this->content .= $line;
			}
		}

		return true;
	}

	/**
	 * Write contents of current config file
	 * @param $file string full path to output file
	 * @return boolean file write is successful
	 */
	function writeConfig($file) {
		if (!(file_exists($file) && is_writable($file))
			&& !(!file_exists($file) && is_dir(dirname($file)) && is_writable(dirname($file)))) {
			// File location cannot be written to
			return false;
		}

		$fp = @fopen($file, 'wb');
		if (!$fp) {
			return false;
		}

		fwrite($fp, $this->content);
		fclose($fp);
		return true;
	}

	/**
	 * Return the contents of the current config file.
	 * @return string
	 */
	function getFileContents() {
		return $this->content;
	}
}
