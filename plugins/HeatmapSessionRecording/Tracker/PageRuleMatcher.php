<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\HeatmapSessionRecording\Tracker;

use Piwik\Piwik;
use Piwik\Plugins\HeatmapSessionRecording\HeatmapSessionRecording;
use Piwik\UrlHelper;

class PageRuleMatcher
{
    const ATTRIBUTE_URL = 'url';
    const ATTRIBUTE_PATH = 'path';
    const ATTRIBUTE_URLPARAM = 'urlparam';

    const TYPE_ANY = 'any';
    const TYPE_EXISTS = 'exists';
    const TYPE_EQUALS_SIMPLE = 'equals_simple';
    const TYPE_EQUALS_EXACTLY = 'equals_exactly';
    const TYPE_CONTAINS = 'contains';
    const TYPE_STARTS_WITH = 'starts_with';
    const TYPE_REGEXP = 'regexp';

    /**
     * @var array
     */
    private $pageRule;

    public function __construct($pageRule)
    {
        $this->pageRule = $pageRule;
    }

    /**
     * Check if the experiment matches the given target.
     *
     * @param string $url
     * @return bool
     * @throws \Exception
     */
    public function matches($url)
    {
        if (empty($this->pageRule['type']) || empty($this->pageRule['attribute'])) {
            return true;
        }

        $attributeValue = $this->getValueForAttribute($url);

        switch (strtolower($this->pageRule['attribute'])) {
            case self::ATTRIBUTE_URL:
            case self::ATTRIBUTE_PATH:
                return $this->matchesTargetValue($attributeValue, $this->pageRule['type'], $this->pageRule['inverted'], $this->pageRule['value']);
            case self::ATTRIBUTE_URLPARAM:
                $value2 = null;
                if (isset($this->pageRule['value2'])) {
                    $value2 = $this->pageRule['value2'];
                }
                return $this->matchesTargetValue($attributeValue, $this->pageRule['type'], $this->pageRule['inverted'], $value2);
        }

        return false;
    }

    protected function getValueForAttribute($url)
    {
        if (!empty($url) && is_string($url)) {
            $enabledForceSample = HeatmapSessionRecording::ULR_PARAM_FORCE_SAMPLE . '=1';
            $disabledForceSample = HeatmapSessionRecording::ULR_PARAM_FORCE_SAMPLE . '=0';
            $enabledForceScreen = HeatmapSessionRecording::ULR_PARAM_FORCE_CAPTURE_SCREEN . '=1';
            $disabledForceScreen = HeatmapSessionRecording::ULR_PARAM_FORCE_CAPTURE_SCREEN . '=0';

            $url = str_replace(array(
                '?' . $enabledForceSample,
                '?' . $disabledForceSample,
                '?' . $enabledForceScreen,
                '?' . $disabledForceScreen
            ), '?', $url);
            $url = rtrim($url, '?'); // eg when https://www.example.com?pk_hsr_forcesample=1
            $url = str_replace(array(
                '&' . $enabledForceSample,
                '&' . $disabledForceSample,
                '&' . $enabledForceScreen,
                '&' . $disabledForceScreen
            ), '', $url);
        }

        switch (strtolower($this->pageRule['attribute'])) {
            case self::ATTRIBUTE_URL:
                return $url;
            case self::ATTRIBUTE_PATH:
                $urlParsed = parse_url($url);
                if (isset($urlParsed['path'])) {
                    return $urlParsed['path'];
                }
                return '';
            case self::ATTRIBUTE_URLPARAM:
                $urlParsed = parse_url($url);
                $targetValue = null;
                if (!empty($urlParsed['query']) && !empty($this->pageRule['value'])) {
                    $paramName = $this->pageRule['value'];
                    $params = UrlHelper::getArrayFromQueryString($urlParsed['query']);
                    if (isset($params[$paramName])) {
                        $targetValue = $params[$paramName];
                    }
                }
                return $targetValue;
        }
    }

    private function removeWwwSubdomain($host)
    {
        return str_replace('www.', '', $host);
    }

