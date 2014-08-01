<?php namespace Comodojo\Gitdoc;

class Collector {

	/**
	 * Current time, for unique zip files
	 */
	private $current_time = null;

	// Things related to configuration file (gitdoc.json)

	/**
	 * gitdoc.json main configuration
	 */
	private $configuration = null;

	/**
	 * Project name (from gitdoc.json)
	 */
	private $projectName = null;

	/**
	 * Project description (from gitdoc.json)
	 */
	private $projectDesc = null;

	/**
	 * Project hash (from gitdoc.json)
	 */
	private $projectHash = null;

	// Things related to request (webhook)

	/**
	 * Payload, as received from webhook
	 */
	private $payload = null;

	/**
	 * Event
	 */
	private $event = null;

	// Things derived from payload

	private $vendor = null;

	private $repository = null;

	private $tag = null;

	private $compare = null;

	private $repository_html = null;

	private $senderName = null;

	private $senderAvatar = null;

	private $senderHtml = null;

	// Collector Internals

	private $action = null;

	private $supported_events = Array('push', 'delete', 'create');

	private $git_archive_url_pattern = "https://github.com/__VENDOR__/__REPOSITORY__/archive/__TAG__.zip";

	private $gitdoc_download_path = "__VENDOR__-__PROJECT__-__EVENT__-__TIME__.zip";

	private $gitdoc_extract_path = "__VENDOR__-__PROJECT__-__EVENT__-__TIME__/";

	private $gitdoc_releases_config = "releases.json";

	private $gitdoc_release_path = "__VENDOR__-__PROJECT__-__EVENT__-__TIME__/__REPOSITORY__-__TAG__/";

	final public function __construct() {

		$this->current_time = time();

		$this->gitdoc_download_path		=	DISPATCHER_DOWNLOAD_FOLDER.$this->gitdoc_download_path;
		$this->gitdoc_extract_path		=	DISPATCHER_DOC_FOLDER.$this->gitdoc_extract_path;
		$this->gitdoc_releases_config	=	DISPATCHER_DOC_FOLDER.$this->gitdoc_releases_config;
		$this->gitdoc_release_path		=	DISPATCHER_DOC_FOLDER.$this->gitdoc_release_path;

	}

	final public function setConfiguration($configuration) {

		$this->configuration = $configuration;

	}

	final public function setProject($docId) {

		foreach ($this->configuration['projects'] as $index => $project) {
			
			if ( $project['docId'] == $docId ) {

				$this->projectName = isset($project['name']) ? $project['name'] : $docId;

				$this->projectDesc = isset($project['description']) ? $project['description'] : "";

				$this->projectHash = isset($project['hash']) ? $project['hash'] : null;

				$this->project = $this->projectName;

				return true;

			}

		}

		return false;

	}

	final public function checkConsistence($payload, $payloadHash=null) {

		if ( is_null($payloadHash) OR !isset($this->configuration["projects"][$this->project]["hash"]) ) return true;

		else return hash_hmac('sha1', $payload, $this->configuration["projects"][$this->project]["hash"]) == $payloadHash;

	}
	
	final public function setEvent($event) {

		if ( in_array($event, $this->supported_events) ) {

			$this->event = $event;

			return true;

		}

		else return false;

	}

	final public function setPayload($payload) {

		$this->payload = json_decode( $payload, true );

		list($this->vendor, $this->repository) = explode("/", $this->payload['repository']['full_name']);

		$this->repository_html = $this->payload['repository']['html_url'];

		switch ($this->event) {

			case 'push':

				$this->tag = 'master';
				$this->compare = $this->payload["compare"];
				$this->senderName = $this->payload["pusher"]["name"];

				break;

			case 'delete':
			case 'create':
				$this->tag = $this->payload['ref'];
				$this->senderName['sender']['login'];
				$this->senderAvatar['sender']['avatar_url'];
				$this->senderHtml['sender']['html_url'];
				break;

		}

	}

	final public function processDownload() {

		if ( $this->event == 'delete' ) {

			$this->action = "DELETE_TAG";

			return false;

		}

		else if ( $this->event == 'push' AND $this->payload['ref'] == 'refs/heads/master') {

			$this->action = "PUSH_LIVE";

			return true;

		}

		else if ( $this->event == 'create' AND $this->payload['ref_type'] == 'tag' ) {

			$this->action = "CREATE_TAG";

			return true;

		}

		else return false;

	}

	final public function getDownloadUrl() {

		return str_replace(array("__VENDOR__", "__REPOSITORY__", "__TAG__"), array($this->vendor, $this->repository, $this->tag), $this->git_archive_url_pattern);

	}

	final public function getDownloadPath() {

		return str_replace(array("__VENDOR__", "__PROJECT__", "__EVENT__", "__TIME__"), array($this->vendor, $this->project, $this->event, $this->current_time), $this->gitdoc_download_path);

	}

