<?php namespace Comodojo\Gitdoc;

use \Comodojo\Exception\IOException;

class Parser {

	private $final_markdown = '';

	private $index = array();

	final public function implodeChapters($release_path) {

		$summary = file_get_contents($release_path."summary.json");

		if ( $summary === false ) throw new IOException("Summary file not readable");
		
		$summary = json_decode($summary, true);

		foreach ($summary["chapters"] as $name => $file) {
        
			$file = file_get_contents($release_path.$file);

			if ( $file === false ) throw new IOException("Error reading chapter ".$name);

			array_push($this->index, $this->processMarkdownHeaders($name, $file));

			$this->final_markdown .= "\n# ".$name."\n".$file."\r\n";

    	}

    	return $this;

	}

	final public function toHtml() {

		$html = \Parsedown::instance()->text($this->final_markdown);

		$processed_html = $this->processHtml($html);

		return $processed_html;

	}

	final public function toMarkdown() {

		return $this->final_markdown;

	}

	final public function getIndex() {

		return $this->index;

	}

	final public function toPdf() {

		define('DOMPDF_ENABLE_AUTOLOAD', false);

		require_once DISPATCHER_REAL_PATH.'vendor/dompdf/dompdf/dompdf_config.inc.php';

		$dompdf = new \DOMPDF();

		$dompdf->load_html($this->toHtml());

		$dompdf->render();

		return $dompdf->output();

	}

	private function processMarkdownHeaders($name, $text) {

		$return = array(
			"ref"		=>	$this->titleToId($name),
			"name"		=>	$name,
			"paragraphs"=>	array()
		);

		$matches = preg_match_all('/(#+)(.*)/', $text, $out, PREG_SET_ORDER);

		foreach ($out as $match) {
			
			list($line, $dashes, $chars) = $match;

			if ( $dashes == '##' ) {

				$return["paragraphs"][trim($chars)] = $this->titleToId($chars);

			}

		}

		return $return;

	}

	private function titleToId($str) {

		if ( $str !== mb_convert_encoding( mb_convert_encoding($str, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32') ) $str = mb_convert_encoding($str, 'UTF-8', mb_detect_encoding($str));
		
		$str = htmlentities($str, ENT_NOQUOTES, 'UTF-8');
		
		$str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\\1', $str);
		
		$str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
		
		$str = preg_replace(array('`[^a-z0-9]`i','`[-]+`'), '-', $str);
		
		$str = strtolower( trim($str, '-') );
		
		return "#".$str;

	}

	private function processHtml($html) {

		$patterns = array();

		$replaces = array();

		foreach ($this->index as $index) {
			
			array_push($patterns, '<h1>'.$index['name'].'</h1>');

			array_push($replaces, '<div class="block-divider" id="'.substr($index['ref'], 1).'"></div>'."\n".'<h1>'.$index['name'].'</h1>');

			foreach ($index["paragraphs"] as $key => $value) {
				
				array_push($patterns, '<h2>'.$key.'</h2>');

				array_push($replaces, '<div class="block-divider" id="'.substr($value, 1).'"></div>'."\n".'<h2>'.$key.'</h2>');

			}

		}

		return str_replace($patterns, $replaces, $html);

	}

}