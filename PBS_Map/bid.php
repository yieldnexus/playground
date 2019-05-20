<?php

	/* ****** SETUP VARS ****** */
	error_reporting(0); ini_set('display_errors', '0'); 
	$RTB 	= array();
	$config = array();
	$config['pbs']		= 'http://ib.adnxs.com/openrtb2';
	$config['pbs_port']	= 80;
	$config['apnxs_id']	= '1db9eb10-7c39-48ac-aad6-cc96184f1c8c';
	$config['bidders']	= '
							{
								"appnexus": {
									"video": {
										"skippable": true,
										"playback_method": ["auto_play_sound_off"]
									},
									"use_pmt_rule": false,
									"placement_id": 15884805
								}
							}
						';


	/* ****** SETUP & VALIDATE REQUEST ****** */

	// Obtain POST json content into $RTB array
	$raw_req	= file_get_contents( 'php://input' );
	$RTB 		= json_decode( $raw_req, TRUE );
	// Validate required oRTB values, fail if not found
	if ( !$RTB['id'] || !$RTB['imp'][0]['id'] ) {
		header("HTTP/1.0 500 Bad Request Data");
		log_message("HTTP/1.0 500 Bad Request Data: " . $raw_req, 1, 20);
		exit();
	}


	/* ****** MODIFY RTB OBJECT ****** */

	// Set up PBS Bidders JSON and add each bidder to each imp object
	$bidders = json_decode( $config['bidders'], TRUE );
	foreach( $RTB['imp'] as $count => $imp ) {
		foreach ( $bidders as $biddername => $bidderconfig ) {
			$RTB['imp'][$count]['ext'][$biddername] = $bidderconfig;

			if ( $RTB['imp'][$count]['banner']['wmax'] ) {
				unset( $RTB['imp'][$count]['banner']['wmax'] );		
			}
			if ( $RTB['imp'][$count]['banner']['hmax'] ) {
				unset( $RTB['imp'][$count]['banner']['hmax'] );		
			}
			if ( $RTB['imp'][$count]['banner']['wmin'] ) {
				unset( $RTB['imp'][$count]['banner']['wmin'] );		
			}
			if ( $RTB['imp'][$count]['banner']['hmin'] ) {
				unset( $RTB['imp'][$count]['banner']['hmin'] );		
			}
		}
	}
	// Set the AppNexus PBS account ID, but first backup any existing ID to a new field
	if ( $RTB['app']['id'] ) {
	   $RTB['app']['publisher']['supply_id'] = $RTB['site']['publisher']['id'];
	   $RTB['app']['publisher']['id'] = $config['apnxs_id'];
	} else {
            $RTB['site']['publisher']['supply_id'] = $RTB['site']['publisher']['id'];
	    $RTB['site']['publisher']['id'] = $config['apnxs_id'];
        }
	if ( $RTB['user'] === array() ) {
		unset( $RTB['user'] );
	}

	$RTB['tmax'] = 500;

	/* ****** SEND REQUEST TO PBS ****** */

	$ch = curl_init( $config['pbs'] );
	curl_setopt( $ch, CURLOPT_PORT, $config['pbs_port'] );
	curl_setopt( $ch, CURLOPT_HEADER, FALSE );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array("content-type: application/json") );
	curl_setopt( $ch, CURLOPT_POST, TRUE );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $RTB ) );

	$bid_response 	= curl_exec( $ch );
	$bid_status 	= curl_getinfo( $ch, CURLINFO_RESPONSE_CODE);


	curl_close($ch);


	/* ****** SEND A RESPONSE BASED ON PBS ****** */
	header("Content-Type: application/json");
	switch ( $bid_status ) {
		case 204:
			header("HTTP/1.0 204 EMPTY");
			log_message("HTTP/1.0 204", 0, 1);
			break;
		case 404:
			header("HTTP/1.0 404 Endpoint not found");
			log_message("HTTP/1.0 404", 1);
			break;
		case 400:
			header("HTTP/1.0 400 Invalid Request");
			log_message("HTTP/1.0 400 Invalid Request: " . $raw_req, 1);
			break;
		case 500:
			header("HTTP/1.0 500 Invalid Response");
			log_message("HTTP/1.0 500 Invalid Response: " . $raw_req, 1, 20);
			break;
		case 200:
			// Only output the response if it is a valid bid
			$bid_response_arr = json_decode( $bid_response, TRUE );
			if ( $bid_response_arr['seatbid'] ) {
				print( $bid_response );
				log_message("SUCCESS for : " . $raw_req . " :: " . $bid_response, 0, 10);
			} else {
				header("HTTP/1.0 204 EMPTY");
				log_message("HTTP/1.0 204 B: ", 0, 1);
			}
			break;
		default:
			header("HTTP/1.0 " . $bid_status );
			log_message("HTTP/1.0 " . $bid_status . ": " . $raw_req, 1, 10);
	}


	function log_message( $message, $error = 0, $rate = 10 ) {
		$p = rand(0,99);
		if ( $p < $rate ) {
			if ( $error ) {
				error_log( $message . "\n", 3, "/tmp/error_log.log");
			} else {
				error_log( $message . "\n", 3, "/tmp/message_log.log");
			}
		}
	}