	final public function getExtractPath() {

		return str_replace(array("__VENDOR__", "__PROJECT__", "__EVENT__", "__TIME__"), array($this->vendor, $this->project, $this->event, $this->current_time), $this->gitdoc_extract_path);

	}

	final public function getReleasePath() {

		return str_replace(array("__VENDOR__", "__PROJECT__", "__EVENT__", "__TIME__", "__REPOSITORY__", "__TAG__"), array($this->vendor, $this->project, $this->event, $this->current_time, $this->repository, $this->tag), $this->gitdoc_release_path);

	}

	final public function updateConfiguration() {

		$configuration = $this->openReleasesConfiguration($this->gitdoc_releases_config);

		switch ($this->action) {
			
			case 'DELETE_TAG':
				$configuration = $this->deleteTag($configuration, $this->tag);
				break;

			case 'PUSH_LIVE':
				$configuration = $this->pushLive($configuration);
				break;

			case 'CREATE_TAG':
				$configuration = $this->createTag($configuration, $this->tag);
				break;

		}

		$configuration = $this->updateReleasesConfigurationInternals($configuration, $this->configuration);

		$this->closeReleasesConfiguration($configuration, $this->gitdoc_releases_config);

	}

	private function openReleasesConfiguration($configuration_file) {

		if ( file_exists( $configuration_file ) ) {

			$configuration = json_decode(file_get_contents($configuration_file), true);

			if ( !array_key_exists($this->project, $configuration["projects"])) {

				$configuration["projects"][$this->project] = array(
					"html_url"	=>	$this->repository_html,
					"live"		=>	array(),
					"latest"	=>	array(),
					"archive"	=>	array()
				);

			}

		} else {

			$configuration = array(
				"sitename" => null,
				"description" => null,
				"showFooter" => null,
				"footerText" => null,
				"showForkMessage" => null,
				"links" => null,
				"projects" => array(
					$this->project => array(
						"html_url"	=>	$this->repository_html,
						"live"		=>	array(),
						"latest"	=>	array(),
						"archive"	=>	array()
					)
				)
			);

		}

		return $configuration;

	}

	private function closeReleasesConfiguration($configuration, $configuration_file) {

		return file_put_contents($configuration_file, json_encode($configuration));

	}

	private function updateReleasesConfigurationInternals($configuration, $gitdocConfiguration) {

		$configuration["sitename"] = isset($gitdocConfiguration["sitename"]) ? $gitdocConfiguration["sitename"] : "gitdoc";
		$configuration["description"] = isset($gitdocConfiguration["description"]) ? $gitdocConfiguration["description"] : "";
		$configuration["showFooter"] = isset($gitdocConfiguration["showFooter"]) ? filter_var($gitdocConfiguration["showFooter"], FILTER_VALIDATE_BOOLEAN) : false;
		$configuration["footerText"] = isset($gitdocConfiguration["footerText"]) ? $gitdocConfiguration["footerText"] : "";
		$configuration["showForkMessage"] = isset($gitdocConfiguration["showForkMessage"]) ? filter_var($gitdocConfiguration["showForkMessage"], FILTER_VALIDATE_BOOLEAN) : false;
		$configuration["links"] = isset($gitdocConfiguration["links"]) ? $gitdocConfiguration["links"] : array();

		return $configuration;

	}

	private function deleteTag($configuration, $tag) {

		if ( !empty($configuration["projects"][$this->project]["latest"]) AND @$configuration["projects"][$this->project]["latest"]["version"] == $tag ) {

			$archives = $configuration["projects"][$this->project]["archive"];

			$previous = end($archives);

			$key = key($archives);

			$previous["version"] = $key;

			$configuration["projects"][$this->project]["latest"] = $previous;

			unset($archives[$key]);

			$configuration["projects"][$this->project]["archive"] = $archives;

		}
		else if ( array_key_exists($tag, $configuration["projects"][$this->project]["archive"]) ) {

			unset($configuration["projects"][$this->project]["archive"][$tag]);

		}

		return $configuration;

	}

	private function createTag($configuration, $tag) {

		$previous_tag = $configuration["projects"][$this->project]["latest"];

		if ( !empty($previous_tag) ) {

			$version = $previous_tag["version"];

			unset($previous_tag["version"]);

			$configuration["projects"]["archive"][$version] = $previous_tag;

		}

		$configuration["projects"][$this->project]["latest"] = array(
			"version"		=>	$tag,
			"path"			=>	$this->getReleasePath(),
			"time"			=>	$this->current_time,
			"senderName"	=>	$this->senderName,
			"senderAvatar"	=>	$this->senderAvatar,
			"senderHtml"	=>	$this->senderHtml
		);

		return $configuration;

	}

	private function pushLive($configuration) {

		$configuration["projects"][$this->project]["live"] = array(
			"path"			=> $this->getReleasePath(),
			"time"			=> $this->current_time,
			"senderName"	=> $this->senderName,
			"compare"		=> $this->compare
		);

		return $configuration;

	}

}
