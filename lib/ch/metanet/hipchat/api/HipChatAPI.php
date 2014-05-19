<?php

namespace ch\metanet\hipchat\api;

/**
 * Wrapper to access REST API of HipChat
 * @author Pascal Muenst <entwicklung@metanet.ch>
 * @copyright Copyright (c) 2014, METANET AG
 */
class HipChatAPI {
	const DEFAULT_TARGET = 'https://api.hipchat.com';
	const API_VERSION_2 = 'v2';

	const BACKGROUND_COLOR_YELLOW = 'yellow';
	const BACKGROUND_COLOR_RED = 'red';
	const BACKGROUND_COLOR_GREEN = 'green';
	const BACKGROUND_COLOR_PURPLE = 'purple';
	const BACKGROUND_COLOR_GRAY = 'gray';
	const BACKGROUND_COLOR_RANDOM = 'random';

	const MESSAGE_FORMAT_TEXT = 'text';
	const MESSAGE_FORMAT_HTML = 'html';

	const EMOTICONS_TYPE_ALL = 'all';
	const EMOTICONS_TYPE_GLOBAL = 'global';
	const EMOTICONS_TYPE_GROUP = 'group';

	protected $token;
	protected $target;
	protected $apiVersion;

	protected $sslVerifyPeer = true;

	/**
	 * @param string $token The access token
	 * @param string $target URL to where the HipChat API is located
	 * @param string $apiVersion Version of the HipChat API that should be used
	 */
	public function __construct($token, $target = self::DEFAULT_TARGET, $apiVersion = self::API_VERSION_2) {
		$this->token = $token;
		$this->target = $target;
		$this->apiVersion = $apiVersion;
	}

	/**
	 * List non-archived rooms for this group.
	 * @param int $startIndex The start index for the result set.
	 * @param int $maxResults The maximum number of results.
	 * @param bool $inclideArchived Include archived rooms too
	 * @return \stdClass
	 */
	public function getAllRooms($startIndex = 0, $maxResults = 100, $inclideArchived = false) {
		return $this->doRequest('room?start-index=' . $startIndex . '&max-results=' . $maxResults . '&include-archived=' . var_export($inclideArchived, true));
	}

	/**
	 * Get room details.
	 * @param string $roomIdOrName The id or name of the room.
	 * @return \stdClass
	 */
	public function getRoom($roomIdOrName) {
		return $this->doRequest('room/' . $roomIdOrName);
	}

	public function updateRoom() {
		// TODO implement
	}

	/**
	 * Fetch chat history for a room.
	 * @param string $roomIdOrName The id or name of the room.
	 * @param string $date Either the latest date to fetch history for in ISO-8601 format, or 'recent' to fetch the
	 * latest 75 messages. Note, paging isn't supported for 'recent', however they are real-time values, whereas date
	 * queries may not include the most recent messages.
	 * @param string $timezone Your timezone. Must be a supported timezone.
	 * @param int $startIndex The offset for the messages to return. Only valid with a non-recent data query.
	 * @param int $maxResults The maximum number of messages to return. Only valid with a non-recent data query.
	 * @param bool $reverse Reverse the output such that the oldest message is first. For consistent paging, set to 'false'.
	 * @return \stdClass
	 */
	public function getRoomHistory($roomIdOrName, $date = 'recent', $timezone = 'UTC', $startIndex = 0, $maxResults = 100, $reverse = true) {
		return $this->doRequest(
			'room/' . $roomIdOrName . '/history?date=' . $date .
			'&timezone=' . $timezone .
			'&start-index=' . $startIndex .
			'&max-results=' . $maxResults .
			'&reverse=' . var_export($reverse, true)
		);
	}

	/**
	 * Send a message to a room.
	 * @param string $roomIdOrName The id or name of the room.
	 * @param string $backgroundColor Background color for message.
	 * @param string $message The message body.
	 * @param bool $notify Whether or not this message should trigger a notification for people in the room
	 * (change the tab color, play a sound, etc). Each recipient's notification preferences are taken into account.
	 * @param string $format Determines how the message is treated by our server and rendered inside HipChat applications.
	 */
	public function sendRoomNotification($roomIdOrName, $message, $backgroundColor = self::BACKGROUND_COLOR_YELLOW, $notify = false, $format = self::MESSAGE_FORMAT_TEXT) {
		$jsonBody = new \stdClass();

		$jsonBody->id_or_name = $roomIdOrName;
		$jsonBody->color = $backgroundColor;
		$jsonBody->message = $message;
		$jsonBody->notfiy = $notify;
		$jsonBody->format = $format;

		$this->doRequest('room/' . $roomIdOrName . '/notification', json_encode($jsonBody));
	}

	/**
	 * Gets all emoticons for the current group
	 * @param int $startIndex The start index for the result set.
	 * @param int $maxResults The maximum number of results.
	 * @param string $type The type of emoticons to get.
	 * @return \stdClass List of emoticons
	 */
	public function getAllEmoticons($startIndex = 0, $maxResults = 100, $type = self::EMOTICONS_TYPE_ALL) {
		return $this->doRequest(
			'emoticon?start-index=' . $startIndex .
			'&max-results=' . $maxResults .
			'&type=' . $type
		);
	}

	/**
	 * Get emoticon details.
	 * @param string $emoticonIdOrKey The id or shortcut of the emoticon.
	 * @return \stdClass The emoticon object
	 */
	public function getEmoticon($emoticonIdOrKey) {
		return $this->doRequest('emoticon/' . $emoticonIdOrKey);
	}

	/**
	 * @param $token
	 * @param $addOnIdOrKey
	 * @return null|\stdClass
	 */
	public function getAddOnInstallableData($token, $addOnIdOrKey) {
		return $this->doRequest('addon/' . $addOnIdOrKey . '/installable/' . $token);
	}

	/**
	 * @param string $apiMethodString The URI string for the API request
	 * @param string|null $jsonBody A JSON encoded string with data or null
	 * @return \stdClass|null The JSON object or null
	 * @throws HipChatAPIException
	 */
	protected function doRequest($apiMethodString, $jsonBody = null) {
		$ch = curl_init($this->target . '/' . $this->apiVersion . '/' . $apiMethodString);

		$headers = array(
			'Authorization: Bearer ' . $this->token
		);

		if($jsonBody !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
			$headers[] = 'Content-Type: application/json';
		}

		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => $this->sslVerifyPeer,
			CURLOPT_HTTPHEADER => $headers
		));

		$response = curl_exec($ch);
		$curlError = curl_errno($ch);

		if($curlError != 0)
			throw new HipChatAPIException('cURL error: ' . curl_error($ch), $curlError);

		//$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if(strlen($response) === 0)
			return null;

		$json = json_decode($response);

		if(isset($json->error) === true)
			throw new HipChatAPIException(strip_tags($json->error->message), $json->error->code);

		return $json;
	}

	/**
	 * Change the API token
	 * @param string $token The new token
	 */
	public function changeToken($token) {
		$this->token = $token;
	}

	/**
	 * Deactivate verification of the SSL peer by cURL
	 * @param bool $sslVerifyPeer
	 */
	public function setSSLVerifyPeer($sslVerifyPeer) {
		$this->sslVerifyPeer = $sslVerifyPeer;
	}
}

/* EOF */