    protected function matchesTargetValue($attributeValue, $type, $invert, $valueToMatch)
    {
        $matches = false;

        if (is_string($attributeValue)) {
            $attributeValue = strtolower($attributeValue);
        }

        if (is_string($valueToMatch) && $type !== 'regexp') {
            $valueToMatch = strtolower($valueToMatch);
        }

        switch ($type) {
            case self::TYPE_ANY:
                $matches = true;
                break;
            case self::TYPE_EXISTS:
                if ($attributeValue !== null) {
                    $matches = true;
                }
                break;
            case self::TYPE_EQUALS_SIMPLE:
                $parsedActual = parse_url($attributeValue);
                $parsedMatch = parse_url($valueToMatch);

                if (isset($parsedActual['host'])) {
                    $parsedActual['host'] = $this->removeWwwSubdomain($parsedActual['host']);
                }
                if (isset($parsedMatch['host'])) {
                    $parsedMatch['host'] = $this->removeWwwSubdomain($parsedMatch['host']);
                }

                if (!isset($parsedMatch['host']) || $parsedActual['host'] == $parsedMatch['host']) {
                    if (!isset($parsedActual['path']) && !isset($parsedMatch['path'])) {
                        $matches = true;
                    } elseif (isset($parsedActual['path']) && isset($parsedMatch['path'])) {
                        if ($parsedActual['path'] == $parsedMatch['path'] ||
                            $parsedActual['path'] == $parsedMatch['path'] . '/' ||
                            $parsedActual['path'] == '/' . $parsedMatch['path'] ||
                            $parsedActual['path'] == '/' . $parsedMatch['path'] . '/' ||
                            $parsedActual['path'] . '/' == $parsedMatch['path']) {
                            $matches = true;
                        }
                    }
                }

                break;
            case self::TYPE_EQUALS_EXACTLY:
                if ($attributeValue && $attributeValue === $valueToMatch) {
                    $matches = true;
                }

                if ($valueToMatch && @parse_url($valueToMatch, PHP_URL_PATH) === '/' && $valueToMatch === ($attributeValue . '/')) {
                    $matches = true;
                }

                if ($attributeValue && @parse_url($attributeValue, PHP_URL_PATH) === '/' && $attributeValue === ($valueToMatch . '/')) {
                    $matches = true;
                }

                break;
            case self::TYPE_CONTAINS:
                if ($attributeValue && strpos($attributeValue, $valueToMatch) !== false) {
                    $matches = true;
                }
                break;
            case self::TYPE_STARTS_WITH:
                if ($attributeValue && strpos($attributeValue, $valueToMatch) === 0) {
                    $matches = true;
                }
                break;
            case self::TYPE_REGEXP:
                $pattern = '/' . str_replace('/', '\/', $valueToMatch) . '/';
                if (preg_match($pattern, $attributeValue)) {
                    $matches = true;
                }
                break;
        }

        if ($invert) {
            return !$matches;
        }

        return $matches;
    }

    public static function doesTargetTypeRequireValue($type)
    {
        return $type !== self::TYPE_ANY;
    }

    public static function getAvailableTargetTypes()
    {
        $targetTypes = array();

        $urlOptions = array(
            self::TYPE_EQUALS_EXACTLY => Piwik::translate('HeatmapSessionRecording_TargetTypeEqualsExactly'),
            self::TYPE_EQUALS_SIMPLE => Piwik::translate('HeatmapSessionRecording_TargetTypeEqualsSimple'),
            self::TYPE_CONTAINS => Piwik::translate('HeatmapSessionRecording_TargetTypeContains'),
            self::TYPE_STARTS_WITH => Piwik::translate('HeatmapSessionRecording_TargetTypeStartsWith'),
            self::TYPE_REGEXP => Piwik::translate('HeatmapSessionRecording_TargetTypeRegExp'),
        );

        $urlAttribute = array(
            'value' => self::ATTRIBUTE_URL,
            'name' => Piwik::translate('HeatmapSessionRecording_TargetAttributeUrl'),
            'types' => array(),
            'example' => 'http://www.example.com/' . Piwik::translate('HeatmapSessionRecording_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => self::ATTRIBUTE_PATH,
            'name' => Piwik::translate('HeatmapSessionRecording_TargetAttributePath'),
            'types' => array(),
            'example' => '/' . Piwik::translate('HeatmapSessionRecording_FilesystemDirectory')
        );
        foreach ($urlOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }
        $targetTypes[] = $urlAttribute;


        $urlAttribute = array(
            'value' => self::ATTRIBUTE_URLPARAM,
            'name' => Piwik::translate('HeatmapSessionRecording_TargetAttributeUrlParameter'),
            'types' => array(),
            'example' => Piwik::translate('HeatmapSessionRecording_TargetAttributeUrlParameterExample')
        );

        $parameterOptions = array(
            self::TYPE_EXISTS => Piwik::translate('HeatmapSessionRecording_TargetTypeExists'),
            self::TYPE_EQUALS_EXACTLY => Piwik::translate('HeatmapSessionRecording_TargetTypeEqualsExactly'),
            self::TYPE_CONTAINS => Piwik::translate('HeatmapSessionRecording_TargetTypeContains'),
            self::TYPE_REGEXP => Piwik::translate('HeatmapSessionRecording_TargetTypeRegExp'),
        );

        foreach ($parameterOptions as $key => $value) {
            $urlAttribute['types'][] = array('value' => $key, 'name' => $value);
        }

        $targetTypes[] = $urlAttribute;

        return $targetTypes;
    }

}
