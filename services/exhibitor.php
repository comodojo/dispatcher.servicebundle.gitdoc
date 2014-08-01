<?php namespace Comodojo\Dispatcher\Service;

use \Comodojo\Dispatcher\Template\TemplateBootstrap;
use \Comodojo\Exception\DispatcherException;
use \Comodojo\Gitdoc\Parser;

class exhibitor extends Service {
    
    public function setup() {

    	$this->expects("GET", Array("project","version"));

        $this->likes("GET", Array("format"));

        $this->setContentType("text/html");

    }

    public function get() {

    	$attributes = $this->getAttributes();

    	// load bootstrap template

        $template = new TemplateBootstrap("dash");

        $parser = new Parser();

        // load configuration file

        $configuration = file_get_contents(DISPATCHER_DOC_FOLDER."releases.json");

        if ( $configuration === false ) throw new DispatcherException("Unable to read releases file", 500);
        
        $configuration = $this->deserialize->fromJson($configuration);

        $projects = $configuration["projects"];

        

        if ( !isset($projects[$attributes["project"]]) ) throw new DispatcherException("Unknown project", 400);

        $project = $projects[$attributes["project"]];

        $template->setTitle($configuration['sitename'])->setBrand($configuration['sitename']);

        foreach ($configuration["links"] as $name => $link) {

            $template->addMenuItem($name, $link, "right");

        }

        $menuitems = array();

        if ( !empty($project['live']) ) array_push($menuitems, array( 'live' => '#live' ));

        if ( !empty($project['latest']) ) array_push($menuitems, array( $project['latest']['version'] => '#'.$project['latest']['version'] ));

        if ( !empty($project['archive']) ) foreach ($$project['archive'] as $version => $properties) array_push($menuitems, array( $version => '#'.$version ));

        $template->addDropdown($project, $menuitems);

        if ( $attributes["version"] == "live" ) $template->setContent($parser->implodeChapters($project["live"]["path"])->toHtml());

        else if ( $attributes["version"] == $project["latest"]["version"] ) $template->setContent($parser->implodeChapters($project["latest"]["path"])->toHtml());

        else if ( in_array($attributes["version"], $project["archive"]) ) $template->setContent($parser->implodeChapters($project["archive"][$attributes["version"]]["path"])->toHtml());

        else throw new DispatcherException("Unknown version", 400);

        return $template->serialize();

    }

}






    // public function get() {

    //     $template = new TemplateBootstrap("dash");

    //     $template->setTitle("Comodojo dispatcher API")->setBrand("comodojo/dispatcher/api");

    //     // $http = new Httprequest("https://github.com/comodojo/dispatcher.docs/archive/master.zip");

    //     // $body = $http->setPort(443)->get();

    //     // file_put_contents(DISPATCHER_LOG_FOLDER."file.zip", $body);
        
    //     // $zip = new Zip();

    //     // $zip->open(DISPATCHER_LOG_FOLDER."file.zip")->extract(DISPATCHER_LOG_FOLDER);

    //     $Parsedown = new \Parsedown();

    //     $summary = json_decode(file_get_contents(DISPATCHER_LOG_FOLDER."dispatcher.docs-master/summary.json"), true);

    //     $return = "<h1>".$summary["title"]."</h1><h2>".$summary['subtitle']."</h2>";

    //     foreach ($summary["chapters"] as $name => $file) {
            
    //         $return .= "<h1>".$name."</h1>".$Parsedown->text(file_get_contents(DISPATCHER_LOG_FOLDER."dispatcher.docs-master/".$file));

    //     }

    //     $template->setContent($return);

    //     return $template->serialize();
    
    // }

/*

Configuration file example:

{
	"sitename": ""
	"description": ""
	"showFooter": ""
	"footerText": ""
	"showForkMessage": ""
	"links": ""
	"projects": {
		"my_project": {
			"html_url": "html_url",
			"live": {
				"path": "/my/docpath/live/",
				"time": 122334143234,
				"senderName": "ciccio",
				"compare": "jfjfjff"
			},
			"latest": {
				"1.0.1": {
					"path": "/my/docpath/version1/",
					"time": 122334143234,
					"senderName": "ciccio",
					"senderAvatar": "https://avatars.githubusercontent.com/u/6752317?",
					"senderHtml": "https://github.com/baxterthehacker",
				}
			},
			"archive": {
				"0.1.0": {
					"path": "/my/docpath/arciveversion-01/",
					"time": 2342142341
				},
				"1.0.0": {
					"path": "/my/docpath/arciveversion-01/",
					"time": 2342142341
				}
			}
		}	
	}
}
*/