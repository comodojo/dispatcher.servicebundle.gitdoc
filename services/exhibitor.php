<?php namespace Comodojo\Dispatcher\Service;

use \Comodojo\Dispatcher\Template\TemplateBootstrap;
use \Comodojo\Exception\DispatcherException;
use \Comodojo\Gitdoc\Parser;

class exhibitor extends Service {
    
    private $supported_formats = array('html', 'markdown', 'pdf', 'printable');

    public function setup() {

        $this->expects("GET", Array("project","version"));

        $this->likes("GET", Array("format"));

    }

    public function get() {

        $attributes = $this->getAttributes();

        // load parser

        $parser = new Parser();

        // load configuration file

        $configuration = file_get_contents(DISPATCHER_DOC_FOLDER."releases.json");

        if ( $configuration === false ) throw new DispatcherException("Unable to read releases file", 500);
        
        $configuration = $this->deserialize->fromJson($configuration);

        $available_projects = $configuration["projects"];

        list($projects, $versions) = $this->getProjectsAndVersions($available_projects);

        $project = $attributes["project"];

        $version = $attributes["version"];

        if ( isset($attributes['format']) ) {

            $format = strtolower($attributes['format']);

            $format = in_array($format, $this->supported_formats) ? $format : "html";

        } else {

            $format = "html";

        }

        $project_data = $available_projects[$project];

        // parse and return

        switch ($format) {
            
            case 'markdown':

                $this->setContentType("text/x-markdown");

                $return = $this->toMarkdown($parser, $configuration, $project, $version, $project_data);

                break;

            case 'pdf':

                $this->setContentType("application/pdf");
                
                $return = $this->toPdf($parser, $configuration, $project, $version, $project_data);

                break;

            case 'printable':

                $this->setContentType("text/html");
                
                $return = $this->toPrintable($parser, $configuration, $project, $version, $project_data);

                break;
            
            case 'html':
            default:
                
                $this->setContentType("text/html");

                $return = $this->toHtml($parser, $configuration, $project, $version, $project_data, $projects, $versions);

                break;
        }

        return $return;

    }

    private function getProjectsAndVersions($available_projects) {

        $projects = array();

        $versions = array();

        foreach ($available_projects as $project => $data) {
            
            array_push($projects, $project);

            $versions[$project] = array();

            if ( empty($data['latest']) ) {

                array_push($versions[$project], 'live');

            }
            else {

                array_push($versions[$project], 'live');

                array_push($versions[$project], $data['latest']['version']);

            }

            foreach ($data['archive'] as $archivedProjectVersion => $archivedProjectData) {
                
                array_push($versions[$project], $archivedProjectVersion);

            }

        }

        return array($projects, $versions);

    }

    private function toHtml($parser, $configuration, $project, $version, $project_data, $projects, $versions) {

        $template = new TemplateBootstrap("dash", "default");

        // setup template

        $template->setTitle($configuration['description'])->setBrand($configuration['sitename']);

        $template->addCss(DISPATCHER_BASEURL.'vendor/comodojo/dispatcher.servicebundle.gitdoc/resources/css/gitdoc.css');

        $template->addScript(DISPATCHER_BASEURL.'vendor/comodojo/dispatcher.servicebundle.gitdoc/resources/js/gitdoc.js');

        $template->addMenu("right")->addMenu("left")->addMenu("side");

        foreach ($configuration["links"] as $name => $link) {

            $template->addMenuItem($name, $link, "right");

        }

        foreach ($projects as $pr) {
            
            $menuitems = array();

            foreach ($versions[$pr] as $ver) $menuitems[$ver] = DISPATCHER_BASEURL.$pr.'/'.$ver.'/';

            $template->addMenuItem($pr, '#', "left", $menuitems);

        }

        // go parser

        if ( $version == "live" ) {

            $path = $project_data["live"]["path"];

            $time = $project_data["live"]["time"];

            $senderName = $project_data["live"]["senderName"];

            $senderHtml = null;

            $senderAvatar = null;

            $compare = $project_data["live"]["compare"];

        }

        else if ( $version == $project_data["latest"]["version"] ) {

            $path = $project_data["latest"]["path"];

            $time = $project_data["latest"]["time"];

            $senderName = $project_data["latest"]["senderName"];

            $senderHtml = $project_data["latest"]["senderHtml"];

            $senderAvatar = $project_data["latest"]["senderAvatar"];

            $compare = null;

        }

        else {

            $path = $project_data["archive"][$version]["path"];

            $time = $project_data["archive"][$version]["time"];

            $senderName = $project_data["archive"][$version]["senderName"];

            $senderHtml = $project_data["archive"][$version]["senderHtml"];

            $senderAvatar = $project_data["archive"][$version]["senderAvatar"];

            $compare = null;

        }

        $header = $this->headerBlock($project, $version, $senderName, $time, $senderAvatar, $senderHtml, $compare);

        $footer = $this->footerBlock($project_data['html_url']);

        $content = $parser->implodeChapters($path)->toHtml();

        $index = $parser->getIndex();

        foreach ($index as $chapter) {
            
            $template->addMenuItem($chapter["name"], $chapter["ref"], "side", $chapter["paragraphs"]);

        }

        $template->setContent( ($configuration['showHeader'] ? $header : '') .$content . ($configuration['showFooter'] ? $footer : ''));

        return $template->serialize();


    }

