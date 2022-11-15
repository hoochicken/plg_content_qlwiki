# plg_content_qlwiki

plg_content_qlwiki is a so-called content plugin for the CMS Joomla!. 

## What about a coffee ..

I love coding. My extensions are for free. Wanna say thanks? You're welcome! 
<https://www.buymeacoffee.com/mareikeRiegel>

## Usage 

Add tag {qlwiki'} into article text. Via url parameter you can connect to a wiki; e. g.: {qlwiki url=http://de.wikipedia.org/wiki/Joomla}.

If you use a local, private wiki; enter the url in {qlwiki'} tag or simply use the default settings of plugin in backend. (Of course without '; if I used ' here, iut wouldn't display the tags but the wiki entry;-)

## Examples

* some wiki (that has entry 'Joomla') set in params :{qlwiki title="Joomla"}
* Wikipedia: `{qlwiki url="https://de.wikipedia.org/wiki/Joomla"}`
* Wikipedia: `{qlwiki url="https://en.wikipedia.org/wiki/Computer_network" edit="0" action="render"}`
* `{qlwiki url="https://en.wikipedia.org/wiki/Computer_network" edit="0"}`


## Settings

| param | value |
| --- | --- |
url | e. g.https://de.wikipedia.org/wiki/Joomla for wikipedia | or for a local wiki as well like https://wiki.local?title=Joomla or http://wiki.local/Joomla
action | e. g. 'render', I can't imagine a case when to set action not to render; but just in case you need it set
title | for local wiki to get entry intended; ! replace blank space ' ' by underscore '_' !
login | set yes for your local, private wiki if locked behind a login
user | e. g. 'wikiuser', for local, private wiki locked behind a login
password | e. g. 'yourPassword$!??', for local, private wiki locked behind a login
useragent | string
edit | e. g. 0 = never; 1 = always; 2 = only Super Administrators
cut | 0 für nicht beschneide; 1-10000 etc für auf angegebene Zahl an Zeichen zuschneiden
to | 0 all; 1 Show introtext (no directory); 2 Show introtext AND directory; 3Show only first image
hideImages | 0 no; 1 yes

## Extracts from article and wiki API

To show only extracts of a wiki article, you have to use the wiki api - and that makes things sophisticated thanks to the params. I decided just to give you some examples:

Pluging params might name the wiki api, then you font have to mention it on everr single tag:

* url = https://en.wikipedia.org/w/api.php

Tag within article

* `{qlwiki query="action=parse&section=0&prosp=text&page=termoli&format=json&formatversion=2"}`
* Important is the "section=0" stuff; herer you can choose which section you would like to display; it starts counting on '0', so if you want to display the 3rd section it would be section=2.

You can override (for another api) in every call - they produce the same result.

* `{qlwiki  url="https://fr.wikipedia.org/w/api.php?action=query&prop=extracts&format=json&exintro=&titles=Termoli"}`  
OR
* `{qlwiki  url="https://fr.wikipedia.org/w/api.php" query="action=query&prop=extracts&format=json&exintro=&titles=Termoli"}`

Further examples:

* `{qlwiki url="https://fr.wikipedia.org/w/api.php?action=query&prop=extracts&format=json&exintro=&titles=Joomla!"}`
* `{qlwiki url="https://fr.wikipedia.org/w/api.php" query="action=query&prop=extracts&format=json&exintro=&titles=Joomla!"}`
* `{qlwiki url="https://en.wikipedia.org/w/api.php" query="action=query&prop=extracts&format=json&exintro=&titles=Joomla!"}`
* `{qlwiki url="https://en.wikipedia.org/w/api.php?action=query&prop=extracts&format=json&exintro=&titles=Joomla!&formatversion=2"}`
* `{qlwiki url="https://en.wikipedia.org/w/api.php?action=parse&format=json&formatversion=2&section=0&prop=text&page=Joomla!"}`

I am not yet happy with the sophisticated handling, its due to the wiki aps which seems to have somehow evolved/grown into what it is now, without a real planning. But we can be very happy, that there is one at all:-)

## Trouble shooting

* when connecting via curl, mind to allow curl_exec on server
* mind the protocol on the url: make sure you use https or http according to wiki url
