<?php namespace Comodojo\Dispatcher\Service;

use Comodojo\Exception\DispatcherException;
use Comodojo\Exception\HttpException;
use Comodojo\Exception\ZipException;

use Comodojo\Httprequest\Httprequest;
use Comodojo\Gitdoc\Collector;
use Comodojo\Zip\Zip;

class receiver extends Service {
    
    public function setup() {

        $this->expects('POST', Array('docId'), Array('payload'));

    }

    public function post() {

        // Init resources

        $collector = new Collector();

        $zip = new Zip();

        $cache = $this->getCacher();

        // Acquire attributes, parameters and logger

        $attributes = $this->getAttributes();

        $parameters = $this->getParameters();

        $logger = $this->getLogger();

        // get info from request

        $docId = $attributes['docId'];

        $payload = $parameters['payload'];

        $payloadHash = $this->getRequestHeader('X-Hub-Signature');
        
        $event = $this->getRequestHeader('X-Github-Event');

        // $logger->debug($event);

        // $logger->debug($payload);        

        // get main configuration

        $config = file_get_contents(DISPATCHER_REAL_PATH.'configs/gitdoc.json');

        if ( $config === false ) throw new DispatcherException("Invalid configuration file", 500);
        
        $config = $this->deserialize->fromJson($config);

        // start populating collector

        $collector->setConfiguration($config);
        
        if ( !$collector->setProject($docId) ) throw new DispatcherException("Unknown project", 400);

        if ( !$collector->checkConsistence($payload, $payloadHash) ) throw new DispatcherException("Wrong payload signature", 400);

        if ( !$collector->setEvent($event) ) throw new DispatcherException("Event not supported", 400);

        $collector->setPayload($payload);

        try {

            if ( $collector->processDownload() ) {

                // $logger->debug($collector->getDownloadUrl());

                $http = new Httprequest( $collector->getDownloadUrl(), false );

                $zipball = $http->get();

                $download = file_put_contents( $collector->getDownloadPath(), $zipball );

                if ( $download === false ) throw new DispatcherException("Download folder not writeable", 500);

                $zip->setMask(0777)->open($collector->getDownloadPath())->extract($collector->getExtractPath());

                unlink($collector->getDownloadPath());

            }
            
            $collector->updateConfiguration();

            $cache->purge();

        } catch (HttpException $he) {

            throw new DispatcherException("Unable to get repository zipball", 500);
            
        } catch (ZipException $ze) {

            // $logger->error($ze->getMessage());

            throw new DispatcherException("Unable to unzip repository zipball", 500);
            
        } catch (DispatcherException $de) {

            throw $de;
            
        } catch (Exception $e) {

            throw $e;
            
        }

        return 'OK';
    
    }

}