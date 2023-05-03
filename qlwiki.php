<?php
/**
 * @package        plg_content_qlwiki
 * @copyright    Copyright (C) 2022 ql.de All rights reserved.
 * @author        Ingo Holewczuk info@ql.de; Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
defined('_JEXEC') or die ('Restricted Access');

jimport('joomla.plugin.plugin');

class plgContentQlwiki extends JPlugin
{

    const TEMPLATE_CONTENT = '<div class="qlwiki">%s</div>';
    const TEMPLATE_BUTTON = '<a class="btn" href="%s" target="_blank">%s</a>';
    const READ_MORE = 'read more';

    /** @var string start tag in curled brackets, like {qlwiki url="http://de.wikipedia.org/wiki/Joomla"} */
    protected $str_call_start = 'qlwiki';

    /** @var array attributes that possibly extracted from tag */
    protected $arr_attributes = [
        // basic params
        'url', 'title', 'action', 'serversettings',
        'query',
        'striplinks',
        'login', 'user', 'password', 'user_agent', 'edit',
        'readmoreText',
        // alternations of wiki article
        'articleCut', 'articleStripTags', 'articleTo', 'articleHideImages', 'articleInfoTable', 'articleReadAll',
        'categoryCut', 'categoryStripTags', 'categoryTo', 'categoryHideImages', 'categoryInfoTable', 'categoryReadAll',
        'featuredCut', 'featuredStripTags', 'featuredTo', 'featuredHideImages', 'featuredInfoTable', 'featuredReadAll',
    ];

    /** @var array */
    protected $states = [];

    /** @var */
    public $params;

    /** @var */
    public $error = false;

    /** @var */
    public $viewType;

    /**
     * constructor
     * setting language
     * @param $subject
     * @param $config
     */
    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
        //$input=JFactory::getApplication()->input;$this->viewType=$input->get('view');
    }

    /**
     * onContentPrepare :: some kind of controller of plugin
     * @param $context
     * @param $article
     * @param $params
     * @param int $page
     * @return bool
     * @throws Exception
     */
    public function onContentPrepare(string $context, &$article, &$params, $page = 0)
    {
        // leave if search plugin is working
        if ('com_finder.indexer' === $context) {
            return true;
        }

        // if no tag is found, leave as well
        if (false === strpos($article->text, $this->str_call_start)) {
            return true;
        }

        // check where we are, means in which view the article is used, in category, article, or featured view
        $this->viewType = substr($context, (strpos($context, '.') + 1));
        if ('article' !== $this->viewType && 'category' !== $this->viewType && 'featured' !== $this->viewType) {
            $this->viewType = 'article';
        }

        // finally alter article text
        $article->text = $this->clearTags($article->text);
        $article->text = $this->replaceStartTags($article->text);
    }

    /**
     * method to set states
     */
    private function setDefaultStates(): void
    {
        foreach ($this->arr_attributes as $strParamName) {
            $this->states[$strParamName] = $this->params->get($strParamName, 0);
        }
    }

    /**
     * method to get attributes
     * @param $text
     * @return mixed
     * @throws Exception
     */
    private function replaceStartTags(string $text): string
    {
        // get matches of tag
        $matches = $this->getMatches($text, $this->str_call_start);

        // if no matches found, return right away
        if (0 === count($matches)) {
            return $text;
        }

        // iterate through matches
        foreach ($matches as $match) {
            $this->error = false;

            // set default states, and enrich it with current setting of qlwiki tag
            $this->setDefaultStates();
            $arrAttributes = $this->getAttributes(strip_tags($match[1]));
            $this->writeAttributesToStates($arrAttributes);
            // echo '<pre>';print_r($this->states);die;

            // set user state agent
            if (isset($this->states['user_agent'])) {
                ini_set('user_agent', $this->states['user_agent']);
            }

            // get url and work with it properly
            $url = $this->buildUrl($this->states['url']);

            // get data from wiki, probably via curl
            $output = $this->getWikiData($url);

            // alter wiki content
            $output = $this->workItOutput($output, $url);
            if (1 == $this->states[$this->viewType . 'HideImages']) {
                $output = preg_replace('#<img\s+[^>]*src="([^"]*)"[^>]*>#', '', $output);
            }
            $text = str_replace($match[0], $output, $text);
        }
        return $text;
    }

    private function workItOutput(string $output, string $strUrl): string
    {
        $strResult = $output;

        // strip line breaks (?why)
        $result = str_replace('\n', '', $output);
        if (is_string($result)) {
            $testResult = json_decode($result);
            if (is_object($testResult)) {
                $result = $testResult;
            }
        }

        // if error occured, write error messsage into output
        if (isset($result->error)) {
            $strResult = '<div class="message error alert">';
            $strResult .= '<strong>' . JText::_('PLG_CONTENT_QLWIKI_URL') . '</strong>: ' . htmlentities($strUrl) . '<br />';
            foreach ($result->error as $k => $v) {
                $strResult .= '<strong>' . ucwords($k) . '</strong>: ' . $v . '<br />';
            }
            $strResult .= '</div>';
            $this->error = true;
        }

        // if action is parse, do it
        if (0 < strpos($strUrl, 'action=parse')) {
            if (isset($result->parse) && isset($result->parse->text)){
                $strResult = $result->parse->text;
            }
        }

        // if acrion is query, do it
        if (0 < strpos($strUrl, 'action=query')) {
            $strResult = '';
            if (isset($result->query) && isset($result->query->pages) && (is_object($result->query->pages) || is_array($result->query->pages))) {
                foreach ($result->query->pages as $v) {
                    $strResult .= $v->extract;
                }
            }
        }

        // strip links if wished
        if (1 == $this->states['striplinks'] && is_string($strResult)) {
            $strResult = str_replace('</a>', '', $strResult);
            $strResult = preg_replace('`(?i)<a([^>]+)>`i', '', $strResult);
        } elseif (2 == $this->states['striplinks'] && is_string($strResult)) {
            $arrUrl = parse_url($strUrl);
            $wiki = '';
            if (isset($arrUrl['scheme'])) $wiki .= $arrUrl['scheme'] . '://';
            if (isset($arrUrl['host'])) $wiki .= $arrUrl['host'];
            $strResult = preg_replace('`href="(?!http)`i', 'target="_blank" href="' . $wiki, $strResult);
        }

        $output = $strResult;
        $output = $this->outputEdit($output);
        $output = $this->outputCut($output);
        return $output;
    }

    /**
     * getWikiData
     * @param $url
     * @return bool|false|string
     * @throws Exception
     */
    private function getWikiData(string $url): string
    {
        switch ($this->states['serversettings']) {
            case 'curl' :
                $output = $this->getWikiDataViaCurl($url);
                break;
            case '3rd_way' :
                $output = $this->getWikiDataVia3($url);
                break;
            case '4th_way' :
                $output = $this->getWikiDataVia4($url);
                break;
            case '5th_way' :
                $output = $this->getWikiDataVia5($url);
                break;
            case 'default' :
            default :
                $output = $this->getWikiDataViaDefault($url);
        }
        return $output;
    }

    /**
     * getWikiData defaulty
     * @param $url
     * @return false|string
     * @throws Exception
     */
    private function getWikiDataViaDefault(string $url): string
    {
        if (isset($this->states['user_agent'])) {
            ini_set('user_agent', $this->states['user_agent']);
        }
        if (isset($this->states['login']) && 1 == $this->states['login']) {
            $context = stream_context_create(array('http' => array('header' => "Authorization: Basic " . base64_encode($this->states['user'] . ':' . $this->states['password']))));
            $output = @file_get_contents($url, false, $context);
        } else {
            $output = @file_get_contents($url, false);
        }
        if (!$output) {
            JFactory::getApplication()->enqueueMessage($this->get('_name') . ': ' . sprintf(JText::_('PLG_CONTENT_QLWIKI_ERROROCCURRED'), $this->states['title']));
        }
        return $output;
    }

    /**
     * getWikiData via Curl
     * @param $url
     * @return bool|string
     * @throws Exception
     */
    private function getWikiDataViaCurl($url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (isset($this->states['login']) && 1 == $this->states['login']) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->states['user'] . ':' . $this->states['password']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, $this->states['user_agent']);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $output = curl_exec($ch);
        curl_close($ch);
        if (false === $output) {
            JFactory::getApplication()->enqueueMessage($this->get('_name') . ': ' . sprintf(JText::_('PLG_CONTENT_QLWIKI_ERROROCCURRED'), $this->states['title']));
        }
        return $output;
    }

    /**
     * getWikiData via Curl
     * @param $url
     * @return string
     */
    private function getWikiDataVia3($url): string
    {
        $output = '';
        return $output;
    }

    /**
     * getWikiData via Curl
     * @param $url
     * @return string
     */
    private function getWikiDataVia4($url): string
    {
        $output = '';
        return $output;
    }

    /**
     * getWikiData via Curl
     * @param $url
     * @return string
     */
    private function getWikiDataVia5($url): string
    {
        $output = '';
        return $output;
    }

    /**
     * check if user is logged in
     */
    private function outputEdit(string $output): string
    {
        if ($this->checkIfAllowed()) {
            return $output;
        }
        $output = preg_replace('#<span class="editsection">(.*)</span>#Uis', '\\2', $output);
        $output = preg_replace('#<span class="mw-editsection">(.*)</span>#Uis', '\\2', $output);
        $output = str_replace('">edit</a>', '"></a>', $output);
        $output = str_replace('<span class="mw-editsection-bracket">]</span>', '', $output);
        return $output;
    }

    /**
     * @return bool
     */
    private function checkIfAllowed():bool
    {
        if (!isset($this->states['edit']) || !is_array($this->states['edit'])) {
            return false;
        }
        $user = JFactory::getUser();
        $userGroup = $user->get('groups');
        if (!is_array($userGroup)) {
            return false;
        }
        $checkArray = array_intersect($userGroup, $this->states['edit']);
        if (0 < count($checkArray)) {
            return true;
        }
        return false;
    }

    /**
     * check if user is logged in
     */
    private function outputCut(string $output): string
    {
        $to = (int)$this->states[$this->viewType . 'To'];
        $infoTable = (bool)$this->states[$this->viewType . 'InfoTable'];
        $striptags = (bool)$this->states[$this->viewType . 'StripTags'];
        $cut = (int)$this->states[$this->viewType . 'Cut'] ?? 0;
        $readAll = (bool)$this->states[$this->viewType . 'ReadAll'];

        switch ($to) {
            case 0:
                ;
                break;
            case 1:
                $pos = strpos($output, '<div id="toc"');
                $output = substr($output, 0, $pos);
                break;
            case 2:
                $pos = strpos($output, '<h2><span');
                $output = substr($output, 0, $pos);
                break;
            case 3:
                $pos = strpos($output, '<img');
                $output = substr($output, $pos);
                $pos = strpos($output, '>');
                $output = substr($output, 0, $pos + 1);
                break;
        }
        if (!$infoTable) {
            $output = $this->stripInfotable($output);
        }
        if ($striptags) {
            $output = strip_tags($output);
        }
        if (0 < $cut) {
            $output = substr($output, 0, $cut);
        }

        if ($readAll && false === $this->error) {
            $output .= $this->addBtnReadAll();
        }
        return sprintf(self::TEMPLATE_CONTENT, $output);
    }

    /**
     * check if user is logged in
     */
    private function checkUserLoggedIn(): bool
    {
        $user = JFactory::getUser();
        if ($user->id > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * check if user is logged in
     */
    private function checkUserAdministrator():bool
    {
        $user = JFactory::getUser();
        if (in_array(8, $user->groups)) {
            return true;
        }
        return false;
    }

    /**
     * method to get attributes
     */
    private function replaceEndTags(string $text): ?array
    {
        $matches = $this->getMatches($text, $this->str_call_end);
        if (0 === count($matches)) {
            return $text;
        }
        foreach ($matches as $match) {
            $output = '';
            $text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $text, 1);
        }
        return $text;
    }

    /**
     * method to get attributes
     */
    private function getAttributes(string $string): array
    {
        $selector = implode('|', $this->arr_attributes);
        preg_match_all('~(' . $selector . ')="(.+?)"~s', $string, $matches);
        $arr_attributes = [];
        if (is_array($matches)) {
            foreach ($matches[0] as $k => $v) {
                if (isset($matches[1][$k]) && isset($matches[2][$k])) {
                    $arr_attributes[$matches[1][$k]] = $matches[2][$k];
                    if ('edit' == $matches[1][$k]) {
                        $arr_attributes[$matches[1][$k]] = explode(',', $matches[2][$k]);
                    }
                }
            }
        }
        //echo '<pre>';print_r($arr_attributes);die;
        return $arr_attributes;
    }

    /**
     * method to get attributes
     * @param $arrAttributes
     */
    private function writeAttributesToStates(array $arrAttributes): void
    {
        if (!is_array($arrAttributes) || 0 === count($arrAttributes)) {
            return;
        }
        foreach ($arrAttributes as $k => $v) {
            if (!is_array($v) && '' === (string)$v) {
                continue;
            }
            $this->states[$k] = $v;
        }
    }

    /**
     * method to get Url Request
     */
    private function getUrlRequests(): string
    {
        $arrRequests = [];
        if ('' != $this->states['title']) {
            $arrRequests[] = 'title=' . $this->states['title'];
        }
        // if(''!=$this->states['action']) $arrRequests[]='action='.$this->states['action'];
        return implode('&', $arrRequests);
    }

    /**
     * method to clear tags
     * @param $str
     * @return mixed|string|string[]|null
     */
    private function clearTags(string $str): ?string
    {
        // strip <p> tag in front of qlwiki tag
        $str = str_replace('<p>{' . $this->str_call_start, '{' . $this->str_call_start, $str);

        // strip <p> tag behind qlwiki tag
        $regex = '!{' . $this->str_call_start . '\s(.*?)}</p>!';
        $str = preg_replace($regex, '{' . $this->str_call_start . '$1}', $str);

        // return clean string
        return $str;
    }

    /**
     * method to get matches according to search string
     * @param $text string haystack
     * @param $searchString string needle, string to be searched
     * @return mixed
     */
    public function getMatches(string $text, string $searchString): array
    {
        $searchString = preg_replace('!{\?}!', ' ', $searchString);
        $searchString = preg_replace('?/?', '\/', $searchString);
        $strRegex = '/{' . $searchString . '+(.*?)}/i';
        preg_match_all($strRegex, $text, $matches, PREG_SET_ORDER);
        return $matches;
    }

    private function stripInfotable(string $string): string
    {
        // hatefull brute force, sometimes a solution
        $stringReplaced = preg_replace('~<table(.)*</table>~Us', '', $string);
        if (is_null($stringReplaced)) {
            $stringReplaced = $string;
        }
        return $stringReplaced;
    }

    private function addBtnReadAll(): string
    {
        $readMore = empty(trim($this->states['readmoreText']))
            ? self::READ_MORE
            : $this->states['readmoreText'];

        // get basic url without parameters
        $url = $this->states['url'] ?? '';
        if (is_numeric(strpos($url, '?'))) {
            $url = substr($url, 0, strpos($url, '?'));
        }

        // build html of button and return
        return sprintf(self::TEMPLATE_BUTTON, $url, JText::_($readMore));
    }

    private function buildUrl(string $url): string
    {
        // encode as html entity, ? is done underneath, really needed here? doing it twice might cause problem, hein?
        $this->states['url'] = $url;
        if (empty($this->states['query'])) {
            $url .= '?' . $this->states['query'];
        }

        // add further url parameters wird & (if params alsready exists) or ? (if new params are to be added)
        if (preg_match('/\?/', $url)) {
            $url .= '&' . $this->getUrlRequests();
        } else {
            $url .= '?' . $this->getUrlRequests();
        }

        // add action
        if (false === strpos($url, 'action=') && false === strpos($url, '?')) {
            $url .= '?action=' . $this->states['action'];
        } elseif (false === strpos($url, 'action=')) {
            $url .= '&action=' . $this->states['action'];
        }

        // add protocol
        if (false === strpos($url, 'http')) {
            $url = $this->params->get('url') . $url;
        }
        // add protocol
        if (false === strpos($url, 'http')) {
            $url = 'https://' . $url;
        }

        // further settings
        if (false !== strpos($url, 'action=parse') || false !== strpos($url, 'action=query')) {
            if (false === strpos($url, 'formatversion=2') && 0 < strpos($url, 'formatversion=')) {
                $url = preg_replace('`formatversion=([0-9]*)`i', 'formatversion=2', $url);
            } elseif (false === strpos($url, 'formatversion=2')) {
                $url .= '&formatversion=2';
            }
        }

        // encode url properly an return
        $url = html_entity_decode($url);
        return $url;
    }
}