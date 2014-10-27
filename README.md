# Comodojo dispatcher GitDoc services bundle

Gitdoc is a comodojo/dispatcher framework service+plugin bundle used to publish the [comodojo::docs website](http://docs.comodojo.org/).

This plugin is designed to manage different projects' documentation, mantained as markdown files on multiple GitHub repositories.

Build and presentation processes are triggered via GitHub push/tag events; on each (supported) event message, whole docs archive is downloaded from GitHub and parsed to compose static html.

## Installation

1. Install [comodojo/dispatcher via composer](http://dispatcher.comodojo.org/#install). 

2. Require comodojo/dispatcher.servicebundle.gitdoc package:

    commposer require comodojo/dispatcher.servicebundle.gitdoc dev-master

3. Ensure that `downloads` and `docs` folders inside dispatcher project are readable/writeable by apache user and add following two lines to `dispatcher-config.php`:

    define('DISPATCHER_DOWNLOAD_FOLDER', DISPATCHER_REAL_PATH."downloads/");
    define('DISPATCHER_DOC_FOLDER', DISPATCHER_REAL_PATH."docs/");

4. Create `gitdoc.json` file under `config` directory; file content should follow this schema:

        {
            "sitename": "Foo docs",
            "description": "Foo Documentation",
            "showHeader": true,
            "showFooter": true,
            "links": {
                "External link": "http://www.example.org"
            },
            "projects": [
                {
                    "name": "bar",
                    "description": "the bar project",
                    "docId": "bar",
                    "hash": "myReallySecureGitHubWebhookHash"
                }
            ]
        }

* showHeader and showFooter are boolean values: if true, header and footer information sections will be displayed
* each link in links object will add an external href to the top/right menu item
* each project in project object will declare a new documentation section:
    * name: docsite name
    * description
    * docId (see next section to understand how to use it)
    * hash (optional): secret hook hash

5. Setup a WebHook on GitHub repository:

    * Payload URL: `http://your.documentation.site/receiver/[docId - as in previous section]/`
    * Content type: `application/x-www-form-urlencoded`
    * Secret: `hash` parameter as in previous section
    * Events: select *Create*, *Delete*, *Push*

Your docsite is now ready to receive updates.

## Repository summary

Repository should contain markdown files, one for each chapter, plus one `summary.json` file like this:

    {
        "title": "Foo docs",
        "subtitle": "My fantastic project",
        "chapters": {
            "First chapter": "first.md",
            "Second chapter": "second.md",
            "Foo chapter": "foo.md",
            "Conclusions": "foo.conclusions.md"
        }
    }

In `chapters` object, keys will become chapters name added directly in html.

h1 (#) and h2 (##) headings will be linked to left-sidebar srollspy.

## Usage

Just navigate to `http://your.documentation.site/docId/` to show your documentation.

Dispatcher will inject routes automatically, one for each project that received at least 1 update.

Because of this behaviour, it is strongly suggested to not add other rules/services into dispatcher instance used to publish docs.