    private function toMarkdown($parser, $configuration, $project, $version, $project_data) {

        if ( $version == "live" ) $path = $project_data["live"]["path"];

        else if ( $version == $project_data["latest"]["version"] ) $path = $project_data["latest"]["path"];

        else $path = $project_data["archive"][$version]["path"];

        $content = $parser->implodeChapters($path)->toMarkdown();

        return $content;

    }

    private function toPdf($parser, $configuration, $project, $version, $project_data) {

        if ( $version == "live" ) $path = $project_data["live"]["path"];

        else if ( $version == $project_data["latest"]["version"] ) $path = $project_data["latest"]["path"];

        else $path = $project_data["archive"][$version]["path"];

        $content = $parser->implodeChapters($path)->toPdf();

        return $content;

    }

    private function toPrintable($parser, $configuration, $project, $version, $project_data) {

        $template = new TemplateBootstrap("basic", "lumen");

        // setup template

        $template->setTitle($configuration['description'])->setBrand($configuration['sitename']);

        // go parser

        if ( $version == "live" ) {

            $path = $project_data["live"]["path"];

            $time = $project_data["live"]["time"];

            $senderName = $project_data["live"]["senderName"];

            $senderHtml = null;

            $senderAvatar = null;

            $compare = $project_data["live"]["compare"];

        }

        else if ( $version == $project_data["latest"]["version"] ) {

            $path = $project_data["latest"]["path"];

            $time = $project_data["latest"]["time"];

            $senderName = $project_data["latest"]["senderName"];

            $senderHtml = $project_data["latest"]["senderHtml"];

            $senderAvatar = $project_data["latest"]["senderAvatar"];

            $compare = null;

        }

        else {

            $path = $project_data["archive"][$version]["path"];

            $time = $project_data["archive"][$version]["time"];

            $senderName = $project_data["archive"][$version]["senderName"];

            $senderHtml = $project_data["archive"][$version]["senderHtml"];

            $senderAvatar = $project_data["archive"][$version]["senderAvatar"];

            $compare = null;

        }

        $content = $parser->implodeChapters($path)->toHtml();

        $template->setContent($content);

        return $template->serialize();

    }

    private function headerBlock($project, $version, $senderName, $time, $senderAvatar=null, $senderHtml=null, $compare=null) {

        $header = '
            <div class="header-box clearfix">
                <div class="square pull-left">
            ';

        if ( is_null($senderAvatar) ) {

            $header .= '<span class="glyphicon glyphicon-user glyphicon-lg"></span>';

        } else {

            $header .= '<img src="'.$senderAvatar.'" alt="" />';

        }

        $header .= '
                </div>
                <h4>Document Informations</h4>
                <p>Last authored on '.date("D M j G:i:s T Y", $time).' by '.(is_null($senderHtml) ? $senderName : '<a href="'.$senderHtml.'" target="_blank">'.$senderName.'</a>').'. ';

        if ( !is_null($compare) ) {

            $header .= 'Compare revision on <a href="'.$compare.'" target="_blank">GitHub</a>.';

        }

        $header .= '<p>
            <a role="button" target="_blank" href="'.DISPATCHER_BASEURL.$project."/".$version.'/printable/" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-print"></span>&nbsp;&nbsp;Printable version</a>&nbsp;&nbsp;
            <a role="button" target="_blank" href="'.DISPATCHER_BASEURL.$project."/".$version.'/markdown/" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-th-list"></span>&nbsp;&nbsp;Markdown Source</a>&nbsp;&nbsp;
            <a role="button" target="_blank" href="'.DISPATCHER_BASEURL.$project."/".$version.'/pdf/" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-file"></span>&nbsp;&nbsp;Download pdf</a>
        </p>';

        $header .= '</p>
            </div>
        ';
        
        if ( $version == "live" ) {

                $header .= "<div class='alert-message alert-message-warning'>
                        <h4>Warning: live docs here!</h4>
                        <p>This page contains a work in progress version of project documentation. If available, select a release version from upper menu.</p>
                </div>";

        }

        return $header;

    }

    private function footerBlock($url) {

        $footer = '
            <div class="block-divider"></div>
            <hr/>
            <div class="block-divider"></div>
            <div class="header-box clearfix">
                <div class="square pull-left">
                    <span class="glyphicon glyphicon-pencil glyphicon-lg"></span>
                </div>
                <h4>Document source available on GitHub</h4>
                <p>Just <a href="'.$url.'" target="_blank">fork and edit it</a> to correct an error or contribute to its writing!</p>
                <p class="text-muted">Except where otherwise noted, this site and its content are licensed under a <a rel="license" href="//creativecommons.org/licenses/by/4.0/" target="_blank">Creative Commons Attribution 4.0 International license</a>.</p>
            </div>';

        return $footer;

    }

}