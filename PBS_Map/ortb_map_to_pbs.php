<?php

	/* ****** SETUP VARS ****** */
	error_reporting(0); ini_set('display_errors', '0'); 
	$RTB 	= array();
	$congig = array();
	$config['pbs'] 		= 'http://silvermine.io/openrtb2/auction';
	$config['pbs_port'] = 8000;
	$congig['apnxs_id']	= '1db9eb10-7c39-48ac-aad6-cc96184f1c8c';
	$congig['bidders'] 	= '
							{
								"appnexus": {
									"video": {
										"skippable": true,
										"playback_method": ["auto_play_sound_off"]
									},
									"use_pmt_rule": false,
									"placement_id": 13232361
								}
							}
						';


	/* ****** SETUP & VALIDATE REQUEST ****** */

	// Obtain POST json content into $RTB array
	$raw_req 	= file_get_contents( 'php://input' );
	$RTB 		= json_decode( $raw_req, TRUE );
	// Validate required oRTB values, fail if not found
	if ( !$RTB['id'] || !$RTB['imp'][0]['id'] ) {
		header("HTTP/1.0 500 Invalid Request");
		exit();
	}


	/* ****** MODIFY RTB OBJECT ****** */

	// Set up PBS Bidders JSON and add each bidder to each imp object
	$bidders = json_decode( $congig['bidders'], TRUE );
	foreach( $RTB['imp'] as $count => $imp ) {
		foreach ( $bidders as $biddername => $bidderconfig ) {
			$RTB['imp'][$count]['ext'][$biddername] = $bidderconfig;
		}
	}
	// Set the AppNexus PBS account ID, but first backup any existing ID to a new field
	$RTB['site']['publisher']['supply_id'] = $RTB['site']['publisher']['id'];
	$RTB['site']['publisher']['id'] = $congig['apnxs_id'];



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
			break;
		case 404:
			header("HTTP/1.0 404 Endpoint not found");
			break;
		case 400:
			header("HTTP/1.0 400 Invalid Request");
			break;
		case 500:
			header("HTTP/1.0 500 Invalid Response");
			break;
		case 200:
			// Only output the response if it is a valid bid
			$bid_response_arr = json_decode( $bid_response, TRUE );
			if ( $bid_response_arr['seatbid'] ) {
				print( $bid_response );
			} else {
				header("HTTP/1.0 204 EMPTY");
			}
			break;
		default:
			header("HTTP/1.0 " . $bid_status );
	}
	exit();

