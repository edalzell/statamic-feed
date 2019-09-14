<?php

namespace Statamic\Addons\Feed;

use SimpleXMLElement;
use Statamic\API\Arr;
use Statamic\API\URL;
use Statamic\API\Data;
use Statamic\API\File;
use Statamic\API\Parse;
use Statamic\API\Config;
use Statamic\View\Modify;
use Illuminate\Http\Request;
use Statamic\Extend\Controller;
use Statamic\Data\Entries\Entry;
use Statamic\API\Entry as EntryAPI;
use Statamic\View\Antlers\Template;

class FeedController extends Controller
{
    /** @var array */
    private $feedConfig;

    /** @var string */
    private $title;

    /** @var string */
    private $feed_url;

    /** @var string */
    private $site_url;

    /** @var array */
    private $name_fields;

    /** @var string */
    private $author_field;

    /** @var string */
    private $custom_content;

    /** @var string */
    private $partial;

    /** @var \Statamic\Data\Entries\EntryCollection */
    private $entries;

    public function __construct(Request $request)
    {
        $config = collect($this->getConfig('feeds', []))->first(function ($key, $feed) use ($request) {
            return $feed['route'] == $request->getPathInfo();
        });

        $this->title = array_get($config, 'title');
        $this->name_fields = array_get($config, 'name_fields', []);
        $this->author_field = array_get($config, 'author_field');
        $this->custom_content = array_get($config, 'custom_content', false);
        $this->partial = array_get($config, 'partial');
        $this->site_url = URL::makeAbsolute(Config::getSiteUrl());
        $this->feed_url = $request->fullUrl();
        $this->entries = EntryAPI::whereCollection(array_get($config, 'collections', []))
            ->removeUnpublished()
            ->removeFuture()
            ->multisort('date:desc|title:asc')
            ->limit(20);
    }

    public function json()
    {
        return [
            'version' => 'https://jsonfeed.org/version/1',
            'title' => $this->title,
            'home_page_url' => $this->site_url,
            'feed_url' => $this->feed_url,
            'items' => $this->getItems(),
        ];
    }

    public function atom()
    {
        $atom = new SimpleXMLElement('<feed xmlns="http://www.w3.org/2005/Atom"></feed>');
        $atom->addAttribute('xmlns:xml:lang', 'en');
        $atom->addAttribute('xmlns:xml:base', $this->site_url);

        $atom->addChild('id', $this->feed_url);
        $atom->addChild('title', htmlspecialchars($this->title));

        if ($this->entries->count()) {
            $atom->addChild('updated', $this->entries->first()->date()->toRfc3339String());
        }

        $link = $atom->addChild('link');
        $link->addAttribute('rel', 'self');
        $link->addAttribute('href', $this->feed_url);
        $link->addAttribute('xmlns', 'http://www.w3.org/2005/Atom');

        if ($author = $this->getConfig('feed_author')) {
            $atom->addChild('author')->addChild('name', $author);
        }

        collect($this->getConfig('discovery', []))->each(function ($url, $key) use ($atom) {
            $link = $atom->addChild('link');
            $link->addAttribute('rel', 'hub');
            $link->addAttribute('href', '//' . $url);
            $link->addAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        });

        $this->entries->each(function ($entry, $key) use ($atom) {
            $entryXml = $atom->addChild('entry');

            $entryXml->addChild('id', 'urn:uuid:' . $entry->id());
            $entryXml->addChild('title', htmlspecialchars($entry->get('title')));
            if ($this->author_field) {
                $entryXml->addChild('author')
                ->addChild('name', $this->makeName($entry->get($this->author_field)));
            }
            $entryXml->addChild('link')->addAttribute('href', $entry->absoluteUrl());
            $entryXml->addChild('updated', $entry->date()->toRfc3339String());
            if ($this->getContent($entry)) {
                $entryXml->addChild('content', htmlspecialchars(Modify::value($this->getContent($entry))->fullUrls()))
                    ->addAttribute('type', 'html');
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
                'content_html' => (string) Modify::value($this->getContent($entry))->fullUrls(),
            ];

            if ($entry->has($this->author_field)) {
                $item['author'] = ['name' => $this->makeName($entry->get($this->author_field))];
            }

            if ($entry->has('link')) {
                $item['external_url'] = $entry->get('link');
            }

            return $item;
        })->values()->all();
    }

    private function getContent(Entry $entry)
    {
        if ($this->custom_content) {
            return Parse::template(File::disk('theme')->get("partials/{$this->partial}.html"), $entry->toArray());
        } else {
            return $entry->parseContent();
        }
    }

    private function makeName($id)
    {
        $name = 'Anonymous';

        if ($author = Data::find($id)) {
            $this->name_fields;
            $name = implode(
                ' ',
                array_merge(
                    array_flip($this->name_fields),
                    Arr::only($author->data(), $this->name_fields)
                )
            );
        }

        return $name;
    }
}
