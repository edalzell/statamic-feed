<?php

namespace Statamic\Addons\Feed;

use SimpleXMLElement;
use Statamic\API\Arr;
use Statamic\API\URL;
use Statamic\API\Data;
use Statamic\API\Parse;
use Statamic\API\Config;
use Statamic\View\Modify;
use Statamic\API\Collection;
use Statamic\Extend\Controller;
use Statamic\Data\Entries\Entry;

class FeedController extends Controller
{
    /** @var string */
    private $author_field;

    /** @var \Statamic\Data\Entries\EntryCollection */
    private $entries;

    /** @var string */
    private $site_url;

    /** @var string */
    private $feed_url;

    public function init() {
        $this->author_field = $this->getConfig('author_field', 'author');
        $this->site_url = URL::makeAbsolute(Config::getSiteUrl());
        $this->feed_url = request()->fullUrl();

        $this->entries = Collection::whereHandle('blog')->entries()->limit(20);
    }

    public function json()
    {
        return [
            'version' => 'https://jsonfeed.org/version/1',
            'title' => $this->getConfig('json_title', 'JSON Feed'),
            'home_page_url' => $this->site_url,
            'feed_url' => $this->feed_url,
            'items' => $this->getItems()
        ];
    }

    public function atom() {

        $atom = new SimpleXMLElement('<feed xmlns="http://www.w3.org/2005/Atom"></feed>');
        $atom->addAttribute( 'xmlns:xml:lang', 'en');
        $atom->addAttribute( 'xmlns:xml:base', $this->site_url);

        $atom->addChild('id', $this->feed_url);
        $atom->addChild('title', htmlspecialchars($this->getConfig('atom_title', 'Atom Feed')));
        $atom->addChild('updated', $this->entries->first()->date()->toRfc3339String());

        $link = $atom->addChild('link');
        $link->addAttribute('rel', 'self');
        $link->addAttribute('href', $this->feed_url);
        $link->addAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        collect($this->getConfig('discovery', []))->each(function($url, $key) use ($atom) {
            $link = $atom->addChild('link');
            $link->addAttribute('rel', 'hub');
            $link->addAttribute('href', '//' . $url);
            $link->addAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        });

        $this->entries->each(function ($entry, $key) use ($atom) {
            $entryXml = $atom->addChild('entry');

            $entryXml->addChild('id', 'urn:uuid:' . $entry->id());
            $entryXml->addChild('title', htmlspecialchars(Modify::value($entry->get('title'))->cdata()));
            $entryXml->addChild('link')->addAttribute('href', $entry->absoluteUrl());
            $entryXml->addChild('updated', $entry->date()->toRfc3339String());
            $entryXml->addChild('summary', htmlspecialchars(Modify::value($this->getContent($entry))->fullUrls()->cdata()))
                ->addAttribute('type', 'html');

            if ($entry->has($this->author_field)) {
                $entryXml->addChild('author')
                    ->addChild('name', $this->makeName($entry->get($this->author_field)));
            }
        });

        return response($atom->asXML(), 200, ['Content-Type' => 'application/atom+xml']);
    }

    private function getItems()
    {
        return $this->entries->map(function ($entry) {
            $item = [
                'id' => $entry->id(),
                'title' => $entry->get('title'),
                'url' => $entry->absoluteUrl(),
                'date_published' => $entry->date()->toRfc3339String(),
                'content_html' => (string)Modify::value($this->getContent($entry))->fullUrls()
            ];

            if ($entry->has('author')) {
                $item['author'] = ['name' => $this->makeName($entry->get('author'))];
            }

            if ($entry->has('link')) {
                $item['external_url'] = $entry->get('link');
            }

            return $item;
        })->values()->all();
    }

    private function getContent(Entry $entry)
    {
        if ($this->getConfigBool('custom_content', false)) {
            $content = Parse::template($this->getConfig('content'), $entry->data());
        } else {
            $content = $entry->parseContent();
        }

        return $content;
    }

    private function makeName($id) {
        $name = '';

        if ($author = Data::find($id)) {
            $name_fields = $this->getConfig('name_fields');
            $name = implode(' ',
                            array_merge(array_flip($name_fields),
                                        Arr::only($author->data(), $name_fields)));
        }

        return $name;
    }
}
