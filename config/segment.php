<?php
/**
 *  @license
 *  Copyright 2014 Bit API Hub
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 *  Config file for Segment.io for FuelPHP
 *  @link https://segment.com/docs/libraries
 *  @link https://github.com/BitAPIHub/FuelPHP-Segment-io
 */

// NOTICE: Copy this file to your APPPATH/config directory before editing it. 

return array(
		
	/**
	 * General configuration
	 */
	
	/**
	 * The Segment.io write key
	 */
	'write_key'	=> '[YOUR WRITE KEY HERE]',
	
	/**
	 * Enter the configuration changes you wish to set for the PHP library.
	 * Please note that if you enable debugging on PHP, console debugging in JS will also be enabled.
	 * 
	 * @link https://segment.com/docs/libraries/php/#configuration
	 */
	'configure'	=> array(
	
		'consumer'		=> 'socket', // socket, fork_curl, file
		'debug'			=> false,
		'ssl'			=> false,
		//'error_handler'	=> function ($code, $message) {},
		
		// fork_curl
		// 'max_queue_size'	=> 10000,
		// 'batch_size'		=> 100,
		
		// file
		// 'filename'	=> '/tmp/analytics.log'
	
	),
	
);